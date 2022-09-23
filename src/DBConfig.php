<?php

namespace AJUR\DBWrapper;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DBConfig
{
    const DEFAULT_CHARSET = 'utf8';
    const DEFAULT_CHARSET_COLLATE = 'utf8_general_ci';

    public function __construct(array $connection_config, array $options = [], LoggerInterface $logger = null)
    {
        $this->logger = is_null($logger) ? new NullLogger() : $logger;

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
        $this->slow_query_threshold *= 1000;
    }

}