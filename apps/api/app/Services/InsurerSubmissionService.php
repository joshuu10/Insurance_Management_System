<?php

namespace App\Services;

use App\Models\InsurerSubmission;
use App\Models\Quote;
use App\Models\User;
use App\Support\Uuid;
use Illuminate\Support\Facades\DB;

class InsurerSubmissionService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Submit a quote to insurer via manual or sandbox channel.
     * Never fabricates an insurer-confirmed premium.
     */
    public function submitQuote(Quote $quote, User $actor, string $channel = 'sandbox'): InsurerSubmission
    {
        if (! in_array($channel, ['manual', 'sandbox'], true)) {
            throw new \InvalidArgumentException('Only manual or sandbox channels are available until insurer API credentials exist.');
        }

        if (! in_array($quote->status, ['validated', 'draft', 'awaiting_insurer'], true)) {
            throw new \RuntimeException("Quote status '{$quote->status}' cannot be submitted.");
        }

        return DB::transaction(function () use ($quote, $actor, $channel) {
            $submission = InsurerSubmission::query()->create([
                'id' => Uuid::v7(),
                'organization_id' => $quote->organization_id,
                'subject_type' => 'quote',
                'subject_id' => $quote->id,
                'carrier_id' => $quote->carrier_id,
                'channel' => $channel,
                'status' => $channel === 'sandbox' ? 'sandbox_ack' : 'queued',
                'integration_state' => $channel === 'sandbox' ? 'SANDBOX' : 'NOT_CONFIGURED',
                'packet' => [
                    'quote_reference' => $quote->reference,
                    'customer_id' => $quote->customer_id,
                    'carrier_id' => $quote->carrier_id,
                    'product_id' => $quote->carrier_product_id,
                    'risk_summary' => $quote->risk_summary,
                    'answers' => $quote->answers,
                    'notice' => 'No live insurer confirmation. Staff must record insurer response with evidence.',
                ],
                'staff_notes' => $channel === 'sandbox'
                    ? 'Sandbox acknowledgement only — not an insurer-confirmed quote.'
                    : 'Queued for manual export / staff handoff to insurer.',
                'created_by' => $actor->id,
                'acknowledged_at' => $channel === 'sandbox' ? now() : null,
            ]);

            $quote->status = 'awaiting_insurer';
            $quote->premium_classification = 'awaiting_insurer';
            $quote->premium_amount = null; // never invent
            $quote->save();

            $this->audit->log(
                action: 'quote.submitted_to_insurer',
                subjectType: Quote::class,
                subjectId: $quote->id,
                after: [
                    'channel' => $channel,
                    'submission_id' => $submission->id,
                    'premium_classification' => 'awaiting_insurer',
                ],
                actor: $actor,
            );

            return $submission;
        });
    }

    /**
     * Record an insurer-confirmed quote with evidence (manual entry).
     */
    public function recordInsurerConfirmedPremium(
        Quote $quote,
        User $actor,
        string $amount,
        string $evidenceNote,
    ): Quote {
        if ($quote->status !== 'awaiting_insurer' && $quote->status !== 'submitted_to_insurer') {
            throw new \RuntimeException('Only awaiting-insurer quotes can receive insurer-confirmed premiums.');
        }

        if (trim($evidenceNote) === '') {
            throw new \InvalidArgumentException('Evidence note is required for insurer-confirmed premiums.');
        }

        $before = $quote->only(['status', 'premium_amount', 'premium_classification']);

        $quote->premium_amount = $amount;
        $quote->premium_classification = 'insurer_confirmed';
        $quote->status = 'insurer_confirmed';
        $quote->caveats = trim(($quote->caveats ? $quote->caveats."\n" : '').'Insurer confirmation evidence: '.$evidenceNote);
        $quote->save();

        $this->audit->log(
            action: 'quote.insurer_confirmed',
            subjectType: Quote::class,
            subjectId: $quote->id,
            before: $before,
            after: $quote->only(['status', 'premium_amount', 'premium_classification']),
            reason: $evidenceNote,
            actor: $actor,
        );

        return $quote->fresh();
    }
}
