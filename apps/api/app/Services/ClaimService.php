<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\ClaimTimelineEntry;
use App\Models\Policy;
use App\Models\User;
use App\Support\Uuid;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClaimService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function createFnol(Policy $policy, User $actor, array $data): Claim
    {
        if (! in_array($policy->status, ['active', 'grace'], true)) {
            throw new RuntimeException('FNOL is only allowed against active or grace policies.');
        }

        return DB::transaction(function () use ($policy, $actor, $data) {
            $duplicate = Claim::query()
                ->where('policy_id', $policy->id)
                ->whereDate('incident_at', date('Y-m-d', strtotime($data['incident_at'])))
                ->whereIn('status', ['draft', 'submitted', 'acknowledged', 'documents_pending', 'insurer_investigating'])
                ->exists();

            $claim = Claim::query()->create([
                'id' => Uuid::v7(),
                'reference' => 'CL-'.strtoupper(substr(Uuid::v7(), 0, 8)),
                'organization_id' => $policy->organization_id,
                'policy_id' => $policy->id,
                'customer_id' => $policy->customer_id,
                'carrier_id' => $policy->carrier_id,
                'owner_user_id' => $actor->id,
                'status' => 'draft',
                'incident_at' => $data['incident_at'],
                'incident_location' => $data['incident_location'] ?? null,
                'description' => $data['description'],
                'police_reference' => $data['police_reference'] ?? null,
                'declared_amount' => $data['declared_amount'] ?? null,
                'currency' => $data['currency'] ?? 'TZS',
                'urgency' => $data['urgency'] ?? 'normal',
                'coverage_provisional_notice' => true,
                'fraud_review_flag' => $duplicate ? 'possible_duplicate' : null,
                'staff_notes' => $duplicate
                    ? 'Possible duplicate FNOL for same policy/incident date — internal review flag only.'
                    : null,
            ]);

            $this->timeline($claim, $actor, 'fnol_drafted', 'FNOL draft created. Coverage display is provisional from policy; agency does not assure payable cover.', [
                'fraud_review_flag' => $claim->fraud_review_flag,
            ], 'customer');

            $this->audit->log(
                action: 'claim.fnol_drafted',
                subjectType: Claim::class,
                subjectId: $claim->id,
                after: ['reference' => $claim->reference, 'policy_id' => $policy->id],
                actor: $actor,
            );

            return $claim;
        });
    }

    public function submit(Claim $claim, User $actor): Claim
    {
        if ($claim->status !== 'draft') {
            throw new RuntimeException('Only draft claims can be submitted.');
        }

        $claim->status = 'submitted';
        $claim->submitted_at = now();
        $claim->save();

        $this->timeline($claim, $actor, 'fnol_submitted', 'FNOL submitted to agency for acknowledgement and insurer dispatch.', [
            // customer-visible
            'submitted_at' => $claim->submitted_at?->toIso8601String(),
        ], 'customer');

        $this->audit->log(
            action: 'claim.submitted',
            subjectType: Claim::class,
            subjectId: $claim->id,
            after: ['status' => 'submitted'],
            actor: $actor,
        );

        return $claim->fresh();
    }

    public function acknowledge(Claim $claim, User $actor): Claim
    {
        if ($claim->status !== 'submitted') {
            throw new RuntimeException('Only submitted claims can be acknowledged.');
        }

        $claim->status = 'acknowledged';
        $claim->save();

        $this->timeline($claim, $actor, 'agency_acknowledged', 'Agency acknowledged FNOL. Insurer investigation decision remains with insurer.', null, 'customer');

        $this->audit->log(
            action: 'claim.acknowledged',
            subjectType: Claim::class,
            subjectId: $claim->id,
            after: ['status' => 'acknowledged'],
            actor: $actor,
        );

        return $claim->fresh();
    }

    /**
     * Record insurer-provided claim update. Agency cannot invent settlement authority.
     */
    public function recordInsurerUpdate(Claim $claim, User $actor, array $data): Claim
    {
        $allowed = [
            'documents_pending', 'insurer_investigating', 'assessed',
            'insurer_approved', 'insurer_rejected', 'partially_approved',
            'payment_pending', 'insurer_settled', 'closed',
        ];

        if (! in_array($data['status'], $allowed, true)) {
            throw new RuntimeException('Invalid insurer update status.');
        }

        if (in_array($data['status'], ['insurer_approved', 'partially_approved', 'insurer_settled'], true)
            && empty($data['insurer_reference'])) {
            throw new RuntimeException('Insurer reference/evidence is required for approval or settlement updates.');
        }

        $before = $claim->only(['status', 'insurer_approved_amount']);
        $claim->status = $data['status'];
        if (array_key_exists('insurer_approved_amount', $data)) {
            $claim->insurer_approved_amount = $data['insurer_approved_amount'];
        }
        if ($data['status'] === 'closed') {
            $claim->closed_at = now();
        }
        $claim->save();

        $this->timeline($claim, $actor, 'insurer_update', $data['message'] ?? 'Insurer status update recorded.', [
            'insurer_reference' => $data['insurer_reference'] ?? null,
            'status' => $claim->status,
            'insurer_approved_amount' => $claim->insurer_approved_amount,
        ], 'customer');

        $this->audit->log(
            action: 'claim.insurer_update',
            subjectType: Claim::class,
            subjectId: $claim->id,
            before: $before,
            after: $claim->only(['status', 'insurer_approved_amount']),
            reason: $data['insurer_reference'] ?? null,
            actor: $actor,
        );

        return $claim->fresh();
    }

    private function timeline(
        Claim $claim,
        User $actor,
        string $eventType,
        string $message,
        ?array $payload = null,
        string $visibility = 'staff',
    ): void {
        ClaimTimelineEntry::query()->create([
            'id' => Uuid::v7(),
            'claim_id' => $claim->id,
            'organization_id' => $claim->organization_id,
            'event_type' => $eventType,
            'message' => $message,
            'visibility' => $visibility,
            'actor_id' => $actor->id,
            'payload' => $payload,
        ]);
    }
}
