<?php

class FailedTestFinder
{
    private GitHubApiClientInterface $api;
    private LogParser $parser;

    public function __construct(GitHubApiClientInterface $api, LogParser $parser)
    {
        $this->api    = $api;
        $this->parser = $parser;
    }

    /**
     * Find unique failed test names across recent failed workflow runs.
     *
     * @return string[] Unique "Namespace\Class::method" test names, sorted.
     */
    public function find(string $repo, string $workflow, string $branch, int $maxRuns): array
    {
        $data = $this->api->get(
            "/repos/$repo/actions/workflows/$workflow/runs?status=failure&branch=$branch&per_page=$maxRuns"
        );

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

            $jobs = $this->api->get("/repos/$repo/actions/runs/$runId/jobs?filter=latest&per_page=100");

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

                $jobId     = $job['id'] ?? null;
                $conclusion = $job['conclusion'] ?? null;

                if (!is_int($jobId) || $conclusion !== 'failure') {
                    continue;
                }

                $log = $this->api->getRaw("/repos/$repo/actions/jobs/$jobId/logs");

                if ($log === null) {
                    continue;
                }

                $tests = $this->parser->extractFailedTests($log);
                $failedTests = array_merge($failedTests, $tests);
            }
        }

        $unique = array_values(array_unique($failedTests));
        sort($unique);

        return $unique;
    }
}
