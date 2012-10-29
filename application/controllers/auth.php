<?php

class Auth extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function login()
    {
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
        {
            $next = $this->input->get_post('next');
            if ($this->orca_auth->login())
            {
                if ($next)
                {
                    redirect( $next );
                }
                else
                {
                    redirect( site_url() );
                }
            }
            else
            {
                flashmsg_set('Username atau password anda kurang tepat. Mohon ulangi kembali');
            }
        }
        
        $this->load->view('login');
    }
    
    public function logout()
    {
        $this->orca_auth->logout();
        redirect( site_url() );
    }
}
