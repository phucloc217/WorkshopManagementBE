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
class User extends Model
{
	use HasApiTokens, Notifiable;
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
		'phone'
	];

	public function task_logs()
	{
		return $this->hasMany(TaskLog::class);
	}
}
