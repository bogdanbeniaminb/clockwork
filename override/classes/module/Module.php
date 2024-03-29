<?php

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
        if (!class_exists(BB\Clockwork\Profiler::class)) {
            @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
        }
        $timeEnd = microtime(true);
        if (class_exists(BB\Clockwork\Profiler::class)) {
            BB\Clockwork\Profiler::getInstance()->interceptModule(
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
