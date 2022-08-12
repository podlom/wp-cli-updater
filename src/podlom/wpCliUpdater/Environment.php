<?php

namespace podlom\wpCliUpdater;

class Environment
{
    private int $id;

    private string $name;

    private string $type;

    private string $user;

    private string $host;

    private string $path;

    public function __construct($id, $name, $type, $user, $host, $path)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->user = $user;
        $this->host = $host;
        $this->path = $path;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function prepareCommand(string $command): string
    {
        echo __METHOD__ . ' +' . __LINE__ . ' $command: ' . var_export($command, true) . PHP_EOL . PHP_EOL;
        $modifiedCommand = $command;
        if ($this->type == 'ssh') {
            if (!empty($this->user)) {
                $modifiedCommand = $this->type . " " . $this->user . "@" . $this->host . " ";
            } else {
                $modifiedCommand = $this->type . " " . $this->host . " ";
            }
            if (!empty($this->path)) {
                $modifiedCommand .= "'cd " . $this->path . " && " . $command . "'";
            } else {
                $modifiedCommand .= "'" . $command . "'";
            }
            echo __METHOD__ . ' +' . __LINE__ . ' $modifiedCommand: ' . var_export($modifiedCommand, true) . PHP_EOL;
        }

        return $modifiedCommand;
    }
}