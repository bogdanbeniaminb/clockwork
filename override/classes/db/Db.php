<?php

abstract class Db extends DbCore
{
    /**
     * Add SQL_NO_CACHE in SELECT queries
     *
     * @var bool
     */
    public $disableCache = false;

    /**
     * Total of queries
     *
     * @var int
     */
    public $count = 0;

    /**
     * List of queries
     *
     * @var array
     */
    public $queries = array();

    /**
     * List of uniq queries (replace numbers by XX)
     *
     * @var array
     */
    public $uniqQueries = array();

    /**
     * List of tables
     *
     * @var array
     */
    public $tables = array();


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
            if (!isset($this->uniqQueries[$sql])) {
                $this->uniqQueries[$sql] = 0;
            }
            $this->uniqQueries[$sql]++;

            // No cache for query
            if ($this->disableCache && !stripos($sql, 'SQL_NO_CACHE')) {
                $sql = preg_replace(
                    '/^\s*select\s+/i',
                    'SELECT SQL_NO_CACHE ',
                    trim($sql)
                );
            }

            $start = microtime(true);
        }

        // Execute query
        $result = parent::query($sql);

        if (!$explain) {
            $end = microtime(true);
            $duration = $end - $start;

            // Get tables in query
            preg_match_all(
                '/(from|join)\s+`?' . _DB_PREFIX_ . '([a-z0-9_-]+)/ui',
                $sql,
                $matches
            );
            foreach ($matches[2] as $table) {
                if (!isset($this->tables[$table])) {
                    $this->tables[$table] = [
                        'count' => 0,
                        'duration' => 0,
                    ];
                }
                $this->tables[$table]['count']++;
                $this->tables[$table]['duration'] += $duration * 1000;
            }

            $stack = debug_backtrace(false);
            while (preg_match('@[/\\\\]classes[/\\\\]db[/\\\\]@i', $stack[0]['file'])) {
                array_shift($stack);
            }
            $stack_light = [];
            foreach ($stack as $call) {
                $stack_light[] = [
                    'file' => isset($call['file']) ? $call['file'] : 'undefined',
                    'line' => isset($call['line']) ? $call['line'] : 'undefined'
                ];
            }

            $this->queries[] = [
                'query' => $sql,
                'time' => $duration,
                'stack' => $stack_light,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $result;
    }
}
