<?php

namespace App\Http\Requests;

use App\Models\NursingVoucher;
use Illuminate\Foundation\Http\FormRequest;

class RejectNursingVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('nursingVoucher');

        return $voucher instanceof NursingVoucher
            && $this->user()?->can('reject', $voucher) === true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['rejection_reason.required' => 'El motivo del rechazo es obligatorio.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_reason' => trim((string) $this->input('rejection_reason')),
        ]);
    }
}
