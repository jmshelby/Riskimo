<?php

use Illuminate\Database\Eloquent\Builder;
use Riskimo\Mongodb\Eloquent\GeospatialTrait;

/**
 * Tracks the positions that a user's group has traveled to or planned to travel to
 *
 * Fields:
 *   -user_id
 *   -unit_count
 */
class Group extends Moloquent
{

	protected $table = 'user_group';

	protected $dates = array('arrival_time', 'departure_time');

	protected $appends = array('current_position', 'historic_positions');

	// == Factories ==============================================================

	public static function createGroup(User $user)
	{
		$group = new static;
		$group->user()->associate($user);
		$group->unit_count = 1;

		$group->save();

		return $group;
	}

	// == Relationships ==========================================================

	public function user()
	{
		return $this->belongsTo('User');
	}

	public function groupPositions()
	{
		return $this->hasMany('GroupPosition');
	}


	// == Accessors ==============================================================

	public function addUnits($count = 1)
	{
		$result = $this->increment('unit_count', $count);
		// TODO if result isn't 1, throw exception ? return false?
		return $this->unit_count;
	}

	public function resetUnits($points = 1)
	{
		$this->unit_count = $points;
		return $this->save();
	}

	// Just for the manager to store positions for other people to fetch
	protected $currentGroupPostion;
	public function setCurrentGroupPosition($position)
	{
		$this->currentGroupPostion = $position;
		return $this;
	}

	public function getCurrentGroupPosition()
	{
		return $this->currentGroupPostion;
	}

	public function getCurrentPositionAttribute($value)
	{
		return $this->getCurrentGroupPosition();
	}


	protected $historicGroupPostions;
	public function setHistoricGroupPositions($positions) {
		$this->historicGroupPositions = $positions;
	}

	public function getHistoricGroupPositions() {
		return $this->historicGroupPositions;
	}

	public function getHistoricPositionsAttribute() {
		return $this->getHistoricGroupPositions();
	}

/*
	// == Scopes =================================================================

	public function scopeNewestFirst($q)
	{
		return $q->orderBy('created_at', 'desc');
	}

	public function scopeLastArrivalFirst($q)
	{
		return $q->orderBy('arrival_time', 'desc');
	}

	public function scopeActive($q)
	{
		return $q->where('abandoned_fl', false);
	}

	public function scopeForUser($q, User $user)
	{
		return $q->where('user_id', $user->id);
	}

	// == Accessors ==============================================================

	public function hasArrived($currentTime = null)
	{
		// Get current time
		if (is_null($currentTime)) {
			$currentTime = Carbon\Carbon::now();
		}

		return $this->arrival_time->lt($currentTime);
	}

	public function abandon($save = true)
	{
		$this->abandoned_fl = true;
		if ($save) {
			$this->save();
		}

		return $this;
	}

	public static function getLastPosition(User $user) {
		return static::forUser($user)->active()->lastArrivalFirst()->first();
	}
*/

}
