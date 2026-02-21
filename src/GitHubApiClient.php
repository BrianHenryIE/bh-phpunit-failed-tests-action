<?php

/**
 * GitHub API client implementation.
 *
 * @package brianhenryie/bh-phpunit-failed-tests-action
 */

namespace BrianHenryIE\PHPUnitFailedTestsAction;

/**
 * Sends authenticated requests to the GitHub REST API via curl.
 */
class GitHubApiClient implements GitHubApiClientInterface
{
    /**
     * GitHub personal access token or Actions token.
     */
    private string $token;

    /**
     * Base URL for all API requests.
     */
    private string $baseUrl;

    /**
     * Constructor.
     *
     * @param string $token   GitHub token for API access.
     * @param string $baseUrl Base API URL (overridable for tests).
     */
    public function __construct(
        string $token,
        string $baseUrl = 'https://api.github.com'
    ) {
        $this->token   = $token;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Make a GET request and return the decoded JSON body.
     *
     * @param string $path API path.
     *
     * @return array<mixed>|null Null on error or non-2xx response.
     */
    public function get(string $path): ?array
    {
        $body = $this->request($path);
        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Make a GET request and return the raw response body.
     *
     * @param string $path API path.
     *
     * @return string|null Null on error or non-2xx response.
     */
    public function getRaw(string $path): ?string
    {
        return $this->request($path);
    }

    /**
     * Execute an HTTP GET request and return the response body.
     *
     * @param string $path API path to append to the base URL.
     *
     * @return string|null Null on curl error or non-2xx status.
     */
    private function request(string $path): ?string
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->token,
                    'Accept: application/vnd.github+json',
                    'X-GitHub-Api-Version: 2022-11-28',
                    'User-Agent: bh-phpunit-failed-tests-action',
                ],
            ]
        );

        $body       = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $statusCode >= 400) {
            return null;
        }

        return $body;
    }
}
