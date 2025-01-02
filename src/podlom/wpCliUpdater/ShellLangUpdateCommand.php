<?php
/**
 * @author    Taras Shkodenko <taras@shkodenko.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

namespace podlom\wpCliUpdater;


use podlom\wpCliUpdater\AbstractCommand;


final class ShellLangUpdateCommand extends AbstractCommand
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
        // TODO: check if we have to escape shell like: $command = escapeshellcmd($this->getCommand());
        $command = $this->getCommand();
        echo __METHOD__ . ' +' . __LINE__ . ' Original command: ' . var_export($command, true) . PHP_EOL . PHP_EOL;
        $command = $this->environment->prepareCommand($command);
        echo __METHOD__ . ' +' . __LINE__ . ' Executing command: ' . var_export($command, true) . ' ...' . PHP_EOL;
        $this->result = shell_exec($command);

        $updateLanguageCommand = "/usr/local/bin/wp language core update && /usr/local/bin/wp language theme update --all && /usr/local/bin/wp language plugin update --all";
        echo __METHOD__ . ' +' . __LINE__ . ' Original update command: ' . var_export($updateLanguageCommand, true) . PHP_EOL . PHP_EOL;
        $updateLanguageCommand = $this->environment->prepareCommand($updateLanguageCommand);
        echo __METHOD__ . ' +' . __LINE__ . ' Fixed update command: ' . var_export($updateLanguageCommand, true) . PHP_EOL . PHP_EOL;
        $this->setCommand($updateLanguageCommand);
        //
        echo __METHOD__ . ' +' . __LINE__ . ' Executing update language command: ' . var_export($updateLanguageCommand, true) . ' ...' . PHP_EOL;
        $this->result = shell_exec($updateLanguageCommand);

        parent::execute();
    }
}