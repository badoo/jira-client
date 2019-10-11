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

    /**
     * ASection constructor.
     * @param \Badoo\Jira\REST\ClientRaw $Jira
     */
    public function __construct(\Badoo\Jira\REST\ClientRaw $Jira)
    {
        $this->Jira = $Jira;
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
            $Section = new $section_class($this->Jira, $section_key);
            $this->sections[$section_key] = $Section;
        }

        return $this->sections[$section_key];
    }
}
