<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Component extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-getComponent
     *
     * Get single JIRA Component data by ID
     *
     * @param int    $id              - unique Component ID
     *
     * @return \stdClass - component info (some of data is not shown)
     *                       [
     *                         'project'      => <component's project key (e.g. 'EX'), string>,
     *                         'projectId'    => <component's project ID, int>,
     *                         'id'           => <unique Component ID, int>,
     *                         'name'         => <texual component name, string>,
     *                         'description'  => <detailed component description, string>,
     *                         'lead'         => <Jira user info, \stdClass>,
     *                         'assignee'     => <Jira user info, \stdClass>,
     *                         'assigneeType' => <one of supported assignee types (e.g. PROJECT_LEAD), string>
     *                         ...
     *                       ]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id) : \stdClass
    {
        return $this->Jira->get("component/{$id}");
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-createComponent
     *
     * Create new component with name <name> in project <project>
     *
     * @param string|int $project     - parent project ID or key (e.g. 10000 or 'EX')
     * @param string $name            - component name
     * @param array $optional_fields  - additional fields to set for component
     *
     * @return \stdClass
     * @see Component::get() DocBlock for more info about format
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(string $project, string $name, array $optional_fields = []) : \stdClass
    {
        $args = $optional_fields;

        if (is_numeric($project)) {
            $args['projectId'] = (int)$project;
        } else {
            $args['project'] = $project;
        }

        $args['name'] = $name;

        return $this->Jira->post("component", $args);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-updateComponent
     * @see Component::create() method DocBlock for more info about parameters and returned data.
     *
     * Update an existing component
     *
     * @param int   $id         - unique Component ID
     * @param array $update     - fields to update
     *                              e.g.:
     *                                  {
     *                                      'name': "Component 1",
     *                                      'description': "This is a JIRA component",
     *                                      'leadUserName': "fred",
     *                                      'assigneeType': "PROJECT_LEAD",
     *                                      'isAssigneeTypeValid': false,
     *                                      'project': "PROJECTKEY",
     *                                      'projectId': 10000
     *                                  }
     *
     * @return \stdClass - updated component info
     * @see Component::get() DocBlock for more info about format
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function update(
        int $id,
        array $update = []
    ) : \stdClass {
        return $this->Jira->put("component/{$id}", $update);
    }


    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/component-delete
     *
     * Delete an existing Component
     *
     * @param int $id               - unique Component ID
     * @param int $move_issues_to   - apply this component to all issues, who had the deleted one
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(int $id, int $move_issues_to = 0) : void
    {
        $args = [];

        if ($move_issues_to === 0) {
            $args['moveIssuesTo'] = $move_issues_to;
        }

        $this->Jira->delete("component/{$id}", $args);
    }
}
