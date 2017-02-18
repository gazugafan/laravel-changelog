<?php namespace Gazugafan\Changelog;

use Gazugafan\Changelog\Exceptions\ChangelogException;
use Gazugafan\Changelog\Facades\Change;

class ChangelogObserver
{
	public function saving($record)
	{
		if ($record->isDirty())
		{
			$record->{$record->getChangeIDColumn()} = Change::getChangeID();
			if (!$record->{$record->getChangeIDColumn()} && $record->getForceChangeLogging())
			{
				throw new ChangelogException('Cannot save this model outside of a change log (because forceChangeLogging is enabled). Start a new change with Change::begin() first.');
			}
		}
	}
}