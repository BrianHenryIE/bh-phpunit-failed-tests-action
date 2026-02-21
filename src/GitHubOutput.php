<?php

class GitHubOutput
{
    private ?string $outputFile;

    public function __construct(?string $outputFile = null)
    {
        $this->outputFile = $outputFile ?? (getenv('GITHUB_OUTPUT') ?: null);
    }

    /**
     * Set a step output value, written to $GITHUB_OUTPUT when running in Actions.
     */
    public function set(string $name, string $value): void
    {
        if ($this->outputFile !== null) {
            file_put_contents($this->outputFile, "$name=$value\n", FILE_APPEND);
        }
    }

    /**
     * Print a log line to stdout.
     */
    public function log(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Open a collapsible log group in GitHub Actions.
     */
    public function group(string $name): void
    {
        echo "::group::$name" . PHP_EOL;
    }

    /**
     * Close the current log group.
     */
    public function endGroup(): void
    {
        echo '::endgroup::' . PHP_EOL;
    }
}
