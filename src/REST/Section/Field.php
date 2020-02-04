<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\REST\Section;

class Field extends Section
{
    /** @var array */
    protected $fields_list;
    /** @var array */
    protected $system_fields;
    /** @var array */
    protected $custom_fields;

    /**
     * Create new custom field.
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/field-createCustomField
     *
     * @param string $name          - new custom field name
     * @param string $description   - field description
     * @param string $type          - field type
     * @param array $add_properties - additional properties for field.
     *                                They may depend on field type you are trying to create.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function create(string $name, string $description, string $type, array $add_properties = []) : void
    {
        $args = [
            'name'          => $name,
            'description'   => $description,
            'type'          => $type,
        ];

        $args = array_merge($args, $add_properties);

        $this->Jira->post('/field', $args);

        // Drop fields list cache, we just have added a new field into the list
        $this->fields_list   = null;
        $this->system_fields = null;
        $this->custom_fields = null;
    }

    /**
     * Get list of all fields, system and custom ones
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/7.6.1/#api/2/field-getFields
     *
     * @param bool $reload_cache - force data reload. The method caches fields list, you can bypass cache and make it
     *                             to load fresh data from API once again.
     *
     * @return \stdClass[] - list of fields, both system and custom, indexed by IDs
     *                       (e.g. 'description' or 'customfield_12345')
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function list(bool $reload_cache = false) : array
    {
        if (!isset($this->fields_list) || $reload_cache) {
            $this->fields_list = [];

            $fields_list = $this->Jira->get('/field');
            foreach ($fields_list as $field_info) {
                $is_custom = $field_info->custom;
                $field_id = $field_info->id;

                $this->fields_list[$field_id] =  $field_info;

                if ($is_custom) {
                    $this->custom_fields[$field_id] = $field_info;
                } else {
                    $this->system_fields[$field_id] = $field_info;
                }
            }
        }

        return $this->fields_list;
    }

    /**
     * @see Field::list() method DocBlock for more information
     *
     * Get list of system fields
     *
     * @param bool $reload_cache - force data reload
     *
     * @return \stdClass[] - list of system fields indexed by field ID (e.g. 'description')
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listSystem(bool $reload_cache = false) : array
    {
        if (!isset($this->system_fields) || $reload_cache) {
            $this->list($reload_cache);
        }

        return $this->system_fields;
    }

    /**
     * @see Field::list() method DocBlock for more information
     *
     * Get list of custom fields
     *
     * @param bool $reload_cache - force data reload
     *
     * @return \stdClass[] - list of custom fields indexed by field ID (e.g. 'customfield_12345')
     * @throws \Badoo\Jira\REST\Exception
     */
    public function listCustom(bool $reload_cache = false) : array
    {
        if (!isset($this->custom_fields) || $reload_cache) {
            $this->list($reload_cache);
        }

        return $this->custom_fields;
    }

    /**
     * @param string $id
     * @param bool $reload_cache
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function get(string $id, bool $reload_cache = false) : \stdClass
    {
        $fields = $this->list($reload_cache);

        if (!isset($fields[$id])) {
            throw new \Badoo\Jira\REST\Exception("Field with ID '{$id}' not found in JIRA");
        }

        return $fields[$id];
    }

    /**
     * Search field by name. Return the first one found
     *
     * NOTE: this is synthetic method, JIRA API has no special method
     *
     * @param string $name - search for field with this name
     * @param bool $case_sensitive - perform case-sensitive or case-insensitive search
     * @param bool $reload_cache - force internal cache reload and request API for the fresh data before search
     *
     * @return \stdClass[] - list of fields with given name
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function search(string $name, bool $case_sensitive = true, bool $reload_cache = false) : array
    {
        $found = [];

        if ($case_sensitive) {
            foreach ($this->list($reload_cache) as $FieldInfo) {
                if ($FieldInfo->name === $name) {
                    $found[$FieldInfo->id] = $FieldInfo;
                }
            }
        } else {
            $name = strtolower($name);

            foreach ($this->list($reload_cache) as $FieldInfo) {
                if (strtolower($FieldInfo->name) === $name) {
                    $found[$FieldInfo->id] = $FieldInfo;
                }
            }
        }

        return $found;
    }
}
