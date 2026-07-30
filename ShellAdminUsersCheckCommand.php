<?php

declare(strict_types=1);

namespace podlom\wpCliUpdater;

use JsonException;
use RuntimeException;
use Throwable;

final class ShellAdminUsersCheckCommand extends AbstractCommand
{
    public const STATUS_OK = 0;
    public const STATUS_SECURITY_WARNING = 1;
    public const STATUS_TECHNICAL_ERROR = 2;

    /**
     * Raw WP-CLI output.
     */
    private string $result = '';

    /**
     * @var array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    private array $addedAdministrators = [];

    /**
     * @var array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    private array $removedAdministrators = [];

    /**
     * @var array<int, array{
     *     before:array{
     *         ID:int,
     *         user_login:string,
     *         user_email:string
     *     },
     *     after:array{
     *         ID:int,
     *         user_login:string,
     *         user_email:string
     *     }
     * }>
     */
    private array $changedAdministrators = [];

    private bool $acceptBaseline;

    private string $snapshotDirectory;

    private bool $jsonOutput = false;

    private array $jsonReport = [];

    public function __construct(
        string $command,
        Environment $environment,
        bool $acceptBaseline = false,
        ?string $snapshotDirectory = null,
        bool $jsonOutput = false
    ) {
        parent::__construct($command);

        $this->setEnvironment($environment);
        $this->acceptBaseline = $acceptBaseline;

        /*
         * __DIR__:
         * project/src/podlom/wpCliUpdater
         *
         * dirname(__DIR__, 3):
         * project
         */
        $this->snapshotDirectory = $snapshotDirectory
            ?? dirname(__DIR__, 3) . '/var/security';

        $this->jsonOutput = $jsonOutput;
    }

    public function getJsonReport(): array
    {
        return $this->jsonReport;
    }

    private function outputJson(): void
    {
        echo json_encode(
                $this->jsonReport,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) . PHP_EOL;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * @return array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    public function getAddedAdministrators(): array
    {
        return $this->addedAdministrators;
    }

    /**
     * @return array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    public function getRemovedAdministrators(): array
    {
        return $this->removedAdministrators;
    }

    public function getSnapshotFilePath(): string
    {
        $environment = $this->requireEnvironment();

        return sprintf(
            '%s/admin-users-env-%d.json',
            rtrim($this->snapshotDirectory, '/\\'),
            $environment->getId()
        );
    }

    public function execute(): void
    {
        $this->resetComparisonResult();

        try {
            $currentAdministrators = $this->fetchCurrentAdministrators();
            $snapshotFile = $this->getSnapshotFilePath();

            /*
             * First run: create the trusted baseline.
             */
            if (!is_file($snapshotFile)) {
                $this->saveSnapshot(
                    $snapshotFile,
                    $currentAdministrators
                );

                $this->status = self::STATUS_OK;

                echo 'Administrator security baseline created.' . PHP_EOL;
                echo 'Administrators: '
                    . count($currentAdministrators)
                    . PHP_EOL;

                $this->printAdministrators($currentAdministrators);

                return;
            }

            $previousAdministrators = $this->loadSnapshot(
                $snapshotFile
            );

            $this->compareAdministrators(
                $previousAdministrators,
                $currentAdministrators
            );

            $this->jsonReport = [
                'environment' => [
                    'id' => $this->requireEnvironment()->getId(),
                    'name' => $this->requireEnvironment()->getName(),
                ],
                'status' => 'ok',
                'exit_code' => self::STATUS_OK,
                'checked_at' => gmdate('c'),
                'administrators' => [
                    'previous_count' => count($previousAdministrators),
                    'current_count' => count($currentAdministrators),
                ],
                'changes' => [
                    'added' => array_values(
                        $this->addedAdministrators
                    ),
                    'removed' => array_values(
                        $this->removedAdministrators
                    ),
                    'modified' => array_values(
                        $this->changedAdministrators
                    ),
                ],
            ];

            /*
             * Explicitly accept the current administrator list.
             */
            if ($this->acceptBaseline) {
                $this->saveSnapshot(
                    $snapshotFile,
                    $currentAdministrators
                );

                $this->status = self::STATUS_OK;

                echo 'Current administrator list accepted '
                    . 'as the new security baseline.'
                    . PHP_EOL;

                echo 'Administrators: '
                    . count($currentAdministrators)
                    . PHP_EOL;

                return;
            }

            /*
             * A new administrator is a security warning.
             *
             * Do not overwrite the trusted baseline here.
             */
            if ($this->addedAdministrators !== []) {
                $this->status = self::STATUS_SECURITY_WARNING;

                $this->jsonReport['status'] = 'warning';
                $this->jsonReport['exit_code'] =
                    self::STATUS_SECURITY_WARNING;

                if ($this->jsonOutput) {
                    $this->outputJson();
                } else {
                    $this->printSecurityWarning(
                        count($previousAdministrators),
                        count($currentAdministrators)
                    );
                }

                return;
            }

            /*
             * Removed administrators or changed account data are reported,
             * but do not produce a security-warning exit code.
             *
             * The baseline is still preserved until --accept is used.
             */
            if (
                $this->removedAdministrators !== []
                || $this->changedAdministrators !== []
            ) {
                $this->status = self::STATUS_OK;
                $this->printNonSecurityChanges();

                return;
            }

            $this->status = self::STATUS_OK;

            if ($this->jsonOutput) {

                $this->outputJson();

            } else {

                echo 'Security check passed.'
                    . PHP_EOL;

                echo 'Administrator count: '
                    . count($currentAdministrators)
                    . PHP_EOL;

                echo 'No new administrator accounts detected.'
                    . PHP_EOL;
            }
        } catch (Throwable $exception) {
            $this->status = self::STATUS_TECHNICAL_ERROR;

            if ($this->jsonOutput) {

                $this->outputJsonError(
                    $exception->getMessage(),
                    self::STATUS_TECHNICAL_ERROR
                );

            } else {

                fwrite(
                    STDERR,
                    'ERROR: '
                    . $exception->getMessage()
                    . PHP_EOL
                );
            }
        } finally {
            parent::execute();
        }
    }

    /**
     * @return array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    private function fetchCurrentAdministrators(): array
    {
        if (!function_exists('exec')) {
            throw new RuntimeException(
                'The PHP exec() function is unavailable.'
            );
        }

        $environment = $this->requireEnvironment();

        $preparedCommand = $environment->prepareCommand(
            $this->getCommand()
        );

        if (!$this->jsonOutput) {
            echo 'Executing command: '
                . $preparedCommand
                . PHP_EOL;
        }

        $output = [];
        $exitCode = self::STATUS_OK;

        /*
         * 2>&1 allows us to capture stderr together with stdout.
         */
        exec(
            $preparedCommand . ' 2>&1',
            $output,
            $exitCode
        );

        $this->result = implode(PHP_EOL, $output);
        $this->result = trim($this->result);

        if ($exitCode !== self::STATUS_OK) {
            throw new RuntimeException(
                sprintf(
                    'WP-CLI command failed with exit code %d.%s%s',
                    $exitCode,
                    $this->result === '' ? '' : ' Output: ',
                    $this->result
                )
            );
        }

        if ($this->result === '') {
            throw new RuntimeException(
                'WP-CLI returned an empty response '
                . 'while listing administrators.'
            );
        }

        try {
            $jsonStart = strpos($this->result, '[');

            if ($jsonStart === false) {
                throw new RuntimeException(
                    'WP-CLI did not return JSON. Output: '
                    . $this->result
                );
            }

            $this->result = substr(
                $this->result,
                $jsonStart
            );

            $decoded = json_decode(
                $this->result,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'WP-CLI returned invalid JSON: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'WP-CLI administrator response '
                . 'must be a JSON array.'
            );
        }

        return $this->normalizeAdministrators($decoded);
    }

    /**
     * Convert WP-CLI data to a predictable structure indexed by user ID.
     *
     * @param array<mixed> $administrators
     *
     * @return array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    private function normalizeAdministrators(
        array $administrators
    ): array {
        $normalized = [];

        foreach ($administrators as $index => $administrator) {
            if (!is_array($administrator)) {
                throw new RuntimeException(
                    sprintf(
                        'Administrator item at index %s '
                        . 'is not an object.',
                        (string) $index
                    )
                );
            }

            if (
                !array_key_exists('ID', $administrator)
                || !array_key_exists(
                    'user_login',
                    $administrator
                )
                || !array_key_exists(
                    'user_email',
                    $administrator
                )
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Administrator item at index %s '
                        . 'is missing a required field.',
                        (string) $index
                    )
                );
            }

            $id = filter_var(
                $administrator['ID'],
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id <= 0) {
                throw new RuntimeException(
                    sprintf(
                        'Administrator item at index %s '
                        . 'has an invalid ID.',
                        (string) $index
                    )
                );
            }

            if (isset($normalized[$id])) {
                throw new RuntimeException(
                    sprintf(
                        'Duplicate administrator ID %d '
                        . 'was returned by WP-CLI.',
                        $id
                    )
                );
            }

            $normalized[$id] = [
                'ID' => $id,
                'user_login' => (string) $administrator[
                'user_login'
                ],
                'user_email' => (string) $administrator[
                'user_email'
                ],
            ];
        }

        /*
         * Stable ordering prevents false differences.
         */
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @return array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }>
     */
    private function loadSnapshot(
        string $snapshotFile
    ): array {
        $contents = file_get_contents($snapshotFile);

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read administrator snapshot: '
                . $snapshotFile
            );
        }

        try {
            $snapshot = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Administrator snapshot contains invalid JSON: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (
            !is_array($snapshot)
            || !isset($snapshot['administrators'])
            || !is_array($snapshot['administrators'])
        ) {
            throw new RuntimeException(
                'Administrator snapshot has an invalid structure: '
                . $snapshotFile
            );
        }

        $environment = $this->requireEnvironment();

        if (
            isset($snapshot['environment']['id'])
            && (int) $snapshot['environment']['id']
            !== $environment->getId()
        ) {
            throw new RuntimeException(
                'Administrator snapshot belongs '
                . 'to another environment.'
            );
        }

        return $this->normalizeAdministrators(
            $snapshot['administrators']
        );
    }

    /**
     * @param array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }> $administrators
     */
    private function saveSnapshot(
        string $snapshotFile,
        array $administrators
    ): void {
        $directory = dirname($snapshotFile);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Unable to create snapshot directory: '
                . $directory
            );
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'Snapshot directory is not writable: '
                . $directory
            );
        }

        $environment = $this->requireEnvironment();

        $snapshot = [
            'version' => 1,
            'environment' => [
                'id' => $environment->getId(),
                'name' => $environment->getName(),
            ],
            'captured_at' => gmdate('c'),
            'administrators' => array_values(
                $administrators
            ),
        ];

        try {
            $json = json_encode(
                $snapshot,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to encode administrator snapshot: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }

        /*
         * Write to a temporary file first and then rename it.
         * This prevents partially written snapshot files.
         */
        $temporaryFile = tempnam(
            $directory,
            'admin-users-'
        );

        if ($temporaryFile === false) {
            throw new RuntimeException(
                'Unable to create a temporary snapshot file in: '
                . $directory
            );
        }

        try {
            $bytesWritten = file_put_contents(
                $temporaryFile,
                $json . PHP_EOL,
                LOCK_EX
            );

            if ($bytesWritten === false) {
                throw new RuntimeException(
                    'Unable to write temporary '
                    . 'administrator snapshot.'
                );
            }

            if (!rename($temporaryFile, $snapshotFile)) {
                throw new RuntimeException(
                    'Unable to replace administrator snapshot: '
                    . $snapshotFile
                );
            }

            /*
             * Owner: read/write
             * Group: read
             * Others: no access
             */
            @chmod($snapshotFile, 0640);
        } finally {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    /**
     * @param array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }> $previous
     *
     * @param array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }> $current
     */
    private function compareAdministrators(
        array $previous,
        array $current
    ): void {
        /*
         * Compare by WordPress user ID, not merely by count.
         */
        $this->addedAdministrators = array_diff_key(
            $current,
            $previous
        );

        $this->removedAdministrators = array_diff_key(
            $previous,
            $current
        );

        $this->changedAdministrators = [];

        foreach (
            array_intersect_key($current, $previous)
            as $id => $administrator
        ) {
            if ($administrator !== $previous[$id]) {
                $this->changedAdministrators[$id] = [
                    'before' => $previous[$id],
                    'after' => $administrator,
                ];
            }
        }
    }

    private function printSecurityWarning(
        int $previousCount,
        int $currentCount
    ): void {
        fwrite(
            STDERR,
            'SECURITY WARNING: '
            . 'New WordPress administrator detected!'
            . PHP_EOL
        );

        fwrite(
            STDERR,
            'Previous administrator count: '
            . $previousCount
            . PHP_EOL
        );

        fwrite(
            STDERR,
            'Current administrator count: '
            . $currentCount
            . PHP_EOL
        );

        fwrite(
            STDERR,
            'Added administrators:'
            . PHP_EOL
        );

        $this->printAdministrators(
            $this->addedAdministrators,
            STDERR
        );

        if ($this->removedAdministrators !== []) {
            fwrite(
                STDERR,
                'Removed administrators:'
                . PHP_EOL
            );

            $this->printAdministrators(
                $this->removedAdministrators,
                STDERR
            );
        }

        fwrite(
            STDERR,
            'The saved baseline was not changed. '
            . 'Review the account and rerun with --accept '
            . 'if it is legitimate.'
            . PHP_EOL
        );
    }

    private function printNonSecurityChanges(): void
    {
        echo 'No new administrator accounts detected.'
            . PHP_EOL;

        if ($this->removedAdministrators !== []) {
            echo 'Removed administrators:' . PHP_EOL;

            $this->printAdministrators(
                $this->removedAdministrators
            );
        }

        if ($this->changedAdministrators !== []) {
            echo 'Changed administrator records:'
                . PHP_EOL;

            foreach (
                $this->changedAdministrators as $change
            ) {
                echo sprintf(
                        '- ID %d: %s <%s> -> %s <%s>',
                        $change['before']['ID'],
                        $change['before']['user_login'],
                        $change['before']['user_email'],
                        $change['after']['user_login'],
                        $change['after']['user_email']
                    ) . PHP_EOL;
            }
        }

        echo 'The saved baseline was not changed. '
            . 'Run again with --accept '
            . 'to store the current list.'
            . PHP_EOL;
    }

    /**
     * @param array<int, array{
     *     ID:int,
     *     user_login:string,
     *     user_email:string
     * }> $administrators
     *
     * @param resource|null $stream
     */
    private function printAdministrators(
        array $administrators,
              $stream = null
    ): void {
        foreach ($administrators as $administrator) {
            $email = $administrator['user_email'] === ''
                ? ''
                : ' <'
                . $administrator['user_email']
                . '>';

            $line = sprintf(
                    '- ID %d: %s%s',
                    $administrator['ID'],
                    $administrator['user_login'],
                    $email
                ) . PHP_EOL;

            if ($stream === null) {
                echo $line;
            } else {
                fwrite($stream, $line);
            }
        }
    }

    private function requireEnvironment(): Environment
    {
        $environment = $this->getEnvironment();

        if ($environment === null) {
            throw new RuntimeException(
                'Command environment is not configured.'
            );
        }

        return $environment;
    }

    private function resetComparisonResult(): void
    {
        $this->addedAdministrators = [];
        $this->removedAdministrators = [];
        $this->changedAdministrators = [];
    }

    private function outputJsonError(
        string $message,
        int $exitCode
    ): void {
        echo json_encode(
                [
                    'status' => 'error',
                    'exit_code' => $exitCode,
                    'message' => $message,
                    'checked_at' => gmdate('c'),
                ],
                JSON_PRETTY_PRINT
            ) . PHP_EOL;
    }
}