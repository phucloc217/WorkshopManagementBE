<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Warehouse extends Model
{
	use HasUuids;
	protected $table = 'warehouses';
	public $incrementing = false;
	public $timestamps = false;

	protected $fillable = [
		'name',
		'description',
		'workshop_id',
		'is_active'
	];

	public function workshop()
	{
		return $this->belongsTo(Workshop::class);
	}
	public function parts()
	{
		return $this->hasMany(WarehouseParts::class);
	}
	public function scopeAccessibleBy($query, $user)
	{
		if ($user->hasRole('admin')) {
			return $query;
		}
		return $query->whereIn('id', $user->warehouseIds());
	}
}
