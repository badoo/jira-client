<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\CustomFields;

/**
 * Class TextField
 * @package Badoo\Jira\CustomFields\Abstracts
 *
 * Wrapper class for 'text' type custom field
 */
abstract class TextField extends CustomField
{
    /**
     * @return string
     *
     * @throws \Badoo\Jira\REST\Exception
     */
    public function getValue()
    {
        return (string)$this->getOriginalObject();
    }

    /**
     * @param string $value
     * @return array
     */
    public static function generateSetter($value) : array
    {
        if ($value !== null) {
            $value = (string)$value;
        }

        return [ ['set' => $value] ];
    }
}
