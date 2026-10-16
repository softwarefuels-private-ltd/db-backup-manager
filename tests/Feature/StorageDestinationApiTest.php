<?php

namespace Tests\Feature;

use App\Models\StorageDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorageDestinationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_azure_storage_destination_with_account_credentials(): void
    {
        $payload = [
            'name' => 'Work Reports Azure',
            'driver' => 'azure',
            'account_name' => 'filesystemhelp',
            'account_key' => base64_encode('fake-account-key-12345678901234567890'),
            'container' => 'ps-container',
            'prefix' => 'work-reports',
        ];

        $response = $this->postJson('/api/v1/destinations', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Work Reports Azure',
                'driver' => 'azure',
                'container' => 'ps-container',
                'account_name' => 'filesystemhelp',
            ]);

        $this->assertDatabaseHas('storage_destinations', [
            'name' => 'Work Reports Azure',
            'driver' => 'azure',
            'bucket' => 'ps-container',
            'username' => 'filesystemhelp',
            'root' => 'work-reports',
        ]);
    }

    public function test_can_create_azure_storage_destination_with_connection_string(): void
    {
        $fakeKey = base64_encode('fake-account-key-12345678901234567890');
        $connStr = "DefaultEndpointsProtocol=https;AccountName=filesystemhelp;AccountKey={$fakeKey};EndpointSuffix=core.windows.net";

        $payload = [
            'name' => 'Azure Via Connection String',
            'driver' => 'azure',
            'container' => 'ps-container',
            'connection_string' => $connStr,
        ];

        $response = $this->postJson('/api/v1/destinations', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Azure Via Connection String',
                'driver' => 'azure',
                'container' => 'ps-container',
            ]);

        $this->assertDatabaseHas('storage_destinations', [
            'name' => 'Azure Via Connection String',
            'driver' => 'azure',
            'bucket' => 'ps-container',
        ]);
    }

    public function test_validates_required_azure_credentials(): void
    {
        $payload = [
            'name' => 'Missing Azure Auth',
            'driver' => 'azure',
            'container' => 'ps-container',
        ];

        $response = $this->postJson('/api/v1/destinations', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_name']);
    }

    public function test_can_test_azure_storage_destination_permissions(): void
    {
        Http::fake([
            'https://filesystemhelp.blob.core.windows.net/ps-container/.test-dbmanager-*' => function ($request) {
                if ($request->method() === 'PUT') {
                    return Http::response('', 201);
                }
                if ($request->method() === 'HEAD') {
                    return Http::response('', 200);
                }
                if ($request->method() === 'DELETE') {
                    return Http::response('', 202);
                }

                return Http::response('', 200);
            },
        ]);

        $dest = StorageDestination::create([
            'name' => 'Work Reports Azure',
            'driver' => 'azure',
            'username' => 'filesystemhelp',
            'password' => base64_encode('fake-account-key-12345678901234567890'),
            'bucket' => 'ps-container',
            'root' => '',
        ]);

        $response = $this->postJson("/api/v1/destinations/{$dest->id}/test");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_credentials_are_encrypted_in_db_auto_decrypted_in_code_and_hidden_from_json(): void
    {
        $fakeSecretKey = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
        $fakePassword = 'sftp-secret-password-xyz';
        $fakePrivateKey = '-----BEGIN OPENSSH PRIVATE KEY-----fake-key-----END OPENSSH PRIVATE KEY-----';
        $fakeConnStr = 'DefaultEndpointsProtocol=https;AccountName=test;AccountKey=secret;';

        $dest = StorageDestination::create([
            'name' => 'Secure S3 and SFTP',
            'driver' => 's3',
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => $fakeSecretKey,
            'password' => $fakePassword,
            'private_key' => $fakePrivateKey,
            'connection_string' => $fakeConnStr,
            'bucket' => 'my-bucket',
        ]);

        // 1. Raw DB values are encrypted and not plain text
        $raw = DB::table('storage_destinations')->where('id', $dest->id)->first();
        $this->assertNotEquals($fakeSecretKey, $raw->secret_access_key);
        $this->assertStringNotContainsString($fakeSecretKey, $raw->secret_access_key);
        $this->assertNotEquals($fakePassword, $raw->password);
        $this->assertStringNotContainsString($fakePassword, $raw->password);
        $this->assertNotEquals($fakePrivateKey, $raw->private_key);
        $this->assertStringNotContainsString($fakePrivateKey, $raw->private_key);
        $this->assertNotEquals($fakeConnStr, $raw->connection_string);
        $this->assertStringNotContainsString($fakeConnStr, $raw->connection_string);

        // 2. Eloquent model auto-decrypts attributes when accessed by code
        $fresh = StorageDestination::find($dest->id);
        $this->assertEquals($fakeSecretKey, $fresh->secret_access_key);
        $this->assertEquals($fakePassword, $fresh->password);
        $this->assertEquals($fakePrivateKey, $fresh->private_key);
        $this->assertEquals($fakeConnStr, $fresh->connection_string);

        // 3. Hidden from serialization and API responses
        $destArray = $fresh->toArray();
        $this->assertArrayNotHasKey('secret_access_key', $destArray);
        $this->assertArrayNotHasKey('password', $destArray);
        $this->assertArrayNotHasKey('private_key', $destArray);
        $this->assertArrayNotHasKey('connection_string', $destArray);

        $response = $this->getJson("/api/v1/destinations/{$dest->id}");
        $response->assertStatus(200)
            ->assertJsonMissingPath('secret_access_key')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('private_key')
            ->assertJsonMissingPath('connection_string');
    }
}
