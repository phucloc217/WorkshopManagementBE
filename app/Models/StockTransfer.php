<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockTransfer extends Model
{
    use HasUuids;
    protected $table = 'stock_transfers';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at'     => 'datetime',
        'approved_at'    => 'datetime',
        'transferred_at' => 'datetime',
        'received_at'    => 'datetime',
    ];

    protected $fillable = [
        'transfer_no',
        'from_warehouse_id',
        'to_warehouse_id',
        'document',
        'transfer_reason',
        'note',
        'status',
        'created_by',
        'approved_by',
        'transferred_by',
        'received_by',
        'created_at',
        'approved_at',
        'transferred_at',
        'received_at',
        'reference_type',
        'reference_id',
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
    public function scopeAccessibleBy($query, $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }
        return $query->whereIn('warehouse_id', $user->warehouseIds());
    }
    // StockTransfer.php
    public function scopeAccessibleBy($query, $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $ids = $user->warehouseIds();

        return $query->where(function ($q) use ($ids) {
            $q->whereIn('from_warehouse_id', $ids)
                ->orWhereIn('to_warehouse_id', $ids);   // ✅ kho đích cũng được tính
        });
    }
}
