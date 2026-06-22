<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JobPart extends Model
{
    use HasUuids;
    protected $table = 'job_parts';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'issued_date' => 'datetime',
        'is_warranty' => 'bool'
    ];
    protected $fillable = [
        'job_order_id',
        'part_id',
        'qty',
        'qty_issued',
        'is_warranty',
        'qty_actual_use',
        'issued_by',
        'received_by',
        'created_at',
        'created_by',
        'issued_date'
    ];
    public function job_order()
	{
		return $this->belongsTo(JobOrder::class);
	}

	public function task_logs()
	{
		return $this->hasMany(TaskLog::class);
	}
    public function part()
{
    return $this->belongsTo(Part::class, 'part_id');
}
}
