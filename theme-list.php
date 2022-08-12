<?php

namespace podlom\wpCliUpdater;

spl_autoload_register(function ($className) {
    $file = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    $file = str_replace('podlom/wpCliUpdater', __DIR__ . '/src/podlom/wpCliUpdater', $file);
    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    return false;
});

$environents = require_once 'config/environments.php';
if (empty($environents) && !is_array($environents)) {
    echo 'Error: empty config/environments.php' . PHP_EOL;
    exit(1);
}

$envId = 0;
if (isset($argv[1]) && !empty($argv[1])) {
    $envId = intval($argv[1]);
}
if ($envId > 0) {
    echo __FILE__ . ' +' . __LINE__ . ' $envId: ' . var_export($envId, true) . PHP_EOL;
    if (isset($environents[$envId])) {
        $env = new Environment($environents[$envId][0], $environents[$envId][1], $environents[$envId][2], $environents[$envId][3], $environents[$envId][4], $environents[$envId][5]);

        $command = new ShellThemeListCommand("/usr/local/bin/wp theme list", $env);
        $command->execute();
    } else {
        echo 'Error: requested evirontment ID was not found in config/environments.php' . PHP_EOL;
        exit(2);
    }
} else {
    echo 'Usage: php ' . $argv[0] . ' envID' . PHP_EOL;
}

