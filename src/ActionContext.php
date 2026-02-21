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
