<?php

namespace App\Services;

use App\Domain\AuditAction;
use App\Domain\MoveType;
use App\Domain\RefType;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Admin;
use App\Models\DamageLog;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only thing allowed to touch inventory.stock. Every change is a signed
 * ledger row in the same transaction, and the availability guard lives inside
 * the UPDATE — never a read-then-write clamp (invariant 7, BUSINESS_LOGIC §3).
 */
class InventoryService
{
    public const MAX_STOCK = 100000;

    public function __construct(private AuditService $audit) {}

    /**
     * Apply a signed stock movement. Caller must already be inside a
     * transaction. Refuses (and rolls back the caller) rather than clamping.
     */
    public function move(
        int $inventoryId,
        string $type,
        int $qty,
        string $reason,
        ?string $refType = null,
        ?int $refId = null,
        ?Admin $actor = null,
    ): void {
        if ($qty === 0) {
            throw new \InvalidArgumentException('A stock movement cannot have qty 0.');
        }
        if (! in_array($type, MoveType::ALL, true)) {
            throw new \InvalidArgumentException("Unknown move type: {$type}");
        }

        // Guard inside the write: a decrease is only applied if it leaves a
        // non-negative stock. Affected-rows = 0 means insufficient stock.
        $affected = DB::update(
            'UPDATE inventory SET stock = stock + ? WHERE inventory_id = ? AND stock + ? >= 0 AND stock + ? <= ?',
            [$qty, $inventoryId, $qty, $qty, self::MAX_STOCK]
        );

        if ($affected === 0) {
            $available = (int) DB::table('inventory')
                ->where('inventory_id', $inventoryId)
                ->value('stock');

            if ($qty < 0) {
                throw InsufficientStockException::for($inventoryId, abs($qty), $available);
            }

            throw new DomainException(
                'That change would push stock past the maximum of '.number_format(self::MAX_STOCK).'.',
                ['inventory_id' => $inventoryId]
            );
        }

        StockMovement::create([
            'inventory_id' => $inventoryId,
            'move_type' => $type,
            'qty' => $qty,
            'reason' => $reason,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_by' => $actor?->admin_id,
        ]);
    }

    public function available(int $inventoryId): int
    {
        return (int) DB::table('inventory')->where('inventory_id', $inventoryId)->value('stock');
    }

    /**
     * @param  array{name:string,category:string,unit_price:numeric,wholesale_price:numeric,low_stock_threshold?:int,initial_stock?:int}  $data
     */
    public function create(array $data, ?Admin $actor = null): InventoryItem
    {
        return DB::transaction(function () use ($data, $actor) {
            $item = InventoryItem::create([
                'name' => $data['name'],
                'category' => $data['category'],
                'unit_price' => $data['unit_price'],
                'wholesale_price' => $data['wholesale_price'],
                'stock' => 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 10,
            ]);

            if (! empty($data['initial_stock'])) {
                $this->move($item->inventory_id, MoveType::IN, (int) $data['initial_stock'], 'Opening stock', RefType::MANUAL, null, $actor);
            }

            $this->audit->admin(AuditAction::INVENTORY_CREATED, $actor?->admin_id, $actor?->admin_name, 'inventory', $item->inventory_id, $item->name);

            return $item->refresh();
        });
    }

    /**
     * @param  array{name?:string,category?:string,unit_price?:numeric,wholesale_price?:numeric,low_stock_threshold?:int}  $data
     */
    public function update(int $inventoryId, array $data, ?Admin $actor = null): InventoryItem
    {
        return DB::transaction(function () use ($inventoryId, $data, $actor) {
            $item = InventoryItem::findOrFail($inventoryId);

            $item->fill(array_filter([
                'name' => $data['name'] ?? null,
                'category' => $data['category'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'wholesale_price' => $data['wholesale_price'] ?? null,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            ], fn ($v) => $v !== null));
            $item->save();

            $this->audit->admin(AuditAction::INVENTORY_UPDATED, $actor?->admin_id, $actor?->admin_name, 'inventory', $item->inventory_id, $item->name);

            return $item;
        });
    }

    public function archive(int $inventoryId, ?Admin $actor = null): InventoryItem
    {
        return DB::transaction(function () use ($inventoryId, $actor) {
            $item = InventoryItem::findOrFail($inventoryId);
            $item->update(['is_active' => false, 'archived_at' => now()]);

            $this->audit->admin(AuditAction::INVENTORY_ARCHIVED, $actor?->admin_id, $actor?->admin_name, 'inventory', $item->inventory_id, $item->name);

            return $item;
        });
    }

    public function restore(int $inventoryId, ?Admin $actor = null): InventoryItem
    {
        return DB::transaction(function () use ($inventoryId, $actor) {
            $item = InventoryItem::findOrFail($inventoryId);
            $item->update(['is_active' => true, 'archived_at' => null]);

            $this->audit->admin(AuditAction::INVENTORY_RESTORED, $actor?->admin_id, $actor?->admin_name, 'inventory', $item->inventory_id, $item->name);

            return $item;
        });
    }

    /** Set stock to an exact value, recorded as a signed Adjustment movement. */
    public function adjustStock(int $inventoryId, int $newStock, string $reason, ?Admin $actor = null): InventoryItem
    {
        if ($newStock < 0) {
            throw new DomainException('Stock cannot be negative.');
        }

        return DB::transaction(function () use ($inventoryId, $newStock, $reason, $actor) {
            $item = InventoryItem::findOrFail($inventoryId);
            $delta = $newStock - $item->stock;

            if ($delta !== 0) {
                $this->move($inventoryId, MoveType::ADJUSTMENT, $delta, $reason, RefType::MANUAL, null, $actor);
            }

            $this->audit->admin(
                AuditAction::STOCK_ADJUSTED,
                $actor?->admin_id,
                $actor?->admin_name,
                'inventory',
                $inventoryId,
                "Stock {$item->stock} -> {$newStock} ({$reason})"
            );

            return $item->refresh();
        });
    }

    public function logDamage(
        int $inventoryId,
        int $sacks,
        string $damageType,
        string $dateLogged,
        ?string $notes = null,
        ?Admin $actor = null,
    ): DamageLog {
        if ($sacks <= 0) {
            throw new DomainException('Damage must be at least one sack.');
        }

        return DB::transaction(function () use ($inventoryId, $sacks, $damageType, $dateLogged, $notes, $actor) {
            $item = InventoryItem::findOrFail($inventoryId);

            // Placeholder avoids a UNIQUE collision before the real code exists.
            $damage = DamageLog::create([
                'damage_code' => 'TMP'.Str::upper(Str::random(8)),
                'inventory_id' => $inventoryId,
                'variety_name' => $item->name,
                'damage_type' => $damageType,
                'sacks' => $sacks,
                'date_logged' => $dateLogged,
                'notes' => $notes,
                'logged_by' => $actor?->admin_id,
            ]);

            $damage->update(['damage_code' => 'DMG-'.str_pad((string) $damage->damage_log_id, 6, '0', STR_PAD_LEFT)]);

            // Guarded decrease — throws and rolls back the damage row if short.
            $this->move($inventoryId, MoveType::OUT, -$sacks, "Damage: {$damageType}", RefType::DAMAGE, $damage->damage_log_id, $actor);

            $this->audit->admin(AuditAction::DAMAGE_LOGGED, $actor?->admin_id, $actor?->admin_name, 'damage_log', $damage->damage_log_id, "{$item->name} x{$sacks}");

            return $damage->refresh();
        });
    }
}
