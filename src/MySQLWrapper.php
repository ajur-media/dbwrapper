<?php

namespace AJUR\DBWrapper;

use mysqli;
use mysqli_result;
use Psr\Log\LoggerInterface;

class MySQLWrapper
{
    /**
     * @var false|mysqli
     */
    private $connection;

    /**
     * @var DBConfig
     */
    private $config;

    public $mysqlcountquery;

    public function __construct(array $connection_config, array $options = [], LoggerInterface $logger = null)
    {
        $this->connection = false;
        $this->config = new DBConfig($connection_config, $options, $logger);
    }

    private function initConnection()
    {
        $this->connection = mysqli_connect($this->config->hostname, $this->config->username, $this->config->password, $this->config->database, $this->config->port);

        if (mysqli_connect_error()) {
            $this->config->logger->emergency("[DBWrapper Error] Unable initialize connection", [mysqli_connect_errno(), mysqli_connect_error(), $this->db_config]);
            $this->db_config['password'] = '*********';
            throw new \RuntimeException( sprintf("[DBWrapper Error] Unable initialize connection: [%s]( %s )", mysqli_connect_errno(), mysqli_connect_error() ), mysqli_connect_errno());
        }

        if ($this->config->charset) {
            mysqli_query($this->connection, "SET CHARACTER SET {$this->config->charset}");
            mysqli_query($this->connection, "SET NAMES {$this->config->charset}");
            mysqli_query($this->connection, "set character_set_server='{$this->config->charset}'");
            mysqli_query($this->connection, "set character_set_results='{$this->config->charset}'");
            mysqli_query($this->connection, "set character_set_connection='{$this->config->charset}'");

            if ($this->config->charset_collate) {
                mysqli_query($this->connection, "SET SESSION collation_connection='{$this->config->charset_collate}'");
            }
        }
    }

    public function close()
    {
        if ($this->connection) {
            mysqli_close($this->connection);
        }
    }


    public function fetch($result)
    {
        if (is_null($result) and isset($this->result)) {
            if (!$this->result instanceof mysqli_result) {
                $this->config->logger->error(__METHOD__ . " tries to execute mysqli_fetch_assoc() on boolean, stack trace: ", [debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);
                return null;
            } else {
                return mysqli_fetch_assoc($this->result);
            }

        } else {

            if (!$result instanceof mysqli_result) {
                $this->config->logger->error(__METHOD__ . " tries to execute mysqli_fetch_assoc() on boolean, stack trace: ", [debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)]);
                return null;
            } else {
                return mysqli_fetch_assoc($result);
            }
        }
    }

    public function num_rows($res)
    {
        return mysqli_num_rows($res);
    }

    public function insert_id()
    {
        return mysqli_insert_id($this->connection);
    }

    public function create($fields, $table, $hash = null, $joins = null, $needpages = true):string
    {
        $where = "";
        $limit = "";
        $perpage = 0;
        $own_cond = "";
        $having = "";
        $force_index = "";
        $custom_condition = [];

        if (array_key_exists('custom_condition', $hash)) {
            $custom_condition = $hash['custom_condition'];
            unset($hash['custom_condition']);
        }

        if (isset($hash['having'])) {
            $having = $hash['having'];
        }

        if (isset($hash['own_cond'])) {
            $own_cond = $hash['own_cond'];
        }

        if (isset($hash['force_index'])) {
            $force_index = $hash['force_index'];
        }

        if (isset($hash['perpage']) and is_numeric($hash['perpage'])) {
            $perpage = $hash['perpage'];
        }
        unset($hash['perpage']);

        // page, limit, offset
        if (isset($hash['page']) and is_numeric($hash['page']) and isset($hash['limit'])) {
            if (isset($hash['offset'])) {
                $limit = "LIMIT {$hash['limit']} OFFSET {$hash['offset']}";
            } else {
                $from = (ceil($hash['page']) - 1) * $hash['limit'];
                $limit = "LIMIT " . $from . ", " . $hash['limit'];
            }
        }

        if (isset($hash['limit']) and is_numeric($hash['limit']) and !isset($hash['page'])) {
            $_lim = ceil($hash['limit']);
            if (isset($hash['offset'])) {
                $limit = "LIMIT {$_lim} OFFSET {$hash['offset']}";
            } else {
                $limit = "LIMIT {$_lim}";
            }
        }

        // все записи
        if (isset($hash['limit']) and $hash['limit'] == "all" and !isset($hash['page'])) {
            $limit = "";
        }

        if (isset($hash['offset'])) {
            unset($hash['offset']);
        }

        $order = "";
        if (isset($hash['order'])) {
            $order = "ORDER BY " . $hash['order'];
        }

        $group = "";
        if (isset($hash['group'])) {
            $group = "GROUP BY " . $hash['group'];
        }

        unset($hash['limit'], $hash['page'], $hash['order'], $hash['own_cond'], $hash['having'], $hash['force_index'], $hash['group']);

        if (is_array($hash))

            foreach ($hash as $key => $value) {
                if (is_array($value) and (isset($value['from']) or isset($value['to']))) {
                    // тип выбора "от" и "до"
                    if (isset($value['from']) and strlen($value['from']) > 0 and !isset($value['to']))
                        $where .= " AND {$key}>=" . $value['from'] . "";

                    if (!isset($value['from']) and isset($value['to']) and strlen($value['to']) > 0)
                        $where .= " AND {$key}<=" . $value['to'] . "";

                    if (isset($value['from']) and isset($value['to']) and strlen($value['to']) > 0 and strlen($value['from']) > 0)
                        $where .= " AND {$key}<=" . $value['to'] . " AND {$key}>=" . $value['from'] . "";

                } elseif (is_array($value) and (isset($value['like']) and $value['like'] == 1 and isset($value['string']))) {
                    // LIKE
                    $swhere = array();
                    if (is_array($value['fields'])) {
                        foreach ($value['fields'] as $search_field) {
                            $swhere[] = "{$search_field} LIKE '%" . $value['string'] . "%'";
                        }
                    }
                    $where .= " AND (" . join(" OR ", $swhere) . ")";

                } elseif (is_array($value) and isset($value['operand'])) {

                    if (is_array($value['value'])) {
                        foreach ($value['value'] as $vvv) {
                            $where .= " AND {$key} {$value['operand']} " . $vvv . "";
                        }
                    } else {
                        $where .= " AND {$key} {$value['operand']} " . $value['value'] . "";
                    }

                } elseif (is_array($value) and isset($value['or'])) {

                    if (is_array($value['value'])) {
                        $lll = array();
                        foreach ($value['value'] as $k => $vvv) {
                            $lll[] = "{$k} = {$vvv}";
                        }
                        $where .= " AND (" . implode(" OR ", $lll) . ")";
                    }

                } elseif (is_array($value)) {
                    // множественный выбор
                    $c = "";
                    if (strstr($key, "!")) {
                        $key = str_replace("!", "", $key);
                        $c = " NOT ";
                    }
                    $where .= " AND {$key} {$c} IN (" . implode(", ", $value) . ")";

                } else {

                    $c = "";
                    if (strstr($key, "!")) {
                        $key = str_replace("!", "", $key);
                        $c = "!";
                    }
                    $where .= " AND {$key} {$c}= '" . $value . "'";

                }
            }

        if (count($custom_condition) > 0) {
            $where_custom_condition = implode(' AND ', $custom_condition);
            $where .= ' AND ' . $where_custom_condition;
        } elseif (isset($own_cond) and strlen($own_cond) > 0) {
            $where .= " AND " . $own_cond;
        }

        $where_pages = $where;

        $query = "SELECT {$fields} FROM {$table}";

        if (isset($force_index) and strlen($force_index) > 0) {
            $query .= PHP_EOL . " FORCE INDEX ({$force_index})";
        }

        if (is_array($joins)) {
            foreach ($joins as $key => $value) {
                $query .= PHP_EOL . " LEFT JOIN {$key} ON ({$value})";
            }
        }

        $query
            .= " WHERE 1=1 {$where} "
            . ((isset($having) and strlen($having) > 0) ? "HAVING {$having}" : "")
            . " {$group} {$order}";

        $query_pages = "SELECT COUNT(*) FROM {$table}";

        if (is_array($joins)) {
            foreach ($joins as $key => $value) {
                $query_pages .= PHP_EOL . " LEFT JOIN {$key} ON ({$value})";
            }
        }

        $query_pages .= PHP_EOL . " WHERE 1=1 {$where_pages} {$group} ";

        $limit = ' ' . $limit;

        if ($needpages) {
            $res = $this->query($query_pages);
            $this->total = mysqli_num_rows($res);

            $tmp = mysqli_fetch_field($res);

            if ($this->total == 1 and ($tmp->name == "COUNT(*)")) {
                $this->total = $this->result($res, 0);
            }

            $this->pages = array();

            if ($perpage > 0) {
                for ($i = 1; $i <= ceil($this->total / $perpage); $i++) {
                    $this->pages[] = $i;
                }
            }
        }
        $this->query = $query . $limit;
        return $query . $limit;
    }

    public function query($query, $log_sql_request = false)
    {
        if ($log_sql_request) {
            $this->config->logger->debug('[MYSQL QUERY]', [$query]);
        }

        $error = false;
        $this->request_error = false;

        $this->mysqlcountquery++;

        $time_start = microtime(true);

        if ($this->connection === false) {
            $this->initConnection();
        }

        if (!$result = mysqli_query($this->connection, $query)) {
            $error = true;
            $this->request_error = true;
        }

        $time_finish = microtime(true);
        $time_consumed = $time_finish - $time_start;

        if ($error) {
            $this->config->logger->error("mysqli_query() error: ", [
                ((php_sapi_name() == "cli") ? __FILE__ : ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])),
                mysqli_error($this->connection),
                $query
            ]);
        }

        if (($time_consumed > $this->config->slow_query_threshold)) {
            $this->config->logger->info("mysqli_query() slow: ", [
                $time_consumed,
                ((php_sapi_name() == "cli") ? __FILE__ : ($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])),
                $query
            ]);
        }

        $this->mysqlquerytime += $time_consumed;
        $this->result = $result;
        return $result;
    }

    public function result($res, $row)
    {
        $r = mysqli_fetch_array($res);
        return $r[$row];
    }

    public function getQueryCount()
    {
        return $this->mysqlcountquery;
    }

    public function getQueryTime()
    {
        return $this->mysqlquerytime;
    }



}