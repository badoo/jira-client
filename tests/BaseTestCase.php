<?php

declare(strict_types=1);

namespace Badoo\Jira\UTests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case with common functionality.
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Create new object of stdClass with defined properties.
     *
     * @param array<string, mixed> $properties Property-value map to setup object properties.
     *
     * @return \stdClass
     */
    protected static function createCustomObject(array $properties): \stdClass
    {
        $object = new \stdClass();
        foreach ($properties as $name => $value) {
            $object->{$name} = $value;
        }

        return $object;
    }
}
