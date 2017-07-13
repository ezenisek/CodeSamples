<?php
/**
 * Maestro AuthType_LDAP class
*
* A Meastro LDAP Authentication Type.  This class holds the configuration,
* connection, and authentication code for the LDAP authentication type.
*
*/
class AuthType_LDAP extends AuthType {

  public $type;
  public $config_template;

  private $conn;
  private $config_values;

  function __construct($config_values = false){
    $type = 'LDAP';
    $config_template = '{"DN":"","RDN":"","User":"","Password":"","Host":"",
        "Port":"","Search_Field":"","Email_Field":"","Display_Name_Field":"",
        "First_Name_Field":"","Last_Name_Field":""}';
    parent::__construct($type,$config_template);

    if($config_values){
      // Verify that config_values matches the structure of config_template
      if(isJSON($config_values) === true){
        $valuearray = json_decode($config_values,true);
        $templatearray = json_decode($this->config_template,true);
        if(!empty(array_diff_key($valuearray,$templatearray))){
          $this->error = 1;
          $this->message = "Config template value mismatch";
          $this->throwError();
          return false;
        }
      } else {
        $this->error = 1;
        $this->message = "Non JSON config values found";
        $this->throwError();
        return false;
      }
      $cvalarray = json_decode($config_values,true);
      foreach($cvalarray as $col => $val){
       $col = strtolower($col);
       $this->$col = $val;
      }
      $this->config_values = $config_values;
      $this->setConnect();
    }
  }

  function verifyAuth(){
    // This function verifies that the authentication source is working based
    // on the config values.  In this case we attempt to bind with the
    // given user credentials.
    $this->setConnect();
    if(!$this->error)
      return true;
    else
      return false;
  }

  function verifyUser($username){
    if($arrResults = $this->LDAPUserVerify($username)){
      return $this->resultProcess($username,$arrResults[0]);
    }
    return false;
  }

  function verifyLogin($username,$password){
    if(!$arrResults = $this->LDAPUserVerify($username))
      return false;
    if(!@ldap_bind($this->conn,$arrResults[0]['dn'], $password))
    {
      // User exists but password is incorrect
      $this->error = 1;
      $this->message = 'Invalid username or password - cannot connect.';
      $this->throwError();
      return false;
    }
    return $this->resultProcess($username,$arrResults[0]);
  }

  function setConnect(){
    if(empty($this->config_values)){
      $this->error = 1;
      $this->message = 'No configuration values set for this connection.';
      $this->throwError();
      return false;
    }
    $this->conn = $this->LDAPConnect();
  }

  function LDAPConnect()
  {
    // This function creates our initial LDAP connection and returns the
    // connection reference.

    $username = $this->user;
    $password = $this->password;
    $ldapconfig['host'] = $this->host;
    $ldapconfig['port'] = $this->port;
    $ldapconfig['RDN'] = $this->rdn;
    $ds = '';

    if(!$ds=@ldap_connect($ldapconfig['host'],$ldapconfig['port']))
    {
      $this->message = "Could not connect to LDAP Server.";
      $this->error = 1;
      $this->throwError();
      return false;
    }

    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);

    if(!$ldapBind = @ldap_bind($ds, $ldapconfig['RDN'], $password))
    {
      $this->message = "Could not Bind to LDAP Server.";
      $this->error = 1;
      $this->throwError();
      return false;
    }
    return $ds;
  }

  function LDAPUserVerify($needle)
  {
    // This function takes a username and verifies that it exists in LDAP.
    // If so, it returns an array of results.  If not, it returns false;
    $filter = '(&('.$this->search_field.'='.trim($needle).'))';
    $returnAttribs = array(
        "$this->email_field",
        "$this->display_name_field",
        "distinguishedname",
        "$this->last_name_field",
        "$this->first_name_field",
        "$this->search_field");
    $searchResults = ldap_search($this->conn,$this->dn,$filter,$returnAttribs,0,0,10);

    if(!ldap_count_entries($this->conn,$searchResults))
      return false;
    else
      return ldap_get_entries($this->conn,$searchResults);
  }

  function resultProcess($username,$result){
    $processed = array(
      'display_name' => $result[$this->display_name_field][0],
      'email' => $result[$this->email_field][0],
      'first_name' => $result[$this->first_name_field][0],
      'last_name' => $result[$this->last_name_field][0],
      'username' => $username
    );
    return $processed;
  }
}
?>