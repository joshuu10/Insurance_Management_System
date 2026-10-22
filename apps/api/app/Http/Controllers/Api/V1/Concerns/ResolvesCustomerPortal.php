<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCustomerPortal
{
    protected function portalCustomer(Request $request): Customer
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('customer');

        if (! $user->customer_id || ! $user->customer) {
            throw new HttpException(403, 'Not a customer portal account.');
        }

        if (! $user->hasPermission('portal.access')) {
            throw new HttpException(403, 'Missing permission: portal.access');
        }

        return $user->customer;
    }
}
