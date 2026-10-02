<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectNursingVoucherReturnRequest;
use App\Http\Requests\StoreNursingVoucherReturnRequest;
use App\Models\NursingVoucher;
use App\Models\NursingVoucherReturn;
use App\Services\NursingVoucherReturnNotificationService;
use App\Services\NursingVoucherReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NursingVoucherReturnController extends Controller
{
    public function store(
        StoreNursingVoucherReturnRequest $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherReturnService $service,
        NursingVoucherReturnNotificationService $notifications,
    ): RedirectResponse {
        $data = $request->validated();
        $return = $service->request($nursingVoucher, $request->user(), $data['items'], $data['notes'] ?? null);
        $notifications->notifyRequested($return);

        return back()->with('success', 'La devolución se solicitó correctamente.');
    }

    public function receive(
        Request $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherReturn $nursingVoucherReturn,
        NursingVoucherReturnService $service,
        NursingVoucherReturnNotificationService $notifications,
    ): RedirectResponse {
        $this->ensureBelongsToVoucher($nursingVoucherReturn, $nursingVoucher);
        Gate::authorize('receiveReturn', $nursingVoucher);
        $return = $service->receive($nursingVoucherReturn, $request->user());
        $notifications->notifyReceived($return);

        return back()->with('success', 'La devolución fue recibida y el inventario se reintegró correctamente.');
    }

    public function reject(
        RejectNursingVoucherReturnRequest $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherReturn $nursingVoucherReturn,
        NursingVoucherReturnService $service,
        NursingVoucherReturnNotificationService $notifications,
    ): RedirectResponse {
        $this->ensureBelongsToVoucher($nursingVoucherReturn, $nursingVoucher);
        Gate::authorize('rejectReturn', $nursingVoucher);
        $service->reject($nursingVoucherReturn, $request->user(), $request->validated('rejection_reason'));
        $notifications->notifyRejected($nursingVoucherReturn->fresh());

        return back()->with('success', 'La devolución fue rechazada.');
    }

    public function cancel(
        Request $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherReturn $nursingVoucherReturn,
        NursingVoucherReturnService $service,
        NursingVoucherReturnNotificationService $notifications,
    ): RedirectResponse {
        $this->ensureBelongsToVoucher($nursingVoucherReturn, $nursingVoucher);
        Gate::authorize('cancelReturn', $nursingVoucher);
        $service->cancel($nursingVoucherReturn, $request->user());
        $notifications->closeAction($nursingVoucherReturn);

        return back()->with('success', 'La solicitud de devolución fue cancelada.');
    }

    private function ensureBelongsToVoucher(
        NursingVoucherReturn $return,
        NursingVoucher $voucher,
    ): void {
        abort_unless($return->nursing_voucher_id === $voucher->id, 404);
    }
}
