<?php

class FailedTestFinder
{
    public function __construct(
        private GitHubApiClientInterface $api,
        private LogParser $parser
    ) {
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

        if (!$data || empty($data['workflow_runs'])) {
            return [];
        }

        $failedTests = [];

        foreach ($data['workflow_runs'] as $run) {
            $runId = $run['id'];

            $jobs = $this->api->get("/repos/$repo/actions/runs/$runId/jobs?filter=latest&per_page=100");

            if (!$jobs || empty($jobs['jobs'])) {
                continue;
            }

            foreach ($jobs['jobs'] as $job) {
                if ($job['conclusion'] !== 'failure') {
                    continue;
                }

                $log = $this->api->getRaw("/repos/$repo/actions/jobs/{$job['id']}/logs");

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
