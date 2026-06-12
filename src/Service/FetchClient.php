<?php

declare(strict_types=1);

namespace Daybreak\Service;

interface FetchClient
{
    /**
     * @return array{status:int,body:string,etag:?string,last_modified:?string,not_modified:bool}
     */
    public function get(string $url, ?string $etag = null, ?string $lastModified = null): array;
}
