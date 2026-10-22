<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\Concerns\ResolvesCustomerPortal;
use App\Http\Controllers\Controller;
use App\Models\CarrierProduct;
use App\Models\Claim;
use App\Models\CustomerNotification;
use App\Models\Document;
use App\Models\PortalPaymentTransaction;
use App\Models\PortalServiceRequest;
use App\Models\PremiumInstruction;
use App\Models\Policy;
use App\Models\ProductVersion;
use App\Services\ClaimService;
use App\Services\Portal\CustomerNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerPortalController extends Controller
{
    use ResolvesCustomerPortal;

    public function __construct(
        private readonly ClaimService $claims,
        private readonly CustomerNotifier $notifier,
    ) {
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $user = $request->user();
        $user->load(['organization', 'branch', 'roles']);

        return response()->json([
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_e164' => $user->phone_e164,
                'preferred_locale' => $user->preferred_locale,
                'roles' => $user->roleSlugs(),
                'permissions' => $user->permissions(),
                'is_portal' => true,
                'customer' => [
                    'id' => $customer->id,
                    'display_name' => $customer->display_name,
                    'email' => $customer->email,
                    'phone_e164' => $customer->phone_e164,
                    'type' => $customer->type,
                    'kyc_status' => $customer->kyc_status,
                    'identity_verification_status' => $customer->identity_verification_status,
                    'identity_verified_at' => $customer->identity_verified_at,
                    'national_id_last4' => $customer->national_id_last4,
                    'preferred_locale' => $customer->preferred_locale,
                ],
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'legal_name' => $user->organization->legal_name,
                    'trading_name' => $user->organization->trading_name,
                ] : null,
                'notice' => 'Customer portal shows agency-held records. Cover and claim outcomes remain with the insurer.',
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $user = $request->user();

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:160'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'preferred_locale' => ['nullable', 'string', 'max:12'],
            'national_id_last4' => ['nullable', 'string', 'size:4'],
        ]);

        if (isset($data['display_name'])) {
            $customer->display_name = $data['display_name'];
            $user->name = $data['display_name'];
        }
        if (array_key_exists('phone_e164', $data)) {
            $customer->phone_e164 = $data['phone_e164'];
            $user->phone_e164 = $data['phone_e164'];
        }
        if (isset($data['preferred_locale'])) {
            $customer->preferred_locale = $data['preferred_locale'];
            $user->preferred_locale = $data['preferred_locale'];
        }
        if (array_key_exists('national_id_last4', $data)) {
            $customer->national_id_last4 = $data['national_id_last4'];
        }

        $customer->save();
        $user->save();

        $this->notifier->notify($customer, 'account', 'Profile updated', 'Your portal profile details were saved.');

        return response()->json(['data' => $customer->fresh(), 'notice' => 'Profile updated.']);
    }

    public function products(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $rows = CarrierProduct::query()
            ->with([
                'carrier:id,legal_name,is_illustrative,disclaimer',
                'productClass:id,name,code',
                'versions' => fn ($q) => $q->where('is_current', true),
            ])
            ->where('status', 'active')
            ->whereHas('carrier', function ($q) use ($customer) {
                $q->whereHas('appointments', function ($a) use ($customer) {
                    $a->where('organization_id', $customer->organization_id)
                        ->where('status', 'active');
                });
            })
            ->orderBy('name')
            ->get()
            ->map(function (CarrierProduct $p) {
                /** @var ProductVersion|null $version */
                $version = $p->versions->first();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'carrier' => $p->carrier,
                    'product_class' => $p->productClass,
                    'coverage_summary' => $version?->disclosures,
                    'eligibility_guidance' => 'Subject to insurer underwriting. Agency cannot bind cover.',
                    'required_documents' => $version?->required_documents,
                    'questionnaire_schema' => $version?->questionnaire_schema,
                    'requires_insurer_rating' => $p->requires_insurer_rating,
                ];
            });

        return response()->json([
            'data' => $rows,
            'notice' => 'Catalogue is agency-appointed products only. Not live insurer pricing or binding.',
        ]);
    }

    public function policies(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.policy.view'), 403, 'Missing permission: portal.policy.view');

        $rows = Policy::query()
            ->with(['carrier:id,legal_name,is_illustrative', 'carrierProduct:id,name'])
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('effective_to')
            ->get()
            ->map(function (Policy $p) {
                $daysToExpiry = now()->diffInDays($p->effective_to, false);

                return [
                    ...$p->toArray(),
                    'days_to_expiry' => (int) floor($daysToExpiry),
                    'renewal_reminder' => $daysToExpiry >= 0 && $daysToExpiry <= 60,
                    'premium_due' => [
                        'amount' => $p->premium_amount,
                        'currency' => $p->currency,
                        'source' => 'agency_record',
                        'notice' => 'From agency-held schedule — confirm with insurer before paying if unsure.',
                    ],
                ];
            });

        return response()->json([
            'data' => $rows,
            'notice' => 'Policy details are agency records of insurer-issued cover. Not a certificate of insurance.',
        ]);
    }

    public function showPolicy(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.policy.view'), 403, 'Missing permission: portal.policy.view');

        $policy = Policy::query()
            ->with(['carrier:id,legal_name,is_illustrative', 'carrierProduct:id,name'])
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        $docs = Document::query()
            ->where('organization_id', $customer->organization_id)
            ->where('subject_type', 'policy')
            ->where('subject_id', $policy->id)
            ->whereIn('classification', ['customer_visible', 'public', 'internal'])
            ->orderByDesc('created_at')
            ->get(['id', 'purpose', 'original_filename', 'mime_type', 'status', 'issuer', 'classification', 'created_at']);

        $instructions = PremiumInstruction::query()
            ->where('organization_id', $customer->organization_id)
            ->where('policy_id', $policy->id)
            ->orderByDesc('created_at')
            ->get(['id', 'status', 'amount', 'currency', 'beneficiary_name', 'beneficiary_channel', 'payment_reference', 'payer_instructions', 'routes_to_insurer']);

        return response()->json([
            'data' => $policy,
            'documents' => $docs,
            'premium_instructions' => $instructions,
            'notice' => 'Verification and settlement decisions remain with the insurer / regulator pathways.',
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $policyIds = Policy::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $rows = Document::query()
            ->where('organization_id', $customer->organization_id)
            ->where(function ($q) use ($customer, $policyIds) {
                $q->where(function ($a) use ($customer) {
                    $a->where('subject_type', 'customer')->where('subject_id', $customer->id);
                })->orWhere(function ($a) use ($policyIds) {
                    $a->where('subject_type', 'policy')->whereIn('subject_id', $policyIds);
                });
            })
            ->orderByDesc('created_at')
            ->get(['id', 'subject_type', 'subject_id', 'purpose', 'original_filename', 'mime_type', 'status', 'issuer', 'created_at']);

        return response()->json(['data' => $rows]);
    }

    public function downloadDocument(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $policyIds = Policy::query()->where('customer_id', $customer->id)->pluck('id');

        $doc = Document::query()
            ->where('organization_id', $customer->organization_id)
            ->where(function ($q) use ($customer, $policyIds) {
                $q->where(function ($a) use ($customer) {
                    $a->where('subject_type', 'customer')->where('subject_id', $customer->id);
                })->orWhere(function ($a) use ($policyIds) {
                    $a->where('subject_type', 'policy')->whereIn('subject_id', $policyIds);
                })->orWhere(function ($a) use ($customer) {
                    $claimIds = Claim::query()->where('customer_id', $customer->id)->pluck('id');
                    $a->where('subject_type', 'claim')->whereIn('subject_id', $claimIds);
                });
            })
            ->findOrFail($id);

        $body = "Wabantu Insurance — sandbox document\n"
            ."Filename: {$doc->original_filename}\n"
            ."Purpose: {$doc->purpose}\n"
            ."Issuer: {$doc->issuer}\n"
            ."Status: {$doc->status}\n"
            ."Notice: Binary object storage not configured. This is a metadata placeholder download only.\n";

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $doc->original_filename.'.txt', [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function premiumDues(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $policies = Policy::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'grace'])
            ->get(['id', 'carrier_policy_number', 'premium_amount', 'currency', 'effective_to', 'status']);

        $instructions = PremiumInstruction::query()
            ->where('organization_id', $customer->organization_id)
            ->whereIn('policy_id', $policies->pluck('id'))
            ->whereIn('status', ['created', 'pending_payer', 'provider_processing'])
            ->get();

        return response()->json([
            'data' => [
                'policies' => $policies,
                'premium_instructions' => $instructions,
            ],
            'notice' => 'Amounts are agency-held. Paying here (sandbox) does not bind cover or confirm insurer receipt.',
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $rows = PortalPaymentTransaction::query()
            ->with(['policy:id,carrier_policy_number'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function initiatePayment(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $data = $request->validate([
            'policy_id' => [
                'required',
                'uuid',
                Rule::exists('policies', 'id')
                    ->where('organization_id', $customer->organization_id)
                    ->where('customer_id', $customer->id),
            ],
            'premium_instruction_id' => ['nullable', 'uuid'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $policy = Policy::query()->findOrFail($data['policy_id']);
        $instruction = null;
        if (! empty($data['premium_instruction_id'])) {
            $instruction = PremiumInstruction::query()
                ->where('organization_id', $customer->organization_id)
                ->where('policy_id', $policy->id)
                ->findOrFail($data['premium_instruction_id']);
        }

        $amount = $data['amount'] ?? $instruction?->amount ?? $policy->premium_amount;
        if ($amount === null) {
            throw new HttpException(422, 'No premium amount available on agency record.');
        }

        $tx = PortalPaymentTransaction::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'policy_id' => $policy->id,
            'premium_instruction_id' => $instruction?->id,
            'reference' => 'PAY-'.strtoupper(Str::random(10)),
            'gateway' => 'sandbox',
            'status' => 'pending',
            'amount' => $amount,
            'currency' => $instruction?->currency ?? $policy->currency ?? 'TZS',
            'fabricated_live_confirmation' => false,
            'notice' => 'Sandbox payment initiated. Not an insurer-confirmed receipt.',
            'gateway_payload' => ['mode' => 'sandbox', 'step' => 'initiated'],
        ]);

        $this->notifier->notify(
            $customer,
            'payment',
            'Payment initiated (sandbox)',
            "Reference {$tx->reference} is pending. Confirm in portal to simulate gateway success.",
            ['payment_id' => $tx->id]
        );

        return response()->json([
            'data' => $tx,
            'notice' => 'Gateway sandbox only — does not issue or renew cover.',
        ], 201);
    }

    public function confirmPaymentSandbox(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $tx = PortalPaymentTransaction::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        if (! in_array($tx->status, ['pending', 'processing'], true)) {
            throw new HttpException(422, 'Payment is not awaiting confirmation.');
        }

        $tx->status = 'sandbox_confirmed';
        $tx->receipt_number = 'RCPT-'.strtoupper(Str::random(8));
        $tx->confirmed_at = now();
        $tx->fabricated_live_confirmation = false;
        $tx->notice = 'Sandbox gateway reported success. Not an insurer settlement confirmation.';
        $tx->gateway_payload = array_merge($tx->gateway_payload ?? [], ['step' => 'sandbox_confirmed']);
        $tx->save();

        $this->notifier->notify(
            $customer,
            'payment',
            'Sandbox payment confirmed',
            "Receipt {$tx->receipt_number}. Agency will reconcile with insurer separately.",
            ['payment_id' => $tx->id]
        );

        return response()->json([
            'data' => $tx,
            'receipt' => [
                'receipt_number' => $tx->receipt_number,
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'confirmed_at' => $tx->confirmed_at,
            ],
            'notice' => $tx->notice,
        ]);
    }

    public function paymentReceipt(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $tx = PortalPaymentTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'sandbox_confirmed')
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'receipt_number' => $tx->receipt_number,
                'reference' => $tx->reference,
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'confirmed_at' => $tx->confirmed_at,
                'policy_id' => $tx->policy_id,
            ],
            'notice' => 'Sandbox receipt only.',
        ]);
    }

    public function claims(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.claim.submit'), 403, 'Missing permission: portal.claim.submit');

        $rows = Claim::query()
            ->with(['policy:id,carrier_policy_number'])
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => $rows,
            'notice' => 'FNOL coordinates notification. Agency does not assure payable cover or settle claims without delegated authority.',
        ]);
    }

    public function showClaim(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.claim.submit'), 403, 'Missing permission: portal.claim.submit');

        $claim = Claim::query()
            ->with([
                'policy:id,carrier_policy_number',
                'timeline' => fn ($q) => $q->where('visibility', 'customer'),
            ])
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        $evidence = Document::query()
            ->where('organization_id', $customer->organization_id)
            ->where('subject_type', 'claim')
            ->where('subject_id', $claim->id)
            ->get(['id', 'purpose', 'original_filename', 'mime_type', 'status', 'created_at']);

        return response()->json([
            'data' => $claim,
            'evidence' => $evidence,
            'acknowledgement' => $claim->submitted_at
                ? 'FNOL submitted to your agency for insurer coordination.'
                : null,
        ]);
    }

    public function storeClaim(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.claim.submit'), 403, 'Missing permission: portal.claim.submit');

        $data = $request->validate([
            'policy_id' => [
                'required',
                'uuid',
                Rule::exists('policies', 'id')
                    ->where('organization_id', $customer->organization_id)
                    ->where('customer_id', $customer->id),
            ],
            'incident_at' => ['required', 'date'],
            'incident_location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'police_reference' => ['nullable', 'string', 'max:120'],
            'declared_amount' => ['nullable', 'numeric', 'min:0'],
            'urgency' => ['nullable', Rule::in(['normal', 'high', 'critical'])],
        ]);

        $policy = Policy::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($data['policy_id']);

        try {
            $claim = $this->claims->createFnol($policy, $request->user(), $data);
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $this->notifier->notify(
            $customer,
            'claim',
            'FNOL draft created',
            "Claim {$claim->reference} saved as draft. Submit when ready.",
            ['claim_id' => $claim->id]
        );

        return response()->json([
            'data' => $claim,
            'acknowledgement' => 'Draft FNOL recorded.',
            'notice' => 'Coverage is provisional. Your agency will coordinate with the insurer.',
        ], 201);
    }

    public function submitClaim(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.claim.submit'), 403, 'Missing permission: portal.claim.submit');

        $claim = Claim::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        try {
            $claim = $this->claims->submit($claim, $request->user());
        } catch (\RuntimeException $e) {
            throw new HttpException(422, $e->getMessage());
        }

        $this->notifier->notify(
            $customer,
            'claim',
            'FNOL submitted',
            "Claim {$claim->reference} was submitted. Track status in My claims.",
            ['claim_id' => $claim->id]
        );

        return response()->json([
            'data' => $claim,
            'acknowledgement' => 'FNOL submitted to your agency for coordination.',
        ]);
    }

    public function attachClaimEvidence(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        abort_unless($request->user()->hasPermission('portal.claim.submit'), 403, 'Missing permission: portal.claim.submit');

        $claim = Claim::query()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->findOrFail($id);

        $data = $request->validate([
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:60'],
            'content_hash' => ['nullable', 'string', 'max:128'],
        ]);

        $doc = Document::query()->create([
            'organization_id' => $customer->organization_id,
            'subject_type' => 'claim',
            'subject_id' => $claim->id,
            'purpose' => $data['purpose'] ?? 'claim_evidence',
            'issuer' => 'customer',
            'original_filename' => $data['original_filename'],
            'mime_type' => $data['mime_type'] ?? null,
            'storage_path' => null,
            'content_hash' => $data['content_hash'] ?? null,
            'classification' => 'customer_visible',
            'status' => 'metadata_only',
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $doc,
            'notice' => 'Evidence metadata registered. Binary object storage upload is deferred; staff can request re-upload when storage is live.',
        ], 201);
    }

    public function renewals(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $rows = Policy::query()
            ->with(['carrier:id,legal_name'])
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'grace'])
            ->whereBetween('effective_to', [now(), now()->addDays(90)])
            ->orderBy('effective_to')
            ->get();

        return response()->json([
            'data' => $rows,
            'notice' => 'Renewal reminders from agency records. Contact staff to progress insurer renewal.',
        ]);
    }

    public function serviceRequests(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $rows = PortalServiceRequest::query()
            ->with(['carrierProduct:id,name', 'policy:id,carrier_policy_number'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeServiceRequest(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(['quotation', 'product_enquiry', 'renewal', 'support', 'general'])],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:10'],
            'carrier_product_id' => ['nullable', 'uuid'],
            'policy_id' => ['nullable', 'uuid'],
        ]);

        if (! empty($data['policy_id'])) {
            Policy::query()
                ->where('organization_id', $customer->organization_id)
                ->where('customer_id', $customer->id)
                ->findOrFail($data['policy_id']);
        }

        $row = PortalServiceRequest::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'status' => 'open',
            'subject' => $data['subject'],
            'body' => $data['body'],
            'carrier_product_id' => $data['carrier_product_id'] ?? null,
            'policy_id' => $data['policy_id'] ?? null,
        ]);

        $this->notifier->notify(
            $customer,
            'service',
            'Request received',
            "Your {$data['type']} request “{$data['subject']}” is open for staff follow-up.",
            ['service_request_id' => $row->id]
        );

        return response()->json([
            'data' => $row,
            'acknowledgement' => 'Request logged for agency staff. Not an automated quote or binding.',
        ], 201);
    }

    public function notifications(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $rows = CustomerNotification::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows,
            'notice' => 'In-app notifications. Push/SMS channels remain sandbox/conditional.',
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $customer = $this->portalCustomer($request);
        $row = CustomerNotification::query()
            ->where('customer_id', $customer->id)
            ->findOrFail($id);
        $row->is_read = true;
        $row->read_at = now();
        $row->save();

        return response()->json(['data' => $row]);
    }

    public function communicationHistory(Request $request): JsonResponse
    {
        $customer = $this->portalCustomer($request);

        $notifications = CustomerNotification::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $requests = PortalServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'notifications' => $notifications,
                'service_requests' => $requests,
            ],
        ]);
    }
}
