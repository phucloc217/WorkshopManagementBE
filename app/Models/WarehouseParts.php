<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WarehouseParts extends Model
{
    use HasUuids;
    protected $table = 'warehouse_parts';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'warehouse_id',
        'part_id',
        'qty',
    ];
    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
