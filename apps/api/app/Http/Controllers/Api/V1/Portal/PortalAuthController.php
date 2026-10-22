<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PortalOtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Portal\CustomerNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CustomerNotifier $notifier,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'preferred_locale' => ['nullable', 'string', 'max:12'],
            'national_id_last4' => ['nullable', 'string', 'size:4'],
        ]);

        $org = $this->registrationOrganization();
        $branch = Branch::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('is_head_office')
            ->first();

        $portalRole = Role::query()
            ->where('organization_id', $org->id)
            ->where('slug', 'customer_portal')
            ->firstOrFail();

        $result = DB::transaction(function () use ($data, $org, $branch, $portalRole) {
            $customer = Customer::query()->create([
                'organization_id' => $org->id,
                'branch_id' => $branch?->id,
                'type' => 'individual',
                'display_name' => $data['name'],
                'email' => $data['email'],
                'phone_e164' => $data['phone_e164'] ?? null,
                'preferred_locale' => $data['preferred_locale'] ?? 'en-TZ',
                'kyc_status' => 'pending',
                'identity_verification_status' => 'pending',
                'national_id_last4' => $data['national_id_last4'] ?? null,
            ]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'organization_id' => $org->id,
                'branch_id' => $branch?->id,
                'customer_id' => $customer->id,
                'phone_e164' => $data['phone_e164'] ?? null,
                'preferred_locale' => $data['preferred_locale'] ?? 'en-TZ',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->roles()->attach($portalRole->id);

            return [$user, $customer];
        });

        /** @var User $user */
        /** @var Customer $customer */
        [$user, $customer] = $result;

        $this->notifier->notify(
            $customer,
            'account',
            'Welcome to the customer portal',
            'Your account was created. Complete identity verification when prompted. Cover is only bound by the insurer.',
        );

        $this->audit->log(
            action: 'portal.register',
            subjectType: User::class,
            subjectId: (string) $user->id,
            after: ['customer_id' => $customer->id],
            request: $request,
            actor: $user,
        );

        $token = $user->createToken('portal')->plainTextToken;
        $user->load(['organization', 'branch', 'roles', 'customer']);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleSlugs(),
                'permissions' => $user->permissions(),
                'is_portal' => true,
                'customer_id' => $user->customer_id,
                'customer' => [
                    'id' => $customer->id,
                    'display_name' => $customer->display_name,
                    'email' => $customer->email,
                ],
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'legal_name' => $user->organization->legal_name,
                    'intermediary_type' => $user->organization->intermediary_type,
                    'licence_number' => $user->organization->licence_number,
                ] : null,
            ],
            'notice' => 'Registration creates an agency portal account. Identity verification and insurer KYC may still be required.',
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->whereNotNull('customer_id')->first();

        // Always return same shape (no email enumeration)
        $payload = [
            'status' => 'ok',
            'notice' => 'If a portal account exists for that email, a reset token was issued (sandbox: returned below when found).',
        ];

        if ($user) {
            $plain = Str::random(40);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($plain), 'created_at' => now()]
            );
            $payload['sandbox_reset_token'] = $plain;
            $payload['sandbox_email'] = $user->email;
        }

        return response()->json($payload);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (! $row || ! Hash::check($data['token'], $row->token)) {
            throw ValidationException::withMessages(['token' => ['Invalid or expired reset token.']]);
        }

        $user = User::query()->where('email', $data['email'])->whereNotNull('customer_id')->firstOrFail();
        $user->password = $data['password'];
        $user->save();
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        if ($user->customer) {
            $this->notifier->notify(
                $user->customer,
                'account',
                'Password updated',
                'Your portal password was changed successfully.',
            );
        }

        return response()->json(['status' => 'ok', 'notice' => 'Password updated. You can sign in.']);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        abort_unless(config('wabantu.portal_otp_enabled'), 422, 'OTP verification is disabled for this deployment.');

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isPortalUser(), 403, 'Not a customer portal account.');

        $plain = (string) random_int(100000, 999999);
        PortalOtpChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->delete();

        PortalOtpChallenge::query()->create([
            'user_id' => $user->id,
            'channel' => 'email',
            'code_hash' => Hash::make($plain),
            'purpose' => 'identity_verify',
            'expires_at' => now()->addMinutes(10),
        ]);

        $customer = $user->customer;
        $customer->identity_verification_status = 'otp_sent';
        $customer->save();

        return response()->json([
            'status' => 'otp_sent',
            'channel' => 'email_sandbox',
            'sandbox_otp' => $plain,
            'expires_in_seconds' => 600,
            'notice' => 'Sandbox OTP returned in response. Live SMS/email providers are not configured.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isPortalUser(), 403, 'Not a customer portal account.');

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $challenge = PortalOtpChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $challenge) {
            throw ValidationException::withMessages(['code' => ['No active OTP. Request a new code.']]);
        }

        $challenge->attempts++;
        $challenge->save();

        if ($challenge->attempts > 5 || ! Hash::check($data['code'], $challenge->code_hash)) {
            throw ValidationException::withMessages(['code' => ['Invalid OTP code.']]);
        }

        $challenge->consumed_at = now();
        $challenge->save();

        $customer = $user->customer;
        $customer->identity_verification_status = 'verified';
        $customer->identity_verified_at = now();
        $customer->kyc_status = $customer->kyc_status === 'pending' ? 'in_review' : $customer->kyc_status;
        $customer->save();

        $this->notifier->notify(
            $customer,
            'account',
            'Identity verification recorded',
            'OTP verification succeeded (sandbox). Full insurer KYC may still apply.',
        );

        return response()->json([
            'status' => 'verified',
            'identity_verification_status' => $customer->identity_verification_status,
            'notice' => 'OTP verified. This is not a live regulatory KYC clearance.',
        ]);
    }

    private function registrationOrganization(): Organization
    {
        $configured = config('wabantu.portal_registration_org_id');
        if ($configured) {
            return Organization::query()->findOrFail($configured);
        }

        $org = Organization::query()->orderBy('created_at')->first();
        if (! $org) {
            abort(503, 'No organization available for portal registration.');
        }

        return $org;
    }
}
