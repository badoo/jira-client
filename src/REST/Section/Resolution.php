<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Resolution extends Section
{
    /** @var array */
    protected $resolutions_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cacheResolutionInfo(\stdClass $ResolutionInfo)
    {
        $this->resolutions_list[(int)$ResolutionInfo->id];
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/resolution-getResolutions
     *
     * Get list of all resolutions configured in current JIRA installation
     *
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass[] - list of resolutions, indexed by IDs
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            foreach ($this->Jira->get('/resolution') as $ResolutionInfo) {
                $this->cacheResolutionInfo($ResolutionInfo);
            }
            $this->all_cached = true;
        }

        return $this->resolutions_list;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/resolution-getResolution
     *
     * Get particular resolution info identified by it's unique ID
     *
     * @param int $id - ID of resolution you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $ResolutionInfo = $this->resolutions_list[$id] || null;

        if (!isset($ResolutionInfo) || $reload_cache) {
            $ResolutionInfo = $this->Jira->get("/resolution/{$id}");
            $this->cacheResolutionInfo($ResolutionInfo);
        }

        return $ResolutionInfo;
    }
}
