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
 * Class JobTask
 * 
 * @property uuid $id
 * @property uuid|null $job_order_id
 * @property string|null $task_name
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $completed_at
 * @property bool|null $is_warranty
 * 
 * @property JobOrder|null $job_order
 * @property Collection|TaskLog[] $task_logs
 *
 * @package App\Models
 */
class JobTask extends Model
{
	use HasUuids;
	protected $table = 'job_tasks';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'completed_at' => 'datetime',
		'created_at' => 'datetime',
		'started_at' => 'datetime',
		'is_warranty' => 'bool'
	];

	protected $fillable = [
		'job_order_id',
		'task_name',
		'status',
		'completed_at',
		'completed_by',
		'is_warranty',
		'created_at',
		'started_at',
		'created_by',
		'started_by'
	];

	public function jobOrder()
	{
		return $this->belongsTo(JobOrder::class);
	}

	public function task_logs()
	{
		return $this->hasMany(TaskLog::class);
	}
	public function createdBy()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
	public function startedBy()
	{
		return $this->belongsTo(User::class, 'started_by');
	}
	public function completedBy()
	{
		return $this->belongsTo(User::class, 'completed_by');
	}
}
