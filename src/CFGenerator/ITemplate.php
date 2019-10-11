<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CFGenerator;

interface ITemplate
{
    /**
     * Render class definition for field
     *
     * @param \stdClass $FieldInfo - JIRA custom field info as it is returned from JIRA API
     * @param string $full_class_name - name of field class with namespace.
     *
     * @return string - generated class text
     */
    public function render(\stdClass $FieldInfo, string $full_class_name) : string;
}
