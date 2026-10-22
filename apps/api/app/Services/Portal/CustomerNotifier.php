<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\CustomerNotification;

class CustomerNotifier
{
    public function notify(Customer $customer, string $category, string $title, string $body, array $metadata = [], string $channel = 'in_app'): CustomerNotification
    {
        return CustomerNotification::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'channel' => $channel,
            'metadata' => $metadata,
        ]);
    }
}
