<?php

use BB\Clockwork\Profiler;
use Psr\Log\LogLevel;

class PrestaShopLogger extends PrestaShopLoggerCore
{
    public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false, $idEmployee = null)
    {
        if (_PS_MODE_DEV_) {
            if (!class_exists(Profiler::class)) {
                @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
            }
            if (class_exists(Profiler::class)) {
                $profiler = Profiler::getInstance();

                switch ($severity) {
                    default:
                    case 1:
                        $level = LogLevel::INFO;
                        break;
                    case 2:
                        $level = LogLevel::WARNING;
                        break;
                    case 3:
                        $level = LogLevel::ERROR;
                        break;
                    case 4:
                        $level = LogLevel::CRITICAL;
                        break;
                }

                clock()->debug('Message');

                $profiler->clock()->log($level, $message, [
                    'severity' => $severity,
                    'errorCode' => $errorCode,
                    'objectType' => $objectType,
                    'objectId' => $objectId,
                    'allowDuplicate' => $allowDuplicate,
                    'idEmployee' => $idEmployee,
                ]);
            }
        }

        $result = parent::addLog($message, $severity, $errorCode, $objectType, $objectId, $allowDuplicate, $idEmployee);

        return $result;
    }
}
