<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'notes',
    ];

    protected function casts(): array
    {
        return ['invoice_date' => 'date'];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<EntryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EntryItem::class);
    }

    /** @return Attribute<string, never> */
    protected function total(): Attribute
    {
        return Attribute::get(function (): string {
            $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

            return $items->reduce(
                fn (string $total, EntryItem $item): string => bcadd($total, $item->subtotal, 7),
                '0.0000000',
            );
        });
    }
}
