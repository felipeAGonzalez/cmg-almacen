<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateAdministrationVoucherFromConsumptionRequest;
use App\Models\Cabinet;
use App\Services\AdministrationVoucherConsumptionService;
use Illuminate\Http\RedirectResponse;

class AdministrationVoucherConsumptionController extends Controller
{
    public function store(
        GenerateAdministrationVoucherFromConsumptionRequest $request,
        AdministrationVoucherConsumptionService $service,
    ): RedirectResponse {
        $voucher = $service->generate(
            $request->user(),
            Cabinet::query()->findOrFail($request->integer('cabinet_id')),
            $request->date('consumption_date')->toDateString(),
        );

        return redirect()->route('administration-vouchers.show', $voucher)
            ->with('success', 'El vale de reposición se generó a partir del consumo del gabinete.');
    }
}
