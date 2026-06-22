<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Class JobOrder
 * 
 * @property uuid $id
 * @property string $order_no
 * @property uuid|null $workshop_id
 * @property uuid|null $vehicle_id
 * @property uuid|null $customer_id
 * @property string|null $overall_status
 * @property string|null $issue_description
 * @property Carbon|null $received_date
 * @property Carbon|null $created_at
 * @property Carbon|null $delivered_date
 * 
 * @property Workshop|null $workshop
 * @property Vehicle|null $vehicle
 * @property Customer|null $customer
 * @property Collection|JobTask[] $job_tasks
 *
 * @package App\Models
 */
class JobOrder extends Model
{
	use HasUuids;
	protected $table = 'job_orders';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'workshop_id' => 'string',
		'vehicle_id' => 'string',
		'customer_id' => 'string',
		'received_date' => 'datetime',
		'delivered_date' => 'datetime'
	];

	protected $fillable = [
		'order_no',
		'workshop_id',
		'vehicle_id',
		'customer_id',
		'overall_status',
		'issue_description',
		'received_date',
		'delivered_date',
		'odo',
		'type'
	];

	public function workshop()
	{
		return $this->belongsTo(Workshop::class);
	}

	public function vehicle()
	{
		return $this->belongsTo(Vehicle::class);
	}

	public function customer()
	{
		return $this->belongsTo(Customer::class);
	}

	public function job_tasks()
	{
		return $this->hasMany(JobTask::class);
	}
	public function tasks()
	{
		return $this->hasMany(JobTask::class, 'job_order_id');
	}
	public function parts()
	{
		return $this->hasMany(JobPart::class, 'job_order_id');
	}
}
