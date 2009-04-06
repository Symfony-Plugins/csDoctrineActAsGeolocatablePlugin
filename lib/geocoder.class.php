<?php

class Geocoder
{
	 var $params = array('key', 'street', 'city', 'state', 'postal', 'country', 'format');
	 var $address = array();
	 public $error;
	 protected $url, $key, $xml, $coordinates = array();
	 protected $street, $city, $state, $postal, $country, $format;
	
   function geocoder($key = null, $url = 'http://maps.google.com/maps/geo', $format = 'csv') 
   {     
			$p = sfConfig::get('app_geo_google');	
			$this->key = (isset($p['key']) && $p['key']) ? $p['key'] : $key;
			$this->url = (isset($p['url']) && $p['url']) ? $p['url'] : $url;
			$this->format = (isset($p['format']) && $p['format']) ? $p['format'] : $format;
   }
   public function postal($postal)
	 {
		$this->address['postal'] = $postal;
		return $this;
   }
	 public function city($city)
	 {
		$this->address['city'] = $city;
		return $this;
   }
	 public function state($state)
	 {
		$this->address['state'] = $state;
		return $this;
   }
	 public function country($country)
	 {
		$this->address['country'] = $country;
		return $this;
   }
	 public function street($street)
	 {
		$this->address['street'] = $street;
		return $this;
   }
	 public function format($format)
	 {
		$this->address['format'] = $format;
		return $this;
   }
	 public function addressHas($index)
	 {
		return (isset($this->address[(string)$index]) && $this->address[(string)$index]);
	 }
	 public function collapseAddress()
	 {
		$address = '';
		if($this->addressHas('street'))
		{
			$address = $this->address['street'] . ', ';
	 	}
		if($this->addressHas('city'))
		{
			$address .= $this->address['city'] . ', ';
	 	}
		if($this->addressHas('state'))
		{
			$address .= $this->address['state'] . ', ';
	 	}	
	  if($this->addressHas('postal'))
		{
			$address .= $this->address['postal'] . ' ';
	 	}
		if($this->addressHas('country'))
		{
			$address .= $this->address['country'];
	 	}
		return $address;
	 }
	 public function generateUrl()
	 {
		 $url = $this->url.'?q='.urlencode($this->collapseAddress())
											.'&key='.$this->key.'&output='.$this->format.'&sensor=0';

		 return $url;
	 }
	 public function refresh()
	 {
			$this->getResponse();
	 }
   public function getResponse() 
	 {
			if($this->format == 'xml')
			{
      	$response = simplexml_load_file($this->generateUrl());				
			}
			elseif($this->format == 'csv')
			{
				$response = file_get_contents($this->generateUrl());
			}
			
			try
			{
				if($response)
				{
					$this->response = $response;
					return $response;
		  		}
				else
				{
					$this->error = $response->Message;
					return false;
				}
			}
			catch(Exception $e)
			{
				$this->error = $e;
			}
			return false;
	 }
	 public function getLatitude()
	 {
		 $this->getCoordinates();
		 $this->latitude = $this->coordinates[0];
		 return $this->latitude;
	 }
	 public function getLongitude()
	 {
		 	$this->getCoordinates();
			$this->longitude = $this->coordinates[1];
			return $this->longitude;
	 }
	public function getCoordinates()
	{
		if(!$this->coordinates)
		{
			if(!$this->response)
			{
				$this->getResponse();
			}
			if(!$this->coordinates)
			{
				switch($this->format)
				{
					case 'xml':
						$this->coordinates = explode(',', $this->response->Response->Placemark->Point->coordinates );
						break;
					case 'csv':
						$this->coordinates = explode(',', $this->response);
						$this->coordinates = array($this->coordinates[2], $this->coordinates[3]);
						break;
				}
			}
		}
		return $this->coordinates;
	}
	public function corners($miles = 100)
	{
		$degrees = (float)($miles / 60);
		$corners = array();
		$corners['top'] = $this->longitude + $degrees;
		$corners['bottom'] = $this->longitude - $degrees;
		$corners['right'] = $this->latitude + $degrees;
		$corners['left'] = $this->latitude - $degrees;
		
		return $corners;
	}
	 public function get()
	 {
		if($this->getResponse())
		{
			$this->latitude 	= $this->getLatitude();
			$this->longitude 	= $this->getLongitude();
			return true;
		}
	 	return false;
	}
}