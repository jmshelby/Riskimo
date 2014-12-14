<?php namespace Riskimo\Mongodb\Eloquent;

use ReflectionMethod;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ScopeInterface;

/**
 * Eventhough this implements the scope interface, it's actually just a macro 
 * class.
 */
class GeoNearCommandMacro implements ScopeInterface
{
	
	public function apply(Builder $builder)
	{
		// Legacy Geo Near Command
		$builder->macro('geoNearCommand', function(Builder $builder, $lat, $long, $minDistance = null, $maxDistance = null)
		{
			return $this->macro_geoNearCommand($builder, $lat, $long, $minDistance, $maxDistance);
		});
	}

	public function macro_geoNearCommand(Builder $builder, $lat, $long, $minDistance = null, $maxDistance = null)
	{
		$query = $builder->getQuery();

$builder->getModel()->raw()->ensureIndex(array('loc' => '2dsphere'));

		$command = [];
		// Add Command, and Collection Name
		$command['geoNear'] = $builder->getModel()->getTable();

		// Assume spherical for now
		$command['spherical'] = true;

		// Add Point
		$command['near'] = [
			'type' => 'Point',
			'coordinates' => [(float) $long, (float) $lat],
		];
		
		// Add Limit
		if ($query->limit)
			$command['limit'] = $query->limit;

		// Add Search Query
		if (!empty($wheres = $this->compileWheres($builder))) {
			$command['query'] = $wheres;
		}

		// Add Minimum Distance
		if (!is_null($minDistance))
			$command['minDistance'] = (float) $minDistance;

		// Add Maximum Distance
		if (!is_null($maxDistance))
			$command['maxDistance'] = (float) $maxDistance;

		// Execute Command
		$db = \DB::getMongoDB();
		$r = $db->command($command);

		// Check for results
		if (!isset($r['results'])) {
			return new Collection;
		}

		// Convert into collection
		$rCollection = new Collection($r['results']);
		$distances = $rCollection->lists('dis');
		$objects = $rCollection->lists('obj');

		// Add calculated distance to each model
		$collection = $builder->getModel()->hydrate($objects);
		foreach($collection as $index => $message) {
			$message->command_metadata = (object) array(
				'distance' => $distances[$index],
			);
		}

		return $collection;
	}

	public function compileWheres(Builder $builder)
	{
		$query = $builder->getQuery();
		$reflectionMethod = new ReflectionMethod($query, 'compileWheres');
		$reflectionMethod->setAccessible(true);
		$wheres = $reflectionMethod->invoke($query);
		return $wheres;
	}

	public function remove(Builder $builder)
	{
		// Since we are not really a scope, do nothing
	}

}
