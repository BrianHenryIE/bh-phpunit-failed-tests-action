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
     * Find unique failed test names across recent failed workflow runs.
     *
     * @param string $repo     GitHub repository slug (e.g. "owner/repo").
     * @param string $workflow Workflow filename (e.g. "main.yml").
     * @param string $branch   Branch name to filter runs.
     * @param int    $maxRuns  Maximum number of recent runs to inspect.
     *
     * @return string[] Unique "Namespace\Class::method" test names, sorted.
     */
    public function find(
        string $repo,
        string $workflow,
        string $branch,
        int $maxRuns
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
