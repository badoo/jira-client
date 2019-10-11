<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

/**
 * Class LogRecordItem
 *
 * Wrapper for issue change objects of jira history and changelog.
 * Stores information about single issue field change.
 */
class LogRecordItem
{
    const FIELD_TYPE_JIRA   = 'jira';
    const FIELD_TYPE_CUSTOM = 'custom';

    /** @var \stdClass */
    protected $OriginalObject;

    /** @var ILogRecord */
    protected $LogRecord;

    /**
     * Get record item, initialized from \stdClass object (from Jira REST response or WebHook event).
     * @param ILogRecord $ParentLogRecord
     * @param \stdClass  $Item
     *
     * @return LogRecordItem
     */
    public static function fromStdClass(\stdClass $Item, ILogRecord $ParentLogRecord) : LogRecordItem
    {
        $Instance = new static();
        $Instance->OriginalObject = $Item;

        $Instance->LogRecord   = $ParentLogRecord;

        return $Instance;
    }

    protected function getOriginalObject() : \stdClass
    {
        return $this->OriginalObject;
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->LogRecord->getIssue();
    }

    public function getChangeTime() : int
    {
        return $this->LogRecord->getCreated();
    }

    public function getFieldName() : string
    {
        return (string)$this->getOriginalObject()->field;
    }

    public function getFieldType() : string
    {
        return (string)$this->getOriginalObject()->fieldtype;
    }

    public function isFieldSystem() : bool
    {
        return $this->getFieldType() === static::FIELD_TYPE_JIRA;
    }

    public function isFieldCustom() : bool
    {
        return $this->getFieldType() === static::FIELD_TYPE_CUSTOM;
    }

    public function getFrom()
    {
        return $this->getOriginalObject()->from;
    }

    public function getTo()
    {
        return $this->getOriginalObject()->to;
    }

    public function getFromString() : string
    {
        return (string)$this->getOriginalObject()->fromString;
    }

    public function getToString() : string
    {
        return (string)$this->getOriginalObject()->toString;
    }

    public function isStringChanged() : bool
    {
        return $this->getFromString() !== $this->getToString();
    }
}
