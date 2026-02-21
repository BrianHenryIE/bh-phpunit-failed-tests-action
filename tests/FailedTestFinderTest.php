<?php

namespace BrianHenryIE\PHPUnitFailedTestsAction;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FailedTestFinderTest extends TestCase
{
    /**
     * @var MockObject&GitHubApiClientInterface
     */
    private GitHubApiClientInterface $api;
    private FailedTestFinder $finder;

    protected function setUp(): void
    {
        $this->api    = $this->createMock(GitHubApiClientInterface::class);
        $this->finder = new FailedTestFinder($this->api, new LogParser());
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_api_returns_null(): void
    {
        $this->api->method('get')->willReturn(null);

        $this->assertSame([], $this->finder->find('owner/repo', 'main.yml', 'main', 5));
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_there_are_no_failed_runs(): void
    {
        $this->api->method('get')->willReturn(['workflow_runs' => []]);

        $this->assertSame([], $this->finder->find('owner/repo', 'main.yml', 'main', 5));
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_jobs_api_returns_null(): void
    {
        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return null;
            }
        );

        $this->assertSame([], $this->finder->find('owner/repo', 'main.yml', 'main', 5));
    }

    /**
     * @test
     */
    public function it_returns_empty_array_when_no_jobs_failed(): void
    {
        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return ['jobs' => [['id' => 10, 'conclusion' => 'success']]];
            }
        );

        $this->assertSame([], $this->finder->find('owner/repo', 'main.yml', 'main', 5));
    }

    /**
     * @test
     */
    public function it_skips_jobs_whose_log_returns_null(): void
    {
        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return ['jobs' => [['id' => 10, 'conclusion' => 'failure']]];
            }
        );
        $this->api->method('getRaw')->willReturn(null);

        $this->assertSame([], $this->finder->find('owner/repo', 'main.yml', 'main', 5));
    }

    /**
     * @test
     */
    public function it_extracts_failed_tests_from_a_single_job_log(): void
    {
        $log = <<<LOG
        There was 1 failure:

        1) Acme\Tests\FooTest::testSomething
        Failed asserting that false is true.
        LOG;

        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return ['jobs' => [['id' => 10, 'conclusion' => 'failure']]];
            }
        );
        $this->api->method('getRaw')->willReturn($log);

        $this->assertSame(
            ['Acme\Tests\FooTest::testSomething'],
            $this->finder->find('owner/repo', 'main.yml', 'main', 5)
        );
    }

    /**
     * @test
     */
    public function it_deduplicates_the_same_test_across_multiple_runs(): void
    {
        $log = "1) Acme\Tests\FooTest::testSomething\nFailed.";

        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1], ['id' => 2]]];
                }
                return ['jobs' => [['id' => 10, 'conclusion' => 'failure']]];
            }
        );
        $this->api->method('getRaw')->willReturn($log);

        $this->assertSame(
            ['Acme\Tests\FooTest::testSomething'],
            $this->finder->find('owner/repo', 'main.yml', 'main', 5)
        );
    }

    /**
     * @test
     */
    public function it_collects_tests_from_multiple_failed_jobs(): void
    {
        $logA = "1) Acme\Tests\FooTest::testOne\nFailed.";
        $logB = "1) Acme\Tests\BarTest::testTwo\nFailed.";

        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return [
                'jobs' => [
                    ['id' => 10, 'conclusion' => 'failure'],
                    ['id' => 20, 'conclusion' => 'failure'],
                ],
                ];
            }
        );
        $this->api->method('getRaw')->willReturnOnConsecutiveCalls($logA, $logB);

        $result = $this->finder->find('owner/repo', 'main.yml', 'main', 5);

        $this->assertContains('Acme\Tests\FooTest::testOne', $result);
        $this->assertContains('Acme\Tests\BarTest::testTwo', $result);
        $this->assertCount(2, $result);
    }

    /**
     * @test
     */
    public function it_only_fetches_logs_for_failed_jobs(): void
    {
        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return [
                'jobs' => [
                    ['id' => 10, 'conclusion' => 'success'],
                    ['id' => 20, 'conclusion' => 'failure'],
                ],
                ];
            }
        );

        $this->api->expects($this->once())
            ->method('getRaw')
            ->with('/repos/owner/repo/actions/jobs/20/logs')
            ->willReturn("1) Acme\Tests\FooTest::testOne\nFailed.");

        $this->finder->find('owner/repo', 'main.yml', 'main', 5);
    }

    /**
     * @test
     */
    public function it_returns_results_in_sorted_order(): void
    {
        $log = <<<LOG
        1) Acme\Tests\ZTest::testLast
        2) Acme\Tests\ATest::testFirst
        LOG;

        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return ['jobs' => [['id' => 10, 'conclusion' => 'failure']]];
            }
        );
        $this->api->method('getRaw')->willReturn($log);

        $result = $this->finder->find('owner/repo', 'main.yml', 'main', 5);

        $this->assertSame(['Acme\Tests\ATest::testFirst', 'Acme\Tests\ZTest::testLast'], $result);
    }

    /**
     * @test
     */
    public function it_passes_the_correct_path_to_the_runs_api(): void
    {
        $this->api->expects($this->once())
            ->method('get')
            ->with('/repos/owner/repo/actions/workflows/ci.yml/runs?status=failure&branch=develop&per_page=3')
            ->willReturn(['workflow_runs' => []]);

        $this->finder->find('owner/repo', 'ci.yml', 'develop', 3);
    }
}
