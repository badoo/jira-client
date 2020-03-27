<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Issue;

/**
 * Class CreateRequest
 * @package Badoo\Jira\Issue
 *
 * Usage example:
 *
 *      $CreateRequest = new CreateRequest('BO');
 *      $CreateRequest
 *        ->setIssueType('Task')O
 *        ->setPriority('Minor')
 *        ->setSummary('Task Title')
 *        ->setDescription('Task description')
 *        ->addComponent('Collaborative Platform')
 *        ->setFieldValue('Issue for', 'PHP')
 *        ->setFieldValue('Translate it', 'No');
 *
 *      $Issue = $CreateRequest->send();
 */
class CreateRequest
{
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /** @var array - list of fields to set in create request */
    protected $fields = [];

    /** @var string */
    protected $issue_type_name;

    /** @var string */
    protected $summary = '';

    /**
     * IDs of issue components
     * @var int[]
     */
    protected $components = [];

    /**
     * @var bool[] - label names are used as keys. 'true' value means label should be added to a new issue.
     */
    protected $labels = [];

    /**
     * Full list of fields (both custom and system) available on issue create screen.
     * @var array
     */
    protected $available_fields = [];

    /**
     * CreateRequest constructor.
     *
     * @param string $project_key - key of project for new issue (e.g. EX, TEST, IOS)
     * @param string|int $issue_type - textual name or ID of type for issue you are going to create (e.g. 'Bug' or 34)
     *
     * @param \Badoo\Jira\REST\Client|null $Jira
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public function __construct(string $project_key, $issue_type, \Badoo\Jira\REST\Client $Jira = null)
    {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }
        $this->Jira = $Jira;

        $this->fields['project'] = ['key' => trim($project_key)];

        $TypeInfo = $this->loadIssueTypeInfo($issue_type);

        $this->fields['issuetype'] = ['id' => (string)$TypeInfo->id];
        $this->issue_type_name = $TypeInfo->name;

        $this->loadAvailableFields();
    }

    /**
     * @param string|int $issue_type
     *
     * @return \stdClass
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function loadIssueTypeInfo($issue_type) : \stdClass
    {
        if (is_numeric($issue_type)) {
            $TypeInfo = $this->Jira->issueType()->get($issue_type);
        } else {
            $TypeInfo = $this->Jira->issueType()->searchByName($issue_type);
        }

        if (!isset($TypeInfo)) {
            throw new \Badoo\Jira\Exception\Issue(
                "Unknown issue type '{$issue_type}' for project '{$this->getProject()}'",
                \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_TYPE
            );
        }

        return $TypeInfo;
    }

    protected function isCustomField($field_key) : bool
    {
        return (bool)preg_match('/customfield_\d+/', $field_key);
    }

    /**
     * List of fields available for setting during new Jira Issue create.
     *
     * @return \stdClass[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getFieldsForCreate()
    {
        // Project and issue type are always valid here: they are checked at ::setIssueType()
        $CreateMetaInfo = $this->Jira->issue()->getCreateMeta(
            $this->getProject(),
            $this->getIssueTypeID(),
            \Badoo\Jira\REST\Section\Issue::EXP_CREATEMETA_FIELDS
        );

        return $CreateMetaInfo[0]->issuetypes[0]->fields;
    }

    /**
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function loadAvailableFields()
    {
        $fields = $this->getFieldsForCreate();

        foreach ($fields as $field_id => $FieldInfo) {
            $field_meta_info = $this->generateFieldMetaInfo($field_id, $FieldInfo);

            $this->available_fields[$FieldInfo->name] = $field_meta_info;
            if ($field_meta_info['custom'] ?? false) {
                $this->available_fields[$field_id] = $field_meta_info;
            }
        }
    }

    /**
     * Parse field meta information from Jira REST response and generate special attributes/data for further usage.
     *
     * @param string    $field_id
     * @param \stdClass $FieldInfo
     * @return array with following information:
     *                  [
     *                      'key' => <unique field ID>,      // like 'reporter' or 'customfield_1234'
     *                      'type' => <field value type>,    // 'array', 'string', 'number', etc.
     *                      'limited_values' => <true|false> // whether the field has limited set of possible values
     *                      'allowed_values' => <list of allowed values|null>
     *                  ]
     */
    protected function generateFieldMetaInfo($field_id, $FieldInfo) : array
    {
        $is_custom = $this->isCustomField($field_id);

        $field_type         = $FieldInfo->schema->type;
        $has_limited_values = isset($FieldInfo->allowedValues);
        $values_range       = null;

        // List of allowed values is generated only for custom fields, since system fields have different structure.
        if ($is_custom && $has_limited_values) {
            /** @var \stdClass[] $allowed_values_list */
            $allowed_values_list = $FieldInfo->allowedValues;

            if ($field_type === 'string'
                || ($field_type === 'array' && $FieldInfo->schema->items === 'string')) {
                $values_range = [];

                foreach ($allowed_values_list as $AllowedValue) {
                    $values_range[] = $AllowedValue->value;
                }
            }
        }

        return [
            'key'            => $field_id,
            'type'           => $field_type,
            'limited_values' => $has_limited_values,
            'allowed_values' => $values_range,
            'custom'         => $is_custom,
        ];
    }

    /**
     * Check that given field has limited set of possible values.
     *
     * @param string $field_name
     * @return bool
     */
    protected function hasLimitedValues($field_name) : bool
    {
        return $this->available_fields[$field_name]['limited_values'] ?? false;
    }

    /**
     * Check that given field can be set to the given value.
     * By default, any value is allowed if values set is unknown for field (e.g. for system fields)
     *
     * @param string $field_name
     * @param string $value_to_check
     *
     * @return bool
     */
    protected function isValueAllowed($field_name, $value_to_check) : bool
    {
        $allowed_values = $this->available_fields[$field_name]['allowed_values'];

        // List of allowed values is not set for system fields and ones of unknown types (see ::generateFieldMetaInfo())
        if (isset($allowed_values)) {
            if (!is_array($value_to_check)) {
                $value_to_check = [$value_to_check];
            }

            $is_allowed = true;
            foreach ($value_to_check as $item) {
                if (!in_array($item, $allowed_values)) {
                    $is_allowed = false;
                }
            }
            return $is_allowed;
        } else {
            return true;
        }
    }

    /**
     * @param int|int[]|string|string[] $values
     * @return array - generated field value ready to be sent to Jira REST API
     *                 (for fields with several values, like checkboxes).
     */
    protected function generateArrayFieldValue($values) : array
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        // Value to send should be like:
        // [ ['value' => <selected item 1>], ['value' => <selected item 2>], ... ]

        $value_to_send = [];
        foreach ($values as $item) {
            $value_to_send[] = ['value' => $item];
        }

        return $value_to_send;
    }

    /**
     * @param int|string $component - component unique Jira ID or symbolic name (like 12345 or 'Refactoring').
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue on attempt to add unknown component
     */
    protected function addComponent($component)
    {
        if (is_numeric($component)) {
            $component_id = (int)$component;

            try {
                $Component = new \Badoo\Jira\Component($component_id, $this->Jira);
                $Component->getName(); // force API request
            } catch (\Badoo\Jira\REST\Exception $e) {
                throw new \Badoo\Jira\Exception\Issue(
                    "Can't get component with ID '{$component_id}' from Jira: {$e->getMessage()}",
                    \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_COMPONENT
                );
            }
        } else {
            $component = trim((string)$component);

            try {
                $Component = \Badoo\Jira\Component::byName($this->getProject(), $component, $this->Jira);
            } catch (\Badoo\Jira\Exception $e) {
                throw new \Badoo\Jira\Exception\Issue(
                    "Unknown component '{$component}' for project '{$this->getProject()}'",
                    \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_COMPONENT
                );
            }
        }

        $this->components[] = $Component->getId();

        return $this;
    }

    /**
     * @param string|int $value
     * @return array - generated field value ready to be sent to Jira REST API
     *                 (for fields with single value, but limited set of values)
     */
    protected function generateLimitedFieldValue($value)
    {
        return ['value' => $value];
    }

    public function getProject() : string
    {
        return $this->fields['project']['key'] ?? '';
    }

    public function getIssueTypeID() : int
    {
        return $this->fields['issuetype']['id'];
    }

    public function getIssueTypeName() : string
    {
        return $this->issue_type_name;
    }

    /**
     * @param string|int $priority - priority name or unique JIRA ID (e.g. 'Blocker' or 3)
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setPriority($priority)
    {
        if (is_numeric($priority)) {
            $PriorityInfo = $this->Jira->priority()->get($priority);
        } else {
            $PriorityInfo = $this->Jira->priority()->searchByName($priority);
        }

        if (!isset($PriorityInfo)) {
            throw new \Badoo\Jira\Exception\Issue(
                "Unknown priority '{$priority}'",
                \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_PRIORITY
            );
        }

        return $this->setFieldValue('Priority', ['id' => $PriorityInfo->id]);
    }

    /**
     * @param string $summary
     * @return $this
     */
    public function setSummary(string $summary) : CreateRequest
    {
        $this->fields['summary'] = $summary;
        return $this;
    }

    public function getSummary() : string
    {
        return $this->fields['summary'] ?? '';
    }

    /**
     * @param string $assignee
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setAssignee(string $assignee) : CreateRequest
    {
        $assignee = trim($assignee);
        return $this->setFieldValue('Assignee', ['name' => $assignee]);
    }

    /**
     * @param string|\Badoo\Jira\Issue $issue - object of parent issue or just plain issue key as string
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setParentIssue($issue) : CreateRequest
    {
        if ($issue instanceof \Badoo\Jira\Issue) {
            $issue = $issue->getKey();
        }

        return $this->setFieldValue('Parent', ['key' => $issue]);
    }

    /**
     * @param string $description
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setDescription(string $description) : CreateRequest
    {
        $description = trim($description);
        return $this->setFieldValue('Description', $description);
    }

    /**
     * @param string ...$labels
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setLabels(string ...$labels) : CreateRequest
    {
        return $this->setFieldValue("Labels", $labels);
    }

    /**
     * @param int $level_id - security level ID for new issue
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setSecurityLevel(int $level_id) : CreateRequest
    {
        return $this->setFieldValue('Security Level', ['id' => (string)$level_id]);
    }

    /**
     * @param int $ts - timestamp
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setDueDate($ts) : CreateRequest
    {
        return $this->setDateField('Due Date', $ts);
    }

    /**
     * @param string $field_name - имя поля, как оно отображается в интерфейсе Jira (Assignee, Build_Name, ...)
     * @param mixed $value
     * @param bool $skip_unknown - do not throw exception for unknown field name. Just do nothing and return $this.
     *
     * @return $this
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setFieldValue($field_name, $value, $skip_unknown = false)
    {
        if (!array_key_exists($field_name, $this->available_fields)) {
            if ($skip_unknown) {
                return $this;
            }

            throw new \Badoo\Jira\Exception\Issue(
                "Unknown field '{$field_name}' for '{$this->issue_type_name}' issue in project '{$this->getProject()}'",
                \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_FIELD
            );
        }

        $field_key = $this->available_fields[$field_name]['key'];

        // Perform value generation only for custom fields, since system ones have different complex structure.
        if ($this->isCustomField($field_key) && $this->hasLimitedValues($field_name)) {
            if (!$this->isValueAllowed($field_name, $value)) {
                throw new \Badoo\Jira\Exception\Issue(
                    "Value '{$value}' is impossible for custom field '{$field_name}'",
                    \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_FIELD_VALUE
                );
            }

            // Some fields can have several values at the moment (checkboxes, selection lists, etc.)
            if ($this->available_fields[$field_name]['type'] === 'array') {
                $value_to_send = $this->generateArrayFieldValue($value);
            } else {
                $value_to_send = $this->generateLimitedFieldValue($value);
            }
        } else {
            // For regular system fields and custom text/number/etc fields just send the value as-is.
            // It allows to set system fields, like description and assignee, since they require very various structure.
            $value_to_send = $value;
        }

        $this->fields[$field_key] = $value_to_send;

        return $this;
    }

    /**
     * @param string $field_name - field to set
     * @param int|string $date_ts - timestamp of date to set the field
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function setDateField($field_name, $date_ts) : CreateRequest
    {
        $date = date('Y-m-d', $date_ts);
        return $this->setFieldValue($field_name, $date);
    }

    /**
     * @param int|string ...$components
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\Issue when unknown component found in list
     */
    public function addComponents(...$components)
    {
        foreach ($components as $component) {
            $this->addComponent($component);
        }
        return $this;
    }

    /**
     * @param string|string[] $labels - list of labels (or single label) to be added to a new issue.
     * @return $this
     */
    public function addLabels($labels)
    {
        if (!is_array($labels)) {
            $labels = [$labels];
        }

        foreach ($labels as $label) {
            $this->labels[$label] = true;
        }

        return $this;
    }

    /**
     * @return \Badoo\Jira\Issue
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue for unexpected JIRA response.
     */
    public function send() : \Badoo\Jira\Issue
    {
        $fields = $this->fields;

        // Don't try to set components, when list is empty.
        // It may cause an error for projects, where 'components' is not settable during issue creation
        if (!empty($this->components)) {
            $components = [];
            foreach ($this->components as $component_id) {
                $components[] = ['id' => (string)$component_id];
            }
            $fields['components'] = $components;
        }

        // The same for labels: it will cause an error if there is no 'labels' field on Create screen of project where
        // issue is going to be created.
        $labels_to_set = array_keys(array_filter($this->labels));
        if (!empty($labels_to_set)) {
            $fields['labels'] = $labels_to_set;
        }

        $result = $this->Jira->issue()->create($fields);

        if (isset($result->key)) {
            return \Badoo\Jira\Issue::fromStdClass($result, ['id', 'key', 'self'], [], $this->Jira);
        } else {
            throw new \Badoo\Jira\Exception\Issue(
                "Something went wrong during new issue creation in project {$this->getProject()}. "
                . "Unexpected response from JIRA API: " . var_export($result, true),
                \Badoo\Jira\Exception\Issue::ERROR_CODE_UNKNOWN_ERROR
            );
        }
    }
}
