<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class OperationalSetting extends Model
{
    public const CURRENT_ID = 1;

    protected $fillable = [
        'warehouse_service_start_time',
        'warehouse_service_end_time',
    ];

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
            ],
        );
    }
}
