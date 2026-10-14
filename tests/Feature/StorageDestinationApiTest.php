<?php

namespace Tests\Feature;

use App\Models\StorageDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
