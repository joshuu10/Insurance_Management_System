<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Support\Uuid;
use RuntimeException;

class IntegrationHub
{
    /** @return array<int, array{key: string, name: string, description: string}> */
    public function catalog(): array
    {
        return [
            ['key' => 'tira_mis', 'name' => 'TIRA-MIS', 'description' => 'Verification / e-sticker — OFF until authorized credentials exist.'],
            ['key' => 'insurer_api', 'name' => 'Insurer API', 'description' => 'Carrier quote/policy/claim APIs — adapter only.'],
            ['key' => 'premium_payment', 'name' => 'Premium payment (insurer-directed)', 'description' => 'Pay to insurer beneficiary — not brokerage float.'],
            ['key' => 'platform_billing', 'name' => 'Platform SaaS billing', 'description' => 'Software subscription payments — separate ledger from premiums.'],
            ['key' => 'sms', 'name' => 'SMS provider', 'description' => 'Transactional SMS via contracted provider.'],
            ['key' => 'email', 'name' => 'Email provider', 'description' => 'Transactional email.'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp Business', 'description' => 'Requires approved Business API — not unofficial scraping.'],
            ['key' => 'efd', 'name' => 'TRA/EFD fiscal', 'description' => 'Agency invoices only where lawfully required.'],
            ['key' => 'esign', 'name' => 'E-signature', 'description' => 'Audited consent; not regulatory acceptance by itself.'],
        ];
    }

    public function ensureDefaults(Organization $org): void
    {
        foreach ($this->catalog() as $item) {
            if ($item['key'] === 'platform_billing') {
                continue; // platform-level only
            }

            Integration::query()->firstOrCreate(
                ['organization_id' => $org->id, 'provider_key' => $item['key']],
                [
                    'id' => Uuid::v7(),
                    'display_name' => $item['name'],
                    'status' => 'NOT_CONFIGURED',
                    'is_platform_level' => false,
                    'notes' => $item['description'],
                    'config' => ['sandbox_available' => true],
                ]
            );
        }
    }

    public function enableSandbox(Integration $integration): Integration
    {
        if ($integration->status === 'ACTIVE') {
            throw new RuntimeException('Cannot switch an ACTIVE live integration to sandbox without disable workflow.');
        }

        $integration->status = 'SANDBOX';
        $integration->last_health_check_at = now();
        $integration->last_health_status = 'sandbox_ok';
        $integration->notes = trim(($integration->notes ? $integration->notes."\n" : '').'Sandbox enabled — simulations only; no live regulator/insurer confirmations.');
        $integration->save();

        return $integration;
    }

    public function runSandboxProbe(Integration $integration): IntegrationSyncRun
    {
        if (! in_array($integration->status, ['SANDBOX', 'NOT_CONFIGURED'], true)) {
            throw new RuntimeException('Sandbox probe only allowed for SANDBOX or NOT_CONFIGURED integrations.');
        }

        if ($integration->status === 'NOT_CONFIGURED') {
            $this->enableSandbox($integration);
            $integration->refresh();
        }

        $run = IntegrationSyncRun::query()->create([
            'id' => Uuid::v7(),
            'integration_id' => $integration->id,
            'direction' => 'outbound',
            'status' => 'succeeded',
            'request_meta' => [
                'provider' => $integration->provider_key,
                'mode' => 'sandbox',
                'notice' => 'Deterministic sandbox fixture — not a live API call.',
            ],
            'response_meta' => [
                'ok' => true,
                'fabricated_confirmation' => false,
                'message' => 'Sandbox acknowledgement only.',
            ],
            'attempt' => 1,
        ]);

        $integration->last_health_check_at = now();
        $integration->last_health_status = 'sandbox_probe_ok';
        $integration->save();

        return $run;
    }

    public function assertNotLiveFabrication(Integration $integration): void
    {
        if ($integration->status === 'ACTIVE' && empty($integration->credentials_ref)) {
            throw new RuntimeException('ACTIVE integration requires credentials_ref from secret manager — refusing unsafe activation.');
        }
    }
}
