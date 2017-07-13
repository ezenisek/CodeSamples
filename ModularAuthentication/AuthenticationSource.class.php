<?php
/**
 * Maestro AuthenticationSource Class
 *
 * A Meastro Authentication Source
 *
 */
class AuthenticationSource {

  public $auth_id;
  public $name;
  public $type;
  public $typeclass;
  public $config_values;
  public $description;
  public $active;
  public $autoallow;

  protected $spdo;

  function __construct($init){
    $this->spdo = Database::getConnection(); //Use the database class to connect
    if(is_array($init)){
      $id = $this->create($init);
      $this->load($id);
    }
    elseif(is_numeric(intval($init))) {
      $this->load($init);
    }
  }

  function create($init){
    $params = array(); $cols = array(); $vals = array();
    $required = array(
        'name',
        'description',
        'type',
        'config_values'
    );
    foreach($init as $key=>$i){
      $this->$key = validateInput($i);
      $params[':'.$key] = $this->$key;
      $cols[] = $key;
      $vals[] = ':'.$key;
    }
    //Check that we're not missing required fields
    if(array_intersect($required,$cols) != $required){
      throw new Exception('Required column missing during auth source creation');
    }
    $query = 'INSERT INTO auth_sources ('.implode(',',$cols).')
         VALUES ('.implode(',',$vals).')';
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      dieLog('Could not create auth source because '.pdoError($e));
    }
    return $this->spdo->lastInsertId();
  }

  function load($id){
    /* Load the specified category ID */
    $params = array(':id'=>$id);
    $query = "SELECT * FROM auth_sources WHERE auth_id = :id";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      dieLog("Could not load auth source $id because ".pdoError($e));
    }
    if(!$this->spdo->query("SELECT FOUND_ROWS()")->fetchColumn())
      throw new Exception(get_class()." class cannot load a ".get_class()." that does not exist (tried to load $id)");
    $category = $sth->fetch(PDO::FETCH_ASSOC);
    foreach($category as $column=>$value){
      $this->$column = $value;
    }
    // Load other required information
    $classname = "AuthType_$this->type";
    $this->typeclass = new $classname($this->config_values);
  }

  function save(){
    $params = array(
        ':id'=>$this->auth_id,
        ':name'=>$this->name,
        ':description'=>$this->description,
        ':active'=>$this->active,
        ':type'=>$this->type,
        ':config_values'=>$this->config_values,
        ':autoallow'=>$this->autoallow
    );
    $query = 'UPDATE auth_sources SET ';
    foreach($params as $key=>$value){
      if($key != ':id'){
        $col = ltrim($key,':');
        $query .= "$col = $key,";
      }
    }
    $query = rtrim($query,',');
    $query .= " WHERE auth_id = :id";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      dieLog("Could not save auth source $this->name because ".pdoError($e));
    }
  }

  function delete() {
    // Delete this authentication source from the database but only if there
    // are no users.
    $params = array(':id'=>$this->auth_id);
    $query = "SELECT user_id FROM users WHERE auth_id = :id";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      writeLog("Could not get auth user list because ".pdoError($e));
      return false;
    }
    if($this->spdo->query("SELECT FOUND_ROWS()")->fetchColumn() != 0){
      dieLog("Cannot delete an authentication source with active users");
    }

    $query = "DELETE FROM auth_sources WHERE auth_id = :id";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      writeLog("Could not delete auth source $this->auth_id because ".pdoError($e));
      return false;
    }
    return true;
  }

  function verifyAuth(){
    return $this->typeclass->verifyAuth();
  }

  function verifyUser($username){
    if($results = $this->typeclass->verifyUser($username))
      return $results;
    return false;
  }

  function verifyLogin($username,$password,$create = false){
    if(!$this->active)
      return false;
    if(!$this->verifyUser($username)){
      // This user doesn't exist in this system.
      return 'Username not found';
    }
    if($result = $this->typeclass->verifyLogin($username,$password))
    {
      // This user has a login in this authentication system.  Let's see if
      // they have a local account
      if($this->userExistsLocal($username)){
        // They do.  They're good to go.
        return true;
      } else {
        // They don't.  If autoallow is on and create is true,
        // we create an account.  If not, they can't log in without a
        // local account.
        if($this->autoallow && $create){
          $this->createLocalAccount($result);
          return true;
        }
        return "No local account";
      }
      return true;
    }
    return $this->typeclass->message;
  }

  function userExistsLocal($username){
    $params = array(':username'=>$username,':aid'=>$this->auth_id);
    $query = "SELECT user_id FROM users WHERE username = :username AND auth_id = :aid";
    try {
      $sth = $this->spdo->prepare($query);
      $sth->execute($params);
    }
    catch(PDOException $e) {
      dieLog("Could not get user information because ".pdoError($e));
    }
    if(!$this->spdo->query("SELECT FOUND_ROWS()")->fetchColumn()){
      return false;
    }
    return true;
  }

  function createLocalAccount($info){
    // Insert this person into the system
    $init = array(
        'name'=>validateInput($info['display_name']),
        'first'=>validateInput($info['first_name']),
        'last'=>validateInput($info['last_name']),
        'email'=>validateInput($info['email']),
        'username'=>validateInput($info['username'])
    );

    $user = new User($init);
    writeLog("Added user ($username) to auth source $this->type->name via login");

    //Set roles for this user
    $roles = getRoles();  // Maestro specific function
    foreach($roles as $role){
      if($role['give_by_default']){
        $user->addRole($role['role_id']);
      }
    }

    //Send the welcome email.
    $mMail = new MaestroMail();
    $mMail->addTo($user->email);
    $programname = getSetting('programname');
    $subject = "Welcome to $programname!";
    $message = getSetting('email_welcome');
    $mMail->sendIt($subject,$message);
  }
}
?>