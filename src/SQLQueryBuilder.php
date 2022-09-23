<?php

namespace AJUR\DBWrapper;

class SQLQueryBuilder
{
    public static function create($fields, $table, $hash = null, $joins = null, $needpages = true):string
    {
        $where = "";
        $limit = "";
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
        if (isset($hash['limit']) && $hash['limit'] === "all" and !isset($hash['page'])) {
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
    
        if (is_array($hash)) {
            
            foreach ($hash as $key => $value) {
                if (is_array( $value ) and (isset( $value[ 'from' ] ) or isset( $value[ 'to' ] ))) {
                    // тип выбора "от" и "до"
                    if (isset( $value[ 'from' ] ) and strlen( $value[ 'from' ] ) > 0 and !isset( $value[ 'to' ] ))
                        $where .= " AND {$key}>=".$value[ 'from' ]."";
            
                    if (!isset( $value[ 'from' ] ) and isset( $value[ 'to' ] ) and strlen( $value[ 'to' ] ) > 0)
                        $where .= " AND {$key}<=".$value[ 'to' ]."";
            
                    if (isset( $value[ 'from' ] ) and isset( $value[ 'to' ] ) and strlen( $value[ 'to' ] ) > 0 and strlen( $value[ 'from' ] ) > 0)
                        $where .= " AND {$key}<=".$value[ 'to' ]." AND {$key}>=".$value[ 'from' ]."";
            
                } elseif (is_array( $value ) and (isset( $value[ 'like' ] ) and $value[ 'like' ] == 1 and isset( $value[ 'string' ] ))) {
                    // LIKE
                    $swhere = array();
                    if (is_array( $value[ 'fields' ] )) {
                        foreach ($value[ 'fields' ] as $searchfield) {
                            $swhere[] = "{$searchfield} LIKE '%".$value[ 'string' ]."%'";
                        }
                    }
                    $where .= " AND (".join( " OR ", $swhere ).")";
            
                } elseif (is_array( $value ) and isset( $value[ 'operand' ] )) {
            
                    if (is_array( $value[ 'value' ] )) {
                        foreach ($value[ 'value' ] as $vvv) {
                            $where .= " AND {$key} {$value['operand']} ".$vvv."";
                        }
                    } else {
                        $where .= " AND {$key} {$value['operand']} ".$value[ 'value' ]."";
                    }
            
                } elseif (is_array( $value ) and isset( $value[ 'or' ] )) {
            
                    if (is_array( $value[ 'value' ] )) {
                        $lll = array();
                        foreach ($value[ 'value' ] as $k => $vvv) {
                            $lll[] = "{$k} = {$vvv}";
                        }
                        $where .= " AND (".implode( " OR ", $lll ).")";
                    }
            
                } elseif (is_array( $value )) {
                    // множественный выбор
                    $c = "";
                    if (strstr( $key, "!" )) {
                        $key = str_replace( "!", "", $key );
                        $c = " NOT ";
                    }
                    $where .= " AND {$key} {$c} IN (".implode( ", ", $value ).")";
            
                } else {
            
                    $c = "";
                    if (strstr( $key, "!" )) {
                        $key = str_replace( "!", "", $key );
                        $c = "!";
                    }
                    $where .= " AND {$key} {$c}= '".$value."'";
            
                }
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
    
        return $query . ' ' . $limit;
    }
    
}