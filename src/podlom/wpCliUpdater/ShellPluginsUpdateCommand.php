<?php
/**
 * @author    Taras Shkodenko <taras@shkodenko.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

namespace podlom\wpCliUpdater;


use podlom\wpCliUpdater\AbstractCommand;


final class ShellPluginsUpdateCommand extends AbstractCommand
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
        // $command = escapeshellcmd($this->getCommand());
        $command = $this->getCommand();
        echo __METHOD__ . ' +' . __LINE__ . ' Original command: ' . var_export($command, true) . PHP_EOL . PHP_EOL;
        $command = $this->environment->prepareCommand($command);
        echo __METHOD__ . ' +' . __LINE__ . ' Executing command: ' . var_export($command, true) . ' ...' . PHP_EOL;
        $this->result = shell_exec($command);

        $updateAvailable = [];
        if (!empty($this->result)) {
            $aResult = explode(PHP_EOL, $this->result);
            // echo __METHOD__ . ' +' . __LINE__ . ' $aResult : ' . var_export($aResult, true);
            if (is_array($aResult) && !empty($aResult)) {
                for ($i = 1; $i < count($aResult); $i++) {
                    $line = $aResult[$i];
                    if (!empty($line)) {
                        $aLine = explode("\t", $line);
                        // echo __METHOD__ . ' +' . __LINE__ . ' $aLine: ' . var_export($aLine, true);
                        if ($aLine[2] == 'available') {
                            $updateAvailable[] = $aLine;
                        }
                    }
                }
            }
        }
        if (!empty($updateAvailable)) {
            echo __METHOD__ . ' +' . __LINE__ . ' Update available for plugins: ' . var_export($updateAvailable, true) . PHP_EOL;

            foreach ($updateAvailable as $plugin) {
                $updatePluginCommand = "/usr/local/bin/wp plugin update {$plugin[0]}";
                echo __METHOD__ . ' +' . __LINE__ . ' Original update command: ' . var_export($updatePluginCommand, true) . PHP_EOL . PHP_EOL;
                $updatePluginCommand = $this->environment->prepareCommand($updatePluginCommand);
                echo __METHOD__ . ' +' . __LINE__ . ' Fixed update command: ' . var_export($updatePluginCommand, true) . PHP_EOL . PHP_EOL;
                $this->setCommand($updatePluginCommand);
                //
                echo __METHOD__ . ' +' . __LINE__ . ' Executing update command: ' . var_export($updatePluginCommand, true) . ' ...' . PHP_EOL;
                $this->result = shell_exec($updatePluginCommand);
            }
        } else {
            echo __METHOD__ . ' +' . __LINE__ . ' All plugins are up-to-date.' . PHP_EOL;
        }

        parent::execute();
    }
}