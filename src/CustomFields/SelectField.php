<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class SelectField
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'select list' type custom field with several items
 */
abstract class SelectField extends CustomField
{
    /** @var bool[] - current items state. Item names are used as keys.
     *                True: item is selected.
                      False: item is not selected. */
    protected $value;

    /** @var bool[] - new state to be set on ->save() call */
    protected $update;

    /** @return string[] - list of items available for this field. */
    abstract public function getItemsList() : array;

    public function dropCache()
    {
        $this->value = null;
        $this->update = null;

        return parent::dropCache();
    }

    /**
     * @return bool[] - list of all items in select list with their state (selected or not)
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    protected function getItemsState()
    {
        if (!isset($this->value)) {
            $this->value = [];

            $Field = $this->getOriginalObject();

            foreach ($this->getItemsList() as $item_name) {
                $this->value[$item_name] = false;
            }

            foreach ((array)$Field as $SelectItem) {
                /** @var \stdClass $SelectItem */
                $this->value[$SelectItem->value] = true;
            }
        }

        return $this->value;
    }

    /**
     * @return string[] - list of names of selected items
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue() : array
    {
        return array_keys(array_filter($this->getItemsState()));
    }

    /**
     * @param string[] $value - list of names for items we want to mark 'selected'
     * @return array
     */
    public static function generateSetter($value) : array
    {
        $items_to_select = [];
        foreach ($value as $item_name) {
            $items_to_select[] = ['value' => $item_name];
        }

        return [ ['set' => $items_to_select] ];
    }


    /**
     * @param string[] $value - list of names of selected items.
     * @return $this
     */
    public function setValue($value)
    {
        $this->update = [];
        foreach ((array)$value as $item) {
            $this->update[$item] = true;
        }

        $update = static::generateSetter($value);
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * @param string $item_name
     * @param bool   $selected_state - true: select item,
     *                                 false: deselect it.
     *
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\CustomField
     * @throws \Badoo\Jira\REST\Exception
     */
    public function selectItem(string $item_name, bool $selected_state = true)
    {
        if (!in_array($item_name, $this->getItemsList())) {
            throw new \Badoo\Jira\Exception\CustomField(
                "Can't change state of unknown item '{$item_name}'. "
                . "Available items for field '{$this->getName()}' are: '"
                . implode("', '", $this->getItemsList()) . "'\n"
            );
        }

        if (!isset($this->update)) {
            $this->update = $this->getItemsState();
        }
        $this->update[$item_name] = $selected_state;

        $update = static::generateSetter(array_keys(array_filter($this->update)));
        $this->Issue->edit($this->getID(), $update);

        return $this;
    }

    /**
     * @param string $item
     * @return bool
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function isSelected(string $item) : bool
    {
        return $this->getItemsState()[$item] ?? false;
    }

    /**
     * @param bool $selected_state - true: select all items,
     *                               false: deselect all items.
     */
    public function selectAll($selected_state = true)
    {
        if ($selected_state) {
            $this->setValue($this->getItemsList());
        } else {
            $this->setValue([]);
        }
    }
}
