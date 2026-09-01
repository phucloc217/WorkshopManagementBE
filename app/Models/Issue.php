<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Issue extends Model
{
    use HasUuids;

    protected $table = 'issues';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];
}