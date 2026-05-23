<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
	use HasUuids;
	protected $table = 'workshops';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		
		'workshop_code' => 'string',
		'phone' => 'string',
		'is_active' => 'bool'
	];

	protected $fillable = [
		'workshop_code',
		'name',
		'address',
		'phone',
		'is_active'
	];

	public function job_orders()
	{
		return $this->hasMany(JobOrder::class);
	}
}
