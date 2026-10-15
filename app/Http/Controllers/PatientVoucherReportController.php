<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientVoucherReportRequest;
use App\Services\PatientVoucherReportService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;

class PatientVoucherReportController extends Controller
{
    public function index(PatientVoucherReportRequest $request, PatientVoucherReportService $report): View
    {
        $filters = $request->dateRange();

        return view('reports.patient-vouchers.index', [
            'filters' => $filters,
            'patients' => $report->patients($filters),
        ]);
    }

    public function show(PatientVoucherReportRequest $request, PatientVoucherReportService $report): View
    {
        $filters = $request->dateRange();

        try {
            $patientKey = Crypt::decryptString((string) $request->validated('patient'));
        } catch (DecryptException) {
            abort(404);
        }

        return view('reports.patient-vouchers.show', [
            'filters' => $filters,
            ...$report->patient($patientKey, $filters),
        ]);
    }
}
