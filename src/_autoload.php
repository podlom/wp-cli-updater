<?php
/**
 * @author    Taras Shkodenko <taras@shkodenko.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

spl_autoload_register(function ($className) {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    $file = str_replace('podlom/wpCliUpdater', __DIR__ . '/podlom/wpCliUpdater', $file);
    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
});
