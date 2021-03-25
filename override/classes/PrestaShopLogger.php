<?php

class PrestaShopLogger extends PrestaShopLoggerCore
{
    public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false, $idEmployee = null)
    {
        if (_PS_MODE_DEV_) {
            if (!class_exists(BB\Clockwork\Profiler::class)) {
                @include_once(_PS_MODULE_DIR_ . 'clockwork/vendor/autoload.php');
            }
            if (class_exists(BB\Clockwork\Profiler::class)) {
                $profiler = BB\Clockwork\Profiler::getInstance();

                switch ($severity) {
                    default:
                    case 1:
                        $level = Psr\Log\LogLevel::INFO;
                        break;
                    case 2:
                        $level = Psr\Log\LogLevel::WARNING;
                        break;
                    case 3:
                        $level = Psr\Log\LogLevel::ERROR;
                        break;
                    case 4:
                        $level = Psr\Log\LogLevel::CRITICAL;
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
