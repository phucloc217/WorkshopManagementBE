<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Workshop
 * 
 * @property uuid $id
 * @property "char" $workshop_code
 * @property string|null $address
 * @property "char"|null $phone
 * @property bool|null $is_active
 * @property time with time zone|null $created_at
 * 
 * @property Collection|JobOrder[] $job_orders
 *
 * @package App\Models
 */
class Workshop extends Model
{
	protected $table = 'workshops';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'uuid',
		'workshop_code' => '"char"',
		'phone' => '"char"',
		'is_active' => 'bool'
	];

	protected $fillable = [
		'workshop_code',
		'address',
		'phone',
		'is_active'
	];

	public function job_orders()
	{
		return $this->hasMany(JobOrder::class);
	}
}
