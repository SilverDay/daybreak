<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Adapter\JsonApiAdapter;
use Daybreak\Adapter\NormalizedItem;
use Daybreak\Adapter\NvdAdapter;
use Daybreak\Adapter\RansomlookAdapter;
use Daybreak\Adapter\RssAtomAdapter;
use Daybreak\Adapter\SourceAdapter;
use Throwable;

/**
 * Executes a safe, read-only preview fetch for source onboarding.
 */
final class SourcePreviewService
{
    private const SAMPLE_LIMIT = 5;

    /** @var SourceAdapter[] */
    private array $adapters;

    public function __construct(private readonly FetchClient $fetcher)
    {
        $this->adapters = [new RssAtomAdapter(), new RansomlookAdapter(), new NvdAdapter(), new JsonApiAdapter()];
    }

    /**
     * @return array{
     *   ok:bool,
     *   error:?string,
     *   errors:string[],
     *   warnings:string[],
     *   http_status:int,
     *   not_modified:bool,
     *   items_count:int,
     *   sample_items:NormalizedItem[]
     * }
     */
    public function preview(array $source): array
    {
        $adapterType = (string) ($source['adapter_type'] ?? '');
        $adapter = $this->adapterFor($adapterType);
        if ($adapter === null) {
            return [
                'ok' => false,
                'error' => 'Unsupported adapter type selected.',
                'errors' => ['Unsupported adapter type selected.'],
                'warnings' => [],
                'http_status' => 0,
                'not_modified' => false,
                'items_count' => 0,
                'sample_items' => [],
            ];
        }

        try {
            $warnings = [];
            $errors = [];

            if ($adapter instanceof JsonApiAdapter) {
                $preview = $adapter->preview($source, $this->fetcher);
                $result = $preview['result'];
                $warnings = $preview['warnings'];
                $errors = $preview['errors'];
            } else {
                $result = $adapter->fetch($source, $this->fetcher);
            }

            if (!$result->notModified && ($result->httpStatus === 202 || ($result->httpStatus >= 400 && $result->httpStatus !== 304))) {
                $errors[] = 'Preview fetch failed with HTTP ' . $result->httpStatus . '.';
            }

            return [
                'ok' => $errors === [],
                'error' => $errors[0] ?? null,
                'errors' => $errors,
                'warnings' => $warnings,
                'http_status' => $result->httpStatus,
                'not_modified' => $result->notModified,
                'items_count' => count($result->items),
                'sample_items' => array_slice($result->items, 0, self::SAMPLE_LIMIT),
            ];
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'error' => 'Preview failed: ' . mb_substr($throwable->getMessage(), 0, 200),
                'errors' => ['Preview failed: ' . mb_substr($throwable->getMessage(), 0, 200)],
                'warnings' => [],
                'http_status' => 0,
                'not_modified' => false,
                'items_count' => 0,
                'sample_items' => [],
            ];
        }
    }

    private function adapterFor(string $type): ?SourceAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($type)) {
                return $adapter;
            }
        }

        return null;
    }
}
