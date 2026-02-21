<?php

/**
 * PHPUnit test runner.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Runs PHPUnit as a subprocess and returns its exit code.
 */
class PhpUnitRunner
{
    /**
     * Run PHPUnit and return the exit code.
     *
     * @param string $command The PHPUnit binary/command.
     * @param string $filter  Optional --filter regex. Empty skips the flag.
     * @param string $args    Optional extra arguments appended verbatim.
     *
     * @return int PHPUnit exit code (0 = pass, non-zero = failure).
     */
    public function run(
        string $command,
        string $filter = '',
        string $args = ''
    ): int {
        passthru($this->buildCommand($command, $filter, $args), $exitCode);

        return (int) $exitCode;
    }

    /**
     * Build the full shell command string without executing it.
     *
     * @param string $command The PHPUnit binary/command.
     * @param string $filter  Optional --filter regex. Empty skips the flag.
     * @param string $args    Optional extra arguments appended verbatim.
     *
     * @return string The assembled shell command.
     */
    public function buildCommand(
        string $command,
        string $filter = '',
        string $args = ''
    ): string {
        $parts = [$command];

        if ($filter !== '') {
            $parts[] = '--filter ' . escapeshellarg($filter);
        }

        if ($args !== '') {
            $parts[] = $args;
        }

        return implode(' ', $parts);
    }
}
