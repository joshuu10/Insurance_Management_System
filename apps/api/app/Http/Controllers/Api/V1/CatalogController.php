<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\CarrierAppointment;
use App\Models\CarrierProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use ResolvesOrganization;

    public function carriers(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $appointments = CarrierAppointment::query()
            ->with('carrier')
            ->where('organization_id', $org->id)
            ->get();

        return response()->json([
            'data' => $appointments->map(fn (CarrierAppointment $a) => [
                'appointment_id' => $a->id,
                'status' => $a->status,
                'allows_quoting' => $a->allows_quoting,
                'appointed_on' => $a->appointed_on,
                'expires_on' => $a->expires_on,
                'carrier' => $a->carrier,
            ]),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'quote.create');
        $org = $this->organization($request);

        $appointedCarrierIds = CarrierAppointment::query()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->pluck('carrier_id');

        $products = CarrierProduct::query()
            ->with(['carrier:id,legal_name,is_illustrative,status', 'productClass:id,code,name_en'])
            ->whereIn('carrier_id', $appointedCarrierIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $products]);
    }

    public function directory(Request $request): JsonResponse
    {
        $this->assertPermission($request, 'tenant.configure');

        $carriers = Carrier::query()->orderBy('legal_name')->get();

        return response()->json([
            'data' => $carriers,
            'notice' => 'Includes illustrative inactive entries. Not partnerships unless verified appointments exist.',
        ]);
    }
}
