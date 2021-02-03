<?php
declare(strict_types=1);

use BB\Clockwork\Profiler;

class Module extends ModuleCore
{
    protected static function coreLoadModule($moduleName)
    {
        // if not in dev mode, don't store anything
        if (!_PS_MODE_DEV_) {
            return parent::coreLoadModule($moduleName);
        }

        $timeStart = microtime(true);
        $memoryStart = memory_get_usage();
        $result = parent::coreLoadModule($moduleName);
        if (!class_exists(Profiler::class)) {
            include_once(_PS_MODULE_DIR_ . 'clockwork/classes/autoload.php');
        }
        $timeEnd = microtime(true);
        if (class_exists(Profiler::class)) {
            Profiler::getInstance()->interceptModule(
                [
                    'module' => $moduleName,
                    'method' => '__construct',
                    'time' => $timeEnd - $timeStart,
                    'start' => $timeStart,
                    'end' => $timeEnd,
                    'memory' => memory_get_usage() - $memoryStart,
                ]
            );
        }
        return $result;
    }
}
