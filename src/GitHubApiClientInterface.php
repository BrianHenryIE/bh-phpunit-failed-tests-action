<?php

/**
 * GitHub API client interface.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Contract for GitHub API HTTP calls.
 */
interface GitHubApiClientInterface
{
    /**
     * Make a GET request and return the decoded JSON body.
     *
     * @param string $path API path, e.g. "/repos/owner/repo/actions/...".
     *
     * @return array<mixed>|null Null on error or non-2xx response.
     */
    public function get(string $path): ?array;

    /**
     * Make a GET request and return the raw response body.
     *
     * @param string $path API path, e.g. "/repos/owner/repo/actions/...".
     *
     * @return string|null Null on error or non-2xx response.
     */
    public function getRaw(string $path): ?string;
}
