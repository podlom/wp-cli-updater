<?php
/**
 * @author    Taras Shkodenko <taras@shkodenko.com>
 * @copyright Shkodenko V. Taras, https://www.shkodenko.com/
 */

namespace podlom\wpCliUpdater;


use podlom\wpCliUpdater\AbstractCommand;


final class ShellThemeUpdateCommand extends AbstractCommand
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
            echo __METHOD__ . ' +' . __LINE__ . ' Update available for themes: ' . var_export($updateAvailable, true) . PHP_EOL;

            foreach ($updateAvailable as $theme) {
                $updateThemeCommand = "/usr/local/bin/wp theme update {$theme[0]}";
                echo __METHOD__ . ' +' . __LINE__ . ' Original update command: ' . var_export($updateThemeCommand, true) . PHP_EOL . PHP_EOL;
                $updateThemeCommand = $this->environment->prepareCommand($updateThemeCommand);
                echo __METHOD__ . ' +' . __LINE__ . ' Fixed update command: ' . var_export($updateThemeCommand, true) . PHP_EOL . PHP_EOL;
                $this->setCommand($updateThemeCommand);
                //
                echo __METHOD__ . ' +' . __LINE__ . ' Executing update theme command: ' . var_export($updateThemeCommand, true) . ' ...' . PHP_EOL;
                $this->result = shell_exec($updateThemeCommand);
            }
        } else {
            echo __METHOD__ . ' +' . __LINE__ . ' All themes are up-to-date.' . PHP_EOL;
        }

        parent::execute();
    }
}