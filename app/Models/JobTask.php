<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
	protected $table = 'job_tasks';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'uuid',
		'job_order_id' => 'uuid',
		'completed_at' => 'datetime',
		'is_warranty' => 'bool'
	];

	protected $fillable = [
		'job_order_id',
		'task_name',
		'status',
		'completed_at',
		'is_warranty'
	];

	public function job_order()
	{
		return $this->belongsTo(JobOrder::class);
	}

	public function task_logs()
	{
		return $this->hasMany(TaskLog::class);
	}
}
