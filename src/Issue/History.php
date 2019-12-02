<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

/**
 * Class History
 *
 * Issue fields changes history.
 */
class History
{
    /** @var \Badoo\Jira\Issue */
    protected $Issue;
    /** @var HistoryRecord[] */
    protected $records = [];

    public static function forIssue(string $issue_key, \Badoo\Jira\REST\Client $Jira) : \Badoo\Jira\Issue\History
    {
        $Issue = \Badoo\Jira\Issue::byKey($issue_key, [], [\Badoo\Jira\REST\Section\Issue::EXP_CHANGELOG], $Jira);
        return $Issue->getHistory();
    }

    /**
     * Initialize History object on data loaded from API
     *
     * @param \stdClass[] $records - list of history log records (issue->changelog->histories)
     * @param \Badoo\Jira\Issue $Issue
     *
     * @return History
     */
    public static function fromStdClass(array $records, \Badoo\Jira\Issue $Issue)
    {
        $Instance = new self();
        $Instance->Issue = $Issue;

        foreach ($records as $Record) {
            $Instance->records[strtotime($Record->created)] = HistoryRecord::fromStdClass($Record, $Instance);
        }
        ksort($Instance->records);
        $Instance->records = array_values($Instance->records);

        return $Instance;
    }

    /**
     * @return HistoryRecord[]
     */
    public function getRecords() : array
    {
        return $this->records;
    }

    /**
     * @return HistoryRecord[]
     */
    public function getRecordsReverse() : array
    {
        return array_reverse($this->records);
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->Issue;
    }

    /**
     * Track field changes by it's name.
     *
     * @param string $field_name
     * @return LogRecordItem[] - all changes from history for selected field.
     */
    public function trackField($field_name) : array
    {
        $field_changes = [];
        foreach ($this->records as $HistoryRecord) {
            $FieldChange = $HistoryRecord->getFieldChange($field_name);
            if (isset($FieldChange)) {
                $field_changes[] = $FieldChange;
            }
        }

        return $field_changes;
    }

    /**
     * Get the issue update. It contains a list of field changes inside.
     *
     * @return HistoryRecord|null
     */
    public function getLastChanges()
    {
        return end($this->records) ?: null;
    }

    /**
     * Get last change record for specific field.
     *
     * @param string $field_id
     * @return LogRecordItem|null - field change record or null when no changes for field found in history.
     */
    public function getLastFieldChange(string $field_id) : ?LogRecordItem
    {
        for ($i = count($this->records) - 1; $i >= 0; $i--) {
            $HistoryRecord = $this->records[$i];
            $FieldChange = $HistoryRecord->getFieldChange($field_id);
            if (isset($FieldChange)) {
                return $FieldChange;
            }
        }

        return null;
    }

    /**
     * Get total amount of time issue spent in specific status.
     */
    public function getTimeInStatus(string $status_name) : int
    {
        $last_change_time = $this->Issue->getCreatedDate();
        $time_in_status = 0;
        foreach ($this->trackField('status') as $StatusChange) {
            $change_time = $StatusChange->getChangeTime();

            if ($StatusChange->getFromString() == $status_name) {
                $time_in_status += $change_time - $last_change_time;
            }

            $last_change_time = $change_time;
        }

        if ($this->Issue->getStatus()->getName() === $status_name) {
            $time_in_status += time() - $last_change_time;
        }

        return $time_in_status;
    }

    /**
     * Get time in status in work days (excluding weekends).
     */
    public function getWorkdaysInStatus(string $status_name) : float
    {
        $daysBetween = function ($begin, $end) {
            $time = $begin;
            $weekends_count = 0;
            while ($time < $end) {
                $week_day = (int)date('N', $time);
                if ($week_day > 5) {
                    ++$weekends_count;
                }
                $time += 86400;
            }
            $days_between = ($end - $begin) / 86400;
            return $days_between - $weekends_count;
        };

        $total_days = 0;
        $status_changes = $this->trackField('status');
        $last_change_time = $this->getIssue()->getCreatedDate();
        foreach ($status_changes as $StatusChange) {
            if ($StatusChange->getFromString() === $status_name && $StatusChange->isStringChanged()) {
                $total_days += $daysBetween($last_change_time, $StatusChange->getChangeTime());
            }

            if ($StatusChange->isStringChanged()) {
                $last_change_time = $StatusChange->getChangeTime();
            }
        }

        $LastRecord = end($status_changes);

        if ($LastRecord
            && $LastRecord->getFromString() === $status_name
            && !$LastRecord->isStringChanged()) {
            // we are still in status we want to track
            $total_days += $daysBetween($last_change_time, time());
        }

        return round($total_days, 2);
    }

    public function getLastStatusChange() : ?LogRecordItem
    {
        return $this->getLastFieldChange('status');
    }

    public function getTimeInLastStatus() : int
    {
        $LastStatusChange = $this->getLastStatusChange();
        if (isset($LastStatusChange)) {
            return time() - $LastStatusChange->getChangeTime();
        }

        return time() - $this->Issue->getCreatedDate();
    }

    /**
     * Transitions are the issue updates with a 'status' field record inside.
     * The status doesn's need to be changed (e.g. for transitions from <status A> to <status A>),
     * but the record for it is still present in the list of changes
     *
     * @param bool $only_status_changes - return only transitions with actual status change
     *                                    filter out the transitions from <status A> to itself
     *
     * @return HistoryRecord[]
     */
    public function getTransitions(bool $only_status_changes = false) : array
    {
        $transitions = [];
        foreach ($this->records as $HistoryRecord) {
            $StatusChange = $HistoryRecord->getFieldChange('status');

            // Transitions leave 'status' field record in history, even when the status actually not changed.
            if (!isset($StatusChange)) {
                continue;
            }

            if ($only_status_changes && !$StatusChange->isStringChanged()) {
                continue;
            }

            $transitions[] = $HistoryRecord;
        }

        return $transitions;
    }
}
