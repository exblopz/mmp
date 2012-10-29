<?php

class ORCA_Auth
{
    var $ci = null;
    var $table = 'users';
    var $cookiename = 'orcacrm';
    var $auth_salt = '457dc15c2828311f9485706b2e7063f2';
    var $hash = '';
    var $user = null;
    
    function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->ci->load->model('User_model', 'Users');
        $this->ci->load->library('form_validation');
        $this->ci->load->helper(array('form', 'recaptcha'));
        $this->get_current_user();
    }
    
    function login_required()
    {
        if ( !$this->is_logged_in() )
        {
            if ( isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' )
            {
                header('Content-type: application/x-json');
                echo json_encode(array('success' => false, 'next' =>  site_url('/auth/login?next='.rawurlencode(current_url()))));
            }
            else
            {
                redirect('/auth/login?next='.rawurlencode(current_url()));
            }

            exit;
        }
        $this->set_login($this->user);
        $this->check_perms();
    }
    
    function check_perms()
    {
        $uri = uri_string();
        $query = $this->ci->db->query("SELECT * FROM perms WHERE perm_path = ? AND public = 0 LIMIT 1", array($uri));
        if ($query->num_rows())
        {
            $myperms = $this->ci->Users->check_perms( $this->user->id, $uri );
            if ($myperms)
                return true;
            show_error("<p>Sorry, but you don't have permission to view this page</p>", 403);
            exit;
        }
        
        return true;
    }
    
    function is_logged_in()
    {
        return !$this->get_current_user() ? FALSE : TRUE;
    }
    
    function get_current_user()
    {
        if (!$this->user)
        {
            $hash = ($this->hash) ? $this->hash : ( isset( $_COOKIE[$this->cookiename] ) ? $_COOKIE[$this->cookiename] : '' );
            if ($hash)
            {
                $a = explode( ':', $hash );
                $username = array_shift( $a );
                $mbuh = array_shift( $a );

                if ( $mbuh == $this->make_hash($username, '', FALSE) )
                {
                    if ( $row = $this->ci->Users->get_by_userpass( $username ) )
                    {
                        $this->user = $row;
                        return $this->user;
                    }
                }
            }
        }
        return $this->user;
    }
    
    function login()
    {
        $this->ci->form_validation->set_rules('username', 'Username', 'trim|xss_clean|required|min_length[3]|max_length[20]|alpha_dash|strtolower');
        $this->ci->form_validation->set_rules('password', 'Password', 'trim|xss_clean|required');
        if ( isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' )
        {
            if ($this->ci->form_validation->run())
            {
                $username = set_value('username');
                $password = $this->make_hash(set_value('password'), '', true);
                if ( $row = $this->ci->Users->get_by_userpass( $username, $password ) )
                {
                    $this->set_login($row);
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    function logout()
    {
        $this->set_logout();
        redirect(site_url());
        exit;
    }
    
    function set_login($row)
    {
        //7 days is enough
        if (!$this->hash)
        {
            $ip = $this->get_ip();
            $this->hash = $row->username . ':' . $this->make_hash($row->username , '', FALSE);
        }

        setcookie($this->cookiename, $this->hash, time()+(86400), '/' );
        $_COOKIE[$this->cookiename] = $this->hash;

        $this->user = $row;
    }

    function set_logout()
    {
        $this->user = '';
        $this->hash = '';
        setcookie($this->cookiename, $this->hash, time()-(86400), '/');
        $_COOKIE[$this->cookiename] = '';
    }
    
    function make_hash($key, $prefix='', $forever=TRUE)
    {
        $prefix = $prefix ? $prefix . ':' : '';
    
        if ( !$forever )
        {
            $h = date('H');
            $i = date('i');
            if ( $h == '23' && $i == '59' )
            {
                $forever = date('Ymd', strtotime('+1day'));
            }
            else
            {
                $forever = date('Ymd');
            }
        }
        else
        {
            $forever = '';
        }
    
        return sha1($forever . $prefix . $this->auth_salt . ':' . $key);
    }
    
    function get_ip()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Callback function. Check if reCAPTCHA test is passed.
     *
     * @return	bool
     */
    function _check_recaptcha()
    {
        $resp = recaptcha_check_answer($this->ci->config->item('recaptcha_private_key'),
                        $this->get_ip(),
                        $_POST['recaptcha_challenge_field'],
                        $_POST['recaptcha_response_field']);

        if (!$resp->is_valid)
        {
            $this->ci->form_validation->set_message('_check_recaptcha', 'Please enter the text written in image below');
            return FALSE;
        }
        return TRUE;
    }
}

