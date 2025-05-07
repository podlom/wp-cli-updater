<?php
/**
 * @author    Taras Shkodenko <taras.shkodenko@gmail.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

 spl_autoload_register(function ($className) {
    $prefix = 'podlom\\wpCliUpdater\\';
    $base_dir = __DIR__ . '/podlom/wpCliUpdater/';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return false; // not our class
    }

    // get the relative class name
    $relative_class = substr($className, $len);

    // replace namespace separators with directory separators, append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
        return true;
    }

    return false;
});
