<?php namespace Riskimo;

use User;
use Base;

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


}


