<?php

use PHPUnit\Framework\TestCase;

class PhpUnitRunnerTest extends TestCase
{
    private PhpUnitRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new PhpUnitRunner();
    }

    // ── buildCommand ──────────────────────────────────────────────────────────

    /** @test */
    public function build_command_returns_bare_command_when_no_filter_or_args(): void
    {
        $this->assertSame(
            'vendor/bin/phpunit',
            $this->runner->buildCommand('vendor/bin/phpunit')
        );
    }

    /** @test */
    public function build_command_appends_filter_flag(): void
    {
        $command = $this->runner->buildCommand('vendor/bin/phpunit', 'FooTest::testBar');

        $this->assertStringContainsString('--filter', $command);
        $this->assertStringContainsString('FooTest::testBar', $command);
    }

    /** @test */
    public function build_command_shell_escapes_the_filter_value(): void
    {
        // A filter with pipe characters must be shell-escaped so the shell
        // doesn't interpret | as a pipe operator.
        $command = $this->runner->buildCommand('vendor/bin/phpunit', 'FooTest::testA|BarTest::testB');

        $this->assertStringContainsString('--filter', $command);
        // escapeshellarg wraps in single quotes on *nix
        $this->assertStringContainsString("'FooTest::testA|BarTest::testB'", $command);
    }

    /** @test */
    public function build_command_appends_extra_args(): void
    {
        $command = $this->runner->buildCommand('vendor/bin/phpunit', '', '--stop-on-failure');

        $this->assertStringContainsString('--stop-on-failure', $command);
    }

    /** @test */
    public function build_command_omits_filter_flag_when_filter_is_empty(): void
    {
        $command = $this->runner->buildCommand('vendor/bin/phpunit', '', '--stop-on-failure');

        $this->assertStringNotContainsString('--filter', $command);
    }

    /** @test */
    public function build_command_omits_extra_args_when_args_is_empty(): void
    {
        $command = $this->runner->buildCommand('vendor/bin/phpunit');

        $this->assertSame('vendor/bin/phpunit', $command);
    }

    /** @test */
    public function build_command_includes_all_parts_when_all_provided(): void
    {
        $command = $this->runner->buildCommand('vendor/bin/phpunit', 'FooTest::testBar', '--colors=always');

        $this->assertStringStartsWith('vendor/bin/phpunit', $command);
        $this->assertStringContainsString('--filter', $command);
        $this->assertStringContainsString('FooTest::testBar', $command);
        $this->assertStringContainsString('--colors=always', $command);
    }

    // ── run ───────────────────────────────────────────────────────────────────

    /** @test */
    public function run_returns_zero_for_a_passing_command(): void
    {
        $exitCode = $this->runner->run('php -r "exit(0);"');

        $this->assertSame(0, $exitCode);
    }

    /** @test */
    public function run_returns_the_commands_exit_code_on_failure(): void
    {
        $exitCode = $this->runner->run('php -r "exit(2);"');

        $this->assertSame(2, $exitCode);
    }
}
