<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Priority extends Section
{
    /** @var \stdClass[] */
    protected $priorities_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cachePriority(\stdClass $PriorityInfo)
    {
        $this->priorities_list[(int)$PriorityInfo->id] = $PriorityInfo;
    }

    /**
     * List all known issue priorities
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/priority-getPriorities
     *
     * @param bool $reload_cache - force API request to load fresh data
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list($reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            $this->priorities_list = [];
            foreach ($this->Jira->get('priority') as $PriorityInfo) {
                $this->cachePriority($PriorityInfo);
            }
            $this->all_cached = true;
        }

        return $this->priorities_list;
    }

    /**
     * Get particular priority info
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/priority-getPriority
     *
     * @param int  $id              - ID of priority
     * @param bool $reload_cache    - force API request to load fresh data
     *
     * @return \stdClass|\stdClass[]|string|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $PriorityInfo = $this->priorities_list[$id] ?? null;

        if (!isset($PriorityInfo) || $reload_cache) {
            $PriorityInfo = $this->Jira->get("priority/{$id}");
            $this->cachePriority($PriorityInfo);
        }

        return $PriorityInfo;
    }

    /**
     * Search priority by name.
     *
     * NOTE: this is synthetic method, JIRA API has no special method searching priorities by name
     *       The full list of priorities is loaded before search.
     *
     * @see Priority::list()
     *
     * @param string $priority_name - desired priority name
     * @param bool $case_sensitive - perform case sensitive search. True by default
     * @param bool $reload_cache - ignore internal client cache and request JIRA API for fresh data
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     *
     */
    public function searchByName(string $priority_name, bool $case_sensitive = true, bool $reload_cache = false) : ?\stdClass
    {
        $priorities = $this->list($reload_cache);

        if ($case_sensitive) {
            foreach ($priorities as $PriorityInfo) {
                if ($PriorityInfo->name === $priority_name) {
                    return $PriorityInfo;
                }
            }

            return null;
        }

        $priority_name = strtolower($priority_name);

        foreach ($priorities as $PriorityInfo) {
            if (strtolower($PriorityInfo->name) === $priority_name) {
                return $PriorityInfo;
            }
        }

        return null;
    }
}
