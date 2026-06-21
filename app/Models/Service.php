<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Service extends Model
{
    use HasUuids;
    protected $table = 'services';
    public $timestamps = false;
    protected $casts = [
        'estimated_days' => 'decimal:2'
    ];

    protected $fillable = [
        'service_name',
        'estimated_days'
    ];
}
