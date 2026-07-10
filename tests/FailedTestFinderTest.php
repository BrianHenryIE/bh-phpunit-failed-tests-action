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

    /**
     * @test
     */
    public function it_only_collects_failures_from_the_matching_job_name(): void
    {
        $this->api->method('get')->willReturnCallback(
            function (string $path) {
                if (strpos($path, '/actions/workflows/') !== false) {
                    return ['workflow_runs' => [['id' => 1]]];
                }
                return [
                'jobs' => [
                    ['id' => 10, 'conclusion' => 'failure', 'name' => 'test (7.4)'],
                    ['id' => 20, 'conclusion' => 'failure', 'name' => 'test (8.4)'],
                ],
                ];
            }
        );

        // Only the matching job's log should be fetched.
        $this->api->expects($this->once())
            ->method('getRaw')
            ->with('/repos/owner/repo/actions/jobs/20/logs')
            ->willReturn("1) Acme\Tests\BarTest::testUnderEightFour\nFailed.");

        $this->assertSame(
            ['Acme\Tests\BarTest::testUnderEightFour'],
            $this->finder->find('owner/repo', 'main.yml', 'main', 5, 'test (8.4)')
        );
    }

    /**
     * @test
     */
    public function it_resolves_the_current_job_name_by_runner(): void
    {
        $this->api->expects($this->once())
            ->method('get')
            ->with('/repos/owner/repo/actions/runs/99/jobs?per_page=100')
            ->willReturn(
                [
                'jobs' => [
                    ['name' => 'test (7.4)', 'runner_name' => 'Runner A', 'status' => 'in_progress'],
                    ['name' => 'test (8.4)', 'runner_name' => 'Runner B', 'status' => 'in_progress'],
                ],
                ]
            );

        $this->assertSame('test (8.4)', $this->finder->getCurrentJobName('owner/repo', 99, 'Runner B'));
    }

    /**
     * @test
     */
    public function it_returns_empty_job_name_when_the_runner_does_not_match(): void
    {
        $this->api->method('get')->willReturn(
            [
            'jobs' => [
                ['name' => 'test (7.4)', 'runner_name' => 'Runner A', 'status' => 'in_progress'],
            ],
            ]
        );

        $this->assertSame('', $this->finder->getCurrentJobName('owner/repo', 99, 'Runner Z'));
    }

    /**
     * @test
     */
    public function it_does_not_call_the_api_without_a_repo_run_id_or_runner_name(): void
    {
        $this->api->expects($this->never())->method('get');

        $this->assertSame('', $this->finder->getCurrentJobName('owner/repo', 0, 'Runner B'));
        $this->assertSame('', $this->finder->getCurrentJobName('owner/repo', 99, ''));
        $this->assertSame('', $this->finder->getCurrentJobName('', 99, 'Runner B'));
    }
}
