<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Customer
 * 
 * @property uuid $id
 * @property string|null $name
 * @property string|null $phone
 * @property Carbon|null $created_at
 * 
 * @property Collection|JobOrder[] $job_orders
 *
 * @package App\Models
 */
class Customer extends Model
{
	protected $table = 'customers';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'uuid'
	];

	protected $fillable = [
		'name',
		'phone'
	];

	public function job_orders()
	{
		return $this->hasMany(JobOrder::class);
	}
}
