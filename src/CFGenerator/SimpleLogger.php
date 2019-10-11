<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CFGenerator;

class SimpleLogger extends \Psr\Log\AbstractLogger
{
    const LEVELS_MAP = [
        \Psr\Log\LogLevel::EMERGENCY    => 0,
        \Psr\Log\LogLevel::ALERT        => 1,
        \Psr\Log\LogLevel::CRITICAL     => 2,
        \Psr\Log\LogLevel::ERROR        => 3,
        \Psr\Log\LogLevel::WARNING      => 4,
        \Psr\Log\LogLevel::NOTICE       => 5,
        \Psr\Log\LogLevel::INFO         => 6,
        \Psr\Log\LogLevel::DEBUG        => 7,
    ];

    protected $level_threshold = 6;

    public function log($level, $message, array $context = [])
    {
        $level_index = self::LEVELS_MAP[$level] ?? null;

        if (!isset($level_index)) {
            throw new \Psr\Log\InvalidArgumentException(
                "Unknown message level {$level}. Use PSR-3 compatible logging level names"
            );
        }

        if ($level_index > $this->level_threshold) {
            return;
        }

        $search = [];
        $replace = [];
        foreach ($context as $key => $value) {
            $search[] = "{{$key}}";
            $replace[] = $value;
        }

        $message = str_replace($search, $replace, $message);

        echo "[{$level}]: {$message}\n";
    }

    public function setLevelThreshold(string $level_threshold) : SimpleLogger
    {
        if (!isset(self::LEVELS_MAP[$level_threshold])) {
            throw new \Psr\Log\InvalidArgumentException(
                "Unknown log message level {$level_threshold}. Use PSR-3 compatible logging level names"
            );
        }

        $this->level_threshold = self::LEVELS_MAP[$level_threshold];
        return $this;
    }
}
