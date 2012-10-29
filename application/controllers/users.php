<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 * Copyright 2012 by MYRAF, Perum Bandara Santika C2-7, Asrikaton, Kec. Pakis Malang ID
 * All rights reserved
 * 
 * Written By: Muhammad Yusuf Romadhoni Al Faruq
 * exblopz@gmail.com
 * http://abuaisyah.com/
 */

/**
 * Description of users
 *
 * @author MYRAF
 */
 
class Users extends CI_Controller 
{
	public function __construct(){
		parent::__construct();
		$this->load->model('User_model');
	}
	
	public function index(){
		
		$arrfields = array('user_id', 'username', 'password', 'password2', 'realname', 'group_id');
		$data['error'] = '';
		$column = array();
		
		foreach($arrfields as $field){
			$column[$field] = $this->input->post($field);
		}
		
		$save = $this->input->post('save');
		
		if ($save){
			if($this->_checkForm($column) === FALSE){
				//simpan
				if (!empty($column)){
					if (!empty($column['password'])){
						$this->User_model->save(
						array(
							'username' => $column['username'],
							'password' => $this->orca_auth->make_hash($column['password'],'', TRUE),
							'realname' => $column['realname'],
							'group_id' => $column['group_id'],
						), array('userid' => $column['user_id']
						));
					}else{
						$this->User_model->save(
						array(
							'username' => $column['username'],
							'password' => $this->orca_auth->make_hash($column['password'],'', TRUE),
							'realname' => $column['realname'],
							'group_id' => $column['group_id']
						), array('userid' => $column['user_id']
						));
					}
				}else{
				}
			}else{
				$data['error'] = $this->_checkForm($column);
			}
		}
		
		$this->load->view('users',$data);
	}
	
	public function all(){
		header('Content-type: application/json; charset=UTF-8');
		$result = array('success' => true, 'rows' => array(), 'totalCount' => 0);
		
		$result['rows'] = $this->_getAll();
		$result['totalCount'] = count($result['rows']);
		
		echo json_encode($result);
	}
	
	public function delete(){
		$user_id = $this->input->get_post('user_id');
		$this->User_model->delete(array('user_id' => $user_id));
	}
	
	function _checkForm($array){
		if (!preg_match('/^[a-zA-Z]+[a-zA-Z0-9]+[a-zA-Z0-9]$/i', $array['username'])){
			return "Username hanya boleh berisi karakter atau karakter diakhiri dengan angka";
		}else if (!preg_match('/^[a-zA-Z]+[a-zA-Z\. ]+[a-zA-Z\. ]$/i', $array['realname'])){
			return "Isikan hanya dengan karakter";
		}else if ($array['password'] != $array['password2']){
			return "Password tidak sama";
		}else{
			return FALSE;
		}
	}
	
	function _getAll(){
		$result = $this->User_model->all();
		return $result;
	}
	
}

/*
 * End of file users.php
 * */
