<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'staff_id',
        'quantity_change',
        'reason',
    ];

    /** Marks an adjustment as an archive (expired/spoiled/damaged) record. */
    public const ARCHIVE_SUFFIX = ' / archived';

    /**
     * Archive records for the Archived screens, newest first. Items archived before
     * quantities were recorded appear once, with a null quantity.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function archiveFeed(): Collection
    {
        $records = static::query()
            ->with(['item:id,name,unit,inventory_category_id', 'item.category:id,name', 'staff:id,name'])
            ->where('reason', 'like', '%'.self::ARCHIVE_SUFFIX)
            ->latest()->limit(200)->get()
            ->map(fn ($a) => [
                'id' => 'a'.$a->id,
                'date' => $a->created_at,
                'item' => $a->item?->name,
                'unit' => $a->item?->unit,
                'category' => $a->item?->category?->name,
                'reason' => strtolower(str_replace(self::ARCHIVE_SUFFIX, '', $a->reason)),
                'quantity' => abs((int) $a->quantity_change),
                'by' => $a->staff?->name,
            ]);

        $legacy = InventoryItem::query()->with('category:id,name')
            ->where('status', 'like', 'archived_%')
            ->whereNotIn('id', static::query()->where('reason', 'like', '%'.self::ARCHIVE_SUFFIX)->select('inventory_item_id'))
            ->get()
            ->map(fn ($i) => [
                'id' => 'i'.$i->id, 'date' => $i->updated_at, 'item' => $i->name, 'unit' => $i->unit,
                'category' => $i->category?->name, 'reason' => str_replace('archived_', '', $i->status),
                'quantity' => null, 'by' => null,
            ]);

        return $records->concat($legacy)->values();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
