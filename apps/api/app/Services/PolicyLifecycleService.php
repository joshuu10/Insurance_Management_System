<?php

namespace App\Services;

use App\Models\Cancellation;
use App\Models\Endorsement;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\PremiumInstruction;
use App\Models\RenewalCase;
use App\Models\User;
use App\Support\Uuid;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PolicyLifecycleService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CarrierAppointmentGuard $appointments,
    ) {
    }

    /**
     * Intake an insurer-issued policy record. Does not bind cover itself —
     * records provenance from authorized insurer source.
     */
    public function intakeInsurerIssued(Organization $org, User $actor, array $data): Policy
    {
        $this->appointments->assertCanQuote($org, $data['carrier_id']);

        return DB::transaction(function () use ($org, $actor, $data) {
            $exists = Policy::query()
                ->where('carrier_id', $data['carrier_id'])
                ->where('carrier_policy_number', $data['carrier_policy_number'])
                ->exists();

            if ($exists) {
                throw new RuntimeException('A policy with this carrier policy number already exists for the insurer.');
            }

            if ($data['effective_to'] <= $data['effective_from']) {
                throw new RuntimeException('effective_to must be after effective_from.');
            }

            $policy = Policy::query()->create([
                'id' => Uuid::v7(),
                'organization_id' => $org->id,
                'branch_id' => $actor->branch_id,
                'customer_id' => $data['customer_id'],
                'carrier_id' => $data['carrier_id'],
                'carrier_product_id' => $data['carrier_product_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
                'application_id' => $data['application_id'] ?? null,
                'owner_user_id' => $actor->id,
                'carrier_policy_number' => $data['carrier_policy_number'],
                'cover_note_number' => $data['cover_note_number'] ?? null,
                'status' => $data['status'] ?? 'active',
                'verification_status' => $data['verification_status'] ?? 'not_checked',
                'verification_reference' => $data['verification_reference'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'],
                'sum_insured' => $data['sum_insured'] ?? null,
                'premium_amount' => $data['premium_amount'] ?? null,
                'currency' => $data['currency'] ?? 'TZS',
                'insured_risks' => $data['insured_risks'] ?? null,
                'exclusions' => $data['exclusions'] ?? null,
                'schedule' => $data['schedule'] ?? null,
                'source_document_ref' => $data['source_document_ref'] ?? null,
                'provenance' => $data['provenance'] ?? 'manual_staff_entry',
                'intake_notes' => $data['intake_notes'] ?? 'Imported from insurer-issued documentation. Agency does not underwrite.',
                'version' => 1,
            ]);

            $this->audit->log(
                action: 'policy.intake',
                subjectType: Policy::class,
                subjectId: $policy->id,
                after: [
                    'carrier_policy_number' => $policy->carrier_policy_number,
                    'status' => $policy->status,
                    'verification_status' => $policy->verification_status,
                ],
                actor: $actor,
            );

            return $policy;
        });
    }

    public function requestEndorsement(Policy $policy, User $actor, array $data): Endorsement
    {
        if (! in_array($policy->status, ['active', 'grace'], true)) {
            throw new RuntimeException('Endorsements are only allowed on active/grace policies.');
        }

        $endorsement = Endorsement::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $policy->organization_id,
            'policy_id' => $policy->id,
            'reference' => 'E-'.strtoupper(substr(Uuid::v7(), 0, 8)),
            'type' => $data['type'],
            'status' => 'pending_insurer',
            'description' => $data['description'],
            'requested_changes' => $data['requested_changes'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'requested_by' => $actor->id,
        ]);

        $this->audit->log(
            action: 'policy.endorsement.requested',
            subjectType: Endorsement::class,
            subjectId: $endorsement->id,
            after: ['policy_id' => $policy->id, 'type' => $endorsement->type],
            actor: $actor,
        );

        return $endorsement;
    }

    public function applyInsurerApprovedEndorsement(Endorsement $endorsement, User $actor, string $approvalRef): Endorsement
    {
        if ($endorsement->status !== 'pending_insurer') {
            throw new RuntimeException('Only pending endorsements can be marked insurer-approved.');
        }

        $endorsement->status = 'insurer_approved';
        $endorsement->insurer_approval_ref = $approvalRef;
        $endorsement->save();

        $policy = $endorsement->policy;
        $policy->version = $policy->version + 1;
        if (is_array($endorsement->requested_changes)) {
            $schedule = $policy->schedule ?? [];
            $policy->schedule = array_merge($schedule, ['last_endorsement' => $endorsement->requested_changes]);
        }
        $policy->save();

        $endorsement->status = 'applied';
        $endorsement->save();

        $this->audit->log(
            action: 'policy.endorsement.applied',
            subjectType: Endorsement::class,
            subjectId: $endorsement->id,
            after: ['insurer_approval_ref' => $approvalRef, 'policy_version' => $policy->version],
            reason: 'Insurer approval recorded with reference.',
            actor: $actor,
        );

        return $endorsement->fresh();
    }

    public function requestCancellation(Policy $policy, User $actor, array $data): Cancellation
    {
        if (in_array($policy->status, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('Policy is already cancelled or expired.');
        }

        $cancellation = Cancellation::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $policy->organization_id,
            'policy_id' => $policy->id,
            'status' => 'pending_insurer',
            'reason' => $data['reason'],
            'effective_from' => $data['effective_from'],
            'requested_by' => $actor->id,
        ]);

        $this->audit->log(
            action: 'policy.cancellation.requested',
            subjectType: Cancellation::class,
            subjectId: $cancellation->id,
            after: ['policy_id' => $policy->id, 'reason' => $cancellation->reason],
            actor: $actor,
        );

        return $cancellation;
    }

    public function confirmCancellation(Cancellation $cancellation, User $actor, string $insurerRef, ?string $refundAmount = null): Cancellation
    {
        if ($cancellation->status !== 'pending_insurer') {
            throw new RuntimeException('Cancellation is not awaiting insurer confirmation.');
        }

        $cancellation->status = 'confirmed';
        $cancellation->insurer_confirmation_ref = $insurerRef;
        $cancellation->refund_amount = $refundAmount;
        $cancellation->save();

        $policy = $cancellation->policy;
        $before = ['status' => $policy->status];
        $policy->status = 'cancelled';
        $policy->save();

        $this->audit->log(
            action: 'policy.cancellation.confirmed',
            subjectType: Policy::class,
            subjectId: $policy->id,
            before: $before,
            after: ['status' => 'cancelled', 'insurer_confirmation_ref' => $insurerRef],
            reason: $cancellation->reason,
            actor: $actor,
        );

        return $cancellation->fresh();
    }

    public function openRenewalCase(Policy $policy, User $actor): RenewalCase
    {
        $existing = RenewalCase::query()
            ->where('policy_id', $policy->id)
            ->whereIn('status', ['open', 'notified', 'quote_requested', 'offer_ready'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $days = now()->diffInDays($policy->effective_to, false);

        $case = RenewalCase::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $policy->organization_id,
            'policy_id' => $policy->id,
            'status' => 'open',
            'days_to_expiry' => (int) max(0, $days),
            'notes' => 'Renewal opened. No automatic renewal/charge without customer consent and insurer offer.',
        ]);

        $this->audit->log(
            action: 'policy.renewal.opened',
            subjectType: RenewalCase::class,
            subjectId: $case->id,
            after: ['policy_id' => $policy->id, 'days_to_expiry' => $case->days_to_expiry],
            actor: $actor,
        );

        return $case;
    }

    /**
     * Create insurer-directed premium payment instructions (s.72 default).
     */
    public function createPremiumInstruction(Organization $org, User $actor, array $data): PremiumInstruction
    {
        if (! config('wabantu.allow_agent_premium_collection') && ($data['routes_to_insurer'] ?? true) === false) {
            throw new RuntimeException('Routing premium through agency/broker is disabled (Insurance Act s.72 default).');
        }

        $instruction = PremiumInstruction::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'policy_id' => $data['policy_id'] ?? null,
            'quote_id' => $data['quote_id'] ?? null,
            'carrier_id' => $data['carrier_id'],
            'status' => 'created',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'TZS',
            'beneficiary_name' => $data['beneficiary_name'],
            'beneficiary_account' => $data['beneficiary_account'] ?? null,
            'beneficiary_channel' => $data['beneficiary_channel'] ?? 'bank_transfer',
            'payment_reference' => $data['payment_reference'] ?? ('PAY-'.strtoupper(substr(Uuid::v7(), 0, 8))),
            'payer_instructions' => $data['payer_instructions'] ?? 'Pay only to the insurer beneficiary shown. Do not pay the brokerage platform.',
            'routes_to_insurer' => $data['routes_to_insurer'] ?? true,
            'integration_state' => 'NOT_CONFIGURED',
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            action: 'premium_instruction.created',
            subjectType: PremiumInstruction::class,
            subjectId: $instruction->id,
            after: [
                'amount' => $instruction->amount,
                'beneficiary_name' => $instruction->beneficiary_name,
                'routes_to_insurer' => $instruction->routes_to_insurer,
            ],
            actor: $actor,
        );

        return $instruction;
    }
}
