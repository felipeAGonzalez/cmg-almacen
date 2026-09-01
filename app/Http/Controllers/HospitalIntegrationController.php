<?php

namespace App\Http\Controllers;

use App\Exceptions\HospitalIntegrationException;
use App\Services\HospitalPatientService;
use Illuminate\View\View;

class HospitalIntegrationController extends Controller
{
    public function patients(HospitalPatientService $service): View
    {
        $patients = collect();
        $integrationError = null;

        try {
            $patients = $service->activePatients();
        } catch (HospitalIntegrationException $exception) {
            $integrationError = $exception->safeMessage;
        }

        return view('hospital-integration.patients', [
            'patients' => $patients,
            'integrationError' => $integrationError,
        ]);
    }
}
