<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StockIssueItem extends Model
{
    use HasUuids;
    protected $table = 'stock_issue_items';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'stock_issue_id',
        'part_id',
        'qty',
    ];

    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function issue()
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }
}