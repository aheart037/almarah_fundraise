<?php

declare(strict_types=1);

namespace App\Payments\Http;

interface HttpClientInterface
{
    /**
     * @param array<string,string|int> $query
     * @param array<string,mixed>      $options  timeout, connect_timeout, headers, method override
     */
    public function get(string $url, array $query = [], array $options = []): HttpResult;

    /** @param array<string,mixed> $payload */
    public function postJson(string $url, array $payload, array $options = []): HttpResult;

    /** @param array<string,string|int> $fields */
    public function postForm(string $url, array $fields, array $options = []): HttpResult;
}
