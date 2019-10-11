<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\UTests\Helpers;

class StringsTest extends \PHPUnit\Framework\TestCase
{
    public function providerToLabels()
    {
        return [
            'empty string' => ['', '_', '_'],
            'valid label' => ['valid_phpLabel', '_', 'valid_phpLabel'],
            'number first' => ['123 number and spaces', '', '_123numberandspaces'],
            'number middle' => ['a1number/in/the!middle', 'Q', 'a1numberQinQtheQmiddle'],
        ];
    }

    /**
     * @dataProvider providerToLabels
     *
     * @param string $source - text to transform
     * @param string $replace - replacement character
     * @param string $expected - expected transformation result
     */
    public function testToPhpLabel(string $source, string $replace, string $expected)
    {
        $result = \Badoo\Jira\Helpers\Strings::toPHPLabel($source, $replace);

        self::assertEquals($expected, $result, 'Incorrect text to PHP label conversion result');
        self::assertTrue(\Badoo\Jira\Helpers\Strings::isValidPHPLabel($result), "the conversion result '{$result}' is not a valid PHP label");
    }

    public function providerIsValidLabel()
    {
        return [
            'empty string' => ['', false],
            'valid' => ['valid_label', true],
            'number first' => ['1invalid' , false],
            'frbidden characters' => ['text with spaces', false],
            'valid all capitals' => ['ALLCAPITALS', true],
            'underscored' => ['_1_number', true],
        ];
    }

    /**
     * @dataProvider providerIsValidLabel
     *
     * @param string $to_test - text to test for validity as class/constant/variable name
     * @param bool $expected_result - is <to_test> really valid?
     */
    public function testIsValidLabel(string $to_test, bool $expected_result)
    {
        $is_valid = \Badoo\Jira\Helpers\Strings::isValidPHPLabel($to_test);

        $in = $expected_result ? '' : 'in';
        self::assertEquals($expected_result, $is_valid, "Wring result for {$in}valid PHP label '{$to_test}''");
    }

    public function testToCapitalPhpLabel()
    {
        $from = ' 1 some text';

        $capitals = \Badoo\Jira\Helpers\Strings::toCapitalPHPLabel($from);

        self::assertEquals('_1_SOME_TEXT', $capitals);
    }

    public function testToCamelCasePHPLabel()
    {
        $from = ' 1 some text';

        $capitals = \Badoo\Jira\Helpers\Strings::toCamelCasePHPLabel($from);

        self::assertEquals('_1SomeText', $capitals);
    }
}
