<?php 
class Login extends CI_Controller {

	function __construct() {
		parent::__construct();
		
		$this->load->model('Users_Model');
	}
	
	function index() {
		if (! $this->safety->allowByControllerName('login') ) { return errorForbidden(); }
		
		$form = array(
			'frmId'				=> 'frmLogin',
			'buttons'			=> array('<button type="submit" class="btn btn-primary"><i class="icon-signin"></i> '.$this->lang->line('Login').' </button>'),
			'fields'			=> array(
				'email' => array(
					'type'	=> 'text',
					'label'	=> $this->lang->line('Email'), 
					'value'	=> set_value('email')
				),
				'password' => array(
					'type'	=> 'password',
					'label'	=> $this->lang->line('Password'), 
					'value'	=> set_value('password')
				),
				'link'	=> array(
					'type'	=> 'link',
					'label'	=> $this->lang->line('Forgot password'), 
					'value'	=> base_url('forgotPassword'),
				)
			)
		);
		
		$form['rules'] = array( 
			array(
				'field' => 'email',
				'label' => $form['fields']['email']['label'],
				'rules' => 'trim|required|valid_email|callback__validate_login'
			),
			array(				 
				'field' => 'password',
				'label' => $form['fields']['password']['label'],
				'rules' => 'trim|required'
			)
		);		
		
		$this->form_validation->set_rules($form['rules']);
					
		if ($this->input->post() != false) {
			$code = $this->form_validation->run();
			if ($this->input->is_ajax_request()) {
				return loadViewAjax($code, $code == false ? validation_errors() : array('goToUrl' => base_url('home'), 'skipAppLink' => true));
			}
		}
			
		return $this->load->view('pageHtml', array(
			'view'			=> 'login', 
			'title'			=> $this->lang->line('Login'),
			'meta'			=> array(
				'description' 	=> 'Login in clone Reader. Reader of feeds, rss, news',
				'keywords'		=> 'cReader cloneReader login '
			),				
			'form'			=> $form,
		));
	}

	function _validate_login() {
		return $this->safety->login($this->input->post('email'), $this->input->post('password'));
	}

	function facebook() {
		$this->_oauth2('facebook');
	}
	
	function google() {
		$this->_oauth2('google');
	}	
	
	function _oauth2($provider) {
		if (! $this->safety->allowByControllerName('login') ) { return errorForbidden(); }
		
		$this->load->spark('oauth2/0.4.0/');
		$this->config->load('oauth2');
		
		$config = $this->config->item('oauth2');
		$config = $config[$provider];


		$provider = $this->oauth2->provider($provider, array(
			'id' => 	$config['id'],
			'secret' => $config['secret'],
			'scope'	=> 	'email',
		));

		if ( ! $this->input->get('code')) {
			$url = $provider->authorize();
			redirect($url);
		} 

		try  {
			$token 	= $provider->access($_GET['code']);
			$user 	= $provider->get_user_info($token);
			$user 	= $this->Users_Model->loginRemote($user['email'], $user['last_name'], $user['first_name'], $provider, $user['uid'] );
			
			if ($user == null) {
				return errorForbidden();
			}

			$this->session->set_userdata(array(
				'userId'  		=> $user->userId,
				'langId'  		=> $user->langId,
			));
			
			$this->Users_Model->updateUserLastAccess();
			
			redirect('');
		}
		catch (OAuth2_Exception $e) {
			redirect('login');
		}
		
		return errorForbidden();
	}
}
