<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Section
{
    /** @var \Badoo\Jira\REST\ClientRaw */
    protected $Jira;

    /** @var \Badoo\Jira\REST\Section\Section[] $sections */
    protected $sections = [];

    /** @var bool $isCloudJira - for some sections it's important to know if we query cloud or server Jira */
    protected $is_cloud_jira = false;

    /**
     * ASection constructor.
     * @param \Badoo\Jira\REST\ClientRaw $Jira
     */
    public function __construct(\Badoo\Jira\REST\ClientRaw $Jira, ?bool $is_cloud_jira = null)
    {
        $this->Jira = $Jira;
        $this->is_cloud_jira = $is_cloud_jira;
    }

    protected function isCloudJira(): bool
    {
        if ($this->is_cloud_jira === null) {
            $server_info = $this->Jira->get("/serverInfo");
            $this->is_cloud_jira = ($server_info->deploymentType ?? '') === 'Cloud';
        }
        return $this->is_cloud_jira;
    }

    /**
     * @param string $section_key - the unique section key for cache. This prevents twin objects creation for
     *                              the same section on each method call.
     *
     * @param string $section_class - use special custom class for given section.
     *                                E.g. ->getSubSection('/issue', '\Badoo\Jira\REST\Section\Issue') will initialize
     *                                and return \Badoo\Jira\REST\Section\Issue class for section /issue.
     *
     * @return Section
     */
    protected function getSection(string $section_key, string $section_class)
    {
        if (!isset($this->sections[$section_key])) {
            $Section = new $section_class($this->Jira, $this->isCloudJira());
            $this->sections[$section_key] = $Section;
        }

        return $this->sections[$section_key];
    }
}
