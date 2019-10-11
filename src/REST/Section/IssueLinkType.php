<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class IssueLinkType extends Section
{
    /** @var \stdClass[] */
    protected $link_types_list = [];
    /** @var bool */
    protected $all_cached = false;

    protected function cacheLinkType(\stdClass $LinkTypeInfo)
    {
        $this->link_types_list[$LinkTypeInfo->id] = $LinkTypeInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-getIssueLinkTypes
     *
     * Get list of all known issue types
     *
     * @param bool $reload_cache - ignore cache and load fresh data from API
     *
     * @return \stdClass[] - list of all known issue types indexed by IDs
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!$this->all_cached || $reload_cache) {
            $response = $this->Jira->get('issueLinkType');

            foreach ($response->issueLinkTypes as $LinkTypeInfo) {
                $this->cacheLinkType($LinkTypeInfo);
            }

            $this->all_cached = true;
        }

        return $this->link_types_list;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-createIssueLinkType
     *
     * Create a link between two issues.
     *
     * @param string $name      - link type name
     * @param string $outward   - text to display for inward issue
     * @param string $inward    - text to display for outward issue
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        string $name,
        string $inward,
        string $outward
    ) : \stdClass {
        $args = [
            'name'      => $name,
            'inward'    => $inward,
            'outward'   => $outward,
        ];

        $LinkTypeInfo = $this->Jira->post('issueLinkType', $args);
        $this->cacheLinkType($LinkTypeInfo);

        return $LinkTypeInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-getIssueLinkType
     *
     * Get info for specific link type
     *
     * @param int $link_type_id - ID of link type to get
     * @param bool $reload_cache - ignore cache and load fresh data from API
     *
     * @return \stdClass - link type info, see ::create method DocBlock for format description.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $link_type_id, bool $reload_cache = false) : \stdClass
    {
        $LinkTypeInfo = $this->link_types_list[$link_type_id] ?? null;
        if (!isset($LinkTypeInfo) || $reload_cache) {
            $LinkTypeInfo = $this->Jira->get("issueLinkType/{$link_type_id}");
            $this->cacheLinkType($LinkTypeInfo);
        }

        return $LinkTypeInfo;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLinkType-updateIssueLinkType
     *
     * Update link type information
     *
     * @param int $link_type_id - ID of link type to get
     * @param string $name      - new link type name. Empty string means 'do not update'.
     * @param string $outward   - new text to display for inward issue. Empty string means 'do not update'.
     * @param string $inward    - new text to display for outward issue. Empty string means 'do not update'.
     *
     * @return \stdClass - link type info, see ::create method DocBlock for format description.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function update(
        int $link_type_id,
        string $name = '',
        string $inward = '',
        string $outward = ''
    ) : \stdClass {
        $args = [
            'name'      => $name,
            'inward'    => $inward,
            'outward'   => $outward,
        ];

        if (isset($name)) {
            $args['name'] = $name;
        }
        if (isset($inward)) {
            $args['inward'] = $inward;
        }
        if (isset($outward)) {
            $args['outward'] = $outward;
        }

        $LinkTypeInfo = $this->Jira->put("issueLinkType/{$link_type_id}", $args);
        $this->cacheLinkType($LinkTypeInfo);

        return $this->Jira->put("issueLinkType/{$link_type_id}", $args);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLink-deleteIssueLink
     *
     * Delete a link between issues
     *
     * @param int $link_id - ID of link to delete
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(int $link_id) : void
    {
        $this->Jira->delete("issueLinkType/{$link_id}");
    }
}
