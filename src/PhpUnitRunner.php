<?php

class PhpUnitRunner
{
    /**
     * Run PHPUnit and return the exit code.
     *
     * @param string $command The PHPUnit binary/command (e.g. "vendor/bin/phpunit").
     * @param string $filter  Optional --filter value (regex). Empty string skips the flag.
     * @param string $args    Optional extra arguments to append verbatim.
     */
    public function run(string $command, string $filter = '', string $args = ''): int
    {
        passthru($this->buildCommand($command, $filter, $args), $exitCode);

        return (int) $exitCode;
    }

    public function buildCommand(string $command, string $filter = '', string $args = ''): string
    {
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
