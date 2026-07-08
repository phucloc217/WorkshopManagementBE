<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockReceiptItem extends Model
{
    use HasUuids;
    protected $table = 'stock_receipt_items';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'stock_receipt_id',
        'part_id',
        'qty',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function receipt()
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }
}