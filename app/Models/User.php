<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User
 * 
 * @property int $id
 * @property string $name
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool|null $is_active
 * @property string|null $phone
 * 
 * @property Collection|TaskLog[] $task_logs
 *
 * @package App\Models
 */
class User extends Authenticatable
{
	use HasApiTokens, Notifiable, HasRoles;
	protected $table = 'users';

	protected $casts = [
		'is_active' => 'bool'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'name',
		'password',
		'remember_token',
		'is_active',
		'phone',
		'workshop_id'
	];

	public function task_logs()
	{
		return $this->hasMany(TaskLog::class);
	}
	public function workshop()
	{
		return $this->belongsTo(Workshop::class);
	}
	public function workshops()
	{
		return $this->belongsToMany(Workshop::class, 'user_workshops')
			->withPivot('id');
	}

	public function warehouses()
	{
		return $this->belongsToMany(Warehouse::class, 'user_warehouses')
			->withPivot('id');
	}
	// Helper methods
	public function workshopIds(): array
	{
		return $this->workshops()->pluck('workshops.id')->toArray();
	}

	public function warehouseIds(): array
	{
		return $this->warehouses()->pluck('warehouses.id')->toArray();
	}

	public function canAccessWorkshop(string $workshopId): bool
	{
		return $this->hasRole('admin') || in_array($workshopId, $this->workshopIds());
	}

	public function canAccessWarehouse(string $warehouseId): bool
	{
		return $this->hasRole('admin') || in_array($warehouseId, $this->warehouseIds());
	}
}
