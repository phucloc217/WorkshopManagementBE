<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockReceipt extends Model
{
    use HasUuids;
    protected $table = 'stock_receipts';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at'  => 'datetime:d/m/Y H:i',
        'received_at' => 'datetime:d/m/Y H:i',
    ];

    protected $fillable = [
        'receipt_no',
        'warehouse_id',
        'note',
        'status',
        'created_by',
        'created_at',
        'document',
        'import_from',
        'received_at',
        'received_by',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
