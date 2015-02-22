<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/


Route::controller('webservice', 'WebserviceApiController');




Route::get('/', function()
{
	return View::make('hello');
});



Route::get('/house-images/{count}', function($count)
{

	$houses = HouseImages::take($count)->get();

	$response = array();
	foreach($houses as $house) {
		$photos = array();
		if (is_array($house->imagelinks)) {
			foreach($house->imagelinks as $photo) {
				$photos[] = array(
					'tag' => 'unknown',
					'src' => $photo,
				);
			}
		}

		$response[] = array(
			'id' => $house->id,
			'href' => $house->listingurl,
			'photos' => $photos,
		);
	}


	return Response::json($response);
});





