<?php
/**
 * @author    Taras Shkodenko <taras@shkodenko.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

namespace podlom\wpCliUpdater;


require_once 'src/_autoload.php';

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
    if (isset($environents[$envId])) {
        echo __FILE__ . ' +' . __LINE__ . ' $envId: ' . var_export($envId, true) . ' (' . $environents[$envId][1] . ')' . PHP_EOL;
        $env = new Environment($environents[$envId][0], $environents[$envId][1], $environents[$envId][2], $environents[$envId][3], $environents[$envId][4], $environents[$envId][5]);

        $command = new ShellLangUpdateCommand("/usr/local/bin/wp language core list --status=active", $env);
        echo '#--- execute commmand (1): ' . PHP_EOL;
        $command->execute();

        $command = new ShellLangUpdateCommand("/usr/local/bin/wp language core list --status=installed", $env);
        echo '#--- execute commmand (2): ' . PHP_EOL;
        $command->execute();
    } else {
        echo 'Error: requested evirontment ID was not found in config/environments.php' . PHP_EOL;
        exit(2);
    }
} else {
    echo 'Usage: php ' . $argv[0] . ' envID' . PHP_EOL;
}
