<?php

use Riskimo\Mongodb\Eloquent\GeospatialTrait;

class Base extends Moloquent
{

	use CoordinateTrait;
	use GeospatialTrait;

	protected $table = 'base';

	protected $dates = array('established_at');

	// == Factories ==============================================================

	public static function createAtLocation(User $user, $lat, $long)
	{
		$base = new static;

		$base->user()->associate($user);

		$base->lat = $lat;
		$base->long = $long;

		// Mark initial timestamp established
		$base->establish(false);

		$base->save();
		return $base;
	}

	// == Relationships ==========================================================

	public function user()
	{
		return $this->belongsTo('User');
	}

	// == Scopes =================================================================

	public function scopeNewestFirst($q)
	{
		return $q->orderBy('created_at', 'desc');
	}

	public function scopeNewestEstablishedFirst($q)
	{
		return $q->orderBy('established_at', 'desc');
	}

	// == Accessors ==============================================================

	public function establish($save = true)
	{
		if (!is_numeric($this->established_count)) {
			$this->established_count = 0;
		}

		$this->established_count++;
		$this->established_at = $this->freshTimestamp();

		if ($save) {
			$this->save();
		}

		return $this;
	}

}

