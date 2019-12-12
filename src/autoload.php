<?php
/**
 * Wrapper for Composer's vendor/autoload.php.
 *
 * Particularly useful for the Composer vendor binaries.
 * @link https://getcomposer.org/doc/articles/vendor-binaries.md
 */
return (function () {
    $relative_path = DIRECTORY_SEPARATOR . 'vendor' .
        DIRECTORY_SEPARATOR . 'autoload.php';

    foreach ([1, 4] as $level) {
        $absolute_path = dirname(__DIR__, $level) . $relative_path;

        if (file_exists($absolute_path)) {
            return require $absolute_path;
        }
    }

    throw new \RuntimeException('Could not find Composer\'s autoload.php');
})();
