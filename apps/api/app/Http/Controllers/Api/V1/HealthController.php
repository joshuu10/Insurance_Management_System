<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $integrationSummary = [
            'tira' => 'NOT_CONFIGURED',
            'insurer_apis' => 'NOT_CONFIGURED',
            'payments' => 'NOT_CONFIGURED',
        ];

        try {
            $rows = Integration::query()
                ->select('provider_key', 'status')
                ->whereIn('provider_key', ['tira_mis', 'insurer_api', 'premium_payment'])
                ->get()
                ->keyBy('provider_key');

            if ($rows->has('tira_mis')) {
                $integrationSummary['tira'] = $rows['tira_mis']->status;
            }
            if ($rows->has('insurer_api')) {
                $integrationSummary['insurer_apis'] = $rows['insurer_api']->status;
            }
            if ($rows->has('premium_payment')) {
                $integrationSummary['payments'] = $rows['premium_payment']->status;
            }
        } catch (\Throwable) {
            // Health must not 500 if integrations table missing mid-migrate.
        }

        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'edition' => config('wabantu.edition'),
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
            'phase' => '6-hardening',
            'integrations' => $integrationSummary,
            'notice' => 'SANDBOX/NOT_CONFIGURED are not live regulatory or insurer confirmations.',
        ]);
    }
}
