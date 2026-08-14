<?php
/**
 * This file was originally written by Chris Petersen for several different open
 * source projects.  It is distrubuted under the GNU General Public License.
 * I (Chris Petersen) have also granted a special LGPL license for this code to
 * several companies I do work for on the condition that these companies will
 * release any changes to this back to me and the open source community as GPL,
 * thus continuing to improve the open source version of the library.  If you
 * would like to inquire about the status of this arrangement, please contact
 * me personally.
 *
 * ---
 *
 * This is a special object intended to allow users to connect to the mysqli
 * engine provided that it is available and the database version is recent
 * enough.  The parent Database class will first connect with the legacy mysql
 * engine and then check the database version.  If everything checks out, a new
 * connection will be opened using this library.  This library contains fewer
 * feature than the full mysqli library, but attempts better backwards
 * compatibility so programs can take advantage of the better integration of
 * mysqli but still run on older installations.
 *
 * @copyright   Silicon Mechanics
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Database
 *
 * @uses        Database.php
 * @uses        Database_Query_mysqlicompat.php
 *
 **/

/**
 * Database connection class for mysqli, the improved mysql engine.
 **/

class Database_mysqlicompat extends Database {

    /**
     * Constructor
     *
     * @param string          $db_name   Name of the database we're connecting to
     * @param string          $login     Login name to use when connecting
     * @param string          $password  Password to use when connecting
     * @param string          $server    Database server to connect to (Default: localhost)
     * @param int|string|null $port      Port or socket address to connect to
     **/
    public function __construct(string $db_name, string $login, string $password, string $server = 'localhost', int|string|null $port = null) {
        // Ensure mysqli extension is available
        if (!extension_loaded('mysqli')) {
            $this->error("The mysqli PHP extension is not installed or enabled in php.ini.");
            return;
        }

        // Silence automatic exception throwing to preserve legacy error handling
        mysqli_report(MYSQLI_REPORT_OFF);

        $port_num = !empty($port) && is_numeric($port) ? (int)$port : null;
        $socket   = !empty($port) && !is_numeric($port) ? (string)$port : null;

        // Connect to the database
        $this->dbh = @mysqli_connect($server, $login, $password, null, $port_num, $socket);

        if (!$this->dbh) {
            $this->error("Can't connect to the database server.");
            return;
        }

        if (!@mysqli_select_db($this->dbh, $db_name)) {
            $this->error("Can't access the database file.");
        }
    }

    /**
     * Escapes a string and returns it with added quotes. On top of normal escaping,
     * this also escapes ? characters so it's safe to use in other db queries.
     *
     * @param string|null $string string to escape
     *
     * @return string escaped string
     **/
    public function escape(?string $string): string {
        if (is_null($string)) {
            return 'NULL';
        }
        return str_replace('?', '\\?', "'" . mysqli_real_escape_string($this->dbh, $string) . "'");
    }

    /**
     * Changes the regexp-special square brackets used for character class/range
     * operations to the "match any single character" operator, '.'.
     *
     * @param string|null $string string to escape
     *
     * @return string escaped string
     **/
    public function escape_regex(?string $string): string {
        if (is_null($string)) {
            return 'NULL';
        }
        return str_replace(['[', ']'], '.', $string);
    }

    /**
     * Returns an un-executed Database_Query_mysqlicompat object
     *
     * @param string $query The query string
     *
     * @return Database_Query_mysqlicompat
     **/
    public function prepare(string $query): Database_Query_mysqlicompat {
        return new Database_Query_mysqlicompat($this, $query);
    }

    /**
     * @return string The most recent error string
     **/
    public function _errstr(): string {
        return $this->dbh ? mysqli_error($this->dbh) : (mysqli_connect_error() ?? '');
    }

    /**
     * @return int The most recent error number
     **/
    public function _errno(): int {
        return $this->dbh ? mysqli_errno($this->dbh) : mysqli_connect_errno();
    }

    /**
     * @return string Information about the mysql server
     **/
    public function server_info(): string {
        return mysqli_get_server_info($this->dbh);
    }

    /**
     * @return bool true on success
     **/
    public function close(): bool {
        return mysqli_close($this->dbh);
    }

}