<?php
/**
 * Maestro AuthType class
*
* A Meastro LDAP Authentication Type base class.  Specific type classes use
* this base class to load information into the database.
*
*/
class AuthType {

  public $type;
  public $config_template;
  public $error = 0;
  public $message = '';

  function __construct($type,$config_template){
    $this->type = $type;
    $this->config_template = $config_template;
  }

  function getConfigTemplate(){
    return $this->config_template;
  }

  function throwError(){

  }

}