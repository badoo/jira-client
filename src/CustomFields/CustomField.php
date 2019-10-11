<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class CustomField
 * @package Badoo\Jira\CustomFields
 *
 * Base class for any JIRA custom field.
 *
 * When you implement particular custom field class, work with it become simple like that:
 *  $Field = <FeildClass>::forIssue(<issue_key>);   // Create object to work with field data.
 *  $current_value = $Field->getValue();            // get current field value for JIRA issue <issue_key>
 *  $Field->setValue($new_value);                   // change field value
 *  $Field->save();                                 // actually send changes to JIRA
 */
abstract class CustomField
{
    /** @var \stdClass|string|null */
    private $OriginalObject;

    /** @var \Badoo\Jira\Issue */
    protected $Issue;

    /**
     * @param string $issue_key
     * @param \Badoo\Jira\REST\Client|null $Jira
     *
     * @return static
     *
     * @throws \Badoo\Jira\Exception\Issue
     * @throws \Badoo\Jira\REST\Exception
     */
    public static function forIssue(string $issue_key, \Badoo\Jira\REST\Client $Jira = null) : CustomField
    {
        $Issue = \Badoo\Jira\Issue::byKey($issue_key, ['key', static::ID], [], $Jira);
        return $Issue->getCustomField(static::class);
    }

    public function __construct(\Badoo\Jira\Issue $Issue)
    {
        $this->Issue = $Issue;
    }

    /**
     * @param array $expand - list of additional info required to be expaneded to get our field data.
     *                        Is empty in most cases
     *                        @see \Badoo\Jira\REST\Section\Issue::get DocBlock for more info
     *
     * @return mixed
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getOriginalObject(array $expand = [])
    {
        if (!isset($this->OriginalObject)) {
            $this->OriginalObject = $this->Issue->getFieldValue($this->getID(), $expand);
        }

        return $this->OriginalObject;
    }

    /**
     * Drop internal object caches after changes. E.g. after issue save with field updates
     *
     * @return $this
     */
    public function dropCache()
    {
        $this->OriginalObject = null;
        return $this;
    }

    public function getIssue() : \Badoo\Jira\Issue
    {
        return $this->Issue;
    }

    public function isEmpty() : bool
    {
        return $this->getOriginalObject() === null;
    }

    /**
     * @return string - field symbolic name.
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * @return string - custom field's unique ID used in Rest API responses and requests (customfield_<number>)
     */
    public function getID() : string
    {
        return static::ID;
    }

    /**
     * @return int - numeric ID of custom field. The number after 'customfield_' prefix
     */
    public function getCustomID() : int
    {
        return (int)substr($this->getID(), 12); // customfield_12345 -> 12345
    }

    /**
     * Check if current user can edit this issue field.
     * To do that user at least has to have enough permissions and field should be added to issue 'Edit' screen
     *
     * @return bool
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isEditable() : bool
    {
        return $this->Issue->isEditable($this->getID());
    }

    /**
     * Get rendered field value as it is shown in Jira UI.
     * @return string - HTML representation of field value.
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getRenderedValue() : string
    {
        return $this->Issue->getRenderedField($this->getID()) || '';
    }

    /**
     * Get last known field value (at the moment issue information had been loaded last time).
     * If you want to know 'as fresh as possible' value, call ::update() method before this one.
     *
     * @return mixed - current field value, parsed by parseValue()
     */
    abstract public function getValue();

    /**
     * JIRA API accepts expects structures for changing field values
     * Each custom field type requires it's own structure.
     * This method should convert simple data structure, like array of strings, into something expected by JIRA API.
     *
     * See implementations of custom field types for examples of implementation:
     * @see CheckboxField
     * @see TextField
     * @see UserField
     * @see SelectField
     *
     * @param mixed $value - new field value
     *
     * @return array - JIRA API issue field update structure, e.g. [ [ 'set' => 'new text value' ] ]
     * @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for more info on expected return value structure
     */
    abstract public static function generateSetter($value) : array;

    /**
     * Set object's value using data with simple structure.
     * Something simpler than common REST API \stdClass structures: array for 'checkbox' fields, string for 'text' fields,
     * and so on.

     * @param mixed $value - new field value.
     * @return $this
     */
    public function setValue($value)
    {
        $update = static::generateSetter($value);
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * Save field value to Jira.
     * @see \Badoo\Jira\REST\Section\Issue::edit DocBlock for more info about parameters meaning
     *
     * @param array $properties - list of properties for issue edit request
     * @param bool $notify_users - send notification about issue update to users
     *
     * @return $this
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function save(array $properties = [], bool $notify_users = true) : CustomField
    {
        $this->Issue->save($properties, $notify_users);
        return $this;
    }
}
