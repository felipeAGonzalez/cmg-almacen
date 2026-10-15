<?php

namespace App\Http\Requests;

class UpdateAdministrationVoucherRequest extends StoreAdministrationVoucherRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('administrationVoucher');

        return $voucher !== null && $this->user()?->can('update', $voucher) === true;
    }
}
