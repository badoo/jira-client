<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Status extends Section
{
    /** @var array */
    protected $statuses_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cachestatusInfo(\stdClass $StatusInfo)
    {
        $this->statuses_list[(int)$StatusInfo->id] = $StatusInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/status-getStatuses
     *
     * Get list of all statuses configured in current JIRA installation
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of statuses, indexed by IDs
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            foreach ($this->Jira->get('/status') as $StatusInfo) {
                $this->cachestatusInfo($StatusInfo);
            }
            $this->all_cached = true;
        }

        return $this->statuses_list;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/status-getStatus
     *
     * Get particular status info identified by it's unique ID
     *
     * @param int $id - ID of status you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $statusInfo = $this->statuses_list[$id] ?? null;

        if (!isset($statusInfo) || $reload_cache) {
            $statusInfo = $this->Jira->get("/status/{$id}");
            $this->cachestatusInfo($statusInfo);
        }

        return $statusInfo;
    }
}
