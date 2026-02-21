<?php

/**
 * GitHub Actions output helper.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Writes step outputs and log markers for GitHub Actions.
 */
class GitHubOutput
{
    /**
     * Path to the $GITHUB_OUTPUT file, or null when not in Actions.
     */
    private ?string $outputFile;

    /**
     * Constructor.
     *
     * @param string|null $outputFile Path to the output file, or null to read
     *                                from the GITHUB_OUTPUT environment variable.
     */
    public function __construct(?string $outputFile = null)
    {
        $this->outputFile = $outputFile ?? (getenv('GITHUB_OUTPUT') ?: null);
    }

    /**
     * Set a step output value, written to $GITHUB_OUTPUT in Actions.
     *
     * @param string $name  Output variable name.
     * @param string $value Output variable value.
     *
     * @return void
     */
    public function set(string $name, string $value): void
    {
        if ($this->outputFile !== null) {
            file_put_contents(
                $this->outputFile,
                "$name=$value\n",
                FILE_APPEND
            );
        }
    }

    /**
     * Print a log line to stdout.
     *
     * @param string $message Message to print.
     *
     * @return void
     */
    public function log(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Open a collapsible log group in GitHub Actions.
     *
     * @param string $name Group label.
     *
     * @return void
     */
    public function group(string $name): void
    {
        echo "::group::$name" . PHP_EOL;
    }

    /**
     * Close the current log group.
     *
     * @return void
     */
    public function endGroup(): void
    {
        echo '::endgroup::' . PHP_EOL;
    }
}
