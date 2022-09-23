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
        /*$this->logger = is_null($logger) ? new NullLogger() : $logger;
        
        if (empty($connection_config)) {
            $this->logger->emergency("[DBWrapper Error] Connection config is empty");
            throw new \RuntimeException("[DBWrapper Error] Connection config is empty");
        }
    
        $this->db_config = $connection_config;
        $this->driver   = $this->db_config['driver'];
        $this->hostname = $this->db_config['hostname'] ?? '127.0.0.1';
        $this->port     = $this->db_config['port'] ?? 3306;
        $this->username = $this->db_config['username'];
        $this->password = $this->db_config['password'];
        $this->database = $this->db_config['database'];
        $this->is_lazy  = true;
    
        if (!array_key_exists('charset', $this->db_config)) {
            $this->charset = self::DEFAULT_CHARSET;
        } elseif (!is_null($this->db_config['charset'])) {
            $this->charset = $this->db_config['charset'];
        } else {
            $this->charset = null;
        }
    
        if (!array_key_exists('charset_collate', $this->db_config)) {
            $this->charset_collate = self::DEFAULT_CHARSET_COLLATE;
        } elseif (!is_null($this->db_config['charset_collate'])) {
            $this->charset_collate = $this->db_config['charset_collate'];
        } else {
            $this->charset_collate = null;
        }

        // ms
        $this->slow_query_threshold = (array_key_exists('slow_query_threshold', $this->db_config)) ? (float)$this->db_config['slow_query_threshold'] : 1000;

        // microsec
        $this->slow_query_threshold *= 1000;*/

        $this->config = new DBConfig($connection_config, $options, $logger);

        if ($this->is_lazy === false) {
            $this->initConnection();
        }
    }
    
    private function initConnection()
    {
        $dsl = sprintf("mysql:host=%s;port=%s;dbname=%s",
            $this->hostname,
            $this->port,
            $this->database);
        $this->pdo = new PDO($dsl, $this->username, $this->password);

        if ($this->charset) {
            $sql_collate = "SET NAMES {$this->charset}";
            if ($this->charset_collate) {
                $sql_collate .= " COLLATE {$this->charset_collate}";
            }
            $this->pdo->exec($sql_collate);
        }


        /*
        switch ($this->driver) {
            case 'pdo': {
                // PDO
                $dsl = sprintf("mysql:host=%s;port=%s;dbname=%s",
                    $this->hostname,
                    $this->port,
                    $this->database);
                $this->pdo = new PDO($dsl, $this->username, $this->password);

                if ($this->charset) {
                    $sql_collate = "SET NAMES {$this->charset}";
                    if ($this->charset_collate) {
                        $sql_collate .= " COLLATE {$this->charset_collate}";
                    }
                    $this->pdo->exec($sql_collate);
                }

                break;
            }

            case 'mysqli':
            {
                $this->connection = mysqli_connect($this->hostname, $this->username, $this->password, $this->database, $this->port);
                if (mysqli_connect_error()) {
                    $this->logger->emergency("[DBWrapper Error] Unable initialize connection", [mysqli_connect_errno(), mysqli_connect_error(), $this->db_config]);
                    $this->db_config['password'] = '*********';
                    throw new \RuntimeException( sprintf("[DBWrapper Error] Unable initialize connection: [%s]( %s )", mysqli_connect_errno(), mysqli_connect_error() ), mysqli_connect_errno());
                }
    
                if ($this->charset) {
                    mysqli_query($this->connection, "SET CHARACTER SET {$this->charset}");
                    mysqli_query($this->connection, "SET NAMES {$this->charset}");
                    mysqli_query($this->connection, "set character_set_server='{$this->charset}'");
                    mysqli_query($this->connection, "set character_set_results='{$this->charset}'");
                    mysqli_query($this->connection, "set character_set_connection='{$this->charset}'");
            
                    if ($this->charset_collate) {
                        mysqli_query($this->connection, "SET SESSION collation_connection='{$this->charset_collate}'");
                    }
                }
    

                $this->connection_type = 'mysql';
                
                break;
            }
            case 'pgsql': {
                // pg_connect
                $dsl = "host={$this->hostname} port={$this->port} dbname={$this->database} user={$this->username} password={$this->password}";
                $this->connection = pg_connect($dsl);
    
                $dsl = sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
                    $this->hostname,
                    $this->port,
                    $this->database,
                    $this->username,
                    $this->password);
        
                $this->pdo = new \PDO($dsl);
        
                // PGSQL Collate?
        
                $this->connection_type = 'pgsql';
            }
            default: {
                $this->logger->emergency("[DBWrapper Error] Unknown database driver", [ $this->driver ]);
                throw new \RuntimeException("[DBWrapper Error] Unknown database driver");
            }
        } */
    
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

