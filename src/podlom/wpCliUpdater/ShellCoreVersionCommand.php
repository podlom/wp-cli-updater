<?php

namespace podlom\wpCliUpdater;


use podlom\wpCliUpdater\AbstractCommand;


final class ShellCoreVersionCommand extends AbstractCommand
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
        echo __METHOD__ . ' +' . __LINE__ . ' Executing command: ' . var_export($command, true) . ' ...' . PHP_EOL;
        $this->result = shell_exec($command);

        if (!empty($this->result)) {
            echo __METHOD__ . ' +' . __LINE__ . ' WordPress Core Version is: ' . $this->result . PHP_EOL;
        } else {
            echo __METHOD__ . ' +' . __LINE__ . ' Server did not respond. No results.' . PHP_EOL;
        }

        parent::execute();
    }
}