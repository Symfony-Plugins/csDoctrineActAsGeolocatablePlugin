<?php

// 
//  Geolocatable.php
//  csActAsGeolocatablePlugin
//  
//  Created by Brent Shaffer on 2008-12-22.
//  Copyright 2008 Centre{source}. All rights reserved.
// 

class Doctrine_Template_Listener_Geolocatable extends Doctrine_Record_Listener
{
  /**
   * Array of geolocatable options
   */  
  protected $_options = array();


  /**
   * Constructor for Geolocatable Template
   *
   * @param array $options 
   * @return void
   * @author Brent Shaffer
   */  
  public function __construct(array $options)
  {
    $this->_options = $options;
  }


  /**
   * Set the geocodes automatically when a new geolocatable object is created
   *
   * @param Doctrine_Event $event
   * @return void
   * @author Brent Shaffer
   */
  public function preInsert(Doctrine_Event $event)
  {
    $object = $event->getInvoker();
		$object->refreshGeocodes();
  }


  /**
   *
   * @param string $Doctrine_Event 
   * @return void
   * @author Travis Black
   */  
  public function postDelete(Doctrine_Event $event)
  {
    $object = $event->getInvoker();
  }  
}
