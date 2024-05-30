<?php

namespace BB\Clockwork;

use Exception;
use Tools;
use Cache;
use Clockwork\Support\Vanilla\Clockwork;
use Db;
use ObjectModel;
use Configuration;
use Context;

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

    protected $disabled = false;

    protected static $instance = null;

    private function __construct()
    {
        $this->startTime = microtime(true);

        $this->clockwork = Clockwork::init([
            'register_helpers' => true,
            'storage_files_path' => __DIR__ . '/../storage/clockwork',
            'api' => __PS_BASE_URI__ . 'modules/clockwork/actions/endpoint.php?request=',
            'toolbar' => true,
            'web' => [
                'enable' => __PS_BASE_URI__ . 'module/clockwork/web',
                'path' => __DIR__ . '/../views/web/public',
                'uri' =>  __PS_BASE_URI__ . 'modules/clockwork/views/web/public',
            ],
        ]);

        // send the data on shutdown anyway.
        register_shutdown_function([$this, 'shutdown']);
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

    public function disable()
    {
        $this->disabled = true;
        return $this;
    }

    public function enable()
    {
        $this->disabled = true;
        return $this;
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
        // Don't process if disabled.
        if ($this->disabled) {
            return;
        }

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

            $location = str_replace('\\', '/', substr($data['stack'][0]['file'], strlen(_PS_ROOT_DIR_))) . ':' . $data['stack'][0]['line'];
            $file = str_replace('\\', '/', substr($data['stack'][0]['file'], strlen(_PS_ROOT_DIR_)));
            $model = str_replace('.php', '', basename($file));

            $queryRow = [
                'time' => $data['time'],
                'query' => $data['query'],
                'location' => $location,
                'filesort' => false,
                'rows' => 1,
                'group_by' => false,
                'stack' => $data['stack'] ?? [],
            ];

            // error_log('New Query! ' . "\n", 3, ABSPATH . '/clockwork.log');
            // error_log('Query Duration : ' . ($data['time'] * 1000) . "ms\n", 3, ABSPATH . '/clockwork.log');
            // error_log('Query File : ' . $file . "\n", 3, ABSPATH . '/clockwork.log');
            // error_log('Query File Line : ' . $data['stack'][0]['line'] . "\n", 3, ABSPATH . '/clockwork.log');
            // error_log('Model : ' . $model . "\n", 3, ABSPATH . '/clockwork.log');
            // error_log('Query : ' . $data['query'] . "\n" . "\n", 3, ABSPATH . '/clockwork.log');

            $this->clock()->addDatabaseQuery(
                $data['query'],
                [],
                $data['time'] * 1000,
                [
                    'file' => $file,
                    'line' => $data['stack'][0]['line'],
                    'time' => $data['start'] ?? null,
                    'model' => $model,
                ]
            );

            if (preg_match('/^\s*select\s+/i', $data['query'])) {
                $explain = Db::getInstance()->executeS('explain ' . $data['query']);
                if (isset($explain[0]['Extra']) && stristr($explain[0]['Extra'], 'filesort')) {
                    $queryRow['filesort'] = true;
                }

                foreach ($explain as $row) {
                    $queryRow['rows'] *= (int) $row['rows'];
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

        if (property_exists(ObjectModel::class, 'debug_list')) {
            uasort(ObjectModel::$debug_list, function ($a, $b) {
                return (count($a) < count($b)) ? 1 : -1;
            });
        }
        arsort(Db::getInstance()->tables);
        arsort(Db::getInstance()->uniqQueries);

        // add the hooks
        foreach ($this->hooksPerfs as $hook_name => $data) {
            $this->clock()->event(sprintf("Hook: %s", $hook_name), [
                'name' => $hook_name,
                'start' => $data['start'],
                'end' => $data['start'] + $data['time'],
                'memory' => $data['memory'],
            ]);
        }

        // add the modules
        foreach ($this->modulesPerfs as $data) {
            $this->clock()->event(sprintf("Module (__construct): %s", $data['module']), [
                'name' => $data['module'],
                'start' => $data['start'],
                'end' => $data['end'],
                'memory' => $data['memory'],
            ]);
        }


        $info = $this->clock()->userData('prestashop')
            ->title('Prestashop Info');

        $smarty_compilation_types = [
            0 => 'Never recompile',
            1 => 'Recompile if updated',
            2 => 'Force',
        ];

        $info->counters([
            'Prestashop Version' => _PS_VERSION_,
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => Db::getInstance()->getVersion(),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'Smarty Cache' => Configuration::get('PS_SMARTY_CACHE') ? 'Cached' : 'Uncached',
            'Smarty Compilation' => $smarty_compilation_types[Configuration::get('PS_SMARTY_FORCE_COMPILE')] ?? 'N/A',
        ]);

        $info->counters([
            'Hooks' => count($this->hooksPerfs),
            'Total Hooks Time' => round($this->totalHooksTime * 1000, 1) . 'ms',
            'Total Hooks Memory' => $this->getHumanReadableSize($this->totalHooksMemory),
            'Modules' => count($this->modulesPerfs),
            'Total Modules Time' => round($this->totalModulesTime * 1000, 1) . 'ms',
            'Total Modules Memory' => $this->getHumanReadableSize($this->totalModulesMemory),
        ]);

        // add the table with hooks
        if ($this->hooksPerfs) {
            $hooks = [];
            foreach ($this->hooksPerfs as $hook_name => $data) {
                $hooks[] = [
                    'name' => $hook_name,
                    'duration' => round($data['time'] * 1000, 1) . 'ms',
                    'memory' => $data['memory'],
                ];
            }

            foreach ($hooks as &$hook) {
                $hook['memory'] = $this->getHumanReadableSize($hook['memory']);
            }

            $info->table('Hooks', $hooks);
        }

        // add the modules table
        if ($this->modulesPerfs) {
            $modules = [];
            foreach ($this->modulesPerfs as $data) {
                $name = $data['module'];
                if (!isset($modules[$name])) {
                    $modules[$name] = [
                        'name' => $name,
                        'duration' => 0,
                        'memory' => 0,
                    ];
                }
                $modules[$name]['duration'] += round(($data['end'] - $data['start']) * 1000, 2);
                $modules[$name]['memory'] += $data['memory'];
            }

            foreach ($modules as &$module) {
                $module['memory'] = $this->getHumanReadableSize($module['memory']);
            }

            $info->table('Modules', $modules);
        }

        // add the database stress table
        if ($stress = Db::getInstance()->tables) {
            $stress_info = [];
            foreach ($stress as $table => $table_info) {
                if (!is_array($table_info)) continue;
                $stress_info[$table] = [
                    'name' => $table,
                    'count' => $table_info['count'],
                    'duration (ms)' => (int) $table_info['duration'],
                ];
            }

            $info->table('Table stress', $stress_info);
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
                'totalHooksMemory' => $this->getHumanReadableSize($this->totalHooksMemory),
            ],
            'modules' => [
                'perfs' => $this->modulesPerfs,
                'totalHooksTime' => $this->totalModulesTime,
                'totalHooksMemory' => $this->getHumanReadableSize($this->totalModulesMemory),
            ],
            'stopwatchQueries' => $this->queries,
            'doublesQueries' => Db::getInstance()->uniqQueries,
            'tableStress' => Db::getInstance()->tables,
            'objectmodel' => property_exists(
                ObjectModel::class,
                'debug_list'
            ) ? ObjectModel::$debug_list : [],
            'files' => get_included_files(),
        ];

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
        $this->clock()->addView(...$args);
    }

    /**
     * get the human readable size
     * @param integer $bytes
     * @return string
     */
    protected function getHumanReadableSize($bytes)
    {
        $bytes = (int) $bytes;
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf('%.2f %s', $bytes / 1024 ** $factor, $size[$factor]);
    }

    /**
     * Send the data on shutdown anyway.
     */
    public function shutdown()
    {
        if (!_PS_MODE_DEV_) {
            return;
        }
        $this->processData();
    }
}
