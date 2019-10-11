<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Badoo\Jira\Helpers;

class Files
{
    public static function filePutContents($file, $contents, $flags = 0)
    {
        if (file_put_contents($file, $contents, $flags) !== mb_strlen($contents, '8bit')) {
            throw new \RuntimeException("Failed to write to $file");
        }
    }

    public static function fileGetContents($file) : string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new \RuntimeException("Failed to get file contents for $file");
        }

        return $contents;
    }

    public static function exists(string $file_path) : bool
    {
        return \file_exists($file_path);
    }
}
