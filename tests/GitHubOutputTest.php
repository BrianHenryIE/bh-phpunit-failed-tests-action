<?php

use PHPUnit\Framework\TestCase;

class GitHubOutputTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'gh_output_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    // ── set() ─────────────────────────────────────────────────────────────────

    /** @test */
    public function set_writes_name_equals_value_line_to_output_file(): void
    {
        $output = new GitHubOutput($this->tempFile);
        $output->set('my-key', 'my-value');

        $this->assertStringContainsString("my-key=my-value\n", file_get_contents($this->tempFile));
    }

    /** @test */
    public function set_appends_multiple_outputs_to_the_file(): void
    {
        $output = new GitHubOutput($this->tempFile);
        $output->set('key1', 'value1');
        $output->set('key2', 'value2');

        $contents = file_get_contents($this->tempFile);
        $this->assertStringContainsString("key1=value1\n", $contents);
        $this->assertStringContainsString("key2=value2\n", $contents);
    }

    /** @test */
    public function set_does_nothing_when_no_output_file_is_configured(): void
    {
        $output = new GitHubOutput(null);

        // Should not throw or write anywhere.
        $output->set('key', 'value');
        $this->assertTrue(true);
    }

    /** @test */
    public function set_reads_output_file_path_from_environment_when_not_passed(): void
    {
        putenv("GITHUB_OUTPUT={$this->tempFile}");

        try {
            $output = new GitHubOutput();
            $output->set('env-key', 'env-value');
            $this->assertStringContainsString("env-key=env-value\n", file_get_contents($this->tempFile));
        } finally {
            putenv('GITHUB_OUTPUT');
        }
    }

    // ── log() ─────────────────────────────────────────────────────────────────

    /** @test */
    public function log_prints_message_with_newline_to_stdout(): void
    {
        $output = new GitHubOutput(null);

        $this->expectOutputString("Hello, world!\n");
        $output->log('Hello, world!');
    }

    // ── group() / endGroup() ──────────────────────────────────────────────────

    /** @test */
    public function group_prints_actions_group_marker(): void
    {
        $output = new GitHubOutput(null);

        $this->expectOutputString("::group::My Section\n");
        $output->group('My Section');
    }

    /** @test */
    public function end_group_prints_actions_endgroup_marker(): void
    {
        $output = new GitHubOutput(null);

        $this->expectOutputString("::endgroup::\n");
        $output->endGroup();
    }
}
