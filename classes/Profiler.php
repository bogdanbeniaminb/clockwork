<?php

namespace BB\Clockwork;

use Exception;
use Tools;
use Cache;
use Clockwork\Support\Vanilla\Clockwork;
use Db;
use ObjectModel;
use Configuration;

class Profiler
{
    protected $clockwork;

    protected $hooksPerfs = [];
    protected $modulesPerfs = [];
    protected $profiler = [];

    protected $totalFilesize = 0;
    protected $totalGlobalVarSize = 0;
    protected $totalQueryTime = 0;
    protected $totalModulesTime = 0;
    protected $totalModulesMemory = 0;
    protected $totalHooksTime = 0;
    protected $totalHooksMemory = 0;
    protected $startTime = 0;

    protected static $instance = null;

    private function __construct()
    {
        $this->clockwork = Clockwork::init([
            'register_helpers' => true,
            'storage_files_path' => __DIR__ . '/../storage/clockwork',
            'api' => __PS_BASE_URI__ . 'modules/clockwork/actions/endpoint.php?request='
        ]);

        $this->startTime = microtime(true);
    }

    /**
     * Return profiler instance
     *
     * @return self
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Sort array by query time
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    public function sortByQueryTime(array $a, array $b): int
    {
        if ($a['time'] == $b['time']) {
            return 0;
        }

        return ($a['time'] > $b['time']) ? -1 : 1;
    }

    /**
     * Stamp the profiling
     *
     * @param string $block
     */
    public function stamp(string $block)
    {
        $this->profiler[] = [
            'block' => $block,
            'memory_usage' => memory_get_usage(),
            'peak_memory_usage' => memory_get_peak_usage(),
            'time' => microtime(true),
        ];
    }

    /**
     * Get var size
     *
     * @param mixed $var
     */
    private function getVarSize($var)
    {
        $start_memory = memory_get_usage();

        try {
            $tmp = Tools::unSerialize(serialize($var));
        } catch (Exception $e) {
            $tmp = $this->getVarData($var);
        }

        $size = memory_get_usage() - $start_memory;

        return $size;
    }

    /**
     * Get var data
     *
     * @param mixed $var
     *
     * @return string|object
     */
    private function getVarData($var)
    {
        if (is_object($var)) {
            return $var;
        }

        return (string) $var;
    }

    /**
     * Intercept hook and register its data
     *
     * @param string $hookName
     * @param array $params
     */
    public function interceptHook(string $hookName, array $params)
    {
        if (empty($this->hooksPerfs[$hookName])) {
            $this->hooksPerfs[$hookName] = [
                'time' => 0,
                'memory' => 0,
                'modules' => [],
                'start' => microtime(true),
            ];
        }

        $this->hooksPerfs[$hookName]['time'] += $params['time'];
        $this->hooksPerfs[$hookName]['memory'] += $params['memory'];
        $this->hooksPerfs[$hookName]['modules'][] = $params;
        $this->totalHooksMemory += $params['memory'];
        $this->totalHooksTime += $params['time'];
    }

    /**
     * Intercept module
     *
     * @param array $params
     */
    public function interceptModule(array $params)
    {
        $this->modulesPerfs[] = $params;
        $this->totalModulesTime += $params['time'];
        $this->totalModulesMemory += $params['memory'];
    }

    /**
     * Process all data such as Global vars and
     * database queries
     */
    public function processData()
    {
        // Including a lot of files uses memory
        foreach (get_included_files() as $file) {
            $this->totalFilesize += filesize($file);
        }

        foreach ($GLOBALS as $key => $value) {
            if ($key === 'GLOBALS') {
                continue;
            }
            $this->totalGlobalVarSize += ($size = $this->getVarSize($value));

            if ($size > 1024) {
                $this->globalVarSize[$key] = round($size / 1024);
            }
        }

        arsort($this->globalVarSize);

        $cache = Cache::retrieveAll();
        $this->totalCacheSize = $this->getVarSize($cache);

        // Sum querying time
        $queries = Db::getInstance()->queries;
        uasort($queries, [$this, 'sortByQueryTime']);
        foreach ($queries as $data) {
            $this->totalQueryTime += $data['time'];

            $queryRow = [
                'time' => $data['time'],
                'query' => $data['query'],
                'location' => str_replace('\\', '/', substr($data['stack'][0]['file'], strlen(_PS_ROOT_DIR_))) . ':' . $data['stack'][0]['line'],
                'filesort' => false,
                'rows' => 1,
                'group_by' => false,
                'stack' => [],
            ];

            clock()->addDatabaseQuery(
                $data['query'],
                [],
                $data['time'] * 1000,
                [
                    'file' => str_replace('\\', '/', substr($data['stack'][0]['file'], strlen(_PS_ROOT_DIR_))),
                    'line' => $data['stack'][0]['line'],
                    'time' => $data['start'] ?? null,
                ]
            );

            if (preg_match('/^\s*select\s+/i', $data['query'])) {
                $explain = Db::getInstance()->executeS('explain ' . $data['query']);
                if (isset($explain[0]['Extra']) && stristr($explain[0]['Extra'], 'filesort')) {
                    $queryRow['filesort'] = true;
                }

                foreach ($explain as $row) {
                    $queryRow['rows'] *= $row['rows'];
                }

                if (stristr($data['query'], 'group by') && !preg_match('/(avg|count|min|max|group_concat|sum)\s*\(/i', $data['query'])) {
                    $queryRow['group_by'] = true;
                }
            }

            array_shift($data['stack']);
            foreach ($data['stack'] as $call) {
                $queryRow['stack'][] = str_replace('\\', '/', substr($call['file'], strlen(_PS_ROOT_DIR_))) . ':' . $call['line'];
            }

            $this->queries[] = $queryRow;
        }

        uasort(ObjectModel::$debug_list, function ($a, $b) {
            return (count($a) < count($b)) ? 1 : -1;
        });
        arsort(Db::getInstance()->tables);
        arsort(Db::getInstance()->uniqQueries);

        uasort($this->hooksPerfs, [$this, 'sortByQueryTime']);
        foreach ($this->hooksPerfs as $hook_name => $hook_data) {
            clock()->event(sprintf("Hook: %s", $hook_name), [
                'name' => $hook_name,
                'start' => $hook_data['start'],
                'end' => $hook_data['start'] + $hook_data['time'],
            ]);
        }


        $this->sendData();
    }

    /**
     * Prepare and return smarty variables
     *
     * @return array
     */
    public function getSmartyVariables(): array
    {
        $data = [
            'summary' => [
                'loadTime' => $this->profiler[count($this->profiler) - 1]['time'] - $this->startTime,
                'queryTime' => round(1000 * $this->totalQueryTime),
                'nbQueries' => count($this->queries),
                'peakMemoryUsage' => $this->profiler[count($this->profiler) - 1]['peak_memory_usage'],
                'globalVarSize' => $this->globalVarSize,
                'includedFiles' => count(get_included_files()),
                'totalFileSize' => $this->totalFilesize,
                'totalCacheSize' => $this->totalCacheSize,
                'totalGlobalVarSize' => $this->totalGlobalVarSize,
            ],
            'configuration' => [
                'psVersion' => _PS_VERSION_,
                'phpVersion' => PHP_VERSION,
                'mysqlVersion' => Db::getInstance()->getVersion(),
                'memoryLimit' => ini_get('memory_limit'),
                'maxExecutionTime' => ini_get('max_execution_time'),
                'smartyCache' => Configuration::get('PS_SMARTY_CACHE'),
                'smartyCompilation' => Configuration::get('PS_SMARTY_FORCE_COMPILE'),
            ],
            'run' => [
                'startTime' => $this->startTime,
                'profiler' => $this->profiler,
            ],
            'hooks' => [
                'perfs' => $this->hooksPerfs,
                'totalHooksTime' => $this->totalHooksTime,
                'totalHooksMemory' => $this->totalHooksMemory,
            ],
            'modules' => [
                'perfs' => $this->modulesPerfs,
                'totalHooksTime' => $this->totalModulesTime,
                'totalHooksMemory' => $this->totalModulesMemory,
            ],
            'stopwatchQueries' => $this->queries,
            'doublesQueries' => Db::getInstance()->uniqQueries,
            'tableStress' => Db::getInstance()->tables,
            'objectmodel' => ObjectModel::$debug_list,
            'files' => get_included_files(),
        ];

        dump($data);

        return $data;
    }

    /**
     * send data to clockwork
     */
    public function sendData()
    {
        $this->clockwork->requestProcessed();
    }

    /**
     * get the clockwork instance
     */
    public function clock()
    {
        return $this->clockwork;
    }

    /**
     * set the current controller
     */
    public function setController($controller)
    {
        $this->clockwork->getClockwork()->getRequest()->controller = get_class($controller);
    }

    /**
     * add a view
     */
    public function addView(...$args)
    {
        clock()->addView(...$args);
    }
}