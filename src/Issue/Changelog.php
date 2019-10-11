<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

/**
 * Class Changelog
 * @package Badoo\Jira\Issue
 *
 * List of changes for issue's fields obtained from WebHook Jira request.
 * They differ a bit from regular issue's History, so we created special class for it
 */
class Changelog implements ILogRecord
{
    /** @var int - unique changelog record ID. */
    protected $id;
    /** @var \Badoo\Jira\Issue */
    protected $Issue;
    /** @var int */
    protected $timestamp;
    /** @var LogRecordItem[] */
    protected $items = [];

    /**
     * @param \Badoo\Jira\Issue $Issue
     * @param \stdClass     $Changelog
     * @param int           $timestamp - changelog record's timestamp
     * @return Changelog
     */
    public static function fromStdClass(\stdClass $Changelog, \Badoo\Jira\Issue $Issue, int $timestamp)
    {
        $Instance = new self();
        $Instance->id        = $Changelog->id;
        $Instance->Issue     = $Issue;
        $Instance->timestamp = $timestamp;

        foreach ($Changelog->items as $Item) {
            $Instance->items[] = LogRecordItem::fromStdClass($Item, $Instance);
        }
        return $Instance;
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->Issue;
    }

    public function getCreated() : int
    {
        return $this->timestamp;
    }

    /**
     * @return LogRecordItem[]
     */
    public function getItems() : array
    {
        return $this->items;
    }

    /**
     * @param callable $callable
     * @return array
     */
    public function filter($callable)
    {
        if (!is_callable($callable)) {
            throw new \RuntimeException();
        }

        return array_filter($this->items, $callable);
    }
}
