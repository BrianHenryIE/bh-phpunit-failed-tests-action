<?php

/**
 * GitHub Actions environment context.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Reads GitHub Actions environment variables to resolve the current run context.
 */
class ActionContext
{
    /**
     * Return the branch name to search for previous CI failures.
     *
     * On pull_request events GITHUB_HEAD_REF holds the actual PR branch name.
     * On push events GITHUB_REF_NAME holds the branch name.
     * For PRs, GITHUB_REF_NAME is the ephemeral merge ref (e.g. "250/merge")
     * which has no associated workflow run history.
     *
     * @return string Branch name, or "main" if the environment is not set.
     */
    public function getBranch(): string
    {
        $headRef = (string) (getenv('GITHUB_HEAD_REF') ?: '');
        if ($headRef !== '') {
            return $headRef;
        }

        return (string) (getenv('GITHUB_REF_NAME') ?: 'main');
    }

    /**
     * Return the current workflow run ID (GITHUB_RUN_ID).
     *
     * Used to look up the current job and match the same matrix leg in
     * previous runs.
     *
     * @return int Run ID, or 0 if the environment is not set.
     */
    public function getRunId(): int
    {
        return (int) (getenv('GITHUB_RUN_ID') ?: '0');
    }

    /**
     * Return the name of the runner executing the current job (RUNNER_NAME).
     *
     * Concurrent matrix jobs run on distinct runners, so the runner name
     * identifies which job in the current run is "this" one.
     *
     * @return string Runner name, or empty string if not set.
     */
    public function getRunnerName(): string
    {
        return (string) (getenv('RUNNER_NAME') ?: '');
    }

    /**
     * Return the workflow filename derived from GITHUB_WORKFLOW_REF.
     *
     * Extracts the filename from a ref such as:
     * "owner/repo/.github/workflows/main.yml@refs/heads/main"
     *
     * @return string Workflow filename (e.g. "main.yml"), or empty string.
     */
    public function getWorkflowName(): string
    {
        $workflowRef = (string) (getenv('GITHUB_WORKFLOW_REF') ?: '');
        if (preg_match('/\/([^\/]+\.ya?ml)@/', $workflowRef, $m)) {
            return $m[1];
        }

        return '';
    }
}
