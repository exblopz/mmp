<?php

class User_model extends CI_Model
{
	private $table_name = 'users';			// user accounts
	private $perms = NULL;
	private $allperms = NULL;

	public function __construct()
	{
		parent::__construct();
	}
	
	public function get_by_userpass( $username, $password=NULL )
	{
		$this->db->select('u.*,ug.group_id');
		$this->db->from($this->table_name.' as u');
		$this->db->join('user_groups as ug','u.id=ug.user_id');
		$this->db->where('username', $username);
		$strPassword ='';
		if ( !is_null($password) )
		{
			$this->db->where('password', $password);
			
			
		}
		
		$query = $this->db->query($sql);
		//echo $this->db->last_query();
		return $query->num_rows() > 0 ? $query->row() : null;
	}
	
	public function get_company( $client_id )
	{
		if (!isset(self::$companies[$client_id]))
		{
			$r = $this->db->get_where('clients', array('client_id' => $client_id), 1);
			if ($r->num_rows() > 0)
			{
				self::$companies[$client_id] = $r->row();
			}
		}
		return isset(self::$companies[$client_id])?self::$companies[$client_id]:0;
	}
	
	public function check_perms($user_id, $uri)
	{
		$sql = "(SELECT t3.* FROM user_groups t1
				LEFT JOIN group_perms t2 ON t1.group_id = t2.group_id
				LEFT JOIN perms t3 ON t2.perm_id = t3.perm_id WHERE t1.user_id = ? AND t3.perm_path = ?
				ORDER BY t3.parent_id, t3.perm_order, t3.perm_id)
				UNION
				(SELECT t5.* FROM user_perms t4
				LEFT JOIN perms t5 ON t4.perm_id = t5.perm_id
				WHERE t4.user_id = ? AND t5.perm_path = ?
				ORDER BY t5.parent_id, t5.perm_order, t5.perm_id)";
		$query = $this->db->query($sql, array($user_id, $uri, $user_id, $uri));
		$this->perms = array();
		if ( $query->num_rows() > 0 )
		{
			$this->perms = $query->result();
		}
		return $this->perms;
	}
	
	public function my_perms($user_id)
	{
		if ( !$this->perms )
		{
			$sql = "(SELECT t3.* FROM user_groups t1
						LEFT JOIN group_perms t2 ON t1.group_id = t2.group_id
						LEFT JOIN perms t3 ON t2.perm_id = t3.perm_id WHERE t1.user_id = ?
						ORDER BY t3.parent_id, t3.perm_order, t3.perm_id)
					UNION
					(SELECT t5.* FROM user_perms t4
						LEFT JOIN perms t5 ON t4.perm_id = t5.perm_id
						WHERE t4.user_id = ?
						ORDER BY t5.parent_id, t5.perm_order, t5.perm_id)";
			$query = $this->db->query($sql, array($user_id, $user_id));
			$this->perms = array();
			if ( $query->num_rows() > 0 )
			{
				$this->perms = $query->result();
			}
		}
		return $this->perms;
	}
	
	public function print_menu($user_id, $parent_id = 0){
		$this->my_perms($user_id);
		
		$arrperms = array();

		foreach($this->perms as $perm)
		{
			if ($perm->parent_id != $parent_id)
				continue;
			$arrperms[] = $perm;
		}
		
		usort($arrperms, array($this, 'sort_order'));
		
		foreach($arrperms as $perm)
		{
			if ( !$perm->perm_path )
			{
				if ($parent_id == 0)
				{
					echo '<li class="'.$perm->perm_class.'"><h2>'.$perm->perm_name.'</h2>';
				}
				else
				{
					echo '<li><span class="'.$perm->perm_class.'">'.$perm->perm_name.'</span>';
				}
			}
			else
			{
				$path = $perm->perm_path;
				echo '<li><a class="'.$perm->perm_class.'" href="'.site_url($perm->perm_path).'" title="'.$perm->perm_name.'">'.$perm->perm_name.'</a>';
			}

			echo '<ul>';
			$this->print_menu( $user_id, $perm->perm_id );
			
			echo '</ul></li>';
		}
			
	}
	
	function sort_order($a,$b)
	{
		return $a->perm_order - $b->perm_order;
	}
	
	function save($array,$where=array()){
		if (count($where)>0){
			$this->db->where($where);
			$this->db->update($this->table_name);
		}else{
			$this->db->insert($this->table_name, $array);
		}
	}
	
	function delete($where){
		if (count($where)>0){
			$this->db->where($where);
			$this->db->delete($this->table_name);
		}else{
			return FALSE;
		}
	}
	
	function all(){
		$q = $this->db->get($this->table_name);
		if ($q->num_rows()>0){
			return $q->result();
		}else{
			return FALSE;
		}
	}
}
