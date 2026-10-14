<?php

namespace App\Services\Storage;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use SimpleXMLElement;

class AzureBlobStorageAdapter implements FilesystemAdapter
{
    protected string $accountName;

    protected string $accountKey;

    protected string $container;

    protected string $prefix;

    protected string $baseUrl;

    public function __construct(
        ?string $accountName = null,
        ?string $accountKey = null,
        string $container = '',
        string $prefix = '',
        ?string $endpoint = null,
        ?string $connectionString = null
    ) {
        if (! empty($connectionString)) {
            $parsed = $this->parseConnectionString($connectionString);
            $accountName = $accountName ?: ($parsed['AccountName'] ?? null);
            $accountKey = $accountKey ?: ($parsed['AccountKey'] ?? null);

            if (! empty($parsed['BlobEndpoint'])) {
                $endpoint = $endpoint ?: $parsed['BlobEndpoint'];
            } elseif (! empty($parsed['DefaultEndpointsProtocol']) && ! empty($parsed['EndpointSuffix'])) {
                $endpoint = $endpoint ?: "{$parsed['DefaultEndpointsProtocol']}://{$accountName}.blob.{$parsed['EndpointSuffix']}";
            }
        }

        $this->accountName = (string) $accountName;
        $this->accountKey = (string) $accountKey;
        $this->container = trim($container, '/');
        $this->prefix = trim($prefix, '/');

        if (! empty($endpoint)) {
            $this->baseUrl = rtrim($endpoint, '/');
        } else {
            $this->baseUrl = "https://{$this->accountName}.blob.core.windows.net";
        }
    }

    /**
     * Parse an Azure connection string into key-value pairs.
     *
     * @return array<string, string>
     */
    protected function parseConnectionString(string $connectionString): array
    {
        $data = [];
        $parts = explode(';', $connectionString);
        foreach ($parts as $part) {
            $pos = strpos($part, '=');
            if ($pos !== false) {
                $key = trim(substr($part, 0, $pos));
                $val = trim(substr($part, $pos + 1));
                if ($key !== '') {
                    $data[$key] = $val;
                }
            }
        }

        return $data;
    }

    /**
     * Apply the destination prefix to a given path.
     */
    protected function applyPrefix(string $path): string
    {
        $clean = ltrim($path, '/');
        if ($clean === '' || $clean === '.') {
            return $this->prefix;
        }

        return $this->prefix !== '' ? "{$this->prefix}/{$clean}" : $clean;
    }

    /**
     * Strip prefix from a given path.
     */
    protected function stripPrefix(string $path): string
    {
        if ($this->prefix === '') {
            return ltrim($path, '/');
        }

        $prefixWithSlash = "{$this->prefix}/";
        if (str_starts_with($path, $prefixWithSlash)) {
            return substr($path, strlen($prefixWithSlash));
        }

        return ltrim($path, '/');
    }

    /**
     * Build the full URL for a blob.
     */
    protected function buildBlobUrl(string $path): string
    {
        $blobPath = $this->applyPrefix($path);
        if ($blobPath === '') {
            return "{$this->baseUrl}/{$this->container}";
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $blobPath)));

        return "{$this->baseUrl}/{$this->container}/{$encodedPath}";
    }

    /**
     * Sign an HTTP request using Azure SharedKey authorization.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParams
     * @return array<string, string>
     */
    protected function signRequest(string $method, string $path, array $headers = [], array $queryParams = [], ?string $body = null): array
    {
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $headers['x-ms-date'] = $date;
        $headers['x-ms-version'] = '2020-10-02';

        $msHeaders = [];
        foreach ($headers as $k => $v) {
            $lower = strtolower($k);
            if (str_starts_with($lower, 'x-ms-')) {
                $msHeaders[$lower] = trim((string) $v);
            }
        }
        ksort($msHeaders);

        $canonicalizedHeaders = '';
        foreach ($msHeaders as $k => $v) {
            $canonicalizedHeaders .= "{$k}:{$v}\n";
        }

        $resource = "/{$this->accountName}/{$this->container}";
        $blobPath = $this->applyPrefix($path);
        if ($blobPath !== '') {
            $resource .= '/'.ltrim($blobPath, '/');
        }

        if (! empty($queryParams)) {
            ksort($queryParams);
            foreach ($queryParams as $k => $v) {
                $resource .= "\n".strtolower($k).':'.$v;
            }
        }

        $contentLength = '';
        if ($body !== null && in_array(strtoupper($method), ['PUT', 'POST'])) {
            $contentLength = (string) strlen($body);
        } elseif (isset($headers['Content-Length'])) {
            $contentLength = (string) $headers['Content-Length'];
        }

        $contentType = $headers['Content-Type'] ?? '';
        $contentMd5 = $headers['Content-MD5'] ?? '';
        $contentEncoding = $headers['Content-Encoding'] ?? '';
        $contentLanguage = $headers['Content-Language'] ?? '';

        $stringToSign = implode("\n", [
            strtoupper($method),
            $contentEncoding,
            $contentLanguage,
            $contentLength,
            $contentMd5,
            $contentType,
            '', // Date
            '', // If-Modified-Since
            '', // If-Match
            '', // If-None-Match
            '', // If-Unmodified-Since
            '', // Range
            $canonicalizedHeaders.$resource,
        ]);

        if (empty($this->accountKey)) {
            throw new InvalidArgumentException('Azure Storage Account Key is missing or empty.');
        }

        $decodedKey = base64_decode($this->accountKey, true);
        if ($decodedKey === false) {
            throw new InvalidArgumentException('Invalid base64 Azure Storage Account Key.');
        }

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));
        $headers['Authorization'] = "SharedKey {$this->accountName}:{$signature}";

        return $headers;
    }

    /**
     * Send signed HTTP request to Azure Blob Storage REST API.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParams
     */
    protected function send(string $method, string $path, array $headers = [], array $queryParams = [], ?string $body = null): Response
    {
        $headers = $this->signRequest($method, $path, $headers, $queryParams, $body);
        $url = $this->buildBlobUrl($path);
        if (! empty($queryParams)) {
            $url .= '?'.http_build_query($queryParams);
        }

        $httpRequest = Http::withHeaders($headers);
        if ($body !== null) {
            $httpRequest = $httpRequest->withBody($body, $headers['Content-Type'] ?? 'application/octet-stream');
        }

        return match (strtoupper($method)) {
            'GET' => $httpRequest->get($url),
            'PUT' => $httpRequest->put($url),
            'HEAD' => $httpRequest->head($url),
            'DELETE' => $httpRequest->delete($url),
            'POST' => $httpRequest->post($url),
            default => throw new InvalidArgumentException("Unsupported HTTP method [{$method}]"),
        };
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = $this->send('HEAD', $path);

            if ($response->successful()) {
                return true;
            }

            if ($response->status() === 404) {
                return false;
            }

            throw new Exception("Azure Blob returned status {$response->status()}");
        } catch (Exception $e) {
            throw UnableToCheckExistence::forLocation($path, $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        $clean = trim($path, '/');
        if ($clean === '' || $clean === '.') {
            return true;
        }

        $prefix = rtrim($this->applyPrefix($clean), '/').'/';
        $blobs = iterator_to_array($this->listBlobs($prefix, 1));

        return count($blobs) > 0;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $headers = [
            'x-ms-blob-type' => 'BlockBlob',
            'Content-Type' => (string) $config->get('ContentType', 'application/octet-stream'),
        ];

        $response = $this->send('PUT', $path, $headers, [], $contents);

        if (! in_array($response->status(), [200, 201])) {
            throw UnableToWriteFile::atLocation($path, "Azure error ({$response->status()}): {$response->body()}");
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $data = stream_get_contents($contents);
        $this->write($path, $data !== false ? $data : '', $config);
    }

    public function read(string $path): string
    {
        $response = $this->send('GET', $path);

        if ($response->status() === 404) {
            throw UnableToReadFile::fromLocation($path, 'File not found on Azure Blob Storage.');
        }

        if (! $response->successful()) {
            throw UnableToReadFile::fromLocation($path, "Azure error ({$response->status()}): {$response->body()}");
        }

        return $response->body();
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Failed to open temp stream.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $response = $this->send('DELETE', $path);

        if (! in_array($response->status(), [200, 202, 404])) {
            throw UnableToDeleteFile::atLocation($path, "Azure error ({$response->status()}): {$response->body()}");
        }
    }

    public function deleteDirectory(string $path): void
    {
        $clean = trim($path, '/');
        $prefix = $clean !== '' && $clean !== '.' ? rtrim($this->applyPrefix($clean), '/').'/' : $this->prefix;

        foreach ($this->listBlobs($prefix) as $item) {
            if ($item instanceof FileAttributes) {
                $this->delete($this->stripPrefix($item->path()));
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Azure Blob Storage uses a flat hierarchy; directory creation is implicit.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Azure Blob Storage visibility is managed at the container level.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: Visibility::PRIVATE);
    }

    public function mimeType(string $path): FileAttributes
    {
        $response = $this->send('HEAD', $path);

        return new FileAttributes($path, mimeType: $response->header('Content-Type'));
    }

    public function lastModified(string $path): FileAttributes
    {
        $response = $this->send('HEAD', $path);
        $time = strtotime($response->header('Last-Modified') ?: 'now');

        return new FileAttributes($path, lastModified: $time ?: null);
    }

    public function fileSize(string $path): FileAttributes
    {
        $response = $this->send('HEAD', $path);

        return new FileAttributes($path, fileSize: (int) $response->header('Content-Length'));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $clean = trim($path, '/');
        $prefix = $clean !== '' && $clean !== '.' ? rtrim($this->applyPrefix($clean), '/').'/' : $this->prefix;

        foreach ($this->listBlobs($prefix) as $item) {
            if ($item instanceof FileAttributes) {
                $relativePath = $this->stripPrefix($item->path());
                if (! $deep && str_contains($relativePath, '/')) {
                    continue;
                }
                yield new FileAttributes(
                    $relativePath,
                    $item->fileSize(),
                    $item->visibility(),
                    $item->lastModified(),
                    $item->mimeType()
                );
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $contents = $this->read($source);
        $this->write($destination, $contents, $config);
    }

    /**
     * List blobs from Azure container with optional prefix and maxresults limit.
     *
     * @return iterable<StorageAttributes>
     */
    protected function listBlobs(string $prefix = '', ?int $maxResults = null): iterable
    {
        $queryParams = [
            'comp' => 'list',
            'restype' => 'container',
        ];

        if ($prefix !== '') {
            $queryParams['prefix'] = $prefix;
        }

        if ($maxResults !== null) {
            $queryParams['maxresults'] = (string) $maxResults;
        }

        $response = $this->send('GET', '', [], $queryParams);

        if (! $response->successful()) {
            return;
        }

        $xml = @simplexml_load_string($response->body());
        if (! $xml instanceof SimpleXMLElement || ! isset($xml->Blobs->Blob)) {
            return;
        }

        foreach ($xml->Blobs->Blob as $blob) {
            $name = (string) $blob->Name;
            $size = isset($blob->Properties->{'Content-Length'}) ? (int) $blob->Properties->{'Content-Length'} : null;
            $lastModified = isset($blob->Properties->{'Last-Modified'}) ? strtotime((string) $blob->Properties->{'Last-Modified'}) : null;
            $mimeType = isset($blob->Properties->{'Content-Type'}) ? (string) $blob->Properties->{'Content-Type'} : null;

            yield new FileAttributes($name, $size, Visibility::PRIVATE, $lastModified ?: null, $mimeType);
        }
    }
}
