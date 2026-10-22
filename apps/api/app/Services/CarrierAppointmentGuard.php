<?php

namespace App\Services;

use App\Models\CarrierAppointment;
use App\Models\Organization;
use RuntimeException;

class CarrierAppointmentGuard
{
    public function assertCanQuote(Organization $organization, string $carrierId): CarrierAppointment
    {
        $appointment = CarrierAppointment::query()
            ->where('organization_id', $organization->id)
            ->where('carrier_id', $carrierId)
            ->first();

        if (! $appointment) {
            throw new RuntimeException('No appointment exists for this carrier. Quoting is blocked.');
        }

        if ($appointment->status !== 'active') {
            throw new RuntimeException("Carrier appointment status is '{$appointment->status}'. Quoting is blocked.");
        }

        if (! $appointment->allows_quoting) {
            throw new RuntimeException('Appointment does not allow quoting for this carrier.');
        }

        if ($appointment->expires_on && $appointment->expires_on->isPast()) {
            throw new RuntimeException('Carrier appointment has expired. Quoting is blocked.');
        }

        return $appointment;
    }
}
