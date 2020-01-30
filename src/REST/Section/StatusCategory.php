<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class StatusCategory extends Section
{
    /** @var array */
    protected $categories_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cacheStatusCategoryInfo(\stdClass $StatusCategoryInfo)
    {
        $this->categories_list[(int)$StatusCategoryInfo->id] = $StatusCategoryInfo;
    }

    /**
     * Get list of all status categories configured in current JIRA installation
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/statuscategory-getStatusCategoryes
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of statuscategories, indexed by IDs
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            foreach ($this->Jira->get('/statuscategory') as $StatusCategoryInfo) {
                $this->cacheStatusCategoryInfo($StatusCategoryInfo);
            }
            $this->all_cached = true;
        }

        return $this->categories_list;
    }

    /**
     * Get particular status category info identified by it's unique ID
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/statuscategory-getStatusCategory
     *
     * @param int $id - ID of statuscategory you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $StatusCategoryInfo = $this->categories_list[$id] ?? null;

        if (!isset($StatusCategoryInfo) || $reload_cache) {
            $StatusCategoryInfo = $this->Jira->get("/statuscategory/{$id}");
            $this->cacheStatusCategoryInfo($StatusCategoryInfo);
        }

        return $StatusCategoryInfo;
    }
}
