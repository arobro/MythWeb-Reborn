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
 * Query handler for MySQL
 *
 * @copyright   Silicon Mechanics
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Database
 *
 * @uses        Database.php
 * @uses        Database_mysql.php
 * @uses        Database_Query.php
 *
 **/

/**
 * The basic MySQL database query type.
 **/
class Database_Query_mysql extends Database_Query {

    /**
     * Executes the query that was previously passed to the constructor.
     *
     * @param mixed ...$args Query arguments to escape and insert at ? placeholders in $query
     */
    public function execute(...$args): void {
        // Process sub-arrays, etc.
        $args = Database::smart_args($args);

        // Check if enough arguments were passed in
        if (count($args) !== $this->num_args_needed) {
            trigger_error('Database_Query called with ' . count($args) . " arguments, but requires {$this->num_args_needed}.", E_USER_ERROR);
        }

        // Finish any previous statements
        $this->finish();

        // Replace in the arguments
        $this->last_query = '';
        foreach ($this->query as $part) {
            $this->last_query .= $part;
            if (count($args)) {
                $arg = array_shift($args);
                $this->last_query .= is_null($arg)
                    ? 'NULL'
                    : "'" . mysqli_real_escape_string($this->dbh, (string)$arg) . "'";
            }
        }

        // Perform the query
        if ($this->dbh === false) {
            $this->db->error();
            trigger_error($this->db->error, E_USER_ERROR);
        }

        $this->sh = mysqli_query($this->dbh, $this->last_query);

        // Cache execution metadata
        if (is_bool($this->sh)) {
            $this->insert_id     = mysqli_insert_id($this->dbh);
            $this->affected_rows = mysqli_affected_rows($this->dbh);
        } else {
            $this->num_rows      = mysqli_num_rows($this->sh);
        }

        if ($this->sh === false) {
            if ($this->db->fatal_errors) {
                trigger_error('SQL Error: ' . mysqli_error($this->dbh) . ' [#' . mysqli_errno($this->dbh) . ']', E_USER_ERROR);
            } else {
                $this->db->error();
            }
        }
    }

    /**
     * Fetch a single column
     */
    public function fetch_col(): mixed {
        $row = mysqli_fetch_row($this->sh);
        return $row[0] ?? null;
    }

    /**
     * Fetch all remaining values of the first column into an array
     */
    public function fetch_cols(): array {
        $return = [];
        while (($col = $this->fetch_col()) !== null) {
            $return[] = $col;
        }
        return $return;
    }

    /**
     * Fetch a single row
     */
    public function fetch_row(): ?array {
        $result = mysqli_fetch_row($this->sh);
        return $result !== false ? $result : null;
    }

    /**
     * Fetch a single assoc row
     */
    public function fetch_assoc(): ?array {
        $result = mysqli_fetch_assoc($this->sh);
        return $result !== false ? $result : null;
    }

    /**
     * Fetch a single row as an array containing both numeric and assoc fields
     */
    public function fetch_array(int $result_type = MYSQLI_BOTH): array|false|null {
        return mysqli_fetch_array($this->sh, $result_type);
    }

    /**
     * Fetch a single row as an object
     */
    public function fetch_object(): ?object {
        $result = mysqli_fetch_object($this->sh);
        return $result !== false ? $result : null;
    }

    public function data_seek(int $row_number): bool {
        return mysqli_data_seek($this->sh, $row_number);
    }

    public function num_rows(): int {
        return (int) $this->num_rows;
    }

    public function affected_rows(): int {
        return (int) $this->affected_rows;
    }

    public function insert_id(): int|string {
        return $this->insert_id;
    }

    /**
     * Free memory and reset handle
     */
    public function finish(): void {
        if (isset($this->sh) && $this->sh instanceof mysqli_result) {
            mysqli_free_result($this->sh);
        }
        unset($this->sh);
    }
}