<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class CheckboxField
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'checkbox' type custom field with several checkboxes
 */
abstract class CheckboxField extends CustomField
{
    /** @var bool[] - checkbox states, indexed by checkbox names */
    protected $value;

    /** @var bool[] - new checkbox states */
    protected $update;

    /** @return string[] - list of checkboxes available for this field. */
    abstract public function getCheckboxesList() : array;

    public function dropCache()
    {
        $this->value = null;
        $this->update = null;

        return parent::dropCache();
    }

    /**
     * @return bool[] - list of all checkboxes and their states (checked or not)
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getCheckboxesState()
    {
        if (!isset($this->value)) {
            $this->value = [];

            $Field = $this->getOriginalObject();

            foreach ($this->getCheckboxesList() as $checkbox_name) {
                $this->value[$checkbox_name] = false;
            }

            foreach ((array)$Field as $CheckboxInfo) {
                /** @var \stdClass $CheckboxInfo */
                $this->value[$CheckboxInfo->value] = true;
            }
        }

        return $this->value;
    }

    /**
     * @return string[] - list of names of checked items
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue() : array
    {
        return array_keys(array_filter($this->getCheckboxesState()));
    }

    /**
     * @param string[] $value - list of names for checkboxes we want to mark 'checked'
     * @return array
     */
    public static function generateSetter($value) : array
    {
        $items_to_check = [];
        foreach ($value as $checkbox_name) {
            $items_to_check[] = ['value' => $checkbox_name];
        }

        return [ ['set' => $items_to_check] ];
    }

    /**
     * @param string[] $value - list of checked boxes.
     * @return $this
     */
    public function setValue($value)
    {
        $this->update = [];
        foreach ((array)$value as $checkbox) {
            $this->update[$checkbox] = true;
        }

        $update = static::generateSetter($value);
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * @param string $checkbox_name
     * @param bool   $checked_state - true: 'check' item,
     *                                false: 'uncheck' it.
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\CustomField
     * @throws \Badoo\Jira\REST\Exception
     */
    public function checkItem(string $checkbox_name, bool $checked_state = true)
    {
        if (!in_array($checkbox_name, $this->getCheckboxesList())) {
            throw new \Badoo\Jira\Exception\CustomField(
                "Can't change state of unknown checkbox '{$checkbox_name}'. "
                . "Available checkboxes for field '{$this->getName()}' are: '"
                . implode("', '", $this->getCheckboxesList()) . "'\n"
            );
        }

        if (!isset($this->update)) {
            $this->update = $this->getCheckboxesState();
        }
        $this->update[$checkbox_name] = $checked_state;

        $update = static::generateSetter(array_keys(array_filter($this->update)));
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * @param string $checkbox
     * @return bool
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isChecked(string $checkbox) : bool
    {
        return $this->getCheckboxesState()[$checkbox] ?? false;
    }

    /**
     * @param bool $checked_state - true: check all boxes,
     *                              false: uncheck all boxes.
     */
    public function checkAll($checked_state = true)
    {
        if ($checked_state) {
            $this->setValue($this->getCheckboxesList());
        } else {
            $this->setValue([]);
        }
    }
}
