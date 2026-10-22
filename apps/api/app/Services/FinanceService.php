<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\CommissionReceivable;
use App\Models\CommissionSchedule;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Organization;
use App\Models\Policy;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationLine;
use App\Models\User;
use App\Support\Uuid;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinanceService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function ensureDefaultChart(Organization $org): void
    {
        $defaults = [
            ['code' => '1000', 'name' => 'Cash / Bank', 'type' => 'asset'],
            ['code' => '1100', 'name' => 'Commission Receivable', 'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '4000', 'name' => 'Commission Income', 'type' => 'income'],
            ['code' => '5000', 'name' => 'Operating Expense', 'type' => 'expense'],
        ];

        foreach ($defaults as $row) {
            ChartOfAccount::query()->firstOrCreate(
                ['organization_id' => $org->id, 'code' => $row['code']],
                [
                    'id' => Uuid::v7(),
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'is_system' => true,
                ]
            );
        }
    }

    public function expectCommission(Organization $org, User $actor, Policy $policy, ?CommissionSchedule $schedule = null): CommissionReceivable
    {
        $amount = null;
        if ($schedule?->rate_percent !== null && $policy->premium_amount !== null) {
            $amount = bcmul((string) $policy->premium_amount, bcdiv((string) $schedule->rate_percent, '100', 6), 2);
        } elseif ($schedule?->fixed_amount !== null) {
            $amount = (string) $schedule->fixed_amount;
        }

        if ($amount === null) {
            throw new RuntimeException('Cannot compute expected commission without schedule rate/fixed amount and policy premium.');
        }

        $receivable = CommissionReceivable::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'policy_id' => $policy->id,
            'carrier_id' => $policy->carrier_id,
            'commission_schedule_id' => $schedule?->id,
            'status' => 'expected',
            'expected_amount' => $amount,
            'currency' => $policy->currency ?? 'TZS',
            'basis' => 'calculated',
            'notes' => 'Expected commission — awaiting insurer confirmation. Not agency premium float.',
        ]);

        $this->audit->log(
            action: 'commission.expected',
            subjectType: CommissionReceivable::class,
            subjectId: $receivable->id,
            after: ['expected_amount' => $amount, 'policy_id' => $policy->id],
            actor: $actor,
        );

        return $receivable;
    }

    public function confirmCommission(CommissionReceivable $receivable, User $actor, string $confirmedAmount, string $evidence): CommissionReceivable
    {
        if (trim($evidence) === '') {
            throw new RuntimeException('Evidence/statement reference required.');
        }

        $receivable->status = 'insurer_confirmed';
        $receivable->confirmed_amount = $confirmedAmount;
        $receivable->notes = trim(($receivable->notes ? $receivable->notes."\n" : '').'Confirmed: '.$evidence);
        $receivable->basis = 'insurer_statement';
        $receivable->save();

        $this->audit->log(
            action: 'commission.confirmed',
            subjectType: CommissionReceivable::class,
            subjectId: $receivable->id,
            after: ['confirmed_amount' => $confirmedAmount],
            reason: $evidence,
            actor: $actor,
        );

        return $receivable->fresh();
    }

    /**
     * Post balanced double-entry journal for agency economic events only.
     */
    public function postJournal(Organization $org, User $actor, string $memo, array $lines, string $source = 'manual', ?string $sourceId = null): JournalEntry
    {
        if (count($lines) < 2) {
            throw new RuntimeException('Journal requires at least two lines.');
        }

        $debit = '0.00';
        $credit = '0.00';
        foreach ($lines as $line) {
            $debit = bcadd($debit, (string) ($line['debit'] ?? 0), 2);
            $credit = bcadd($credit, (string) ($line['credit'] ?? 0), 2);
        }

        if (bccomp($debit, $credit, 2) !== 0) {
            throw new RuntimeException("Journal unbalanced: debit {$debit} != credit {$credit}.");
        }

        return DB::transaction(function () use ($org, $actor, $memo, $lines, $source, $sourceId, $debit) {
            $entry = JournalEntry::query()->create([
                'id' => Uuid::v7(),
                'reference' => 'JE-'.strtoupper(substr(Uuid::v7(), 0, 8)),
                'organization_id' => $org->id,
                'memo' => $memo,
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'source' => $source,
                'source_id' => $sourceId,
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                JournalLine::query()->create([
                    'id' => Uuid::v7(),
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            $this->audit->log(
                action: 'journal.posted',
                subjectType: JournalEntry::class,
                subjectId: $entry->id,
                after: ['reference' => $entry->reference, 'total' => $debit],
                actor: $actor,
            );

            return $entry->load('lines');
        });
    }

    public function receiveCommission(CommissionReceivable $receivable, User $actor, string $receivedAmount): JournalEntry
    {
        if ($receivable->status !== 'insurer_confirmed' && $receivable->status !== 'expected') {
            throw new RuntimeException('Commission must be expected/confirmed before receipt posting.');
        }

        $org = Organization::query()->findOrFail($receivable->organization_id);
        $this->ensureDefaultChart($org);

        $cash = ChartOfAccount::query()->where('organization_id', $org->id)->where('code', '1000')->firstOrFail();
        $income = ChartOfAccount::query()->where('organization_id', $org->id)->where('code', '4000')->firstOrFail();

        $entry = $this->postJournal($org, $actor, 'Commission received '.$receivable->id, [
            ['account_id' => $cash->id, 'debit' => $receivedAmount, 'credit' => 0, 'memo' => 'Bank'],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => $receivedAmount, 'memo' => 'Commission income'],
        ], 'commission', $receivable->id);

        $receivable->status = 'received';
        $receivable->received_amount = $receivedAmount;
        $receivable->save();

        return $entry;
    }

    public function createReconciliationBatch(Organization $org, User $actor, array $data): ReconciliationBatch
    {
        $batch = ReconciliationBatch::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $org->id,
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'status' => 'open',
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'created_by' => $actor->id,
        ]);

        foreach ($data['lines'] ?? [] as $line) {
            ReconciliationLine::query()->create([
                'id' => Uuid::v7(),
                'batch_id' => $batch->id,
                'external_reference' => $line['external_reference'] ?? null,
                'amount' => $line['amount'],
                'currency' => $line['currency'] ?? 'TZS',
                'status' => 'unmatched',
                'notes' => $line['notes'] ?? null,
            ]);
        }

        return $batch->load('lines');
    }
}
