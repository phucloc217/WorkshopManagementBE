<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    use HasUuids;
    protected $table = 'parts';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		
		'part_code' => 'string'
	];

	protected $fillable = [
		'part_code',
		'description',
		'min_qty'
	];
}
