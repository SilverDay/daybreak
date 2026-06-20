<?php

declare(strict_types=1);

namespace Daybreak\Service;

interface FetchClient
{
    /**
     * @param list<string> $extraHeaders Additional headers (override defaults; e.g. ['Accept: application/json']).
     * @return array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}
     */
    public function get(string $url, ?string $etag = null, ?string $lastModified = null, array $extraHeaders = []): array;

    /**
     * POST a raw JSON body (e.g. Slack/Discord webhook payloads).
     * SSRF-guarded; does not follow redirects.
     *
     * @param list<string> $extraHeaders
     * @return array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}
     */
    public function postJson(string $url, string $jsonBody, array $extraHeaders = []): array;
}
