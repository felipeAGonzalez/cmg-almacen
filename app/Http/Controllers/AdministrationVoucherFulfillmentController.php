<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdministrationVoucherFulfillmentRequest;
use App\Models\AdministrationVoucher;
use App\Services\AdministrationVoucherFulfillmentService;
use Illuminate\Http\RedirectResponse;

class AdministrationVoucherFulfillmentController extends Controller
{
    public function store(StoreAdministrationVoucherFulfillmentRequest $request, AdministrationVoucher $administrationVoucher, AdministrationVoucherFulfillmentService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->fulfill($administrationVoucher, $request->user(), $data['items'], $data['notes'] ?? null);

        return back()->with('success', 'La reposición se registró correctamente.');
    }
}
