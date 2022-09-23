<?php

namespace AJUR\DBWrapper;

use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @method PDOStatement|false   prepare($query = '', array $options = [])
 * @method bool                 beginTransaction()
 * @method bool                 commit()
 * @method bool                 rollBack()
 * @method bool                 inTransaction()
 * @method bool                 setAttribute($attribute, $value)
 * @method int|false            exec(string $statement = '')
 * @method PDOStatement|false   query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args)
 * @method string|false         lastInsertId($name = null)
 * @method string               errorCode()
 * @method array                errorInfo()
 * @method mixed                getAttribute($attribute = '')
 *
 */
class DBWrapper
{
    const DEFAULT_CHARSET = 'utf8';
    const DEFAULT_CHARSET_COLLATE = 'utf8_general_ci';
    
    /**
     * @var LoggerInterface|null
     */
    private $logger;
    
    /**
     * @var array
     */
    private $db_config;
    
    /**
     * @var string
     */
    private $driver;
    
    /**
     * @var string
     */
    private $hostname;
    
    /**
     * @var int
     */
    private $port;
    
    /**
     * @var string
     */
    private $username;
    
    /**
     * @var string
     */
    private $password;
    
    /**
     * @var string
     */
    private $database;
    
    /**
     * @var string
     */
    private $charset;
    
    /**
     * @var string
     */
    private $charset_collate;
    
    /**
     * @var float|int
     */
    private $slow_query_threshold;

    public $is_lazy = true;
    
    /**
     * @var PDO
     */
    public $pdo;

    public $last_query_time;

    public $last_state = [
        'method'    =>  '',
        'query'     =>  '',
        'time'      =>  0,
        'comment'   =>  ''
    ];

    /**
     * @var DBConfig
     */
    private $config;

    public function __construct(array $connection_config, array $options = [], LoggerInterface $logger = null)
    {
        $this->config = new DBConfig($connection_config, $options, $logger);

        if ($this->is_lazy === false) {
            $this->initConnection();
        }
    }
    
    private function initConnection()
    {
        $dsl = sprintf("mysql:host=%s;port=%s;dbname=%s",
            $this->config->hostname,
            $this->config->port,
            $this->config->database);
        $this->pdo = new PDO($dsl, $this->config->username, $this->config->password);

        if ($this->config->charset) {
            $sql_collate = "SET NAMES {$this->config->charset}";
            if ($this->config->charset_collate) {
                $sql_collate .= " COLLATE {$this->config->charset_collate}";
            }
            $this->pdo->exec($sql_collate);
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function __call($function, $args)
    {
        if (empty($this->dbh)) {
            $this->initConnection();
        }

        $this->last_state['method'] = $function;

        if (in_array(strtolower($function), [ 'query', 'prepare' ])) {
            $this->last_state['query'] = $args[0];

            if (preg_match('#^\/\*\s(.+)\s\*\/#', $args[0], $matches)) {
                $this->last_state['comment'] = $matches[0];
            };
        }

        // invoke the original method
        $before_call = microtime(true);
        $result = call_user_func_array (array($this->pdo, $function), $args);
        $after_call = microtime(true);

        $this->last_state['time'] = $after_call - $before_call;

        if ($this->last_state['time'] >= $this->slow_query_threshold) {
            var_dump('Logging event');
            $this->logger->debug($function);
        }

        return $result;
    }

    public function getLastQueryTime(): string
    {
        return number_format($this->last_state['time'], 6, '.', '');
    }

    public function getLastState():array
    {
        $result = $this->last_state;
        $result['time'] = number_format($result['time'], 6, '.', '');
        return $result;
    }
}

