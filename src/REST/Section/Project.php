<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Project extends Section
{
    /**
     * Get specific project info
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProject
     *
     * @param string|int $project - project ID (e.g. 100500) or key (e.g. 'EX')
     * @param string[] $expand - ask JIRA to provide additional info in response
     *
     * @return \stdClass - project info
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get($project, array $expand = [])
    {
        $parameters = [];

        if (!empty($expand)) {
            $parameters['expand'] = implode(',', $expand);
        }

        return $this->Jira->get("project/{$project}", $parameters);
    }

    /**
     * List all project components
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProjectComponents
     *
     * @param string|int $project - project ID (e.g. 100500) or key (e.g. 'EX')
     *
     * @return \stdClass[] - list of Component info objects
     * @see Component::get() for mor info about data structure
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listComponents($project) : array
    {
        return $this->Jira->get("project/{$project}/components");
    }

    /**
     * List all project versions
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProjectVersions
     *
     * @param string|int $project - project ID (e.g. 100500) or key (e.g. 'EX')
     *
     * @return \stdClass[] - list of Version info objects
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listVersions($project) : array
    {
        return $this->Jira->get("project/{$project}/versions");
    }
}
