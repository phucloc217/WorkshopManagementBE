<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockIssue extends Model
{
    use HasUuids;
    protected $table = 'stock_issues';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    protected $fillable = [
        'issue_no',
        'warehouse_id',
        'job_order_id',
        'note',
        'status',
        'created_by',
        'created_at',
        
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function jobOrder()
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function items()
    {
        return $this->hasMany(StockIssueItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}