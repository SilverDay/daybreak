<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\FetchClient;
use RuntimeException;

final class FakeFetchClient implements FetchClient
{
    /** @var array<string,array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}> */
    private array $responses;

    /** @var list<array{url:string,etag:?string,lastModified:?string}> */
    public array $calls = [];

    /** @param array<string,array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function get(string $url, ?string $etag = null, ?string $lastModified = null): array
    {
        $this->calls[] = ['url' => $url, 'etag' => $etag, 'lastModified' => $lastModified];

        if (!array_key_exists($url, $this->responses)) {
            throw new RuntimeException('No fake response configured for ' . $url);
        }

        return $this->responses[$url];
    }
}
