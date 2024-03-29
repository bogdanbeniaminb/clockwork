<?php

class Hook extends HookCore
{
    public static function coreCallHook($module, $method, $params)
    {
        // if not in dev mode, don't store anything
        if (!_PS_MODE_DEV_) {
            return parent::coreCallHook($module, $method, $params);
        }

        $timeStart = microtime(true);
        $memoryStart = memory_get_usage();

        $result = parent::coreCallHook($module, $method, $params);

        $timeEnd = microtime(true);

        if (!class_exists(BB\Clockwork\Profiler::class)) {
            @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
        }
        if (class_exists(BB\Clockwork\Profiler::class)) {
            BB\Clockwork\Profiler::getInstance()->interceptHook(
                substr($method, 4),
                [
                    'module' => $module->name,
                    'params' => $params,
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
