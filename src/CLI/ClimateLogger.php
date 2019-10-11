<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CLI;

class ClimateLogger extends \Psr\Log\AbstractLogger
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

    const LEVEL_COLOR_MAP = [
        \Psr\Log\LogLevel::EMERGENCY    => 'background_red',
        \Psr\Log\LogLevel::ALERT        => 'red',
        \Psr\Log\LogLevel::CRITICAL     => 'light_red',
        \Psr\Log\LogLevel::ERROR        => 'light_red',
        \Psr\Log\LogLevel::WARNING      => 'light_yellow',
        \Psr\Log\LogLevel::NOTICE       => 'yellow',
        \Psr\Log\LogLevel::INFO         => 'green',
        \Psr\Log\LogLevel::DEBUG        => 'light_blue',
    ];

    /** @var \League\CLImate\CLImate */
    protected $Climate;

    protected $level_threshold = 4;

    public function __construct(\League\CLImate\CLImate $Climate)
    {
        $this->Climate = $Climate;
    }

    protected function getLevelIndex(string $level) : int
    {
        $level_index = self::LEVELS_MAP[$level] ?? null;

        if (!isset($level_index)) {
            throw new \Psr\Log\InvalidArgumentException(
                "Unknown message level {$level}. Use PSR-3 compatible logging level names"
            );
        }

        return $level_index;
    }

    public function setLevelThreshold(string $level_threshold) : ClimateLogger
    {
        $this->level_threshold = $this->getLevelIndex($level_threshold);
        return $this;
    }

    public function log($level, $message, array $context = [])
    {
        $level_index = $this->getLevelIndex($level);

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

        $color = self::LEVEL_COLOR_MAP[$level] ?? 'white';
        $level = strtoupper($level);

        $this->Climate->to('error')->out("<$color>[{$level}]</$color>: {$message}");
    }
}
