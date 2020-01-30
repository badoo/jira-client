<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class IssueLink extends Section
{
    /**
     * Create a link between two issues.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLink-linkIssues
     *
     * @param string $type          - link type name. Don't mess with link texts for 'in' and 'out' ends.
     * @param string $inward_issue  - attach inward end of the link to this issue.
     *                                this issue will be shown on page with Inward Description text
     * @param string $outward_issue - attach outward end of the link to this issue
     *                                this issue will be shown on page with Outward Description text
     * @param string $comment       - add a comment to both linked issues
     * @param array $visibility     - set comment visibility
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        string $type,
        string $outward_issue,
        string $inward_issue,
        string $comment = '',
        array $visibility = []
    ) : void {
        $args = [
            'type'          => ['name' => $type],
            'outwardIssue'  => ['key' => $outward_issue],
            'inwardIssue'   => ['key' => $inward_issue],
        ];

        if (!empty($comment)) {
            $comment_arg = [
                'body' => $comment
            ];

            if (!empty($visibility)) {
                $comment_arg['visibility'] = $visibility;
            }

            $args['comment'] = $comment_arg;
        }

        $this->Jira->post('issueLink', $args);
    }

    /**
     * Get info for specific link between two issues
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLink-getIssueLink
     *
     * @param int $link_id - ID of link to get
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $link_id) : \stdClass
    {
        return $this->Jira->get("issueLink/{$link_id}");
    }

    /**
     * Delete a link between issues
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issueLink-deleteIssueLink
     *
     * @param int $link_id - ID of link to delete
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(int $link_id) : void
    {
        $this->Jira->delete("issueLink/{$link_id}");
    }

    /**
     * List links attached to specific issue.
     *
     * NOTE: this is a synthetic method. JIRA API has no special method for doing this.
     *
     * @param string $issue_key - get links attached to this issue
     * @param string $type - list only links of specific type. Search is performed in link type name AND
     *                       inward/outward description text
     *                       Example:
     *                         for 'Dependency' value this will return both 'blocks' and 'is blocked by'
     *                         for 'blocks' this will return only 'blocks' links
     * @param bool $case_sensitive - perform case sensitive filtration by <type> parameter
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listForIssue(string $issue_key, string $type = '', bool $case_sensitive = false) : array
    {
        $IssueInfo = $this->Jira->get("/issue/{$issue_key}", ['fields' => 'issuelinks']);

        $links = $IssueInfo->fields->issuelinks;

        if (empty($type)) {
            return $links;
        }

        $filtered = [];
        if ($case_sensitive) {
            foreach ($links as $LinkInfo) {
                if ($LinkInfo->type->name === $type ||
                    $LinkInfo->type->inward === $type ||
                    $LinkInfo->type->outward === $type) {
                    $filtered[] = $LinkInfo;
                }
            }
        } else {
            $type = strtolower($type);
            foreach ($links as $LinkInfo) {
                if (strtolower($LinkInfo->type->name) === $type ||
                    strtolower($LinkInfo->type->inward) === $type ||
                    strtolower($LinkInfo->type->outward) === $type) {
                    $filtered[] = $LinkInfo;
                }
            }
        }

        return $filtered;
    }
}
