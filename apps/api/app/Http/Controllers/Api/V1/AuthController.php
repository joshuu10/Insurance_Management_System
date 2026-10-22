<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        $this->audit->log(
            action: 'auth.login',
            subjectType: User::class,
            subjectId: (string) $user->id,
            after: ['device' => $credentials['device_name'] ?? 'api'],
            request: $request,
            actor: $user,
        );

        $user->load(['organization', 'branch', 'roles', 'customer']);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        if ($user) {
            $this->audit->log(
                action: 'auth.logout',
                subjectType: User::class,
                subjectId: (string) $user->id,
                request: $request,
            );
        }

        return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['organization', 'branch', 'roles', 'customer']);

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roleSlugs(),
            'permissions' => $user->permissions(),
            'is_portal' => $user->isPortalUser(),
            'customer_id' => $user->customer_id,
            'customer' => $user->customer ? [
                'id' => $user->customer->id,
                'display_name' => $user->customer->display_name,
                'email' => $user->customer->email,
            ] : null,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'legal_name' => $user->organization->legal_name,
                'intermediary_type' => $user->organization->intermediary_type,
                'licence_number' => $user->organization->licence_number,
                'onboarding_completed' => $user->organization->onboarding_completed,
            ] : null,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name,
            ] : null,
        ];
    }
}
