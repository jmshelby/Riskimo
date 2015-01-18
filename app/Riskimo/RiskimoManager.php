<?php namespace Riskimo;

use User;
use Base;
use Group;
use GroupPosition;

use Carbon\Carbon;

use Riskimo\Geometry\LatLng;
use Riskimo\Geometry\SphericalGeometry as Geo;

class RiskimoManager
{

	public function __construct()
	{

	}

	public function userEstablishesBase(User $user, $lat, $long)
	{

		// TODO Garbage Collection for expired bases ....
		//    - need to either run GC here, or filter them out of the following query...

		// First, query for nearby bases for this person, just use 2000 meters for now
		$bases = $user->bases()->geoNearCommand($lat, $long, null, 2000);

		// If one found, re-establish that one...
		if ($bases->count())
		{
			$base = $bases->first()->establish();
			$base->save();
		}

		// Create a new base
		else
		{
			$base = Base::createAtLocation($user, $lat, $long);
		}

		return $base;
	}

	public function getUsersLastEstablishedBase(User $user)
	{
		$lastBase = $user->bases()->newestEstablishedFirst()->first();
		if (!$lastBase) {
// TODO -- figure out the best way to initialize a base
$lastBase = $this->userEstablishesBase($user, 45.78, -104.03);
		}
		return $lastBase;
	}

	public function userGrowsUnits(User $user)
	{
		return $user->group->addUnits();
	}

	public function getUserBases(User $user) {
		return $user->bases()->newestEstablishedFirst()->get();
	}

	// ==== Group Stuff ==========================================

	public function fetchGroups()
	{
		// TODO -- add bounding box for viewport query
		// TODO -- should we filter anything out here?
		$groups = Group::all();
		// TODO -- is there a way to make this call faster?
		foreach($groups as $group) {

			$positionData = $this->getGroupPosition($group);
			$group->setCurrentGroupPosition($positionData);

			$historicData = $this->getGroupHistoricPositions($group);
			$group->setHistoricGroupPositions($historicData);

// Load user for consumer view TODO - fix so we can remove
$group->user;
		}
		return $groups;
	}

	// Command the group to start traveling to a position
	public function userCommandsGroupPosition(User $user, $lat, $long)
	{
		// get most recent position(model); where status == active
		$marker = $this->_getLastPosition($user->group);

		// if time of arrival is after now:
		if (!$marker->hasArrived()) {
			// calc current position (using func below)
			$calcPos = $this->getUserGroupPosition($user);
			// create marker for now at position
			$newMarker = GroupPosition::createMarker($user->group, $calcPos->latitude, $calcPos->longitude, $marker->origin, $marker->departure_time);
			// disable/inactivate current position(model)
			$marker->abandon();
			// use the new marker now
			$marker = $newMarker;
		}

		// calculate time of arrival from now
		$arrivalTime = $this->_calculateTimeOfArrival($marker->lat, $marker->long, $lat, $long);

		$newMarker = GroupPosition::createMarker($user->group, $lat, $long, $marker, null, $arrivalTime);

return $this->getUserGroupPosition($user);
// TODO -- return more stats
	}

	public function getUserGroupPosition(User $user)
	{
		return $this->getGroupPosition($user->group);
	}

	public function getGroupPosition(Group $group)
	{
		// get most recent position(model); where status == active
		$marker = $this->_getLastPosition($group);

		// if time of arrival is before now:
		if ($marker->hasArrived()) {
			// return position; state = awaiting
// TODO -- return more stats
			return (object) array(
				'latitude' => $marker->lat,
				'longitude' => $marker->long,
				'state' => 'awaiting',
				'marker' => $this->_markerData($marker),
			);
		}

		// calculate current position
		// get seconds between departure & arrival time
		$totalTravelTime = $marker->departure_time->diffInSeconds($marker->arrival_time);
		// get seconds between departure & now
		$travelTime = $marker->departure_time->diffInSeconds(Carbon::now());
		// get percent of completion (traveled time / travel time)
		$percent = ($totalTravelTime == 0) ? 1 : $travelTime / $totalTravelTime;
		// get interpolation, position percentage between marker and origin
		$position = $this->_interpolationBetween($marker->origin->lat, $marker->origin->long, $marker->lat, $marker->long, $percent);

		// return position; state = traveling/resting 
// TODO -- return more stats
		return (object) array(
			'latitude' => $position->getLat(),
			'longitude' => $position->getLng(),
			'state' => 'traveling',
			'seconds_remaining' => $totalTravelTime - $travelTime,
			'marker' => $this->_markerData($marker),
		);
		// TODO -- how do figure out if they're resting or traveling
	}

	protected function _getLastPosition(Group $group)
	{
		// get most recent position(model); where status == active
		$marker = GroupPosition::getLastPosition($group);

		// Make sure there is a marker
		if (!$marker) {
			// Get last base established
			$lastBase = $this->getUsersLastEstablishedBase($group->user);
			// Create new marker with position of last base
			$marker = GroupPosition::createMarker($group, $lastBase->lat, $lastBase->long, null);
			// TODO -- should the marker be created with a date from the base? or now?
		}

		return $marker;
	}

	// ====================================================================

	public function getGroupHistoricPositions($group) {
		$positions = GroupPosition::forGroup($group)->active()->lastArrivalFirst()->get();

		$returnData = array();
		foreach($positions as $position) {

			// Skip the target position
			if (!$position->hasArrived()) continue;

			$returnData[] = (object) array(
				'latitude' => $position->lat,
				'longitude' => $position->long,
			);
		}

		return $returnData;
	}

	// ====================================================================

	// How long does it take on average for the group to move
	protected function _getTravelRate() {
		// meters per second
		// 1 m/s:
		//   2.2 miles per hour (about)
		//   3.2 feet per second (about)
return 8; // About 17 mph for now (might be a little fast in the long run)
		return 2;
	}

	// What percentage of the time does a group need to rest while traveling
	protected function _getRestRate() {
		// Should we do something more realistic
		return 0.2;
	}

	protected function _calculateTimeOfArrival($aLat, $aLong, $bLat, $bLong, $scale = 5000)
	{
		// calculate distance between current, and new
		$distance = $this->_distanceBetween($aLat, $aLong, $bLat, $bLong);
		// calculate travel time
		// calc initial travel time: d / travelRate (meters per second)
		$initialSeconds = $distance / $this->_getTravelRate();
		// add sleep/rest time: d * restRate (some percentage of time needed to rest)
		$weightedSeconds = $initialSeconds * ( $this->_getRestRate() + 1.0);
		// TODO -- decrease factor so things are scaled as faster
		$travelSeconds = $weightedSeconds / $scale;

		// Return time from now, second later
		return Carbon::now()->addSeconds($travelSeconds);
	}

	// Distance between two points in meters
	protected function _distanceBetween($aLat, $aLong, $bLat, $bLong)
	{
Geo::getEarthRadius(); // for autoload
		$from	= new LatLng($aLat, $aLong);
		$to		= new LatLng($bLat, $bLong);
		return Geo::computeDistanceBetween($from, $to);
	}

	// Position percentage between marker and origin
	protected function _interpolationBetween($aLat, $aLong, $bLat, $bLong, $percentage)
	{
Geo::getEarthRadius(); // for autoload
		$from	= new LatLng($aLat, $aLong);
		$to		= new LatLng($bLat, $bLong);
		return Geo::interpolate($from, $to, $percentage);
	}

	protected function _markerData(GroupPosition $marker)
	{
		return (object) array(
			'id' => $marker->id,
			'origin_id' => $marker->origin_id,
			'location' => array(
				'latitude' => $marker->lat, 
				'longitude' => $marker->long,
			),
			'arrival_time' => (string) $marker->arrival_time,
			'departure_time' => (string) $marker->departure_time,
		);
	}

}
