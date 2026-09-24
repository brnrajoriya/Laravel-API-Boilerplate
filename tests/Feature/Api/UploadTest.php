<?php

namespace Tests\Feature\Api;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_upload_an_image(): void
    {
        $response = $this->postJson('/api/v1/uploads', ['file' => $this->png('photo.png')])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.original_name', 'photo.png')
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonMissingPath('data.path')
            ->assertJsonStructure(['data' => ['id', 'original_name', 'mime_type', 'size', 'url', 'created_at']]);

        $upload = Upload::findOrFail($response->json('data.id'));

        Storage::disk('public')->assertExists($upload->path);
        $this->assertStringNotContainsString('photo', $upload->path); // random stored name
        $this->assertSame($this->user->id, $upload->user_id);
    }

    public function test_rejects_disallowed_types_and_large_files(): void
    {
        $this->postJson('/api/v1/uploads', ['file' => UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['file']]);

        $this->postJson('/api/v1/uploads', ['file' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')])
            ->assertUnprocessable();

        $tooBig = $this->png('huge.png')->size(config('api.uploads.max_kb') + 1);
        $this->postJson('/api/v1/uploads', ['file' => $tooBig])->assertUnprocessable();

        $this->postJson('/api/v1/uploads', [])->assertUnprocessable();
    }

    public function test_users_only_see_and_delete_their_own_files(): void
    {
        $mine = Upload::factory()->for($this->user)->create();
        $theirs = Upload::factory()->create();

        $this->getJson('/api/v1/uploads')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $mine->id);

        $this->getJson("/api/v1/uploads/{$theirs->id}")->assertForbidden()->assertJsonPath('errors.0', 'Forbidden');
        $this->deleteJson("/api/v1/uploads/{$theirs->id}")->assertForbidden();
        $this->assertModelExists($theirs);
    }

    public function test_delete_removes_the_stored_file(): void
    {
        $id = $this->postJson('/api/v1/uploads', ['file' => $this->png('a.png')])->json('data.id');
        $path = Upload::findOrFail($id)->path;

        $this->deleteJson("/api/v1/uploads/{$id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('uploads', ['id' => $id]);
    }

    /**
     * A real 1x1 PNG (no GD extension needed), so the content-based type check is exercised.
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }
}
