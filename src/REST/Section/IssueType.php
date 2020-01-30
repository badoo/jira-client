<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class IssueType extends Section
{
    /** @var \stdClass[] */
    protected $types_list;
    /** @var bool - sign that <loaded_types> contains full list of types available for user */
    protected $all_cached = false;

    protected function cacheTypeInfo(\stdClass $TypeInfo)
    {
        $this->types_list[$TypeInfo->id] = $TypeInfo;
    }

    /**
     * List all issue types visible to the current user
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issuetype-getIssueAllTypes
     *
     * @param bool $reload_cache - ignore internal client cache and request API for fresh data
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            $this->types_list = [];
            foreach ($this->Jira->get("/issuetype") as $TypeInfo) {
                $this->cacheTypeInfo($TypeInfo);
            }
            $this->all_cached = true;
        }

        return $this->types_list;
    }

    /**
     * Get issue type info
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issuetype-getIssueType
     *
     * @param int $id - unique ID of issue type
     * @param bool $reload_cache - ignore internal client cache and request API for fresh data
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id, bool $reload_cache = false) : \stdClass
    {
        $TypeInfo = $this->types_list[$id] ?? null;
        if (!isset($TypeInfo) || $reload_cache) {
            $TypeInfo = $this->Jira->get("/issuetype/{$id}");
        }

        $this->cacheTypeInfo($TypeInfo);
        return $TypeInfo;
    }

    /**
     * Search issue type by name.
     *
     * NOTE: this is synthetic method, JIRA API has no special method searching types by name
     *       The full list of issue types is loaded before search.
     *
     * @see IssueType::list()
     *
     * @param string $type_name - desired issue type name
     * @param bool $case_sensitive - perform case sensitive search. True by default
     * @param bool $reload_cache - ignore internal client cache and request JIRA API for fresh data
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function searchByName(string $type_name, bool $case_sensitive = true, bool $reload_cache = false) : ?\stdClass
    {
        $types = $this->list($reload_cache);

        if ($case_sensitive) {
            foreach ($types as $TypeInfo) {
                if ($TypeInfo->name === $type_name) {
                    return $TypeInfo;
                }
            }

            return null;
        }

        $type_name = strtolower($type_name);

        foreach ($types as $TypeInfo) {
            if (strtolower($TypeInfo->name) === $type_name) {
                return $TypeInfo;
            }
        }

        return null;
    }
}
