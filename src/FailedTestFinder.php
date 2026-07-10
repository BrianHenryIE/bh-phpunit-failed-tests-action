<?php

/**
 * Previously failed test finder.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Queries the GitHub Actions API to find tests that failed in recent runs.
 */
class FailedTestFinder
{
    /**
     * GitHub API client.
     */
    private GitHubApiClientInterface $api;

    /**
     * PHPUnit log parser.
     */
    private LogParser $parser;

    /**
     * Constructor.
     *
     * @param GitHubApiClientInterface $api    GitHub API client.
     * @param LogParser                $parser PHPUnit log parser.
     */
    public function __construct(GitHubApiClientInterface $api, LogParser $parser)
    {
        $this->api    = $api;
        $this->parser = $parser;
    }

    /**
     * Resolve the name of the job currently running this action.
     *
     * Lists the current run's jobs and matches the one executing on this
     * runner. Concurrent matrix legs run on distinct runners, so the runner
     * name uniquely identifies "this" job within the run. The returned name
     * (e.g. "test (8.4)") is used to collect failures only from the matching
     * matrix leg in previous runs.
     *
     * @param string $repo       GitHub repository slug (e.g. "owner/repo").
     * @param int    $runId      Current workflow run ID (GITHUB_RUN_ID).
     * @param string $runnerName Current runner name (RUNNER_NAME).
     *
     * @return string The current job name, or empty string if it cannot be
     *                resolved unambiguously.
     */
    public function getCurrentJobName(
        string $repo,
        int $runId,
        string $runnerName
    ): string {
        if ($repo === '' || $runId <= 0 || $runnerName === '') {
            return '';
        }

        $jobsPath = '/repos/' . $repo . '/actions/runs/'
            . $runId . '/jobs?per_page=100';

        $jobs = $this->api->get($jobsPath);

        $jobsList = is_array($jobs) ? ($jobs['jobs'] ?? null) : null;
        if (!is_array($jobsList)) {
            return '';
        }

        $matches = [];
        foreach ($jobsList as $job) {
            if (!is_array($job)) {
                continue;
            }

            $name = $job['name'] ?? null;
            if (
                ($job['runner_name'] ?? null) === $runnerName
                && ($job['status'] ?? null) === 'in_progress'
                && is_string($name)
            ) {
                $matches[] = $name;
            }
        }

        // Only filter when exactly one job matches, to avoid collecting the
        // wrong leg (or nothing) if the runner name is ambiguous.
        return count($matches) === 1 ? $matches[0] : '';
    }

    /**
     * Find unique failed test names across recent failed workflow runs.
     *
     * @param string $repo     GitHub repository slug (e.g. "owner/repo").
     * @param string $workflow Workflow filename (e.g. "main.yml").
     * @param string $branch   Branch name to filter runs.
     * @param int    $maxRuns  Maximum number of recent runs to inspect.
     * @param string $jobName  When non-empty, only collect failures from jobs
     *                         whose name matches (e.g. the current matrix leg).
     *
     * @return string[] Unique "Namespace\Class::method" test names, sorted.
     */
    public function find(
        string $repo,
        string $workflow,
        string $branch,
        int $maxRuns,
        string $jobName = ''
    ): array {
        $runsPath = '/repos/' . $repo . '/actions/workflows/' . $workflow
            . '/runs?status=failure&branch=' . $branch . '&per_page=' . $maxRuns;

        $data = $this->api->get($runsPath);

        if (!$data) {
            return [];
        }

        $workflowRuns = $data['workflow_runs'] ?? null;
        if (!is_array($workflowRuns)) {
            return [];
        }

        $failedTests = [];

        foreach ($workflowRuns as $run) {
            if (!is_array($run)) {
                continue;
            }

            $runId = $run['id'] ?? null;
            if (!is_int($runId)) {
                continue;
            }

            $jobsPath = '/repos/' . $repo . '/actions/runs/'
                . $runId . '/jobs?filter=latest&per_page=100';

            $jobs = $this->api->get($jobsPath);

            if (!$jobs) {
                continue;
            }

            $jobsList = $jobs['jobs'] ?? null;
            if (!is_array($jobsList)) {
                continue;
            }

            foreach ($jobsList as $job) {
                if (!is_array($job)) {
                    continue;
                }

                $jobId      = $job['id'] ?? null;
                $conclusion = $job['conclusion'] ?? null;

                if (!is_int($jobId) || $conclusion !== 'failure') {
                    continue;
                }

                // Restrict to the current matrix leg so, e.g., a PHP 7.4
                // failure is not collected during a PHP 8.4 run.
                if ($jobName !== '' && ($job['name'] ?? null) !== $jobName) {
                    continue;
                }

                $logPath = '/repos/' . $repo
                    . '/actions/jobs/' . $jobId . '/logs';

                $log = $this->api->getRaw($logPath);

                if ($log === null) {
                    continue;
                }

                $tests       = $this->parser->extractFailedTests($log);
                $failedTests = array_merge($failedTests, $tests);
            }
        }

        $unique = array_values(array_unique($failedTests));
        sort($unique);

        return $unique;
    }
}
