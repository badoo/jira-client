<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class SingleSelectField
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'single select' type custom field
 */
abstract class SingleSelectField extends CustomField
{
    /** @var string */
    protected $value;

    /** @return string[] - list of items available for this field. */
    abstract public function getItemsList() : array;

    /**
     * @return string
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue() : string
    {
        if ($this->getOriginalObject() === null) {
            return '';
        }

        return $this->getOriginalObject()->value;
    }

    /**
     * @param string $value
     * @return array
     */
    public static function generateSetter($value) : array
    {
        if ($value !== null) {
            $value = ['value' => $value];
        }

        return [ [ 'set' => $value ] ];
    }

    /**
     * @param string $value
     * @return $this
     *
     * @throws \Badoo\Jira\Exception\CustomField
     */
    public function setValue($value)
    {
        if (isset($value) && !in_array($value, $this->getItemsList())) {
            throw new \Badoo\Jira\Exception\CustomField(
                "Can't select '{$value}' item. "
                    . "Available items for field '{$this->getName()}' are: '"
                    . implode("', '", $this->getItemsList()) . "'\n"
            );
        }

        parent::setValue($value);

        return $this;
    }
}
