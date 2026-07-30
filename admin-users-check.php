<?php

declare(strict_types=1);

/**
 * @author    Taras Shkodenko <taras.shkodenko@gmail.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

namespace podlom\wpCliUpdater;

require_once __DIR__ . '/src/_autoload.php';

$environments = require __DIR__
    . '/config/environments.php';

if (
    !is_array($environments)
    || $environments === []
) {
    fwrite(
        STDERR,
        'Error: empty or invalid config/environments.php'
        . PHP_EOL
    );

    exit(
    ShellAdminUsersCheckCommand::STATUS_TECHNICAL_ERROR
    );
}

$envId = isset($argv[1])
    ? (int) $argv[1]
    : 0;

$acceptBaseline = in_array(
    '--accept',
    array_slice($argv, 2),
    true
);

$jsonOutput = in_array(
    '--json',
    array_slice($argv, 2),
    true
);

if ($envId <= 0) {
    echo 'Usage: php '
        . $argv[0]
        . ' envID [--accept]'
        . PHP_EOL;

    exit(
    ShellAdminUsersCheckCommand::STATUS_TECHNICAL_ERROR
    );
}

if (!isset($environments[$envId])) {
    fwrite(
        STDERR,
        'Error: requested environment ID '
        . $envId
        . ' was not found in config/environments.php'
        . PHP_EOL
    );

    exit(
    ShellAdminUsersCheckCommand::STATUS_TECHNICAL_ERROR
    );
}

$environmentConfig = $environments[$envId];

if (
    !is_array($environmentConfig)
    || count($environmentConfig) < 6
) {
    fwrite(
        STDERR,
        'Error: invalid configuration for environment ID '
        . $envId
        . PHP_EOL
    );

    exit(
    ShellAdminUsersCheckCommand::STATUS_TECHNICAL_ERROR
    );
}

if (!$jsonOutput) {
    echo sprintf(
            '%s Environment ID: %d (%s)',
            basename(__FILE__),
            $envId,
            $environmentConfig[1]
        ) . PHP_EOL;
}

$environment = new Environment(
    $environmentConfig[0],
    $environmentConfig[1],
    $environmentConfig[2],
    $environmentConfig[3],
    $environmentConfig[4],
    $environmentConfig[5]
);

$wpCommand = implode(
    ' ',
    [
        '/usr/local/bin/wp',
        'user list',
        '--role=administrator',
        '--fields=ID,user_login,user_email',
        '--format=json',
        '--no-color',
        '--quiet',
    ]
);

$command = new ShellAdminUsersCheckCommand(
    $wpCommand,
    $environment,
    $acceptBaseline,
    null,
    $jsonOutput
);

$command->execute();

exit($command->getStatus());