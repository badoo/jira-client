<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Helpers;

class Json
{
    /**
     * Same as `json_encode` but throws exceptions on errors.
     *
     * @see https://www.php.net/manual/en/function.json-encode.php
     *
     * @throws \UnexpectedValueException
     */
    public static function encode($value, $options = 0, $depth = 512) : string
    {
        $json = \json_encode($value, $options, $depth);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \UnexpectedValueException(
                'failed to json_encode value: ' . json_last_error_msg(),
                json_last_error()
            );
        }

        return $json;
    }

    /**
     * Same as `json_decode` but throws exceptions on errors.
     *
     * @see https://www.php.net/manual/en/function.json-decode.php
     *
     * @throws \UnexpectedValueException
     */
    public static function decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        $data = \json_decode($json, $assoc, $depth, $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \UnexpectedValueException(
                'failed to json_decode string: ' . json_last_error_msg(),
                json_last_error()
            );
        }

        return $data;
    }
}
