<?php namespace Gazugafan\Changelog;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
	public function boot()
	{
	}

	public function register()
	{
		$this->app->singleton('Change', function ($app) {
			return new \Gazugafan\Changelog\Connection();
		});
	}
}