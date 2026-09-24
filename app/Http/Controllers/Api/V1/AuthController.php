<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * Register, log in and manage the current user. Protected endpoints expect
 * `Authorization: Bearer {token}` with the token returned by login / register.
 */
class AuthController extends Controller
{
    /**
     * Register.
     *
     * Creates an account and returns an access token.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        event(new Registered($user));

        return $this->success($this->tokenPayload($user, $request), 'Account created successfully.', 201);
    }

    /**
     * Log in.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Always run exactly one hash check, so response time does not reveal whether the email exists.
        $hash = $user->password ?? Cache::rememberForever(
            'auth:dummy-hash:'.config('hashing.bcrypt.rounds'),
            fn () => Hash::make(str()->random(32)),
        );

        if (! Hash::check($request->validated('password'), $hash) || ! $user) {
            return $this->fail('These credentials do not match our records.', 401);
        }

        return $this->success($this->tokenPayload($user, $request), 'Logged in successfully.');
    }

    /**
     * Current user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }

    /**
     * Update profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success($user, 'Profile updated successfully.');
    }

    /**
     * Change password.
     *
     * Also signs out every other device.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return $this->success(null, 'Password changed successfully.');
    }

    /**
     * Log out.
     *
     * Revokes the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Forgot password.
     *
     * Emails a reset link to `FRONTEND_URL/reset-password/{token}?email=...`.
     * The response is the same whether or not the email exists (prevents account enumeration).
     *
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->success(null, 'If that email is registered, a reset link is on its way.');
    }

    /**
     * Reset password.
     *
     * Signs out every device of that user.
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->setRememberToken(str()->random(60));
                $user->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return $this->success(null, __($status));
    }

    /**
     * @return array{token: string, token_type: string, expires_in: int, expires_at: string, user: User}
     */
    private function tokenPayload(User $user, Request $request): array
    {
        $minutes = (int) config('api.token_ttl_minutes');
        $expiresAt = now()->addMinutes($minutes);
        $device = (string) $request->input('device_name', $request->userAgent() ?: 'api');

        return [
            'token' => $user->createToken(str($device)->limit(100, '')->toString(), ['*'], $expiresAt)->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $minutes * 60,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user,
        ];
    }
}
