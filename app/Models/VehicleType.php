<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasUuids;
    protected $table = 'vehicle_types';
    public $timestamps = false;
   

    protected $fillable = [
        'name'
    ];
}
