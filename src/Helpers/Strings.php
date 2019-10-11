<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Helpers;

class Strings
{
    /** @var string - a valid PHP letter as it is described in PHP language documentation.
     *                Is used in REGEX'es for valid PHP labels (constant names, class names, variables and so on) */
    const PHP_LETTER = 'a-zA-Z';

    /**
     * Replaces all incorrect characters (that could not be used in PHP labels, like constants or class names)
     * with the given <replacement>
     * @see https://www.php.net/manual/en/language.variables.basics.php
     *
     * @param string $text - text to translate into a valid PHP label
     * @param string $replacement - a character to be used as replacement for incorrect chars in <text>
     *
     * @return string - valid PHP label that can be used as const, class or variable name
     */
    public static function toPHPLabel(string $text, string $replacement = '_') : string
    {
        $letter = self::PHP_LETTER;
        $label = preg_replace("/[^_0-9{$letter}]+/", $replacement, $text); // e.g. ' 0. #With @specials!' -> '_0_With_specials_'
        $label = trim($label, $replacement); // '_0_With_specials_' -> '0_With_specials'

        if (preg_match("/^[0-9]/", $label)) {
            // label can't start with a number. Put a '_' to the beginning to make a valid label
            $label = "_{$label}"; // '0_With_specials' -> '_0_With_specials'
        }

        if (empty($label)) {
            $label = '_';
        }

        return $label;
    }

    /**
     * Checks if given string contains valid PHP label
     * @see https://www.php.net/manual/en/language.variables.basics.php
     *
     * @param string $text - text to check
     * @return bool - true when <text> is a valid PHP label (can be used as variable, constant or class name)
     */
    public static function isValidPHPLabel(string $text) : bool
    {
        $letter = self::PHP_LETTER;
        return (bool)preg_match("/^[_{$letter}][_0-9{$letter}]*$/", $text);
    }

    /**
     * Generates a valid PHP label with CAPITAL letters and '_' as replacement for unallowed characters
     *
     * Examples:
     *  'my 1 sample textual value!' -> MY_1_SAMPLE_TEXTUAL_VALUE
     *  'translate `em all'          -> TRANSLATE_EM_ALL
     *  '1 - option one'             -> '_1_OPTION_ONE'
     */
    public static function toCapitalPHPLabel(string $text) : string
    {
        $label = static::toPHPLabel($text); // ' 1 - some random text' -> '_1_some_random_text'
        return strtoupper($label); // '_1_some_random_text' -> '_1_SOME_RANDOM_TEXT'
    }

    /**
     * Generate a valid camel cased PHP label
     *
     * Examples:
     *  'my 1 sample textual value!' -> My1SampleTextualValue
     *  'translate `em all'          -> TranslateEmAll
     *  '1 - option one'             -> '_1OptionOne'
     */
    public static function toCamelCasePHPLabel(string $text) : string
    {
        $capital_label = static::toCapitalPHPLabel($text);

        $parts = [];

        if ($capital_label[0] === '_') {
            // make CamelCasedLabel to start with _ if CAPITAL has it first.
            // This means the second char might be a number
            $parts[] = '_';
        }

        $part = strtok($capital_label, '_');
        while ($part !== false) {
            $parts[] = ucfirst(strtolower($part)); // 'AWESOME' -> 'Awesome'
            $part = strtok('_');
        }

        return implode('', $parts); // ['Awesome', 'Class', 'Name'] -> 'AwesomeClassName'
    }
}
