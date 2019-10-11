<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Version extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/version-createVersion
     *
     * Create new version in given project
     *
     * @param $project - project ID (e.g. 100500) or key (e.g. EX)
     * @param string $name - version name
     * @param array $optional_fields - all other version info fields that are not required to be set at the creation
     *                                 See API methof description on web page for more information.
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(
        $project,
        string $name,
        array $optional_fields = []
    ) : \stdClass {
        $parameters = [
            "name" => $name,
        ];

        if (is_numeric($project)) {
            $parameters["projectId"] = (int)$project;
        } else {
            $parameters["project"] = $project;
        }

        $parameters = array_merge($optional_fields, $parameters);

        return $this->Jira->post('/version', $parameters);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/version-updateVersion
     *
     * Update version information.
     *
     * @param int $id
     * @param array $update - info to update. See API method description on web page for more information.
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function update(
        int $id,
        array $update
    ) : \stdClass {
        return $this->Jira->put("/version/{$id}", $update);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/version-delete
     *
     * Delete version
     *
     * @param int $id - ID of verison to delete
     * @param string|null $move_fixed_to - replace deleted version with another one in fixVersions field,
     *                                     null value just deletes version from field
     * @param string|null $move_affected_to - replace deleted version with another one in affectedVersion field
     *                                        null value just deletes version from field
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function delete(int $id, string $move_fixed_to = null, string $move_affected_to = null) : void
    {
        $parameters = [];

        if (!empty($move_fixed_to)) {
            $parameters['moveFixIssuesTo'] = $move_fixed_to;
        }

        if (!empty($move_affected_to)) {
            $parameters['moveAffectedIssuesTo'] = $move_affected_to;
        }

        $this->Jira->delete("/version/${id}", $parameters);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/version-moveVersion
     *
     * Reorder versions sequence on page. Move given issue to the position, or put it after some other version in a list
     *
     * @param int $id
     * @param string $position - new absolute position of version in list
     * @param null $after - relative position, put version <id> after the one specified by <after> self link
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function move(int $id, $position = null, $after = null) : \stdClass
    {
        $parameters = [];

        if (isset($position)) {
            $parameters['position'] = $position;
        } else {
            $parameters['after'] = $after;
        }

        return $this->Jira->post("/version/${id}/move", $parameters);
    }

    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/version-getVersion
     *
     * Get full info about version with <ID>
     *
     * @param int $id - ID of version to load from JIRA
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(int $id) : \stdClass
    {
        return $this->Jira->get("/version/${id}");
    }
}
