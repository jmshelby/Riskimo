<?php

use Illuminate\Support\Collection as Collection;

use Riskimo\Service\Api as ApiService;

class WebserviceApiController extends Controller
{

	public $service;

	public function __construct(ApiService $service)
	{
		$this->service = $service;
	}

	public function _getUser()
	{
		return $this->service->getUser();
	}

	// ========================================================================

	protected function _response_exception(Exception $e)
	{
		return Response::json(array(
			'status' => 'fail',
			'statusText' => $e->getMessage(),
			'stackTrace' => $e->getTrace(),
		));
	}

	protected function _response_error($message)
	{
		return Response::json(array(
			'status' => 'error',
			'statusText' => $message,
		));
	}

	protected function _response_success($response = null, $statusText = null)
	{
		$returnArray = array(
			'status' => 'success',
			'response' => $response,
		);
		if (!is_null($statusText)) {
			$returnArray['statusText'] = $statusText;
		}
		return Response::json($returnArray);
	}

	// ========================================================================

	public function getUser()
	{
		try {
			$user = $this->_getUser();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		} 
		return $this->_response_success($this->_formatUser($user));
	}

	public function getUserPost()
	{
		try {
			$user = $this->_getUser();
			// TODO -- move below logic to manager
			$input = Input::except('username');
			foreach($input as $key => $value) {
				if (in_array($key, array('_id', 'id', 'username', 'created_at', 'updated_at'))) {
					continue;
				}

				$customData = $user->custom_data;
				if (!is_array($customData)) {
					$customData = array();
				}
				$customData[$key] = $value;
				$user->custom_data = $customData;

			}
			$user->save();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}
		return $this->_response_success($this->_formatUser($user));
	}

	public function getStats()
	{
		// Return the overview stats/status of the user
		//
		//  - Team info
		//    - Team ID
		//    - Basic info/stats
		//
		//   - Base Stats
		//     - Location of each
		//     - Age / Expiration
		//     - Connection with another base??
		//
		//   - Team Base Stats
		//     - Location of each
		//     - Age / Expiration
		//     - Connection with other bases??
		//
		//   - Number of troops
		//   - Location of troops
		//
		return $this->_response_error("Not implemented yet");
	}

	public function getBoard()
	{
		// Return the overview of the board
		//
		//   - Current Player's ID
		//
		//   - UnitGroups
		//     - group id (key)
		//       - location
		//       - status (waiting; moving)
		//       - units (number of)
		//       - player id
		//
		try {
			// TODO -- abstract this out sometime
			$user = null;
			if (Input::has('username')) {
				$username = Input::get('username');
				$user = $this->service->getUser();
			}

			$groups = $this->service->fetchGroups();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}
		// TODO - make renderer
		$response = array(
			'groups' => $groups,
			// 'forts' => $forts,
		);

		if ($user) $response['user'] = $this->_formatUser($user);

		return $this->_response_success($response);
	}

	public function getBases() {
		try {
			$bases = $this->service->getUserBases();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}

		return $this->_response_success($this->_formatBase($bases));
	}


	public function anyEstablishBase()
	{
		// "Check-in" to a location to establish as a base for you
		// If you are in a certain proximity to a current base, then
		// you are re-establishing it, or resetting it's expiration
		//
		// Require
		//   - Long/Lat

		try {
			$base = $this->service->userEstablishesBase();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}

		return $this->_response_success($this->_formatBase($base));
	}

	public function anyGrowTroops()
	{
		// Call to increment points/resources that go towards building up your troops

		try {
			$user = $this->service->getUser();
			$this->service->userGrowsTroops();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}

		return $this->_response_success(array(
			'points' => $user->troop_points,
			'troops' => $user->troops,
		));
	}

	/**
	 * Set or Change the destination of the user's group
	 *
	 * Params:
	 *    - username
	 *    - latitude
	 *    - longitude
	 */
	public function anyMoveGroup() {
		try {
			$currentPosition = $this->service->userCommandsGroupPosition();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}

// TODO - make renderer
return $this->_response_success($currentPosition);
	}

	/**
	 * Get current position of group
	 */
	public function anyGroupPosition() {
		try {
			$currentPosition = $this->service->getUserGroupPosition();
		} catch (Exception $e) {
			return $this->_response_exception($e);
		}

// TODO - make renderer
return $this->_response_success($currentPosition);
	}


	// ===== Formatters - Need new location for this ==========================

	public function _formatBase($object)
	{
		if ($object instanceof Collection) $object = array_values($object->all());
		if (is_array($object)) return array_map(__METHOD__, $object);
		$formatted = (object) array(
			'id' => $object->id,
			'location' => array(
				'latitude' => $object->loc->lat,
				'longitude' => $object->loc->long,
			),
			'date' => (string) $object->created_at,
			'date_established' => (string) $object->established_at,
			'user_id' => $object->user->id,
		);
		return $formatted;
	}

	public function _formatUser($object)
	{
		if ($object instanceof Collection) $object = $object->all();
		if (is_array($object)) return array_map(__METHOD__, $object);
		$formatted = (object) array(
			'id' => $object->id,
			'username' => $object->username,
			'created_at' => (string) $object->created_at,
			'updated_at' => (string) $object->updated_at,
		);
		foreach($object->toArray() as $key => $value) {
			if (strpos($key, 'custom_') === 0)
				$formatted->$key = $value;
		}
		return $formatted;
	}

}
