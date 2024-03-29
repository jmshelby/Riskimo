<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;

use Illuminate\Database\Eloquent\Builder;

class User extends Moloquent implements UserInterface, RemindableInterface {

	use UserTrait, RemindableTrait;

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'user';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('password', 'remember_token');

	// == Relationships ==========================================================

	public function bases()
	{
		return $this->hasMany('Base');
	}

	public function group()
	{
		return $this->hasOne('Group');
	}

	/*
	public function addTroopPoints($points = 1)
	{
		$result = $this->increment('troop_points', $points);
		// TODO if result isn't 1, throw exception ? return false?
		return $this->troop_points;
	}

	public function resetTroopPoints($points = 0)
	{
		$this->troop_points = $points;
		return $this->save();
	}
	 */

	// == Event ==================================================================

	protected function performInsert(Builder $query, array $options)
	{
		$result = parent::performInsert($query, $options);

		// Create a group record for the User
		Group::createGroup($this);

		return $result;
	}

}
