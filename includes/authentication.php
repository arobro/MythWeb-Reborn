<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

define('SESSION_TIMEOUT_SECONDS', 7776000);
define('MAX_LOGIN_ATTEMPTS', 10);
define('LOCKOUT_TIME_SECONDS', 500);

function get_safe_redirect_url(?string $url): string {
    $default = 'index.php';
    if (empty($url)) return $default;
    $url = str_replace(["\r", "\n"], '', $url);
    if (preg_match('/^(\/|#|\?)/', $url) && !preg_match('/^[\/\\\\]{2,}/', $url)) {
        $parsed = parse_url($url);
        if (isset($parsed['host']) || isset($parsed['scheme'])) return $default;
        return filter_var($url, FILTER_DEFAULT);
    }
    return $default;
}

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_valid_hex(string $hash, int $length = 64): bool {
    return (bool)preg_match('/^[a-f0-9]{' . $length . '}$/i', $hash);
}

function sanitize_username(string $username): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\.\-]/', '', trim($username)));
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

$client_ip = get_client_ip();
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$is_whitelisted = in_array($remote_addr, ['127.0.0.1', '::1'], true);

$raw_redirect_uri = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '';
$redirect_uri = get_safe_redirect_url($raw_redirect_uri);

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: " . root_url);
    exit();
}

global $db;
$db_error = null;

// Setup Tables
if (is_object($db)) {
    try {
        $db->query("CREATE TABLE IF NOT EXISTS mythweb_users (
            username VARCHAR(128) NOT NULL PRIMARY KEY,
            stored_key VARCHAR(64) NOT NULL,
			token VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $db->query("CREATE TABLE IF NOT EXISTS mythweb_ip_limits (
            ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
            failed_attempts INT NOT NULL DEFAULT 0,
            last_failed INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        $db_error = "Database setup failed.";
    }
} else {
    $db_error = "Global \$db handle unavailable.";
}

function get_db_ip_rate_limit(string $ip): array {
    global $db;
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !is_object($db)) return ['count' => 0, 'last_failed' => 0];
    try {
        $result = $db->query("SELECT failed_attempts, last_failed FROM mythweb_ip_limits WHERE ip_address = ?", [$ip]);
        if ($result && $row = $result->fetch_assoc()) {
            return ['count' => (int)$row['failed_attempts'], 'last_failed' => (int)$row['last_failed']];
        }
    } catch (Exception $e) {}
    return ['count' => 0, 'last_failed' => 0];
}

function update_db_ip_rate_limit(string $ip, int $count, int $last_failed): void {
    global $db;
    if (!is_object($db)) return;
    try {
        if ($count <= 0) {
            $db->query("DELETE FROM mythweb_ip_limits WHERE ip_address = ?", [$ip]);
        } else {
            $db->query(
                "INSERT INTO mythweb_ip_limits (ip_address, failed_attempts, last_failed) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE failed_attempts = VALUES(failed_attempts), last_failed = VALUES(last_failed)",
                [$ip, $count, $last_failed]
            );
        }
    } catch (Exception $e) {}
}

$user_count = 0;
if (is_object($db) && $db_error === null) {
    try {
        $user_count = (int)$db->query_col('SELECT COUNT(*) FROM mythweb_users');
    } catch (Exception $e) {
        $user_count = 0;
    }
}

$now = time();
$ip_limit_data = get_db_ip_rate_limit($client_ip);
$failed_attempts = $ip_limit_data['count'];
$last_failed_time = $ip_limit_data['last_failed'];

if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
    if (($now - $last_failed_time) < LOCKOUT_TIME_SECONDS) {
        $remaining = ceil((LOCKOUT_TIME_SECONDS - ($now - $last_failed_time)) / 60);
        renderAuthPage("Too many failed attempts ({$failed_attempts}/" . MAX_LOGIN_ATTEMPTS . "). Account locked. Try again in {$remaining} minutes.");
    } else {
        $failed_attempts = 0;
        update_db_ip_rate_limit($client_ip, 0, 0);
    }
}

// ACCOUNT CREATION
if (isset($_POST['create_user'])) {
    if (!$is_whitelisted) {
        renderAuthPage("Access Denied", ($user_count === 0), false, ($user_count > 0));
        exit();
    }

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        renderAuthPage("Invalid security token.", ($user_count === 0), $is_whitelisted, ($user_count > 0));
        exit();
    }

    $login = sanitize_username($_POST['access_login'] ?? '');
    $client_stored_key = trim($_POST['access_password'] ?? '');

	if (strlen($login) < 4) {
		renderAuthPage("Username must be at least 4 characters long.", ($user_count === 0), $is_whitelisted, ($user_count > 0));
		exit();
	}
	
    if (empty($login) || !is_valid_hex($client_stored_key, 64)) {
        renderAuthPage("Invalid registration keys provided.", ($user_count === 0), $is_whitelisted, ($user_count > 0));
        exit();
    }

    if (is_object($db)) {
        try {
            $count = (int)$db->query_col("SELECT COUNT(*) FROM mythweb_users WHERE username = ?", [$login]);

            if ($count > 0) {
                $safe_display_login = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');
                renderAuthPage("Username '{$safe_display_login}' is already taken. Please choose another.", false, true, true);
                exit();
            }

			$token = bin2hex(random_bytes(32));
			$db->query(
				"INSERT INTO mythweb_users (username, stored_key, token) VALUES (?, ?, ?)",
				[$login, $client_stored_key, $token]
			);
        } catch (Exception $e) {
            renderAuthPage("A database error occurred while creating the user account.", ($user_count === 0), $is_whitelisted, ($user_count > 0));
            exit();
        }
    }

    session_regenerate_id(true);
    update_db_ip_rate_limit($client_ip, 0, 0);
    $_SESSION['authenticated'] = true;
    $_SESSION['last_activity'] = time();
	$_SESSION['user_token']    = $token;

    header("Location: " . get_safe_redirect_url($redirect_uri));
    exit();
}

if ($user_count === 0) {
    renderAuthPage($db_error ?? "", true, $is_whitelisted);
}

if (isset($_GET['account']) && $_GET['account'] === 'register') {
    if (!$is_whitelisted) {
        renderAuthPage("Access Denied");
    }
    renderAuthPage("", false, true, true);
}

// LOGIN VERIFICATION
if (isset($_POST['access_password'])) {
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_csrf)) {
        http_response_code(403);
        renderAuthPage("Invalid or expired session token (CSRF check failed).");
        exit();
    }

    $login           = sanitize_username($_POST['access_login'] ?? '');
    $submitted_proof = trim($_POST['access_password'] ?? '');
    $posted_nonce    = trim($_POST['submitted_nonce'] ?? '');
    
    $active_nonces   = $_SESSION['login_nonces'] ?? [];
    $db_stored_key   = null;

    if (is_object($db)) {
        try {
            $result = $db->query("SELECT stored_key, token FROM mythweb_users WHERE username = ?", [$login]);
            if ($result && $row = $result->fetch_assoc()) {
                $db_stored_key = $row['stored_key'];
				$db_user_token = $row['token'];
            }
        } catch (Exception $e) {}
    }

    $nonce_valid_in_session = in_array($posted_nonce, $active_nonces, true);
    $authenticated = false;

    if ($nonce_valid_in_session && is_valid_hex($submitted_proof, 64)) {
        if (!empty($db_stored_key)) {
            // 1. ClientSignature = HMAC-SHA256(data: hex2bin(posted_nonce), key: hex2bin(db_stored_key))
            $client_signature = hash_hmac('sha256', hex2bin($posted_nonce), hex2bin($db_stored_key), true);
            
            // 2. Recover ClientKey = ClientProof XOR ClientSignature
            $proof_bytes = hex2bin($submitted_proof);
            $recovered_client_key = $proof_bytes ^ $client_signature;

            // 3. Verify: SHA256(Recovered ClientKey) == db_stored_key
            $expected_stored_key = hash('sha256', $recovered_client_key);

            if (hash_equals(strtolower($db_stored_key), $expected_stored_key)) {
                $authenticated = true;
            }
        } else {
            // Constant-time execution path for invalid usernames
            $dummy_stored_key = hash('sha256', 'dummy_value');
            $dummy_sig = hash_hmac('sha256', hex2bin($posted_nonce), hex2bin($dummy_stored_key), true);
            $dummy_client_key = hex2bin($submitted_proof) ^ $dummy_sig;
            hash_equals($dummy_stored_key, hash('sha256', $dummy_client_key));
        }
    }

    unset($_SESSION['login_nonces']);

    if ($authenticated) {
        update_db_ip_rate_limit($client_ip, 0, 0);
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
		$_SESSION['user_token'] = $db_user_token;

        header("Location: " . get_safe_redirect_url($redirect_uri));
        exit();
    } else {
        $failed_attempts++;
        update_db_ip_rate_limit($client_ip, $failed_attempts, time());

        $remaining_attempts = MAX_LOGIN_ATTEMPTS - $failed_attempts;
        if ($remaining_attempts > 0) {
            renderAuthPage("Invalid username or password. ({$remaining_attempts} attempt(s) remaining)");
        } else {
            renderAuthPage("Too many failed attempts (" . MAX_LOGIN_ATTEMPTS . "/" . MAX_LOGIN_ATTEMPTS . "). Account locked.");
        }
    }
}

if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    renderAuthPage("");
}

if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
    $_SESSION = [];
    session_destroy();
    renderAuthPage("Your session has expired. Please log in again.");
}
$_SESSION['last_activity'] = $now;

function renderAuthPage(string $error_msg = '', bool $no_password_set = false, bool $can_create_user = false, bool $is_registration = false) {
    global $is_whitelisted;

    if (!isset($_SESSION['login_nonces']) || !is_array($_SESSION['login_nonces'])) {
        $_SESSION['login_nonces'] = [];
    }

    $nonce = bin2hex(random_bytes(32));
    $_SESSION['login_nonces'][] = $nonce;
    if (count($_SESSION['login_nonces']) > 5) {
        array_shift($_SESSION['login_nonces']);
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Authentication Required</title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    body { background-color: #101a29; color: #ffffff; font-family: sans-serif; text-align: center; margin-top: 50px; }
    .card { background-color: #335555; border: 1px solid #9090b0; display: inline-block; padding: 20px; text-align: left; min-width: 340px; }
    .header { background-color: #265990; border: 1px solid #9090b0; padding: 10px; font-size: 24px; color: #ff5555; text-align: center; }
    .alert { background-color: #443300; border: 1px solid #ffdd55; color: #ffdd55; padding: 10px; margin-bottom: 15px; width: 340px; margin: 10px auto; }
    .error { color: #ff8888; font-weight: bold; margin-bottom: 10px; word-break: break-all; }
    input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin: 6px 0 15px 0; box-sizing: border-box; }
    input[type="submit"] { width: 100%; background-color: #226633; border: 1px solid #77bb88; color: #fff; padding: 10px; cursor: pointer; font-weight: bold; }
    input[type="submit"]:hover { background-color: #2e8b46; }
    .nav-link { margin-top: 15px; text-align: center; }
    .nav-link a { color: #ffdd55; text-decoration: none; }
    .nav-link a:hover { text-decoration: underline; }
  </style>

<script src="<?php echo root_url.'js/crypto-js.min.js' ?>" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script type="text/javascript">
  function hexToWordArray(hex) {
      return CryptoJS.enc.Hex.parse(hex);
  }

  function wordArrayToHex(wordArray) {
      return wordArray.toString(CryptoJS.enc.Hex);
  }

  function deriveClientKeys(password, salt) {
      const saltWA = /^[a-fA-F0-9]+$/.test(salt) ? hexToWordArray(salt) : CryptoJS.enc.Utf8.parse(salt);
      
      // Standard PBKDF2 with SHA-256, 10000 iterations, 32-byte key length using salt string or hex
      const derivedBitsWA = CryptoJS.PBKDF2(password, saltWA, {
          keySize: 256 / 32,
          iterations: 10000,
          hasher: CryptoJS.algo.SHA256
      });

      const clientKeyWA = CryptoJS.SHA256(derivedBitsWA);
      const storedKeyWA = CryptoJS.SHA256(clientKeyWA);

      return {
          clientKeyWA: clientKeyWA,
          storedKeyHex: wordArrayToHex(storedKeyWA)
      };
  }

  function generateLoginProof(clientKeyWA, storedKeyHex, nonceHex) {
      const nonceWA = hexToWordArray(nonceHex);
      const storedKeyWA = hexToWordArray(storedKeyHex);
      
      // ClientSignature = HMAC-SHA256(data: Nonce, key: StoredKey)
      const clientSignatureWA = CryptoJS.HmacSHA256(nonceWA, storedKeyWA);
      
      // Convert WordArrays to 32-byte Uint8Arrays for XOR
      const clientKeySigBytes = hexToBytes(wordArrayToHex(clientKeyWA));
      const clientSigBytes = hexToBytes(wordArrayToHex(clientSignatureWA));
      
      // ClientProof = ClientKey XOR ClientSignature
      const proofBytes = new Uint8Array(32);
      for (let i = 0; i < 32; i++) {
          proofBytes[i] = clientKeySigBytes[i] ^ clientSigBytes[i];
      }
      
      return bytesToHex(proofBytes);
  }

  function hexToBytes(hex) {
      const bytes = new Uint8Array(hex.length / 2);
      for (let i = 0; i < hex.length; i += 2) {
          bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
      }
      return bytes;
  }

  function bytesToHex(bytes) {
      return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
  }

  let isProcessingAuth = false;

function handleFormSubmit(e) {
    e.preventDefault();

    if (isProcessingAuth) return false;

    const form = document.getElementById('authform');
    const userInput = document.getElementById('access_login');
    const passInput = document.getElementById('access_password');
    const confirmPassInput = document.getElementById('confirm_password');
    const nonceInput = document.getElementById('submitted_nonce');
    const createFlag = document.getElementById('create_user_flag');

    if (!passInput.value || (userInput && !userInput.value)) return false;

    if (createFlag && confirmPassInput) {
        if (passInput.value !== confirmPassInput.value) {
            alert("Passwords do not match.");
            return false;
        }
    }

    isProcessingAuth = true;

    const rawUser = userInput 
        ? userInput.value.trim().toLowerCase().replace(/[^a-zA-Z0-9_\.\-]/g, '') 
        : '';
        
    if (userInput) userInput.value = rawUser;
    const rawPass = passInput.value;
    const staticSalt = "MythWebSault_";

    try {
        if (createFlag) {
            if (confirmPassInput) confirmPassInput.value = '';

            const keys = deriveClientKeys(rawPass, staticSalt);
            passInput.value = keys.storedKeyHex;
            form.submit();
            return false;
        }

        const keys = deriveClientKeys(rawPass, staticSalt);
        const serverNonce = nonceInput ? nonceInput.value.trim() : '';

        // Generate SCRAM proof: ClientKey XOR HMAC(data: Nonce, key: StoredKey)
        const proof = generateLoginProof(keys.clientKeyWA, keys.storedKeyHex, serverNonce);
        passInput.value = proof;
        form.submit();
    } catch (err) {
        isProcessingAuth = false;
        alert("Authentication processing error: " + err.message);
    }

    return false;
}
</script>
</head>
<body>
  <div class="header">
    <?php 
      if ($no_password_set && $can_create_user) {
          echo 'Create Admin';
      } elseif ($is_registration) {
          echo 'Create New Account';
      } else {
          echo 'Login';
      }
    ?>
  </div>
  <br />

  <?php if ($no_password_set): ?>
    <div class="alert">
      <strong>Notice:</strong> No account detected in database.
    </div>
  <?php endif; ?>

  <?php if (!empty($error_msg)): ?>
    <div class="error"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="card">
    <?php if (($no_password_set && $can_create_user) || $is_registration): ?>
		<form method="post" id="authform" onsubmit="return handleFormSubmit(event);">
		  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" />
		  <input type="hidden" id="create_user_flag" name="create_user" value="1" />
		  
		  <h3>Register Account</h3>
		  
		  <label for="access_login">Username:</label>
		  <input 
			type="text" 
			name="access_login" 
			id="access_login" 
			required 
			minlength="4" 
			pattern="[a-zA-Z0-9_\.\-]{4,}"
			title="Username must be at least 4 characters long." 
			autocomplete="username" 
			autofocus 
		  />
		  
		  <label for="access_password">Password:</label>
		  <input 
			type="password" 
			id="access_password" 
			name="access_password" 
			required 
			minlength="5" 
			title="Password must be at least 5 characters long." 
			autocomplete="new-password" 
		  />
		  
		 <label for="confirm_password">Confirm Password:</label>
		 <input 
		   type="password" 
		   id="confirm_password" 
		   required 
		   minlength="5" 
		   title="Password must be at least 5 characters long." 
		   autocomplete="new-password" 
		 />
					
		  <input type="submit" style="background-color: #0000FF;" value="<?php echo $is_registration ? 'Register' : 'Create User'; ?>" />
		</form>
			  
      <?php if ($is_registration): ?>
        <div class="nav-link">
          <a href="?account=">Already have an account?</a>
        </div>
      <?php endif; ?>

    <?php elseif ($no_password_set && !$can_create_user): ?>
      <p style="color: #ffdd55;">Please login with http://127.0.0.1/ to setup.</p>

    <?php else: ?>
      <form method="post" id="authform" onsubmit="return handleFormSubmit(event);">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" name="submitted_nonce" id="submitted_nonce" value="<?php echo htmlspecialchars(trim($nonce), ENT_QUOTES, 'UTF-8'); ?>" />
		
        <label for="access_login">Username:</label>
        <input type="text" name="access_login" id="access_login" required autocomplete="username" autofocus />

        <label for="access_password">Password:</label>
        <input type="password" id="access_password" name="access_password" required autocomplete="current-password" />

        <input type="submit" value="Login" />
      </form>

      <div class="nav-link">
        <?php
        if ($is_whitelisted) {
            echo '<a href="?account=register">Create an account</a>';
        }
        ?>
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
<?php
    exit();
}