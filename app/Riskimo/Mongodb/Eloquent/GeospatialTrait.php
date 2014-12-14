<?php namespace Riskimo\Mongodb\Eloquent;

/**
 * 
 */
trait GeospatialTrait
{

	public static function bootGeospatialTrait()
	{
		static::addGlobalScope(new GeoNearCommandMacro);
	}

}
