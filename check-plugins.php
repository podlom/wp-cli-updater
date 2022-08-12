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

$environents = [
    1 => [1, 'shkodenko.com', 'ssh', 'phpacd', '104.236.235.165', '/home/phpacd/websites/shkodenko.com'],
    2 => [2, 'blog.shkodenko.com.ua', 'ssh', 'phpacd', '104.236.235.165', '/home/phpacd/websites/blog.shkodenko.com.ua'],
];


// command line arguments; check below for usage
// $cmdArgs = getopt('e::');
// echo __FILE__ . ' +' . __LINE__ . ' $cmdArgs: ' . var_export($cmdArgs, true);
// $envId = isset($cmdArgs['e']) ?? $cmdArgs['e'];

$envId = intval($argv[1]);

if ($envId > 0) {
    echo __FILE__ . ' +' . __LINE__ . ' $envId: ' . var_export($envId, true);
    if (isset($environents[$envId])) {
        $env = new Environment($environents[$envId][0], $environents[$envId][1], $environents[$envId][2], $environents[$envId][3], $environents[$envId][4], $environents[$envId][5]);

        $command = new ShellPluginsCommand("/usr/local/bin/wp plugin list", $env);
        echo '#--- execute commmand: ' . PHP_EOL;
        $command->execute();
    }
} else {
    echo 'Usage: php ' . $argv[0] . ' envID' . PHP_EOL;
}

