<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesOrganization
{
    protected function organization(Request $request): Organization
    {
        /** @var User $user */
        $user = $request->user();
        $org = $user->organization;

        if (! $org) {
            throw new HttpException(422, 'User is not attached to an organization.');
        }

        return $org;
    }

    protected function assertPermission(Request $request, string $permission): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user && $user->hasPermission($permission),
            403,
            "Missing permission: {$permission}"
        );
    }
}
