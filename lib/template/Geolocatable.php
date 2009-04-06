<?php

// 
//  Geolocatable.php
//  csActAsGeolocatablePlugin
//  
//  Created by Brent Shaffer on 2009-01-29.
//  Copyright 2008 Centre{source}. Al9 rights reserved.
// 

class Doctrine_Template_Geolocatable extends Doctrine_Template
{    
  /**
   * Array of geolocatable options
   */  
  protected $_options = array('columns'			=> array(
																'latitude'    =>  array(
																		'name' 		=> 'latitude',
																		'type' 		=> 'float',
																		'length'	=>  null,
			                              'alias'   =>  null,
			                              'options' =>  array()),
																'longitude'  	=> 	array(
																		'name' 		=> 'longitude',
																		'type' 		=> 'float',
																		'length' 	=>  null,
			                              'alias'   =>  null,
			                              'options' =>  array()),
														), 'fields'				=> array(
																		'postal'  =>  'postal')
	 );


  /**
   * Constructor for Geolocatable Template
   *
   * @param array $options 
   * @return void
   * @author Brent Shaffer
   */
  public function __construct(array $options = array())
  {
    $this->_options = Doctrine_Lib::arrayDeepMerge($this->_options, $options);
  }


  public function setup()
  {

  }


  /**
   * Set table definition for contactable behavior
   * (borrowed from Sluggable in Doctrine core)
   *
   * @return void
   * @author Brent Shaffer
   */
  public function setTableDefinition()
  {
		foreach ($this->_options['columns'] as $key => $options) {
	    $name = $options['name'];

			if ($options['alias'])
	    {
	      $name .= ' as ' . $options['alias'];
	    }
			
	    $this->hasColumn($name, $options['type'], $options['length'], $options['options']);
		}
		
    $this->addListener(new Doctrine_Template_Listener_Geolocatable($this->_options));
  }

	// ==================
	// = Object Methods =
	// ==================
	public function refreshGeocodes($force = false)
	{
		$object = $this->getInvoker();     
		try
		{
			$geocoder = $object->getGeocoder();
			$latName = $this->_options['columns']['latitude']['name'];
			$lngName = $this->_options['columns']['longitude']['name'];
			
			if ($geocoder->get())
			{		
				$object[(string)$latName] = ($force || !$object[(string)$latName]) ? $geocoder->latitude  : $object[(string)$latName];				
				$object[(string)$lngName] = ($force || !$object[(string)$lngName]) ? $geocoder->longitude : $object[(string)$lngName];
			}
			else
			{
				return false;
			}
			return true;
		}
		catch(Exception $e)
		{
      return false;
		}
	}
	public function getGeocoder()
	{
		$object = $this->getInvoker();
		$geocoder = new Geocoder();
		foreach ($this->_options['fields'] as $key => $value) {
				$geoMethod = is_numeric($key) ? $value : $key;
				$geocoder->$geoMethod($object[(string)$key]);
		}
		return $geocoder;
	}
	public function findAdjacent($distance = null, $distance_unit=null)
	{
		$obj = $this->getInvoker();     
		$latName = $this->_options['columns']['latitude']['name'];
		$lngName = $this->_options['columns']['longitude']['name'];
		if(!$obj[(string)$latName] || $obj[(string)$lngName])
		{
			$obj->refreshGeocodes(true);
			$obj->save();
		}
		$p = sfConfig::get('app_geo_defaults');	
		$distance = $distance ? $distance : $p['distance'];
		$results = $this->findByGeocodeTableProxy($obj[(string)$latName], $obj[(string)$lngName], $distance, $distance_unit);
		foreach ($results as $key => $result) {
			if($result['id'] == $obj['id'])
			{
				unset($results[$key]);
			}
		}
		return $results;
	}
	
	// ================
	// = Table Proxys =
	// ================
	
	/**
	 * refreshAllGeocodes()
	 *
	 * retrieves all geocodes for every object in the table
	 *
	 * @param string $force 
	 * @return void
	 * @author Brent Shaffer
	 */
	public function refreshAllGeocodesTableProxy($force = false)
	{
		$table = $this->getInvoker()->getTable();     
		foreach($table->findAll() as $obj)
		{
			$obj->refreshGeocodes($force);
			$obj->save();
		}
	}
	public function findByGeocodeTableProxy($latitude, $longitude, $distance, $distance_unit = null)
	{
		$q = $this->getDistanceQueryTableProxy($latitude, $longitude, $distance, $distance_unit);
		return $q->execute();
	}
	
	/**
	 * public functiongeolocate()
	 *
	 * @param mixed $options represents either an associative array of column names and values to search on ('city' => 'Nashville') or if there is only one column to search on, takes this value and searches on it (for instance, if POSTAL is being searched, you can pass a zip code directly) 
	 * @param string $distance 
	 * @param string $distance_unit 
	 * @return void
	 * @author Brent Shaffer
	 */
	public function geolocateTableProxy($options, $distance = null, $distance_unit = null)
	{
		$p = sfConfig::get('app_geo_defaults');	
		$distance = $distance ? $distance : $p['distance'];
		$geocoder = new Geocoder();
		foreach ($this->_options['fields'] as $key => $value) {
			if(!is_array($options))
			{
				$geoMethod = is_numeric($key) ? $value : $key;
				$geocoder->$geoMethod($options);				
				break;				
			}
			if(isset($options[(string)$value]))
			{
				$geoMethod = is_numeric($key) ? $value : $key;
				$geocoder->$geoMethod($options[(string)$value]);
			}
		}
		$geocoder->get();
		return $this->findByGeocodeTableProxy($geocoder->latitude, $geocoder->longitude, $distance, $distance_unit);
	}
	
	/**
	 * getDistanceQuery
	 *
	 * Adds a distance unit column to the select statement 
	 * showing how many units of measurement the object is from a 
	 * given latiude and longitude
	 *
	 * @param string $latitude 
	 * @param string $longitude 
	 * @param string $distance 
	 * @param string $distance_unit 
	 * @return void
	 * @author Brent Shaffer
	 */
	public function getDistanceQueryTableProxy($latitude, $longitude, $distance = null, $distance_unit=null)
  {
			$table = $this->getInvoker()->getTable();     
      $query = $table->createQuery();
			$rootAlias = $query->getRootAlias();
			$query->addSelect($rootAlias . '.*');
			$query = $this->addDistanceQueryTableProxy($query, $latitude, $longitude, $distance, $rootAlias, $distance_unit);
			
      return $query;
  }
	public function addDistanceQueryTableProxy($query, $latitude, $longitude, $distance=null, $rootAlias=null, $distance_unit=null )
  {
			$p = sfConfig::get('app_geo_defaults');	
			$distance_unit = $distance_unit ? $distance_unit : $p['distance_unit'];
			$rootAlias = $rootAlias ? $rootAlias : $query->getRootAlias();
      $latName = $this->_options['columns']['latitude']['name'];
      $longName = $this->_options['columns']['longitude']['name'];

      $sql = "((ACOS(SIN(%s * PI() / 180) * SIN(" . $rootAlias . "." . $latName . " * PI() / 180) + COS(%s * PI() / 180) * COS(" . $rootAlias . "." . $latName . " * PI() / 180) * COS((%s - " . $rootAlias . "." . $longName . ") * PI() / 180)) * 180 / PI()) * 60 * %s) AS %s";

			if($distance_unit == 'miles')
			{
      	$querySql = sprintf($sql, $latitude, $latitude, $longitude, '1.1515 * 1.609344', $distance_unit);
			// exit($querySql);

			}
			elseif($distance_unit == 'kilometers')
			{
				$querySql = sprintf($sql, $latitude, $latitude, $longitude, '1.1515', $distance_unit);
			}
			else
			{
				  throw new sfException('Distance Unit must be one of the following: [miles, kilometers]. "'.$distance_unit.'" given.');
			}
			
			$query->addSelect($querySql);
			if($distance)
			{
				$query->addHaving($distance_unit.' < ? ', $distance );
			}

      return $query;
  }
  public function getRelatedClassesArray()
  {
    $relations = array();

    foreach ($this->getInvoker()->getTable()->getRelations() as $rel)
    {
      $componentName = $rel->getTable()->getComponentName();
      $relations[] = $componentName;
    }
		
    return $relations;
  }
}
