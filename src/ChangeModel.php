<?php namespace Gazugafan\Changelog;

use Gazugafan\Changelog\Exceptions\ChangelogException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Closure;

class ChangeModel extends Model
{
	protected $table = 'changes';

	//attributes that are mass assignable...
	protected $fillable = [];
}