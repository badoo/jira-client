<?php
/**
 * @package Jira
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira;

/**
 * Class Issue
 *
 * Represents particular Jira issue
 * with plenty of wrappers to interact with common and custom Jira fields.
 */
class Issue
{
    /*
     * Cache
     */
    /** @var \stdClass - cached issue information from Jira: REST API response 'as is' */
    protected $BaseIssue;

    /** @var \Badoo\Jira\CustomFields\CustomField[] - cached objects for issue's custom fields */
    protected $custom_fields = [];

    /** @var array - cached issue data. Various objects of various types: 'History' issue info, 'Assignee' field, etc.
     *               Everything, which is not classified as custom fields. */
    protected $cached_data = [];

    /** @var string[] - list of expands for issue. REST API request with 'expand' parameter makes API to add additional
     *                  information about issue to response, like issue history, rendered fields and so on. */
    protected $expands = [];

    /**
     * @var array - list of field updates to be sent to JIRA API on ->save() call
     * @see \Badoo\Jira\REST\Section\Issue::edit for more info on structure of data stored here ($update parameter)
     */
    protected $update_fields = [];

    /**
     * @var array<string, mixed>
     *
     * @see getEditMeta()
     *
     * @link https://docs.atlassian.com/software/jira/docs/api/REST/9.7.2/#api/2/issue-getEditIssueMeta Server
     *
     * @link https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issues/#api-rest-api-3-issue-issueidorkey-editmeta-get Cloud
     */
    protected $editMeta = [];

    /*
     * External information sources
     */
    /** @var \Badoo\Jira\REST\Client */
    protected $Jira;

    /*
     * Issue fields and information
     */
    /** @var string */
    protected $key;
    /** @var string */
    protected $project;

    /**
     * Perform brief check of given \stdClass object for correctness and initialize \Badoo\Jira\Issue object on it.
     *
     * @param \stdClass $BaseIssue
     * @param string[] $fields - <BaseIssue> object was loaded only with this fields
     * @param string[] $expand - <BaseIssue> object was loaded with this info expanded
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public static function fromStdClass(
        \stdClass $BaseIssue,
        array $fields = [],
        array $expand = [],
        \Badoo\Jira\REST\Client $Jira = null
    ) : Issue {
        if (empty($BaseIssue->key)) {
            throw new \Badoo\Jira\Exception\Issue('Provided data does not contain attribute "key"');
        }

        if (!empty($fields) && !empty($expand)) {
            throw new \Badoo\Jira\Exception\Issue(
                'Issue object does not support partial fields load in combination with non-empty \'expand\' parameter.' .
                ' Use any of them, but no both to make Issue do optimal requests to JIRA API.' .
                ' More info is in documentation at https://github.com/badoo/jira-client.git'
            );
        }

        static $hack_fields = [
            'key'   => false, // field is not in ->fields, but we don't want to cache it anyway.
            'id'    => true, // field is not in ->fields, cache with hack
            'self'  => true
        ];

        $Issue = new static($BaseIssue->key, $Jira);

        foreach ($hack_fields as $field_id => $cache) {
            if (!$cache) {
                continue;
            }

            // this info on issue is not inside 'fields' subtree of Issue \stdClass object,
            // we have to cache it with hack here.
            if (isset($BaseIssue->{$field_id})) {
                $Issue->cacheData($field_id, $BaseIssue->{$field_id});
            }
        }

        if (empty($fields)) {
            // We save BaseIssue object only when it contains full information on issue fields.
            // This is done to prevent code from requesting API each time we want to get empty issue field.
            // This also preventes from returning 'null' about field that is not empty but we just don't have info on it.
            $Issue->BaseIssue = $BaseIssue;
            $Issue->expands   = $expand;
        } else {
            // We can't store BaseIssue object because we know it does not contain the full fields information.
            // Store all loaded fields in cache to use their values when we need and to not break other field getters.
            foreach ($fields as $field_id) {
                if (array_key_exists($field_id, $hack_fields)) {
                    continue; // we already cached a field
                }

                $Issue->cacheData($field_id, $BaseIssue->fields->{$field_id} ?? null);
            }
        }

        return $Issue;
    }

    /**
     * Load list of issues using search query in Jira Query Language.
     *
     * @see \Badoo\Jira\REST\Section\Issue::search()
     *
     * @param string   $jql    - search query string
     * @param string[] $fields - load only listed field values
     * @param string[] $expand - load additional issues info
     * @param int      $limit  - return at most <limit> issues
     * @param int      $offset - skip first <offset> issues found
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static[] - list of issues are fit the search request.
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function search(
        string $jql,
        array $fields = [],
        array $expand = [],
        int $limit = 1000,
        int $offset = 0,
        \Badoo\Jira\REST\Client $Jira = null
    ) : array {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $issues_list = $Jira->issue()->search($jql, $fields, $expand, $limit, $offset);

        $issue_objects = [];

        foreach ($issues_list as $issue_info) {
            $Issue = static::fromStdClass($issue_info, $fields, $expand, $Jira);
            $Issue->expands = $expand;
            $issue_objects[$Issue->getKey()] = $Issue;
        }

        return $issue_objects;
    }

    /**
     * Load info for issue identified by key from Jira using REST API
     *
     * @see \Badoo\Jira\REST\Section\Issue::get()
     *
     * @param string $issue_key
     * @param string[] $fields - request only fields listed
     * @param string[] $expand - provide additional info for issue
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function byKey(
        string $issue_key,
        array $fields = [],
        array $expand = [],
        \Badoo\Jira\REST\Client $Jira = null
    ) : Issue {
        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $IssueInfo = $Jira->issue()->get($issue_key, $fields, $expand);

        $Issue = static::fromStdClass($IssueInfo, $fields, $expand, $Jira);
        return $Issue;
    }

    /**
     * Load list of issues by their keys from Jira REST API
     *
     * @see \Badoo\Jira\REST\Section\Issue::search()
     *
     * @param string[] $issue_keys - list of issue keys for issues load
     * @param string[] $fields     - load only listed field values
     * @param string[] $expand     - provide additional info for each issue
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @return static[]
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function byKeys(
        array $issue_keys,
        array $fields = [],
        array $expand = [],
        \Badoo\Jira\REST\Client $Jira = null
    ) : array {
        if (empty($issue_keys)) {
            return [];
        }

        $query = "key IN (" . implode(',', $issue_keys) . ")";
        return static::search($query, $fields, $expand, 1000, 0, $Jira);
    }

    /**
     * @see \Badoo\Jira\REST\Section\Issue::EXP_CHANGELOG
     *
     * @param string[] $expand - load extra information for issue
     * @return \stdClass
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getBaseIssue(array $expand = []) : \stdCLass
    {
        foreach ($expand as $group) {
            if (!in_array($group, $this->expands)) {
                // Force cache update when groups required to be expanded are absent in current cache.
                $this->dropCache();
                $this->expands[] = $group;
            }
        }

        if (!isset($this->BaseIssue)) {
            $this->dropCache();
            $this->BaseIssue = $this->Jira->issue()->get($this->getKey(), [], $this->expands);
        }

        return $this->BaseIssue;
    }

    /**
     * Put data portion into internal object cache, storing it under <key> key
     * NOTE: if another portion of data already exists under the same key, it will be silently overwritten.
     *
     * @param string $key - key to store data
     * @param mixed  $value - data to be stored
     * @return $this
     */
    protected function cacheData($key, $value) : Issue
    {
        $this->cached_data[$key] = $value;
        return $this;
    }

    /**
     * Check if data under key <key> is available in cache.
     * NOTE: checks only for key existence, returns true even when value associated with key is 'null' or 'false'.
     *
     * @param string $key - key to check in cache
     * @return bool - true when key with name <key> exists in cache
     */
    protected function isCached($key) : bool
    {
        return array_key_exists($key, $this->cached_data);
    }

    /**
     * @param string $key
     *
     * @return mixed - returns cached data when given key exists, null when no data was cached with given key
     */
    protected function getCachedData($key)
    {
        return $this->cached_data[$key] ?? null;
    }

    /**
     * Drop all cached data. After that any ::get* method call will cause new Jira API request.
     */
    protected function dropCache()
    {
        $this->BaseIssue     = null;
        $this->custom_fields = [];
        $this->cached_data   = [];
        $this->editMeta = [];
    }

    /**
     * Issue constructor.
     *
     * WARNING: new Issue(<key>) can be used in many places to return lists of issues.
     *          This means \Badoo\Jira\Issue class constructor should be 'light' and should not perform full
     *          initialization with REST API requests within to not cause performance degradation.
     *
     * @param string $issue_key
     * @param \Badoo\Jira\REST\Client $Jira - JIRA API client to use instead of global one.
     *                                        Enables you to access several JIRA instances from one piece of code,
     *                                        or use different users for different actions.
     *
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function __construct(string $issue_key, \Badoo\Jira\REST\Client $Jira = null)
    {
        $issue_key = trim($issue_key);
        if ($issue_key === '') {
            throw new \Badoo\Jira\Exception\Issue("Can't create Issue object with empty issue key");
        }

        if (!isset($Jira)) {
            $Jira = \Badoo\Jira\REST\Client::instance();
        }

        $this->Jira = $Jira;
        $this->key = $issue_key;
    }

    public function __toString() : string
    {
        return "[{$this->getKey()}]: {$this->getSummary()}";
    }

    public function getJira() : \Badoo\Jira\REST\Client
    {
        return $this->Jira;
    }

    /**
     * Get value of custom or system field, checking cache first
     *
     * IMPORTANT: cache is used for field-limited initialization, when issue was loaded from API with only selected
     *            fields data instead of default 'all fields info'. Don't bypass it unless you know what you do.
     *
     * @param string $field_id - custom field id (e.g. 'customfield_12345' or 'summary')
     * @param string[] $expand - load extra information for issue before getting field value
     *
     * @return mixed
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getFieldValue(string $field_id, array $expand = [])
    {
        if ($this->isCached($field_id)) {
            return $this->getCachedData($field_id);
        }

        return $this->getBaseIssue($expand)->fields->{$field_id} ?? null;
    }

    /**
     * @param array $editMeta result of {@link Issue::getEditMeta()}
     *
     * @return string|null field id on success or otherwise null
     */
    public function getFieldIdByName(array $editMeta, string $fieldName): ?string
    {
        foreach ($editMeta as $fieldId => $meta) {
            if (in_array($fieldName, [$meta->name ?? "", $meta->key ?? ""], true)) {
                return $fieldId;
            }
        }
        return null;
    }

    /**
     * Get value of custom or system field, checking cache first
     *
     * IMPORTANT: cache is used for field-limited initialization, when issue was loaded from API with only selected
     *            fields data instead of default 'all fields info'. Don't bypass it unless you know what you do.
     *
     * @param string $fieldName - custom field name (e.g. 'Build name' or 'Summary')
     * @param string[] $expand - load extra information for issue before getting field value
     *
     * @return mixed null if there's no such field
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getFieldValueByName(string $fieldName, array $expand = [])
    {
        $fieldId = $this->getFieldIdByName($this->getEditMeta(), $fieldName);
        if ($fieldId === null) {
            return null;
        }
        return $this->getFieldValue($fieldId, $expand);
    }

    /**
     * Get rendered field value, as it will be shown in JIRA Web interface.
     * This is useful e.g. for text fields with formatters (e.g. wiki-), to get the same layout as in issue view page
     * without tricks and hacks.
     *
     * @param string $field_id - ID of field to get (e.g. 'customfield_12345' or 'summary')
     *
     * @return string|null - null when no field with such ID found for issue.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getRenderedField(string $field_id) : ?string
    {
        return $this->getBaseIssue([\Badoo\Jira\REST\Section\Issue::EXP_RENDERED_FIELDS])->renderedFields->{$field_id} ?? null;
    }

    /**
     * Get date/time field value as UNIX timestamp. strtotime() is used to parse the value of the field.
     *
     * @param string $field_id - ID of field to parse, e.g. 'created', 'updated' and so on
     * @param array $expand
     * @return int
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function getDateField(string $field_id, array $expand = []) : int
    {
        $time = $this->getFieldValue($field_id, $expand);

        if (empty($time)) {
            return 0;
        }

        if (is_string($time)) {
            $ts = strtotime($time);

            if ($ts === false) {
                throw new \Badoo\Jira\Exception\Issue(
                    "Can't parse '{$field_id}' field value as time string, strtotime('{$time}') returned 'false'"
                );
            }

            $time = $ts;
            $this->cacheData($field_id, $ts);
        }

        return $time;
    }

    /**
     * @param string $field_class - class name of CustomField to get
     * @return \Badoo\Jira\CustomFields\CustomField
     */
    public function getCustomField($field_class)
    {
        /** @var \Badoo\Jira\CustomFields\CustomField $CustomField */
        $CustomField = $this->custom_fields[$field_class] ?? null;

        if (!isset($CustomField)) {
            $CustomField = new $field_class($this);
            $this->custom_fields[$field_class] = $CustomField;
        }

        return $CustomField;
    }

    /**
     * Returns edit meta of the issue.
     *
     * The answer is cached by default. {@link Issue::dropCache() How to drop the cache}
     *
     * @return array<string,mixed>
     *
     * @throws \Badoo\Jira\REST\Exception
     *
     * @link https://docs.atlassian.com/software/jira/docs/api/REST/9.7.2/#api/2/issue-getEditIssueMeta Server
     *
     * @link https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issues/#api-rest-api-3-issue-issueidorkey-editmeta-get Cloud
     *
     */
    public function getEditMeta(): array
    {
        if (!empty($this->editMeta)) {
            return $this->editMeta;
        }
        $this->editMeta = $this->Jira->issue()->getEditMeta($this->getKey());

        return $this->editMeta;
    }

    /**
     * @param string $field_id - ID of system (e.g. 'description') or custom (e.g. customfield_12345) field you want
     *                           to check
     *
     * @return bool - true means you can update this issue field's value
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isEditable(string $field_id) : bool
    {
        return isset($this->getEditMeta()[$field_id]);
    }

    /**
     * Edit issue fields
     * NOTE: the changes are actually applied only after ->save() call.
     *
     * @param string $field_id - ID of field to update. E.g. 'summary' or 'customfield_12345'
     * @param array $update - update request
     *                          Example: [ [ 'set' => [ 'id' => 1234' ] ]
     *                        @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for more info
     *
     * @return $this
     */
    public function edit(string $field_id, array $update) : \Badoo\Jira\Issue
    {
        $this->update_fields[$field_id] = $update;
        return $this;
    }

    /**
     * Apply all changes planned with ->edit() calls.
     * Actually updated JIRA issue fields
     * NOTE: this method call results in internal issue object cache drop. Any get<Field> call will cause single JIRA
     *       API request to refresh the cache. All get<Field> calls will cause new wrapper objects creation
     *
     * @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for more info about parameters meaning
     *
     * @param array $properties
     * @param bool $notify_users
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function save(array $properties = [], $notify_users = true) : \Badoo\Jira\Issue
    {
        if (empty($this->update_fields)) {
            return $this;
        }

        $this->Jira->issue()->edit(
            $this->getKey(),
            [],
            $this->update_fields,
            $properties,
            $notify_users
        );
        $this->update_fields = [];

        foreach ($this->custom_fields as $CustomField) {
            $CustomField->dropCache();
        }
        $this->dropCache();

        return $this;
    }

    /**
     * Perform a transition on issue. All changes you scheduled by Issue->edit() will be applied during transition if
     * you not  called ->save() yet.
     *
     * @param int $transition_id - ID of transition to do
     * @param bool $safe_transition - perform safe transition: siletly filter out field changes,
     *                                that are not allowed by transition screen.
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception - when JIRA rejected to do issue transition.
     */
    public function transition(int $transition_id, $safe_transition = false) : \Badoo\Jira\Issue
    {
        if ($safe_transition) {
            $this->Jira->issue()->transitions()->do_safe($this->getKey(), $transition_id, [], $this->update_fields);
        } else {
            $this->Jira->issue()->transitions()->do($this->getKey(), $transition_id, [], $this->update_fields);
        }

        $this->update_fields = [];
        $this->dropCache();
        return $this;
    }

    /**
     * Perform a transition on issue using step name instead of ID (the one displayed on button in Web UI).
     * All changes you scheduled by Issue->edit() will be applied during transition if you not  called ->save() yet.
     *
     * @param string $step_name - name of step you want to do
     * @param bool $step_same_status - true: do transition even when it leads to the same status
     *                                 false: throw an exception if issue is already in target status
     * @param bool $safe_transition - drop changes for fields that can't be set during transition because of transition
     *                                screen configuration.
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception - when JIRA rejected to do issue transition.
     */
    public function step(string $step_name, bool $step_same_status = false, bool $safe_transition = false) : \Badoo\Jira\Issue
    {
        $this->Jira->issue()->transitions()->step($this->getKey(), $step_name, [], $this->update_fields, $safe_transition, $step_same_status);

        $this->update_fields = [];
        $this->dropCache();
        return $this;
    }

    /**
     * Delete issue from Jira.
     * DANGER: This action is applied immediately and can't be undone. Be careful.
     */
    public function delete() : void
    {
        $this->Jira->issue()->delete($this->getKey());
    }

    /**
     * @return int
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getId() : int
    {
        if ($this->isCached('id')) {
            return $this->getCachedData('id');
        }

        $id = (int)$this->getBaseIssue()->id;
        $this->cacheData('id', $id);
        return $id;
    }

    /**
     * Issue key is not changed automatically once object is initialized for the first time.
     * This allows you to 'trust' issue key and use it in arrays as index even when issue actually was moved between
     * projects during your code execution.
     *
     * @see Issue::updateKey()
     */
    public function getKey() : string
    {
        return $this->key;
    }

    /**
     * @return string
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getSelfUrl() : string
    {
        if ($this->isCached('self')) {
            return $this->getCachedData('self');
        }

        $self = (string)$this->getBaseIssue()->self;
        $this->cacheData('self', $self);
        return $self;
    }

    /**
     * Update issue key to the latest one.
     * This is not done automatically each time fresh data is loaded from API to prevent unexpected changes of key
     * during execution, when code silently updates data from API
     *
     * @param bool $reload_cache - drop all internal caches of Issue object before getting fresh key.
     *                             This causes full data reload from API, with the same expands issue had before drop.
     *
     * @throws REST\Exception
     */
    public function updateKey(bool $reload_cache = false)
    {
        if ($reload_cache) {
            $this->dropCache();
        }

        $this->key = $this->getBaseIssue()->key;
    }

    /**
     * Get list of keys issue had before the current one.
     * Issue key is changed each time issue is moved from project to project.
     *
     * NOTE: the list _does_not_ include the current issue key.
     *
     * @return string[] - list of old issue keys, ordered from oldest to latest:
     *                     - the key issue had at the moment of creation is listed first
     *                     - the key issue had before the current one is listed last
     */
    public function getPrevKeys() : array
    {
        $previous_keys = [];

        foreach ($this->getHistory()->trackField('Key') as $Change) {
            if ($Change->isStringChanged()) {
                $previous_keys[] = $Change->getFromString();
            }
        }

        return $previous_keys;
    }

    public function getProject() : string
    {
        if (!isset($this->project)) {
            list($project_key) = explode('-', $this->getKey());
            $this->project = $project_key;
        }
        return $this->project;
    }

    /**
     * @param string[] $check_projects - list of project keys, e.g. 'ABC', 'DEF', and so on.
     *
     * @return bool
     */
    public function isInProject(array $check_projects) : bool
    {
        return in_array($this->getProject(), $check_projects);
    }

    public function getUrl() : string
    {
        return $this->Jira->getRawClient()->getJiraUrl() . 'browse/' . $this->getKey();
    }

    /**
     * @return string
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getSummary() : string
    {
        return (string)$this->getFieldValue('summary');
    }

    /**
     * @param string $summary
     * @return $this
     */
    public function setSummary(string $summary) : Issue
    {
        return $this->edit(
            'summary',
            [
                ['set' => $summary]
            ]
        );
    }

    /**
     * @return string
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getDescription() : string
    {
        return (string)$this->getFieldValue('description');
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description) : Issue
    {
        return $this->edit(
            'description',
            [
                ['set' => $description]
            ]
        );
    }

    /**
     * @return \Badoo\Jira\Issue[]
     *
     * @return static[]
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getSubIssues() : array
    {
        return static::search("parent = '{$this->getKey()}'");
    }

    /**
     * @return bool - true when issue has a parent
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isSubIssue() : bool
    {
        return $this->getFieldValue('parent') !== null;
    }

    /**
     * @return bool - true when issue has at least one subtask
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function hasSubIssues() : bool
    {
        return count($this->getFieldValue('subtasks')) > 0;
    }

    /**
     * @return static|null
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getParentIssue() : ?Issue
    {
        if (!$this->isSubIssue()) {
            return null;
        }

        return static::byKey($this->getFieldValue('parent')->key);
    }

    /**
     * @return int - issue creation time unix timestamp
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function getCreatedDate() : int
    {
        return $this->getDateField('created');
    }

    /**
     * @return int - issue update time unix timestamp
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function getUpdatedDate()
    {
        return $this->getDateField('updated');
    }

    /**
     * @return int - issue resolution time unix timestamp (resolved at).
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function getResolutionDate() : int
    {
        return $this->getDateField('resolutiondate');
    }

    /**
     * @return int
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Issue
     */
    public function getDueDate() : int
    {
        return $this->getDateField('duedate');
    }

    /**
     * @return string[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getLabels() : array
    {
        return (array)($this->getFieldValue('labels'));
    }

    /**
     * Set issue labels removing all ones it had before
     *
     * @param string ...$labels
     *
     * @return $this
     */
    public function setLabels(string ...$labels) : Issue
    {
        // We need array_values() here to drop keys preserving from array_unique()
        $new_value = array_values(array_unique($labels));
        return $this->edit(
            'labels',
            [
                ['set' => $new_value]
            ]
        );
    }

    /**
     * Add labels to issue, keeping all labels it already has
     *
     * @param string ...$labels
     *
     * @return $this
     */
    public function addLabels(string ...$labels) : Issue
    {
        $to_add = array_values(array_unique($labels));

        $update_labels = $this->update_fields['labels'] ?? [];
        foreach ($to_add as $label) {
            $update_labels[] = ['add' => $label];
        }

        return $this->edit(
            'labels',
            $update_labels
        );
    }

    /**
     * @return \Badoo\Jira\Issue\Type
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getType() : \Badoo\Jira\Issue\Type
    {
        $cache_key = 'Type';

        $Type = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $Type = \Badoo\Jira\Issue\Type::fromStdClass($this->getFieldValue('issuetype'), $this, $this->Jira);
            $this->cacheData($cache_key, $Type);
        }
        return $Type;
    }

    /**
     * @return \Badoo\Jira\Issue\Status
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getStatus() : \Badoo\Jira\Issue\Status
    {
        $cache_key = 'Status';

        $Status = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $Status = \Badoo\Jira\Issue\Status::fromStdClass($this->getFieldValue('status'), $this, $this->Jira);
            $this->cacheData($cache_key, $Status);
        }
        return $Status;
    }

    /**
     * @return \Badoo\Jira\Issue\Priority|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getPriority() : ?\Badoo\Jira\Issue\Priority
    {
        $cache_key = 'Priority';

        $Priority = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $PriorityInfo = $this->getFieldValue('priority');

            if (isset($PriorityInfo)) {
                $Priority = \Badoo\Jira\Issue\Priority::fromStdClass($PriorityInfo, $this, $this->Jira);
            } else {
                // Issue has no priority. Yes, this is possible.
                $Priority = null;
            }

            $this->cacheData($cache_key, $Priority);
        }
        return $Priority;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setPriority(int $id) : Issue
    {
        return $this->edit(
            "priority",
            [
                ["set" => ["id" => (string)$id]]
            ]
        );
    }

    /**
     * @return Security|null
     *
     * @throws REST\Exception
     */
    public function getSecurity() : ?\Badoo\Jira\Security
    {
        $cache_key = 'Security';

        $Security = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $PriorityInfo = $this->getFieldValue('security');

            if (isset($PriorityInfo)) {
                $Security = \Badoo\Jira\Security::fromStdClass($PriorityInfo, $this, $this->Jira);
            } else {
                // Issue has no priority. Yes, this is possible.
                $Security = null;
            }

            $this->cacheData($cache_key, $Security);
        }
        return $Security;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setSecurity(int $id) : \Badoo\Jira\Issue
    {
        return $this->edit(
            "security",
            [
                ["set" => ["id" => (string)$id]],
            ]
        );
    }

    /**
     * Get current issue resolution. Returns null for unresolved issues.
     *
     * @return \Badoo\Jira\Issue\Resolution|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getResolution() : ?\Badoo\Jira\Issue\Resolution
    {
        $cache_key = 'Resolution';

        $Resolution = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $ResolutionInfo = $this->getFieldValue('resolution');

            if (isset($ResolutionInfo)) {
                $Resolution = \Badoo\Jira\Issue\Resolution::fromStdClass($ResolutionInfo, $this, $this->Jira);
            } else {
                // Issue has no resolution yet (unresolved)
                $Resolution = null;
            }

            $this->cacheData($cache_key, $Resolution);
        }
        return $Resolution;
    }

    /**
     * Change issue resolution
     *
     * @param int $id
     *
     * @return $this
     */
    public function setResolution(int $id) : \Badoo\Jira\Issue
    {
        return $this->edit(
            'resolution',
            [
                ['set' => ['id' => (string)$id]],
            ]
        );
    }

    /**
     * Get list of issue components, indexed by IDs
     *
     * @return Component[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getComponents()
    {
        $components = $this->getCachedData('Components');

        if (!isset($components)) {
            $components = [];

            foreach ((array)$this->getFieldValue('components') as $ComponentInfo) {
                $Component = Component::fromStdClass($ComponentInfo, $this, $this->Jira);
                $components[$Component->getID()] = $Component;
            }

            $this->cacheData('Components', $components);
        }

        return $components;
    }

    /**
     * Set list of issue components to exact ones, clearing previous field value
     *
     * @param string|int ...$components - names or IDs of components to add to issue
     *
     * @return $this
     */
    public function setComponents(...$components)
    {
        $set_components = [];
        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int)$component;
                $set_components[] = ['id' => "$component"];
            } else {
                $set_components[] = ['name' => $component];
            }
        }

        return $this->edit(
            'components',
            ['set' => $set_components]
        );
    }

    /**
     * Add one or several components to issue
     *
     * @param string|int ...$components - names or IDs of components to add to issue
     *
     * @return $this
     */
    public function addComponents(...$components) : Issue
    {
        $components_update = $this->update_fields['components'] ?? [];

        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int)$component;
                $components_update[] = ['add' => ['id' => "$component"]];
            } else {
                $components_update[] = ['add' => ['name' => $component]];
            }
        }

        return $this->edit('components', $components_update);
    }

    /**
     * Remove one or several components to issue
     *
     * @param string|int ...$components
     *
     * @return $this
     */
    public function removeComponents(...$components)
    {
        $components_update = $this->update_fields['components'] ?? [];

        foreach ($components as $component) {
            if (is_numeric($component)) {
                $component = (int)$component;
                $components_update[] = ['remove' => ['id' => "$component"]];
            } else {
                $components_update[] = ['remove' => ['name' => $component]];
            }
        }

        return $this->edit('components', $components_update);
    }

    /**
     * @return \Badoo\Jira\Version[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getVersions() : array
    {
        $Versions = $this->getCachedData('Versions');
        if (!isset($Versions)) {
            $Versions = [];

            $project_id = $this->getBaseIssue()->fields->project->id;
            foreach ((array)$this->getFieldValue('fixVersions') as $VersionInfo) {
                // Don't know why, but list of versions associated with issue has no 'projectId' field.
                $VersionInfo->projectId = $project_id;
                $Versions[] = \Badoo\Jira\Version::fromStdClass($VersionInfo, $this, $this->getJira());
            }
            $this->cacheData('Versions', $Versions);
        }
        return $Versions;
    }

    /**
     * Associate version with issue (add it to fixVersions field).
     * Appends a version to issue's versions list, returns an object for added version.
     *
     * @param string $version_name
     * @param bool   $create - create new version in Jira, if it does not already exist.
     *
     * @return \Badoo\Jira\Version
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception\Version
     */
    public function addVersion($version_name, $create = false)
    {
        $versions_to_set = [];
        foreach ($this->getVersions() as $AlreadyAdded) {
            if ($version_name === $AlreadyAdded->getName()) {
                return $AlreadyAdded;
            }

            $versions_to_set[] = ['id' => "{$AlreadyAdded->getID()}"];
        }

        // Create a new version in Jira project if it is needed.
        if ($create && !\Badoo\Jira\Version::exists($this->getProject(), $version_name, $this->getJira())) {
            $VersionToAdd = new \Badoo\Jira\Version(0, $this->getJira());
            $VersionToAdd->setProject($this->getProject())->setName($version_name)->save();
        } else {
            $VersionToAdd = \Badoo\Jira\Version::byName($this->getProject(), $version_name, $this->getJira());
        }

        // Update list of versions for issue
        $versions_to_set[] = ['id' => "{$VersionToAdd->getID()}"];
        $this->edit(
            "fixVersions",
            [
                ['set' => $versions_to_set]
            ]
        );
        $this->save();

        return $VersionToAdd;
    }

    /**
     * Get list of issue comments
     *
     * @param bool $reload_cache - request API for fresh issue comments ignoring internal class cache
     *
     * @return \Badoo\Jira\Issue\Comment[]
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getComments(bool $reload_cache = false) : array
    {
        $cache_key = 'Comments';

        $comments = $this->getCachedData($cache_key);
        if (!isset($comments) || $reload_cache) {
            $comments_list = $this->getFieldValue('comment')->comments;

            $comments = [];
            foreach ($comments_list as $CommentInfo) {
                $Comment = \Badoo\Jira\Issue\Comment::fromStdClass($CommentInfo, $this);
                $comments[$Comment->getID()] = $Comment;
            }

            $this->cacheData($cache_key, $comments);
        }

        return $comments;
    }

    /**
     * Get comment by ID.
     * NOTE: This method does not call API at all, it just creates you an object.
     *       You will not realize comment does not exist until you try to get some field for it.
     *
     * @param int $id
     *
     * @return \Badoo\Jira\Issue\Comment
     */
    public function getComment(int $id) : \Badoo\Jira\Issue\Comment
    {
        return new \Badoo\Jira\Issue\Comment($id, $this);
    }

    /**
     * Get the most recent comment attached to issue
     *
     * @param bool $reload_cache - force data update. Get the freshest data possible from Jira API, instead of using cache.
     *
     * @return \Badoo\Jira\Issue\Comment|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getLastComment($reload_cache = false) : ?\Badoo\Jira\Issue\Comment
    {
        $comments = $this->getComments($reload_cache);
        $LastComment = end($comments);
        if ($LastComment === false) {
            return null;
        }

        return $LastComment;
    }

    public function addComment(string $text, ?array $visibility = [], bool $expand_rendered = false) : \Badoo\Jira\Issue\Comment
    {
        $CommentInfo = $this->Jira->issue()->comment()->create($this->getKey(), $text, $visibility, $expand_rendered);
        $this->dropCache();

        $Comment = \Badoo\Jira\Issue\Comment::fromStdClass($CommentInfo, $this);
        return $Comment;
    }

    /**
     * Get issue's reporter user information. 'null' is returned for issues, where reporter is not displayed.
     *
     * @return \Badoo\Jira\User|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getReporter() : ?User
    {
        $Reporter = $this->getCachedData('Reporter');

        if (!isset($Reporter) && $this->getFieldValue('reporter') !== null) {
            $Reporter = \Badoo\Jira\User::fromStdClass($this->getFieldValue('reporter'), $this, $this->Jira);
            $this->cacheData('Reporter', $Reporter);
        }

        return $Reporter;
    }

    /**
     * Change issue's reporter
     *
     * @param string|User $user - user to set as issue reporter.
     *
     * @return $this
     */
    public function setReporter($user) : Issue
    {
        if ($user instanceof User) {
            $user = $user->getName();
        }

        return $this->edit(
            'reporter',
            [
                ['set' => ['name' => $user]],
            ]
        );
    }

    /**
     * Get issue's assignee user information. 'null' is returned for unassigned issues.
     *
     * @return \Badoo\Jira\User|null
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getAssignee() : ?User
    {
        $Assignee = $this->getCachedData('Assignee');

        if (!isset($Assignee) && isset($this->getBaseIssue()->fields->assignee)) {
            $Assignee = \Badoo\Jira\User::fromStdClass($this->getFieldValue('assignee'), $this, $this->Jira);
            $this->cacheData('Assignee', $Assignee);
        }

        return $Assignee;
    }

    /**
     * Assign issue to a user
     *
     * @param string|User|null $user - user to set as assignee of issue.
     *                                 null - unassign issue
     *                                 "-1" - set assignee to project default
     *
     * @return $this
     */
    public function setAssignee($user) : Issue
    {
        if ($user instanceof User) {
            $user = $user->getName();
        }

        return $this->edit(
            'assignee',
            [
                ['set' => ['name' => $user]],
            ]
        );
    }

    /**
     * Get list of issue watchers
     *
     * @return \Badoo\Jira\Issue\WatchersList
     *
     * @throws \Badoo\Jira\REST\Exception
     * @throws \Badoo\Jira\Exception
     */
    public function getWatchers() : \Badoo\Jira\Issue\WatchersList
    {
        $cache_key = 'Watchers';

        $WatchersList = $this->getCachedData($cache_key);
        if (!isset($WatchersList)) {
            $watchers = $this->Jira->issue()->watchers()->list($this->getKey());

            $WatchersList = \Badoo\Jira\Issue\WatchersList::fromStdClass($watchers, $this, $this->getJira());

            $this->cacheData($cache_key, $WatchersList);
        }

        return $WatchersList;
    }

    public function getAttachments() : \Badoo\Jira\Issue\Attachments
    {
        $cache_key = 'Attachments';
        $Attachments = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $attachments_list = $this->getFieldValue('attachment') ?? [];
            $Attachments = \Badoo\Jira\Issue\Attachments::fromStdClass($attachments_list, $this);

            $this->cacheData($cache_key, $Attachments);
        }

        return $Attachments;
    }

    public function attachFile(string $file_path, ?string $file_name = null, ?string $file_type = null) : \Badoo\Jira\Issue\File
    {
        return $this->getAttachments()->attach($file_path, $file_name, $file_type);
    }

    public function getHistory() : \Badoo\Jira\Issue\History
    {
        $cache_key = 'History';
        $History = $this->getCachedData($cache_key);
        if (!isset($History)) {
            $records = $this->getBaseIssue([\Badoo\Jira\REST\Section\Issue::EXP_CHANGELOG])->changelog->histories;
            $History = \Badoo\Jira\Issue\History::fromStdClass($records, $this);
            $this->cacheData($cache_key, $History);
        }
        return $History;
    }

    public function getTimeInLastStatus() : int
    {
        return $this->getHistory()->getTimeInLastStatus();
    }

    public function getWorkdaysInStatus(string $status) : float
    {
        return $this->getHistory()->getWorkdaysInStatus($status);
    }

    public function getLinksList() : \Badoo\Jira\Issue\LinksList
    {
        $cache_key = 'Links';

        $LinksList = $this->getCachedData($cache_key);
        if (!$this->isCached($cache_key)) {
            $links = $this->getFieldValue('issuelinks') ?? [];
            $LinksList = \Badoo\Jira\Issue\LinksList::fromStdClass($links, $this);

            $this->cacheData($cache_key, $LinksList);
        }

        return $LinksList;
    }
}
