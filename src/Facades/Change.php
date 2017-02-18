<?php namespace Gazugafan\Changelog\Facades;

use Illuminate\Support\Facades\Facade;

class Change extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'Change';
	}
}
