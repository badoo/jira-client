<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

/**
 * Class HistoryRecord
 *
 * Wrapper for Issue's changelog history records. Each record contains one or more field change items.
 * One record represents single issue change during issue update or transition.
 */
class HistoryRecord implements ILogRecord
{
    /** @var \stdClass */
    protected $OriginalObject;

    /** @var History */
    protected $History;

    /** @var array */
    protected $cache = [];

    public static function fromStdClass(\stdClass $Record, History $History) : HistoryRecord
    {
        $Instance = new static();
        $Instance->OriginalObject = $Record;

        $Instance->History = $History;

        return $Instance;
    }

    protected function getOriginalObject() : \stdClass
    {
        return $this->OriginalObject;
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->History->getIssue();
    }

    public function getHistory() : History
    {
        return $this->History;
    }

    public function getId() : int
    {
        return (int)$this->getOriginalObject()->id;
    }

    public function getAuthor() : \Badoo\Jira\User
    {
        $key = 'Author';

        if (!array_key_exists($key, $this->cache)) {
            $AuthorInfo = $this->getOriginalObject()->author;
            $Issue = $this->getIssue();

            $this->cache[$key] = \Badoo\Jira\User::fromStdClass($AuthorInfo, $Issue, $Issue->getJira());
        }

        return $this->cache[$key];
    }

    public function getCreated() : int
    {
        $key = 'created';

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = (int)strtotime($this->getOriginalObject()->created);
        }

        return $this->cache[$key];
    }

    /**
     * Get all field changes within this hostory record. Single history record refers to single issue update:
     * field change in UI, transition and so on.
     *
     * @return LogRecordItem[]
     */
    public function getItems() : array
    {
        $key = 'changes';

        if (!array_key_exists($key, $this->cache)) {
            $changes = [];

            $changes_info = $this->getOriginalObject()->items;
            foreach ($changes_info as $ChangeInfo) {
                $changes[] = LogRecordItem::fromStdClass($ChangeInfo, $this);
            }

            $this->cache[$key] = $changes;
        }

        return $this->cache[$key];
    }

    /**
     * Get change for specific field, if it was changed in this record.
     *
     * @param string $field_name - field display name (e.g. 'Developer' or 'Epic Link')
     * @return LogRecordItem|null
     */
    public function getFieldChange(string $field_name) : ?LogRecordItem
    {
        foreach ($this->getItems() as $Change) {
            if ($Change->getFieldName() === $field_name) {
                return $Change;
            }
        }

        return null;
    }
}
