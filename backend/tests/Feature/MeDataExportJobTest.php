<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeDataExportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_cannot_create_export_job(): void
    {
        $this->postJson('/api/me/export/jobs', [
            'current_password' => 'anything',
        ])->assertStatus(401);
    }

    public function test_export_job_requires_valid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/export/jobs', [
            'current_password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'Aktualne heslo nie je spravne.');
    }

    public function test_export_job_can_be_created_polled_and_downloaded(): void
    {
        config(['queue.default' => 'sync']);

        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);
        Post::factory()->for($user)->create([
            'content' => 'Export async post',
        ]);

        Sanctum::actingAs($user);

        $createResponse = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.1.11'])
            ->postJson('/api/me/export/jobs', [
                'current_password' => 'secret-password',
            ])
            ->assertStatus(202)
            ->assertJsonStructure([
                'id',
                'status',
                'file_name',
                'download_url',
            ]);

        $jobId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $jobId);

        $statusResponse = $this->getJson('/api/me/export/jobs/' . $jobId)
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'status',
                'file_name',
                'download_url',
                'checksum_sha256',
            ]);

        $this->assertSame('ready', (string) $statusResponse->json('status'));
        $this->assertStringEndsWith('.zip', (string) $statusResponse->json('file_name'));
        $downloadUrl = (string) $statusResponse->json('download_url');
        $this->assertNotSame('', $downloadUrl);

        $downloadResponse = $this->get($downloadUrl)
            ->assertOk();

        $this->assertStringContainsString(
            'attachment; filename=',
            (string) $downloadResponse->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString(
            'application/zip',
            (string) $downloadResponse->headers->get('Content-Type')
        );
    }

    public function test_export_job_status_is_private_to_owner(): void
    {
        config(['queue.default' => 'sync']);

        $owner = User::factory()->create([
            'password' => bcrypt('owner-password'),
        ]);
        $other = User::factory()->create();

        Sanctum::actingAs($owner);
        $createResponse = $this->postJson('/api/me/export/jobs', [
            'current_password' => 'owner-password',
        ])->assertStatus(202);

        $jobId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $jobId);

        Sanctum::actingAs($other);
        $this->getJson('/api/me/export/jobs/' . $jobId)->assertStatus(404);
    }

    public function test_export_job_download_requires_signed_url(): void
    {
        config(['queue.default' => 'sync']);

        $user = User::factory()->create([
            'password' => bcrypt('signed-password'),
        ]);

        Sanctum::actingAs($user);
        $createResponse = $this->postJson('/api/me/export/jobs', [
            'current_password' => 'signed-password',
        ])->assertStatus(202);

        $jobId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $jobId);

        $this->get('/api/me/export/jobs/' . $jobId . '/download')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Download link je neplatny alebo expirovany.');
    }
}
