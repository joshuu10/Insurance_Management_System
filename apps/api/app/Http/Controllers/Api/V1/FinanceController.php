<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\CommissionReceivable;
use App\Models\CommissionSchedule;
use App\Models\JournalEntry;
use App\Models\Policy;
use App\Models\ReconciliationBatch;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FinanceController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly FinanceService $finance)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'finance.reconcile');
        $org = $this->organization($request);
        $this->finance->ensureDefaultChart($org);

        return response()->json([
            'data' => [
                'commission_expected' => CommissionReceivable::query()->where('organization_id', $org->id)->where('status', 'expected')->sum('expected_amount'),
                'commission_confirmed' => CommissionReceivable::query()->where('organization_id', $org->id)->where('status', 'insurer_confirmed')->sum('confirmed_amount'),
                'commission_received' => CommissionReceivable::query()->where('organization_id', $org->id)->where('status', 'received')->sum('received_amount'),
                'journals_posted' => JournalEntry::query()->where('organization_id', $org->id)->where('status', 'posted')->count(),
                'open_reconciliations' => ReconciliationBatch::query()->where('organization_id', $org->id)->where('status', 'open')->count(),
            ],
            'notice' => 'Agency ledger only — not insurer general ledger. Premium float is not agency revenue.',
        ]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'commission.approve');
        $org = $this->organization($request);

        $data = $request->validate([
            'carrier_id' => ['required', 'uuid', 'exists:carriers,id'],
            'carrier_product_id' => ['nullable', 'uuid', 'exists:carrier_products,id'],
            'name' => ['required', 'string', 'max:255'],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['rate_percent']) && empty($data['fixed_amount'])) {
            throw new HttpException(422, 'Provide rate_percent or fixed_amount.');
        }

        $schedule = CommissionSchedule::query()->create([
            ...$data,
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        return response()->json(['data' => $schedule], 201);
    }

    public function expectCommission(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'commission.approve');
        $org = $this->organization($request);

        $data = $request->validate([
            'policy_id' => ['required', 'uuid', Rule::exists('policies', 'id')->where('organization_id', $org->id)],
            'commission_schedule_id' => ['nullable', 'uuid', Rule::exists('commission_schedules', 'id')->where('organization_id', $org->id)],
        ]);

        $policy = Policy::query()->where('organization_id', $org->id)->findOrFail($data['policy_id']);
        $schedule = isset($data['commission_schedule_id'])
            ? CommissionSchedule::query()->where('organization_id', $org->id)->findOrFail($data['commission_schedule_id'])
            : CommissionSchedule::query()
                ->where('organization_id', $org->id)
                ->where('carrier_id', $policy->carrier_id)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->first();

        try {
            $receivable = $this->finance->expectCommission($org, $request->user(), $policy, $schedule);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $receivable], 201);
    }

    public function confirmCommission(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'commission.approve');
        $org = $this->organization($request);
        $receivable = CommissionReceivable::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'confirmed_amount' => ['required', 'numeric', 'min:0'],
            'evidence' => ['required', 'string', 'min:5'],
        ]);

        try {
            $receivable = $this->finance->confirmCommission(
                $receivable,
                $request->user(),
                (string) $data['confirmed_amount'],
                $data['evidence'],
            );
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => $receivable]);
    }

    public function receiveCommission(Request $request, string $id): JsonResponse
    {
        $this->assertPermission($request, 'finance.reconcile');
        $org = $this->organization($request);
        $receivable = CommissionReceivable::query()->where('organization_id', $org->id)->findOrFail($id);

        $data = $request->validate([
            'received_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $journal = $this->finance->receiveCommission(
                $receivable,
                $request->user(),
                (string) $data['received_amount'],
            );
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        return response()->json(['data' => ['receivable' => $receivable->fresh(), 'journal' => $journal]]);
    }

    public function journals(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'finance.reconcile');
        $org = $this->organization($request);

        $rows = JournalEntry::query()
            ->with('lines.account')
            ->where('organization_id', $org->id)
            ->orderByDesc('posted_at')
            ->paginate(25);

        return response()->json($rows);
    }

    public function commissions(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'finance.reconcile');
        $org = $this->organization($request);

        $rows = CommissionReceivable::query()
            ->with('policy:id,carrier_policy_number')
            ->where('organization_id', $org->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($rows);
    }

    public function createReconciliation(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'finance.reconcile');
        $org = $this->organization($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(['insurer_statement', 'bank_statement', 'provider'])],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.amount' => ['required_with:lines', 'numeric'],
            'lines.*.external_reference' => ['nullable', 'string'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $batch = $this->finance->createReconciliationBatch($org, $request->user(), $data);

        return response()->json(['data' => $batch], 201);
    }
}
