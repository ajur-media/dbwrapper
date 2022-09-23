<?php

namespace AJUR\DBWrapper;

class PDOQueryBuilder
{
    /**
     * Строит INSERT-запрос на основе массива данных для указанной таблицы.
     * В массиве допустима конструкция 'key' => 'NOW()'
     * В этом случае она будет добавлена в запрос и удалена из набора данных (он пере).
     *
     * @param $table    -- таблица
     * @param $dataset      -- передается по ссылке, мутабелен
     * @return string       -- результирующая строка запроса
     */
    public static function makeInsertQuery(string $table, &$dataset):string
    {
        if (empty($dataset)) {
            return "INSERT INTO {$table} () VALUES (); ";
        }
        
        $set = [];
        
        $query = "INSERT INTO `{$table}` SET ";
        
        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $set[] = "\r\n `{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }
            
            $set[] = "\r\n `{$index}` = :{$index}";
        }
        
        $query .= implode(', ', $set) . ' ;';
        
        return $query;
    }
    
    /**
     * Build UPDATE query by dataset for given table
     *
     * @param string $table
     * @param $dataset
     * @param $where_condition
     * @return bool|string
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition):string
    {
        if (empty($dataset)) {
            return false;
        }
    
        $crlf = '';
        $set = [];
        
        $query = "UPDATE `{$table}` SET";
        
        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $set[] = "{$crlf} `{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }
            
            $set[] = "{$crlf}`{$index}` = :{$index}";
        }
        
        $query .= implode(', ', $set);
        
        if (is_array($where_condition)) {
            $where_condition = key($where_condition) . ' = ' . current($where_condition);
        }
        
        if ( is_string($where_condition ) && (false === strpos($where_condition, 'WHERE')) ) {
            $where_condition = " WHERE {$where_condition}";
        }
        
        if (is_null($where_condition)) {
            $where_condition = '';
        }
        
        $query .= " {$crlf} $where_condition ;";
        
        return $query;
    }
    
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '')
    {
        if (empty($dataset)) {
            return false;
        }
        
        $fields = [];
        
        $query = "REPLACE `{$table}` SET ";
        
        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $fields[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }
            
            $fields[] = " `{$index}` = :{$index} ";
        }
        
        $query .= implode(', ', $fields);
        
        $query .= " \r\n" . $where . " ;";
        
        return $query;
    }
    
    
}