<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Comment extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getComments
     *
     * List at most <max_results> comments starting from <start_at> for issue <issue_key>
     *
     * @param string $issue_key       - parent issue key
     * @param int    $start_at        - list start position
     * @param int    $max_results     - maximum list size
     * @param string $order_by        - field name to use for ordering
     * @param bool   $expand_rendered - include 'renderedBody' into response data
     *
     * @return \stdClass[] - list of issue comments.
     *                       @see Comment::get() DocBlock method documentation for brief info about response data format
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(string $issue_key, int $start_at = 0, int $max_results = -1, string $order_by = '', bool $expand_rendered = false) : array
    {
        $args = [
            'startAt' => $start_at,
        ];

        if ($max_results >= 0) {
            $args['maxResults'] = $max_results;
        }

        if (!empty($order_by)) {
            $args['orderBy'] = $order_by;
        }

        if ($expand_rendered) {
            $args['expand'] = 'renderedBody';
        }

        $result = $this->Jira->get("issue/{$issue_key}/comment", $args);
        return $result->comments;
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getComment
     *
     * Get single comment ID data
     *
     * @param string $issue_key       - parent issue key
     * @param int    $id              - unique comment ID
     * @param bool   $expand_rendered - include 'renderedBody' into response data
     *
     * @return \stdClass - comment info (some of data is not shown)
     *                       [
     *                         'id'           => <unique commend ID, int>,
     *                         'author'       => <Jira user info>,
     *                         'updateAuthor' => <Jira user info>,
     *                         'body'         => <raw comment text, string>,
     *                         'renderedBody' => <HTML comment text, string>,
     *                         'created'      => <comment creation time, ISO formatted string>,
     *                         'updated'      => <comment last update time, ISO formatted string>,
     *                         'visibility'   => <visibility info>
     *                       ]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $issue_key, int $id, $expand_rendered = false) : \stdClass
    {
        $args = [];
        if ($expand_rendered) {
            $args['expand'] = 'renderedBody';
        }

        return $this->Jira->get("issue/{$issue_key}/comment/{$id}", $args);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-addComment
     *
     * Add a comment with text <text> to issue with key <issue_key>
     *
     * @param string $issue_key       - parent issue key
     * @param string $text            - comment body as raw text
     * @param array|null $visibility  - visibility restrictions,
     *                                    ["type" => "role", "value" => "Administrators"] - project Administrators only
     *                                    []   - default restrictions for new comments
     *                                    null - no restrictions (public access)
     * @param bool   $expand_rendered - include 'renderedBody' into response data
     *
     * @return \stdClass
     * @see Comment::get() DocBlock for more info about format
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(string $issue_key, string $text, ?array $visibility = [], bool $expand_rendered = false) : \stdClass
    {
        $args = [
            'body' => $text,
        ];

        if ($visibility !== []) {
            $args['visibility'] = $visibility;
        }
        if ($expand_rendered) {
            $args['expand'] = 'renderedBody';
        }

        return $this->Jira->post("issue/{$issue_key}/comment", $args);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-updateComment
     * @see Comment::create() method DocBlock for more info about parameters and returned data.
     *
     * Update an existing comment
     *
     * @param string $issue_key         - key of issue that contains the comment
     * @param int    $id                - unique comment ID
     * @param string $text              - new comment body (raw text)
     * @param array|null $visibility    - the visibility restrictions. See Comment::add DocBlock for more info
     *                                      []   - don't update restrictions
     *                                      null - drop restrictions (public access)
     * @param bool   $expand_rendered   - include 'renderedBody' of new comment text into response data
     *
     * @return \stdClass - updated comment data
     * @see Comment::get() DocBlock for more info about format
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function update(
        string $issue_key,
        int $id,
        string $text,
        ?array $visibility = [],
        bool $expand_rendered = false
    ) : \stdClass {
        $args = [
            'body' => $text,
        ];

        if ($visibility !== []) {
            $args['visibility'] = $visibility;
        }

        if ($expand_rendered) {
            $args['expand'] = 'renderedBody';
        }

        return $this->Jira->put("issue/{$issue_key}/comment/{$id}", $args);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-deleteComment
     *
     * Delete an existing comment
     *
     * @param string $issue_key - key of issue that contains the comment
     * @param int $id           - unique comment ID
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(string $issue_key, int $id) : void
    {
        $this->Jira->delete("issue/{$issue_key}/comment/{$id}");
    }
}
