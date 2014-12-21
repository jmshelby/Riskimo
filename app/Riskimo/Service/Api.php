<?php namespace Riskimo\Service;

use User;
use Base;
use Riskimo\RiskimoManager;

use Input;
use Exception;

class Api
{

	protected $_riskimoMan;

	public function __construct(RiskimoManager $riskimoMan)
	{
		$this->_riskimoMan = $riskimoMan;
	}

// == temp - prototype easifiers =================

	public function getParamUserName()
	{
		if (!Input::has('username')) {
			throw new Exception("Username param required");
		} else if (!Input::get('username')) {
			throw new Exception("Username param required");
		}
		return Input::get('username');
	}

	protected function _createUser($username)
	{
		$hash = \Hash::make('password');
		$user = new User;
		$user->username = $username;
		$user->password = $hash;
		$user->save();
		return $user;
	}

	protected $_user;
	public function getUser()
	{
		if (is_null($this->_user)) {
			$user = User::whereUsername($this->getParamUserName())->first();
			if (!$user) {
				$user = $this->_createUser($this->getParamUserName());
			}
			$this->_user = $user;
		}
		return $this->_user;
	}

// ===============================================

	public function getParamLatitude()
	{
		if (!Input::has('latitude')) {
			throw new Exception("Latitude param required");
		} else if (!Input::get('latitude')) {
			throw new Exception("Latitude param required");
		}
		return Input::get('latitude');
	}

	public function getParamLongitude()
	{
		if (!Input::has('longitude')) {
			throw new Exception("Longitude param required");
		} else if (!Input::get('longitude')) {
			throw new Exception("Longitude param required");
		}
		return Input::get('longitude');
	}

// === Overrides to auto pull params =============

	public function userEstablishesBase()
	{
		$user = $this->getUser();
		$lat = $this->getParamLatitude();
		$long = $this->getParamLongitude();
		return $this->_riskimoMan->userEstablishesBase($user, $lat, $long);
	}

	public function userCommandsGroupPosition()
	{
		$user = $this->getUser();
		$lat = $this->getParamLatitude();
		$long = $this->getParamLongitude();
		return $this->_riskimoMan->userCommandsGroupPosition($user, $lat, $long);
	}

	public function fetchGroups()
	{
		return $this->_riskimoMan->fetchGroups();
	}

// ===============================================

	public function __call($method, $parameters)
	{
		// Forward to proximo manager, with player as first param
		$callback = array($this->_riskimoMan, $method);
		// TODO - only add user object param if the function starts with "user" ??
		array_unshift($parameters, $this->getUser());
		return call_user_func_array(
			$callback,
			$parameters
		);
	}

}
