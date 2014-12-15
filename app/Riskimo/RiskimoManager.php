<?php namespace Riskimo;

use User;
use Base;
use BattalionPosition;

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


	public function userGrowsTroops(User $user)
	{
		// Add on troop point to user
		$points = $user->addTroopPoints();

		// Check if user has enough points for a new trooper
		if ($points > 5) {
			$user->addTroops();
			$user->resetTroopPoints();
		}

		// TODO -- move above logic for checking trooper adding
		// TODO -- make logic more dynamic for point threshold
	}

	public function getUserBases(User $user) {
		return $user->bases()->newestEstablishedFirst()->get();
	}

	// ==== Battalion Stuff ==========================================

	// Command the battalion to start traveling to a position
	public function userCommandsBattalionPosition(User $user, $lat, $long)
	{
		// get most recent position(model); where status == active
		$marker = $this->_getLastPosition($user);

		// if time of arrival is after now:
		if (!$marker->hasArrived()) {
			// calc current position (using func below)
			$calcPos = $this->getUserBattalionPosition($user);
			// create marker for now at position
			$newMarker = BattalionPosition::createMarker($user, $calcPos->lat, $calcPos->long, $marker->origin, $marker->departure_time);
			// disable/inactivate current position(model)
			$marker->abandon();
			// use the new marker now
			$marker = $newMarker;
		}

		// calculate time of arrival from now
		$arrivalTime = $this->_calculateTimeOfArrival($marker->lat, $marker->long, $lat, $long);

		$newMarker = BattalionPosition::createMarker($user, $lat, $long, $marker, null, $arrivalTime);

return $this->getUserBattalionPosition($user);
// TODO -- return more stats
	}

	public function getUserBattalionPosition(User $user)
	{
		// get most recent position(model); where status == active
		$marker = $this->_getLastPosition($user);

		// if time of arrival is before now:
		if ($marker->hasArrived()) {
			// return position; state = awaiting
// TODO -- return more stats
			return (object) array(
				'lat' => $marker->lat,
				'long' => $marker->long,
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
		$percent = $travelTime / $totalTravelTime;
		// get interpolation, position percentage between marker and origin
		$position = $this->_interpolationBetween($marker->origin->lat, $marker->origin->long, $marker->lat, $marker->long, $percent);

		// return position; state = traveling/resting 
// TODO -- return more stats
		return (object) array(
			'lat' => $position->getLat(),
			'long' => $position->getLng(),
			'state' => 'traveling',
			'seconds_remaining' => $totalTravelTime - $travelTime,
			'marker' => $this->_markerData($marker),
		);
		// TODO -- how do figure out if they're resting or traveling
	}

	protected function _getLastPosition(User $user)
	{
		// get most recent position(model); where status == active
		$marker = BattalionPosition::getLastPosition($user);

		// Make sure there is a marker
		if (!$marker) {
			// Get last base established
			$lastBase = $user->bases()->newestEstablishedFirst()->first();
			// Create new marker with position of last base
			$marker = BattalionPosition::createMarker($user, $lastBase->lat, $lastBase->long, null);
			// TODO -- should the marker be created with a date from the base? or now?
		}

		return $marker;
	}

	// ====================================================================

	// How long does it take on average for the battalion to move
	protected function _getTravelRate() {
		// meters per second
		// 1 m/s:
		//   2.2 miles per hour (about)
		//   3.2 feet per second (about)
return 8; // About 17 mph for now (might be a little fast in the long run)
		return 2;
	}

	// What percentage of the time does a battalion need to rest while traveling
	protected function _getRestRate() {
		// Should we do something more realistic
		return 0.2;
	}

	protected function _calculateTimeOfArrival($aLat, $aLong, $bLat, $bLong, $scale = 50)
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
Geo::getEarthRadius();
		$from	= new LatLng($aLat, $aLong);
		$to		= new LatLng($bLat, $bLong);
		return Geo::computeDistanceBetween($from, $to);
	}

	// Position percentage between marker and origin
	protected function _interpolationBetween($aLat, $aLong, $bLat, $bLong, $percentage)
	{
Geo::getEarthRadius();
		$from	= new LatLng($aLat, $aLong);
		$to		= new LatLng($bLat, $bLong);
		return Geo::interpolate($from, $to, $percentage);
	}

	protected function _markerData(BattalionPosition $marker)
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
