<?php

abstract class Db extends DbCore
{
    public $count = 0;
    public $queries = [];
    public $uniqQueries = [];
    public $tables = [];

    public function query($sql)
    {
        if (!_PS_MODE_DEV_) {
            return parent::query($sql);
        }

        $explain = false;
        if (preg_match('/^\s*explain\s+/i', $sql)) {
            $explain = true;
        }

        if (!$explain) {
            $uniqSql = preg_replace('/[\'"][a-f0-9]{32}[\'"]/', '<span style="color:blue">XX</span>', $sql);
            $uniqSql = preg_replace('/[0-9]+/', '<span style="color:blue">XX</span>', $uniqSql);
            if (!isset($this->uniqQueries[$uniqSql])) {
                $this->uniqQueries[$uniqSql] = 0;
            }
            ++$this->uniqQueries[$uniqSql];

            // No cache for query
            if (!$this->is_cache_enabled && !stripos($sql, 'SQL_NO_CACHE')) {
                $sql = preg_replace('/^\s*select\s+/i', 'SELECT SQL_NO_CACHE ', trim($sql));
            }

            // Get tables in query
            preg_match_all('/(from|join)\s+`?' . _DB_PREFIX_ . '([a-z0-9_-]+)/ui', $sql, $matches);
            foreach ($matches[2] as $table) {
                if (!isset($this->tables[$table])) {
                    $this->tables[$table] = 0;
                }
                ++$this->tables[$table];
            }

            $start = microtime(true);
        }

        // Execute query
        $result = parent::query($sql);

        if (!$explain) {
            $end = microtime(true);

            $stack = debug_backtrace(false);
            while (preg_match('@[/\\\\]classes[/\\\\]db[/\\\\]@i', $stack[0]['file'])) {
                array_shift($stack);
            }
            $stack_light = [];
            foreach ($stack as $call) {
                $stack_light[] = ['file' => isset($call['file']) ? $call['file'] : 'undefined', 'line' => isset($call['line']) ? $call['line'] : 'undefined'];
            }

            $this->queries[] = [
                'query' => $sql,
                'time' => $end - $start,
                'stack' => $stack_light,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $result;
    }
}
