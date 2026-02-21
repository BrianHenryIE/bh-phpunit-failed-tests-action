<?php

interface GitHubApiClientInterface
{
    /**
     * Make a GET request to the GitHub API and return the decoded JSON body.
     *
     * @return array<mixed>|null Null on error or non-2xx response.
     */
    public function get(string $path): ?array;

    /**
     * Make a GET request to the GitHub API and return the raw response body.
     * Used for log endpoints that return plain text.
     *
     * @return string|null Null on error or non-2xx response.
     */
    public function getRaw(string $path): ?string;
}
