<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TaskLog
 * 
 * @property uuid $id
 * @property uuid|null $job_task_id
 * @property string|null $action_name
 * @property Carbon|null $created_at
 * @property int|null $user_id
 * 
 * @property JobTask|null $job_task
 * @property User|null $user
 *
 * @package App\Models
 */
class TaskLog extends Model
{
	protected $table = 'task_logs';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'uuid',
		'job_task_id' => 'uuid',
		'user_id' => 'int'
	];

	protected $fillable = [
		'job_task_id',
		'action_name',
		'user_id'
	];

	public function job_task()
	{
		return $this->belongsTo(JobTask::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
