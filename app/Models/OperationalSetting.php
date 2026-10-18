<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalSetting extends Model
{
    public const CURRENT_ID = 1;

    protected $fillable = [
        'warehouse_service_start_time',
        'warehouse_service_end_time',
        'warehouse_rest_day',
        'administration_voucher_delegate_user_id',
        'administration_voucher_delegate_day',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_rest_day' => 'integer',
            'administration_voucher_delegate_day' => 'integer',
        ];
    }

    public function administrationVoucherDelegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administration_voucher_delegate_user_id');
    }

    protected function warehouseServiceStartTime(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strlen($value) === 5 ? $value.':00' : $value);
    }

    protected function warehouseServiceEndTime(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strlen($value) === 5 ? $value.':00' : $value);
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => self::CURRENT_ID],
            [
                'warehouse_service_start_time' => '09:00:00',
                'warehouse_service_end_time' => '17:00:00',
                'warehouse_rest_day' => 7,
                'administration_voucher_delegate_user_id' => null,
                'administration_voucher_delegate_day' => null,
            ],
        );
    }
}
