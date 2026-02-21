<?php

namespace BrianHenryIE\PHPUnitFailedTestsAction;

use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase
{
    private LogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LogParser();
    }

    /**
     * @test
     */
    public function it_returns_empty_array_for_empty_log(): void
    {
        $this->assertSame([], $this->parser->extractFailedTests(''));
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_no_failures_present(): void
    {
        $log = "OK (42 tests, 86 assertions)\n";

        $this->assertSame([], $this->parser->extractFailedTests($log));
    }

    /**
     * @test
     */
    public function it_extracts_a_single_namespaced_failure(): void
    {
        $log = <<<LOG
        There was 1 failure:

        1) Acme\Tests\FooTest::testSomething
        Failed asserting that false is true.
        LOG;

        $this->assertSame(
            ['Acme\Tests\FooTest::testSomething'],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_extracts_multiple_failures(): void
    {
        $log = <<<LOG
        There were 2 failures:

        1) Acme\Tests\FooTest::testSomething
        Failed asserting that false is true.

        2) Acme\Tests\BarTest::testOtherThing
        Failed asserting that null is not null.
        LOG;

        $this->assertSame(
            [
                'Acme\Tests\FooTest::testSomething',
                'Acme\Tests\BarTest::testOtherThing',
            ],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_extracts_a_non_namespaced_failure(): void
    {
        $log = "1) FooTest::testSomething\nFailed asserting that false is true.";

        $this->assertSame(
            ['FooTest::testSomething'],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_handles_deeply_nested_namespaces(): void
    {
        $log = "1) Vendor\Package\Sub\Tests\FooTest::testBar\nFailed.";

        $this->assertSame(
            ['Vendor\Package\Sub\Tests\FooTest::testBar'],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_handles_underscores_in_class_and_method_names(): void
    {
        $log = "1) Acme_Tests_Foo_Test::test_something\nFailed.";

        $this->assertSame(
            ['Acme_Tests_Foo_Test::test_something'],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_extracts_class_names_containing_digits(): void
    {
        $log = "1) BrianHenryIE\\Strauss\\Tests\\Issues\\StraussIssue49Test::test_local_symlinked_repositories_fail\nFailed.";

        $this->assertSame(
            ['BrianHenryIE\Strauss\Tests\Issues\StraussIssue49Test::test_local_symlinked_repositories_fail'],
            $this->parser->extractFailedTests($log)
        );
    }

    /**
     * @test
     */
    public function it_does_not_match_lines_without_a_numbered_prefix(): void
    {
        $log = "SomeClass::testMethod failed for other reasons";

        $this->assertSame([], $this->parser->extractFailedTests($log));
    }

    /**
     * @test
     */
    public function it_returns_duplicates_when_the_same_test_appears_twice(): void
    {
        // Deduplication is the caller's responsibility; the parser returns raw matches.
        $log = <<<LOG
        1) Acme\FooTest::testBar
        2) Acme\FooTest::testBar
        LOG;

        $this->assertSame(
            ['Acme\FooTest::testBar', 'Acme\FooTest::testBar'],
            $this->parser->extractFailedTests($log)
        );
    }
}
