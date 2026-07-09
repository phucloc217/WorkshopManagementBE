<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $table = 'login_logs';
    public $timestamps = false;

    protected $casts = [
        'status'   => 'boolean',
        'login_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'phone',
        'ip',
        'user_agent',
        'browser',
        'system',
        'status',
        'login_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
