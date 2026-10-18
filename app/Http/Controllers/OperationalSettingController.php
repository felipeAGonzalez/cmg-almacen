<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateOperationalSettingRequest;
use App\Models\User;
use App\Services\OperationalScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperationalSettingController extends Controller
{
    public function edit(OperationalScheduleService $schedule): View
    {
        return view('operational-settings.edit', [
            'setting' => $schedule->setting(),
            'schedule' => $schedule,
            'warehouseManagers' => User::query()
                ->where('role', UserRole::WAREHOUSE_MANAGER)
                ->orderBy('name')
                ->orderBy('last_name_one')
                ->get(),
        ]);
    }

    public function update(UpdateOperationalSettingRequest $request, OperationalScheduleService $schedule): RedirectResponse
    {
        $schedule->setting()->update($request->validated());

        return redirect()->route('operational-settings.edit')
            ->with('success', 'El horario operativo se actualizó correctamente.');
    }
}
