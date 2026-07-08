<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockTransferItem extends Model
{
    use HasUuids;
    protected $table = 'stock_transfer_items';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_id',
        'part_id',
        'qty',
        'qty_received',
        'note',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }
}
