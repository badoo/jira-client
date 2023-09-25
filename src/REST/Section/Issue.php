<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Issue extends Section
{
    /**
     * Expansion groups. Additional information for issue to be requested from Jira API.
     * Constants are used as $expand parameters in get issue information methods (->get(), ->search(), ...)
     *
     * @see Issue::get() DocBlock for more information.
     */
    const
        EXP_CHANGELOG       = 'changelog',
        EXP_RENDERED_FIELDS = 'renderedFields',
        EXP_CREATEMETA_FIELDS = 'projects.issuetypes.fields';

    /** @var array */
    protected $edit_meta = [];

    /**
     * Get interface for operations with issue comments
     */
    public function comment() : Comment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('comment', Comment::class);
    }

    /**
     * Get interface for operations with issue watchers
     */
    public function watchers() : Watchers
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('watchers', Watchers::class);
    }

    public function attachment() : IssueAttachment
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('attachment', IssueAttachment::class);
    }

    public function transitions() : IssueTransitions
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getSection('transitions', IssueTransitions::class);
    }

    /**
     * Get issue info as array
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue
     *
     * @param string $issue_key
     * @param array  $fields
     * @param array  $expand - provide additional information for issue
     *                         (check 'Expansion' section at https://docs.atlassian.com/jira/REST/cloud/ for more info)
     * @param array  $properties
     *
     * @return \stdClass
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $issue_key, array $fields = [], array $expand = [], array $properties = []) : \stdClass
    {
        $issue_key = trim($issue_key);
        if (empty($issue_key)) {
            throw new \Badoo\Jira\REST\Exception("Can't get info for issue with empty key");
        }

        $args = [];

        if (!empty($fields)) {
            $args['fields'] = implode(',', $fields);
        }

        $args['expand'] = implode(',', array_unique(array_merge($expand, ["names"])));

        if (!empty($properties)) {
            $args['properties'] = $properties;
        }

        return $this->Jira->get("issue/{$issue_key}", $args);
    }

    /**
     * Search for issues using JQL.
     * This method is the same to Client->search() one with some differencies:
     *  - it returns the list of issues from 'issues' response field.
     *  - it has much higher max_results default value
     *  - it always validates your query, you can't disable it
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/search-search
     *
     * @param string    $jql
     * @param string[]  $fields
     * @param string[]  $expand
     * @param int       $max_results
     * @param int       $start_at
     *
     * @return \stdClass[] - list of issues
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function search(
        string $jql,
        $fields = [],
        $expand = [],
        int $max_results = 1000,
        int $start_at = 0
    ) : array {
        $args = [
            'jql'           => $jql,
            'startAt'       => $start_at,
            'maxResults'    => $max_results,
            'validateQuery' => true,
        ];

        if (!empty($fields)) {
            $args['fields'] = $fields;
        }

        $args['expand'] = array_unique(array_merge($expand, ["names"]));

        $result = $this->Jira->post('/search', $args);
        return $result->issues;
    }

    /**
     * Assign issue to a user
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-assign
     *
     * @param string $issue_key
     * @param string|null $user_name -   '-1' = default assignee for project.
     *                                 'null' = unassigned
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function assign($issue_key, ?string $user_name = null) : void
    {
        $this->Jira->put("issue/{$issue_key}/assignee", ['name' => $user_name]);
    }

    /**
     * List transitions available for issue in it's current state.
     * NOTE: 'fields' section with available/required fields exists only when <expand_fields> is set to true.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getTransitions
     *
     * @param string $issue_key
     * @param bool   $expand_fields - add list of fields available for modification during each transition to response
     * @return array[][] - list of possible transitions.
     *                       Transition format sample (some of data is not shown)
     *                         [
     *                           'id'   => <transition id>,
     *                           'name' => <transition textual name shown in UI>,
     *                           'to' => [
     *                             'id'             => <target status ID, like 1234>,
     *                             'name'           => <target status name shown in UI>,
     *                             'description'    => <target status textual description>,
     *                             'statusCategory' => [
     *                                 'id'    => <category ID, like 4>
     *                                 'key'   => <category key, like 'indeterminate'>
     *                                 'name'  => <status category name, like 'In Progress'>
     *                                 ...
     *                             ],
     *                             ...
     *                           ],
     *                           'fields' => [
     *                             <field ID (e.g. 'description' or 'customfield_11111')> => [
     *                               'required' => <bool>
     *                               'name'     => <field textual name shown in UI>
     *                               ...
     *                             ]
     *                           ],
     *                         ]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getTransitions(string $issue_key, bool $expand_fields = false)
    {
        $request_data = [];
        if ($expand_fields) {
            $request_data = ['expand' => 'transitions.fields'];
        }
        $actions_info = $this->Jira->get("issue/{$issue_key}/transitions", $request_data);
        return $actions_info->transitions;
    }

    /**
     * Get metainformation about the issue creation for specific projects and issue types: fields available on
     * creation screen, their possible values and so on.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getCreateIssueMeta
     *
     * @param string|string[] $projects    - list of unique Jira Project keys or IDs (e.g. IOS or 12345).
     * @param string|string[] $issue_types - list of unique Jira issue type names or IDs (e.g. 'Bug' or 12345)
     * @param bool $expand_fields          - request for additional information.
     *
     * @return \stdClass[] - list of projects with issues creation metadata for them
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getCreateMeta($projects, $issue_types = [], bool $expand_fields = false) : array
    {
        if (!is_array($projects)) {
            $projects = [$projects];
        }
        if (!is_array($issue_types)) {
            $issue_types = [$issue_types];
        }

        $p_ids  = [];
        $p_keys = [];
        foreach ($projects as $project) {
            if (is_numeric($project)) {
                $p_ids[] = $project;
            } else {
                $p_keys[] = $project;
            }
        }

        $t_ids = [];
        $t_names = [];
        foreach ($issue_types as $type) {
            if (is_numeric($type)) {
                $t_ids[] = $type;
            } else {
                $t_names[] = $type;
            }
        }

        $parameters = [];
        if (!empty($p_ids)) {
            $parameters['projectIds'] = implode(',', $p_ids);
        }
        if (!empty($p_keys)) {
            $parameters['projectKeys'] = implode(',', $p_keys);
        }
        if (!empty($t_ids)) {
            $parameters['issuetypeIds'] = implode(',', $t_ids);
        }
        if (!empty($t_names)) {
            $parameters['issuetypeNames'] = implode(',', $t_names);
        }
        if ($expand_fields) {
            $parameters['expand'] = 'projects.issuetypes.fields';
        }

        $response = $this->Jira->get('issue/createmeta', $parameters);
        return $response->projects;
    }

    /**
     * Get metainformation about issue editing: what fields are available on edit screen, their possible values, etc.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-getEditIssueMeta
     *
     * @param string $issue_key
     * @return \stdClass[] - list of fields current user can edit in issue
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getEditMeta(string $issue_key) : array
    {
        if (!isset($this->edit_meta[$issue_key])) {
            $response = $this->Jira->get("issue/{$issue_key}/editmeta");
            $this->edit_meta[$issue_key] = get_object_vars($response->fields);
        }

        return $this->edit_meta[$issue_key];
    }

    /**
     * Update issue fields
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-editIssue
     *
     * @param string $issue_key - update this issue
     * @param array  $fields    - a shorthand for issue update rules. Has simpler structure:
     *                              [
     *                                "summary": "This is a shorthand for a set operation on the summary field",
     *                                "customfield_10010": 1,
     *                                "customfield_10000": "This is a shorthand for a set operation on a text custom field",
     *                              ]
     * @param array  $update    - issue update rules.
     *                            Accepts the following data structure:
     *                              [
     *                                "summary": [
     *                                  [ "set": "Bug in business logic" ]
     *                                ],
     *                                "timetracking": [
     *                                  [
     *                                    "edit": [
     *                                      "originalEstimate": "1w 1d",
     *                                      "remainingEstimate": "4d"
     *                                    ]
     *                                  ]
     *                                ],
     *                                "labels": [
     *                                  [ "add": "triaged" ],
     *                                  [ "remove": "blocker" ]
     *                                ],
     *                                "components": [
     *                                  [ "set": "" ]
     *                                ],
     *                              ]
     * @param array $properties   - set issue properties
     * @param bool  $notify_users - notify watchers about the update. Requires administrator privileges in issue's project.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function edit(string $issue_key, array $fields, array $update, array $properties = [], $notify_users = true) : void
    {
        $update_request = [];

        if (!empty($fields)) {
            $update_request['fields'] = $fields;
        }

        if (!empty($update)) {
            $update_request['update'] = $update;
        }

        if (!empty($properties)) {
            $update_request['properties'] = $properties;
        }

        $update_request['notifyUsers'] = $notify_users;

        $this->Jira->put("issue/{$issue_key}", $update_request);
    }

    /**
     * Create new issue
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/issue-createIssue
     *
     * @param array $fields
     * @param array $update
     * @param array $transition
     * @param array $properties
     * @param array $history_meta
     * @return \stdClass
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        array $fields,
        array $update = [],
        array $transition = [],
        array $properties = [],
        array $history_meta = []
    ) : \stdClass {
        $create_request = [];

        if (!empty($fields)) {
            $create_request['fields'] = $fields;
        }

        if (!empty($update)) {
            $create_request['update'] = $update;
        }

        if (!empty($transition)) {
            $create_request['transition'] = $transition;
        }

        if (!empty($properties)) {
            $create_request['properties'] = $properties;
        }

        if (!empty($history_meta)) {
            $create_request['historyMetadata'] = $history_meta;
        }

        return $this->Jira->post('/issue', $create_request);
    }

    public function delete(string $issue_key) : void
    {
        $this->Jira->delete("/issue/{$issue_key}");
    }
}
