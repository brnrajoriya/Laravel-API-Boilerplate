<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const ENVELOPE = ['status', 'data', 'errors', 'hasError', 'message'];

    public function test_register_creates_user_and_returns_token(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'JANE@Example.com',
            'password' => 'secret123',
        ])
            ->assertCreated()
            ->assertJsonStructure([...self::ENVELOPE, 'data' => ['token', 'token_type', 'expires_in', 'expires_at', 'user' => ['id', 'name', 'email']]])
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('hasError', false)
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_validation_errors_use_the_envelope(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', ['name' => '', 'email' => 'taken@example.com', 'password' => '1'])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('hasError', true)
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']])
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'Demo@1234']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'Demo@Example.com', 'password' => 'Demo@1234'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'demo@example.com');
    }

    public function test_login_with_wrong_password_is_401(): void
    {
        User::factory()->create(['email' => 'demo@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'wrong'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('errors.0', 'These credentials do not match our records.');

        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_tokens_expire(): void
    {
        User::factory()->create(['email' => 'demo@example.com', 'password' => 'Demo@1234']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'demo@example.com', 'password' => 'Demo@1234'])->json('data.token');

        $this->assertNotNull(PersonalAccessToken::findToken($token)?->expires_at);

        $this->travel(config('api.token_ttl_minutes') + 1)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => 'fail',
                'data' => [],
                'errors' => ['Unauthenticated.'],
                'hasError' => true,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('a')->plainTextToken;
        $other = $user->createToken('b')->plainTextToken;

        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($current));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
    }

    public function test_update_profile(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me', ['email' => 'taken@example.com'])
            ->assertUnprocessable();

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/me', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_change_password_requires_current_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $current = $user->createToken('current')->plainTextToken;
        $other = $user->createToken('other')->plainTextToken;

        $this->withToken($current)->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['current_password']]);

        $this->withToken($current)->putJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertNotNull(PersonalAccessToken::findToken($current));
        $this->assertNull(PersonalAccessToken::findToken($other));
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk();
    }

    public function test_forgot_password_sends_link_to_frontend_without_revealing_accounts(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.example.com']);
        $user = User::factory()->create();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://app.example.com/reset-password/'.$notification->token);
        });
    }

    public function test_reset_password_with_valid_token_signs_out_everywhere(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old')->plainTextToken;
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertUnprocessable()->assertJsonStructure(['errors' => ['email']]);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'brand-new-pass'])->assertOk();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, config('api.rate_limit.auth')) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope'])
            ->assertTooManyRequests()
            ->assertJsonPath('status', 'fail')
            ->assertHeader('Retry-After');
    }
}
