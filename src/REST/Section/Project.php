<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Project extends Section
{
    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProject
     *
     * Get specific project info
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
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProjectComponents
     *
     * List all project components
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
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getProjectVersions
     *
     * List all project versions
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

    /**
     * Returns latest version in project<br>
     * **ATTENTION**:semver only
     *
     * @see https://www.php.net/manual/en/function.version-compare.php More about version comparison
     *
     * @param $project string|int
     *
     * @return \stdClass|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getLatestVersion($project) : ?\stdClass
    {
        $versions = $this->listVersions($project);
        if (empty($versions)) {
            return null;
        }
        $latest_version = array_pop($versions);
        foreach ($versions as $version) {
            if (version_compare($version->name ?? '', $latest_version->name ?? '', 'gt')) {
                $latest_version = $version;
            }
        }
        return $latest_version;
    }


    /**
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/project-getAllStatuses
     *
     * List all project statuses
     *
     * @param $project
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listStatuses($project) : array
    {
        return $this->Jira->get("project/{$project}/statuses");
    }
}
