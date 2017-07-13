<?php
/**
 * Maestro AuthType_LOCAL class
*
* A Maestro LOCAL Authentication Type.  This class holds the configuration,
* connection, and authentication code for the LOCAL authentication type.
*
*/
class AuthType_LOCAL extends AuthType {

  private $config_values;
  private $spdo;

  function __construct($config_values = false){
    $this->spdo = Database::getConnection();
    $type = 'LOCAL';
    $config_template = '{}';
    parent::__construct($type,$config_template);
  }

  function verifyAuth(){
    // This function verifies that the authentication source is working based
    // on the config values.  In this case we just check for user 0.
    $query = "SELECT name FROM users WHERE user_id = 0";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute();
    }
    catch(PDOException $e) {
      endProcess(0,"Could not get user information because ".pdoError($e));
    }
    if($this->spdo->query("SELECT FOUND_ROWS()")->fetchColumn() != 1)
      return false;
    return true;
  }

  function verifyUser($username){
    $params = array(':username'=>$username);
    $query = "SELECT user_id, name, first, last, email FROM users WHERE username = :username AND auth_id = 1";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      endProcess(0,"Could not get user information because ".pdoError($e));
    }
    if($this->spdo->query("SELECT FOUND_ROWS()")->fetchColumn() != 1){
      return false;
    }
    return $this->resultProcess($username,$sth->fetch());
  }

  function verifyLogin($username,$password){
    if(!$info = $this->verifyUser($username))
      return false;
    $params = array(':userid'=>$info['user_id']);
    $query = "SELECT password FROM auth_local WHERE user_id = :userid";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      endProcess(0,"Could not get user information because ".pdoError($e));
    }
    $hash = $sth->fetchColumn();

    if(password_verify($password, $hash)) {
      return $this->resultProcess($username,$info);
    }
    else {
      // User exists but password is incorrect
      $this->error = 1;
      $this->message = 'Invalid username or password - cannot connect.';
      $this->throwError();
      return false;
    }
  }

  function resultProcess($username,$result){
    $processed = array(
      'display_name' => $result['name'],
      'email' => $result['email'],
      'first_name' => $result['fist'],
      'last_name' => $result['last'],
      'username' => $username,
      'user_id' => $result['user_id']
    );
    return $processed;
  }
}
?>