<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReadyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => false,
            'migrations' => false,
            'audit_immutable' => class_exists(\App\Models\AuditEvent::class),
        ];

        try {
            DB::select('select 1 as ok');
            $checks['database'] = true;
            $checks['migrations'] = Schema::hasTable('migrations') && Schema::hasTable('users');
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'not_ready',
                'checks' => $checks,
                'error' => 'database_unreachable',
            ], 503);
        }

        $ready = $checks['database'] && $checks['migrations'];

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'edition' => config('wabantu.edition'),
            'checks' => $checks,
            'premium_collection_default' => 'insurer_directed',
            'allow_agent_premium_collection' => (bool) config('wabantu.allow_agent_premium_collection'),
        ], $ready ? 200 : 503);
    }
}
