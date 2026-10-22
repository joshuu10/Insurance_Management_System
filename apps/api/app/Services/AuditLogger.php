<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Uuid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?Request $request = null,
        ?User $actor = null,
    ): AuditEvent {
        $request ??= request();
        $user = $actor ?? Auth::user();

        return AuditEvent::query()->create([
            'id' => Uuid::v7(),
            'organization_id' => $user?->organization_id,
            'actor_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'correlation_id' => $request?->header('X-Correlation-ID') ?? (string) Uuid::v7(),
            'reason' => $reason,
        ]);
    }
}
