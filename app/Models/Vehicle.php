<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Vehicle
 * 
 * @property uuid $id
 * @property string|null $vin
 * @property string|null $model
 * @property string|null $motor_number
 * @property time with time zone|null $created_at
 * 
 * @property Collection|JobOrder[] $job_orders
 *
 * @package App\Models
 */
class Vehicle extends Model
{
	protected $table = 'vehicles';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'uuid'
	];

	protected $fillable = [
		'vin',
		'model',
		'motor_number'
	];

	public function job_orders()
	{
		return $this->hasMany(JobOrder::class);
	}
}
