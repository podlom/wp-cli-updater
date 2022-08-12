<?php

namespace podlom\wpCliUpdater;


use podlom\wpCliUpdater\AbstractCommand;


final class ShellPluginsListCommand extends AbstractCommand
{
    private $result;

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    public function __construct(string $command, Environment $environment)
    {
        $this->command = $command;
        $this->setEnvironment($environment);

        parent::__construct($command);
    }

    public function execute(): void
    {
        $command = $this->getCommand();
        $command = $this->environment->prepareCommand($command);
        $this->result = shell_exec($command);

        $updateAvailable = [];
        if (!empty($this->result)) {
            echo __METHOD__ . ' +' . __LINE__ . ' Plugin List command result: ' . PHP_EOL . var_export($this->result, true) . PHP_EOL;
        } else {
            echo __METHOD__ . ' +' . __LINE__ . ' No results. Server did not respond.' . PHP_EOL;
        }

        parent::execute();
    }
}