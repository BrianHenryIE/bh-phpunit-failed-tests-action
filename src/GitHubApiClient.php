<?php

class GitHubApiClient implements GitHubApiClientInterface
{
    private string $baseUrl;

    public function __construct(
        private string $token,
        string $baseUrl = 'https://api.github.com'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function get(string $path): ?array
    {
        $body = $this->request($path);
        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getRaw(string $path): ?string
    {
        return $this->request($path);
    }

    private function request(string $path): ?string
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: bh-phpunit-failed-tests-action',
            ],
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $statusCode >= 400) {
            return null;
        }

        return $body;
    }
}
