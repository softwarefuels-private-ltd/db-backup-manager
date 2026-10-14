<?php

namespace Tests\Unit;

use App\Models\StorageDestination;
use App\Services\DynamicStorageFactory;
use App\Services\Storage\AzureBlobStorageAdapter;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use Tests\TestCase;

class AzureBlobStorageAdapterTest extends TestCase
{
    protected string $accountKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountKey = base64_encode('fake-account-key-12345678901234567890');
    }

    public function test_can_write_blob_to_azure(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/test-folder/doc.txt' => function ($request) {
                $this->assertSame('PUT', $request->method());
                $this->assertSame('BlockBlob', $request->header('x-ms-blob-type')[0]);
                $this->assertStringContainsString('SharedKey filesystemhelp:', $request->header('Authorization')[0]);
                $this->assertSame('Sample Content', $request->body());

                return Http::response('', 201);
            },
        ]);

        $adapter = new AzureBlobStorageAdapter(
            accountName: 'filesystemhelp',
            accountKey: $this->accountKey,
            container: 'ps-container',
            prefix: 'test-folder'
        );

        $adapter->write('doc.txt', 'Sample Content', new Config);
        Http::assertSentCount(1);
    }

    public function test_can_read_blob_from_azure(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/doc.txt' => Http::response('Hello Azure Blob', 200),
        ]);

        $adapter = new AzureBlobStorageAdapter(
            accountName: 'filesystemhelp',
            accountKey: $this->accountKey,
            container: 'ps-container'
        );

        $content = $adapter->read('doc.txt');
        $this->assertSame('Hello Azure Blob', $content);
    }

    public function test_file_exists_returns_true_on_200_and_false_on_404(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/exists.txt' => Http::response('', 200),
            'https://filesystemhelp.blob.core.windows.net/ps-container/missing.txt' => Http::response('', 404),
        ]);

        $adapter = new AzureBlobStorageAdapter(
            accountName: 'filesystemhelp',
            accountKey: $this->accountKey,
            container: 'ps-container'
        );

        $this->assertTrue($adapter->fileExists('exists.txt'));
        $this->assertFalse($adapter->fileExists('missing.txt'));
    }

    public function test_can_delete_blob_from_azure(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/to-delete.txt' => Http::response('', 202),
        ]);

        $adapter = new AzureBlobStorageAdapter(
            accountName: 'filesystemhelp',
            accountKey: $this->accountKey,
            container: 'ps-container'
        );

        $adapter->delete('to-delete.txt');
        Http::assertSentCount(1);
    }

    public function test_dynamic_storage_factory_creates_usable_azure_disk(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/backups/test.sql.gz' => Http::response('', 201),
            'https://filesystemhelp.blob.core.windows.net/ps-container/backups/read.sql.gz' => Http::response('fake compressed sql dump', 200),
        ]);

        $dest = new StorageDestination([
            'name' => 'Azure Backups',
            'driver' => 'azure',
            'username' => 'filesystemhelp',
            'password' => $this->accountKey,
            'bucket' => 'ps-container',
            'root' => 'backups',
        ]);

        $disk = DynamicStorageFactory::make($dest);

        $disk->put('test.sql.gz', 'backup payload');
        $content = $disk->get('read.sql.gz');

        $this->assertSame('fake compressed sql dump', $content);
    }
}
