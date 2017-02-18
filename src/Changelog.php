<?php namespace Gazugafan\Changelog;

use Gazugafan\Changelog\Exceptions\ChangelogException;
use Gazugafan\Changelog\ChangelogObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

trait Changelog
{
	/********************************************************************************
	 * Overridable Options
	 * Set a protected (non-static) property without the _changelog_ prefix to override.
	 ********************************************************************************/

	protected static $_changelog_forceChangelogging = true;
	protected static $_changelog_changeIDColumn = 'change_id';


	/********************************************************************************
	 * Option Getters
	 ********************************************************************************/

	public function getForceChangeLogging() { return isset($this->forceChangeLogging)?$this->forceChangeLogging:static::$_changelog_forceChangelogging; }
	public function getChangeIDColumn() { return isset($this->changeIDColumn)?$this->changeIDColumn:static::$_changelog_changeIDColumn; }


	/********************************************************************************
	 * Relationships
	 ********************************************************************************/

	public function change() { return $this->belongsTo('Gazugafan\Changelog\ChangeModel'); }



	/********************************************************************************
	 * Method Overrides
	 ********************************************************************************/

    /**
     * Add the global scope
     */
    public static function bootChangelog()
    {
		//static::addGlobalScope(new Scopes\TemporalScope);
		static::observe(ChangelogObserver::class);
    }


	/********************************************************************************
	 * New Methods
	 ********************************************************************************/


	/********************************************************************************
	 * Static Methods
	 ********************************************************************************/
}