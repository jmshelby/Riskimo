<?php

use Illuminate\Database\Eloquent\Builder;
use Riskimo\Mongodb\Eloquent\GeospatialTrait;

/**
 * Tracks the positions that a user's group has traveled to or planned to travel to
 *
 * Fields:
 *   -group_id
 *   -origin_id
 *   -pos
 *   -arrival_time
 *   -departure_time
 *   -abandoned_fl
 *   -distance_from_origin?
 */
class GroupPosition extends Moloquent
{

	use CoordinateTrait;
	use GeospatialTrait;

	protected $table = 'group_position';

	protected $dates = array('arrival_time', 'departure_time');

	// == Factories ==============================================================

	public static function createMarker(Group $group, $lat, $long, GroupPosition $origin = null, $departTime = null, $arrivalTime = null)
	{
		$marker = new static;

		$marker->group()->associate($group);

		$marker->lat = $lat;
		$marker->long = $long;

		if ($origin) {
			$marker->origin()->associate($origin);
		}

		if (is_null($departTime)) {
			$marker->departure_time = $marker->freshTimestamp();
		} else {
			$marker->departure_time = $departTime;
		}

		if (is_null($arrivalTime)) {
			$marker->arrival_time = $marker->freshTimestamp();
		} else {
			$marker->arrival_time = $arrivalTime;
		}

		$marker->save();
		return $marker;
	}

	// == Relationships ==========================================================

	public function group()
	{
		return $this->belongsTo('Group');
	}

	public function user()
	{
		// belongs to User through group (I don't think this relationship exists yet)
		//return $this->belongsTo('User');
	}

	public function origin()
	{
		return $this->belongsTo('GroupPosition');
	}

	// == Event ==================================================================

	protected function performInsert(Builder $query, array $options)
	{
		// Insert, abandoned_fl as false
		$this->abandoned_fl = false;

		return parent::performInsert($query, $options);
	}

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

	public function scopeForGroup($q, Group $group)
	{
		return $q->where('group_id', $group->id);
	}

	// == Accessors ==============================================================

	public function hasArrived($currentTime = null)
	{
		// Get current time
		if (is_null($currentTime)) {
			$currentTime = Carbon\Carbon::now();
		}

		return $this->arrival_time->lte($currentTime);
	}

	public function abandon($save = true)
	{
		$this->abandoned_fl = true;
		if ($save) {
			$this->save();
		}

		return $this;
	}

	public static function getLastPosition(Group $group) {
		return static::forGroup($group)->active()->lastArrivalFirst()->first();
	}

}
