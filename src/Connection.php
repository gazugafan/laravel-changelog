<?php namespace Gazugafan\Changelog;

use Gazugafan\Changelog\Exceptions\ChangelogException;
use Gazugafan\Changelog\ChangeModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Closure;

class Connection
{
	protected $_inTransaction = false;

	protected $_change = null;

	protected $_connection = null;

	protected $_authIDClosure = null;

	protected function getAuthID()
	{
		if ($this->_authIDClosure) {
			$closure = $this->_authIDClosure;
			return $closure($this);
		}
		return Auth::id();
	}

	public function authID(closure $closure)
	{
		$this->_authIDClosure = $closure;
		return $this;
	}

	/**
	 * Returns the ID of the change in progress (or null if there is no change in progress)
	 *
	 * @return null|int The ID of the change in progress (or null if one does not exist)
	 */
	public function getChangeID()
	{
		if ($this->_change)
			return $this->_change->id;

		return null;
	}

	/**
	 * Sets the DB connection name
	 *
	 * @param  string  $name
	 * @return \Illuminate\Database\Connection
	 */
	public function connection($name = null)
	{
		$this->_connection = $name;
		return $this;
	}

	/**
	 * Begins a change (and optionally a transaction as well)
	 *
	 * @param string|null $interface A string describing the interface on which this change is taking place. eg. Public, Backend, API, etc.
	 * @param string|null $notes A string describing the intended change in more details. eg. "Putting widget in a box", "Placing an order", etc.
	 * @param bool $useTransaction Whether or not to begin a transaction as well. Only one transaction exist at a time using this method.
	 */
	public function begin(string $interface = null, string $notes = null, $useTransaction = true)
	{
		if ($this->_change)
			throw new ChangelogException('Cannot begin a change because one is already in progress.');

		if ($useTransaction && $this->_inTransaction)
			throw new ChangelogException('Cannot begin a transaction because one is already in progress.');

		$this->_change = new ChangeModel();
		$this->_change->user_id = $this->getAuthID();
		$this->_change->interface = $interface;
		$this->_change->notes = $notes;
		$this->_change->status = 'pending';
		$this->_change->save();

		if ($useTransaction)
		{
			DB::connection($this->_connection)->beginTransaction();
			$this->_inTransaction = true;
		}
	}

	/**
	 * Commits a change that was started with Change::begin() and marks it as successful. If a transaction was started, it is committed as well.
	 *
	 * @throws ChangelogException If no change is in progress to commit.
	 */
	public function commit()
	{
		if (!$this->_change)
			throw new ChangelogException('Cannot commit a change because there is no change in progress');

		$this->_change->status = 'complete';
		$this->_change->save();

		if ($this->_inTransaction)
		{
			DB::connection($this->_connection)->commit();
			$this->_inTransaction = false;
		}

		$this->_change = null;
	}

	/**
	 * Rolls back a change that was started with Change::begin() and marks it as failed. If a transaction was started, it is rolled back as well.
	 *
	 * @throws ChangelogException If no change is in progress to rollBack
	 */
	public function rollBack()
	{
		if (!$this->_change)
			throw new ChangelogException('Cannot rollBack a change because there is no change in progress');

		if ($this->_inTransaction)
		{
			DB::connection($this->_connection)->rollBack();
			$this->_inTransaction = false;
		}

		$this->_change->status = 'failed';
		$this->_change->save();

		$this->_change = null;
	}

	/**
	 * @param Closure $closure
	 * @param int $attempts The number of attempts to make in case deadlock occurs. If set to 0, a transaction will NOT be used.
	 */
	public function transaction(Closure $closure, string $interface = null, string $notes = null, $attempts = 1)
	{
		if ($this->_change)
			throw new ChangelogException('Cannot begin a change because one is already in progress.');

		if ($attempts > 0 && $this->_inTransaction)
			throw new ChangelogException('Cannot begin a transaction because one is already in progress.');

		$this->_change = new ChangeModel();
		$this->_change->user_id = $this->getAuthID();
		$this->_change->interface = $interface;
		$this->_change->notes = $notes;
		$this->_change->status = 'pending';
		$this->_change->save();

		if ($attempts > 0)
		{
			try
			{
				DB::connection($this->_connection)->transaction($closure, $attempts);
			}
			catch(\Exception $e)
			{
				$this->_change->status = 'failed';
				$this->_change->save();
				throw $e;
			}
		}

		$this->_change->status = 'complete';
		$this->_change->save();
		return true;
	}
}