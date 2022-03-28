<?php
use ReportPortalBasic\Enum\LogLevelsEnum as LogLevelsEnum;

/**
 * Enum table with Psr log leves vs ReportPortal log levels.
 *
 */
class PsrLogLevelsEnum
{
    const DEBUG = 'DEBUG';
    const INFO  = 'INFO';
    const NOTICE = 'NOTICE';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    const ALERT = 'ALERT';
    const EMERGENCY = 'EMERGENCY';

    static public function covertLevelFromPsrToReportPortal(string $level): string
    {
        switch ($level) {
            case self::DEBUG:
                return LogLevelsEnum::DEBUG;
            case self::INFO:
                return LogLevelsEnum::INFO;
            case self::NOTICE:
                return LogLevelsEnum::INFO;
            case self::WARNING:
                return LogLevelsEnum::WARN;
            case self::ERROR:
                return LogLevelsEnum::ERROR;
            case self::CRITICAL:
                return LogLevelsEnum::FATAL;
            case self::ALERT:
                return LogLevelsEnum::FATAL;
            case self::EMERGENCY:
                return LogLevelsEnum::FATAL;
            default:
                return LogLevelsEnum::UNKNOWN;
        }
    }
}