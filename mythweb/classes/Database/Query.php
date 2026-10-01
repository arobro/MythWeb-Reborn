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
 * This is an abstract superclass that defines the basic variables and functions
 * used by possible subclasses.
 *
 * @copyright   Silicon Mechanics
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Database
 *
 * @uses        Database.php
 *
 **/

/**
 * Abstract superclass for all database query types.
 **/
abstract class Database_Query {

    /** The related database connection handle */
    public ?\mysqli $dbh = null;

    /** The parent database object */
    public ?Database $db = null;

    /** The current active statement handle or query result */
    public \mysqli_stmt|\mysqli_result|bool|null $sh = null;

    /**
     * The query string, (depending on the engine, broken apart where arguments should be inserted)
     *
     * @var string[]
     */
    public array $query = [];

    /** The most recent query sent to the server */
    public string $last_query = '';

    /** An array of warnings that this query generated */
    public array $warnings = [];

    /** Number of arguments required by $query */
    public int $num_args_needed = 0;

    /** Cache of xxx_num_rows() so multiple queries don't trip over each other */
    public ?int $num_rows = null;

    /** Cache of xxx_affected_rows() so multiple queries don't trip over each other */
    public ?int $affected_rows = null;

    /** Cache of xxx_insert_id() so multiple queries don't trip over each other */
    public int|string|null $insert_id = null;

    /**
     * Constructor.  Parses $query and splits it at ? characters for later
     * substitution in execute().  This should be overridden by engines like mysqli
     * that have their own variable insertion handlers.
     *
     * @param Database $db     The parent Database object
     * @param string   $query  The query string
     **/
    public function __construct(Database $db, string $query) {
        $this->dbh             = $db->dbh;
        $this->db              = $db;
        $this->num_args_needed = max(0, substr_count($query, '?') - substr_count($query, '\\?'));

        // Build an optimized version of the query
        if ($this->num_args_needed > 0) {
            $this->query = [];
            $parts = preg_split('/(\\\\?\\?)/', $query, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    switch ($part) {
                        case '?':
                            break;
                        case '\\?':
                            $lastKey = max(0, count($this->query) - 1);
                            if (!isset($this->query[$lastKey])) {
                                $this->query[$lastKey] = '';
                            }
                            $this->query[$lastKey] .= '?';
                            break;
                        default:
                            $this->query[] = $part;
                    }
                }
            }
        } else {
            $this->query = [$query];
        }
    }
}