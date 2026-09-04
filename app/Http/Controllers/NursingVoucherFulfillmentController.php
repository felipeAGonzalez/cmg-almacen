<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNursingVoucherFulfillmentRequest;
use App\Models\NursingVoucher;
use App\Services\NursingVoucherFulfillmentService;
use Illuminate\Http\RedirectResponse;

class NursingVoucherFulfillmentController extends Controller
{
    public function store(
        StoreNursingVoucherFulfillmentRequest $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherFulfillmentService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $service->fulfill(
            $nursingVoucher,
            $request->user(),
            $data['items'],
            $data['notes'] ?? null,
        );

        return redirect()->route('nursing-vouchers.show', $nursingVoucher)
            ->with('success', 'Surtido registrado correctamente.');
    }
}
