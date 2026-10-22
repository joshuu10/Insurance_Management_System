<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $user->organization;

        if (! $organization) {
            return response()->json([
                'type' => 'https://wabantu.local/problems/no-organization',
                'title' => 'Organization missing',
                'status' => 422,
                'detail' => 'User is not attached to an organization.',
            ], 422);
        }

        if (! $user->hasPermission('tenant.configure')) {
            return response()->json([
                'type' => 'https://wabantu.local/problems/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Missing permission: tenant.configure',
            ], 403);
        }

        $data = $request->validate([
            'intermediary_type' => ['required', Rule::in(['agent', 'brokerage', 'agent_for_broker', 'other'])],
            'licence_number' => ['required', 'string', 'max:120'],
            'licence_category' => ['nullable', 'string', 'max:120'],
            'licence_issued_on' => ['nullable', 'date'],
            'licence_expires_on' => ['nullable', 'date', 'after:licence_issued_on'],
            'legal_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $before = $organization->only([
            'intermediary_type',
            'licence_number',
            'licence_category',
            'licence_issued_on',
            'licence_expires_on',
            'legal_name',
            'onboarding_completed',
            'regulatory_status',
        ]);

        $organization->fill($data);
        $organization->onboarding_completed = true;
        $organization->regulatory_status = 'pending_counsel_review';
        $organization->save();

        $this->audit->log(
            action: 'organization.onboarding.updated',
            subjectType: $organization::class,
            subjectId: $organization->id,
            before: $before,
            after: $organization->only(array_keys($before)),
            reason: 'Guided onboarding step — counsel verification still required before production.',
            request: $request,
        );

        return response()->json([
            'data' => [
                'id' => $organization->id,
                'legal_name' => $organization->legal_name,
                'intermediary_type' => $organization->intermediary_type,
                'licence_number' => $organization->licence_number,
                'onboarding_completed' => $organization->onboarding_completed,
                'regulatory_status' => $organization->regulatory_status,
                'notice' => 'Settings saved. Legal counsel must validate before production activation.',
            ],
        ]);
    }
}
