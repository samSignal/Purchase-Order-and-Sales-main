<?php
require_once '../config.php';
class Login extends DBConnection {
	private $settings;
	public function __construct(){
		global $_settings;
		$this->settings = $_settings;

		parent::__construct();
		ini_set('display_error', 1);
	}
	public function __destruct(){
		parent::__destruct();
	}
	public function index(){
		echo "<h1>Access Denied</h1> <a href='".base_url."'>Go Back.</a>";
	}
	public function login(){
		extract($_POST);
	
		$qry = $this->conn->query("SELECT * FROM users WHERE username = '$username' AND password = md5('$password')");
		if($qry->num_rows > 0){
			$user = $qry->fetch_assoc();
			foreach($user as $k => $v){
				if(!is_numeric($k) && $k != 'password'){
					$this->settings->set_userdata($k, $v);
				}
			}
			$this->settings->set_userdata('user_id', $user['id']); // Store user ID in session
			$this->settings->set_userdata('login_type', 1);
			//echo "<pre>Session Data: " . print_r($_SESSION, true) . "</pre>"; // Debugging
			return json_encode(array('status' => 'success', 'user_id' => $user['id'])); // Include user ID in response
		} else {
			return json_encode(array(
				'status' => 'incorrect',
				'last_qry' => "SELECT * FROM users WHERE username = '$username' AND password = md5('$password')"
			));
		}
	}
	public function logout(){
		if($this->settings->sess_des()){
			redirect('admin/login.php');
		}
	}public function login_user(){
		extract($_POST);
	
		$qry = $this->conn->query("SELECT * FROM users WHERE username = '$username' AND password = md5('$password') AND `type` = 2");
		if($qry->num_rows > 0){
			$user = $qry->fetch_assoc();
			foreach($user as $k => $v){
				$this->settings->set_userdata($k, $v);
			}
			$this->settings->set_userdata('user_id', $user['id']); // Store user ID in session
			$this->settings->set_userdata('login_type', 2);
			$resp = array('status' => 'success', 'user_id' => $user['id']); // Include user ID in response
		} else {
			$resp = array('status' => 'incorrect');
		}
	
		if($this->conn->error){
			$resp['status'] = 'failed';
			$resp['_error'] = $this->conn->error;
		}
	
		return json_encode($resp);
	}
	public function logout_user(){
		if($this->settings->sess_des()){
			redirect('./');
		}
	}
}
$action = !isset($_GET['f']) ? 'none' : strtolower($_GET['f']);
$auth = new Login();
switch ($action) {
	case 'login':
		echo $auth->login();
		break;
	case 'login_user':
		echo $auth->login_user();
		break;
	case 'logout':
		echo $auth->logout();
		break;
	case 'logout_user':
		echo $auth->logout_user();
		break;
	default:
		echo $auth->index();
		break;
}

