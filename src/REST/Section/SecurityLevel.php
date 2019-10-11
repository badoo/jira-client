<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class SecurityLevel extends Section
{
    /** @var array */
    protected $security_levels_list = [];

    protected function cacheSecurityLevelInfo(\stdClass $SecurityLevelInfo)
    {
        $this->security_levels_list[(int)$SecurityLevelInfo->id] = $SecurityLevelInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/securitylevel-getIssuesecuritylevel
     *
     * Get particular security level info identified by it's unique ID
     *
     * @param int $id - ID of security level you want to load
     * @param bool $reload_cache - force API request to get fresh data from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $SecurityLevelInfo = $this->security_levels_list[$id] ?? null;

        if (!isset($SecurityLevelInfo) || $reload_cache) {
            $SecurityLevelInfo = $this->Jira->get("/securitylevel/{$id}");
            $this->cacheSecurityLevelInfo($SecurityLevelInfo);
        }

        return $SecurityLevelInfo;
    }
}
