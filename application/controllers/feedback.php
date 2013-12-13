<?php 
class Feedback extends CI_Controller {

	function __construct() {
		parent::__construct();	
		
		$this->load->model(array('Comments_Model', 'Users_Model'));
	}  
	
	function index() {
		if (! $this->safety->allowByControllerName('feedback') ) { return errorForbidden(); }
		
		$this->load->helper('email');


		
		$userId = (int)$this->session->userdata('userId');
		$data	= array();
		if ($userId != USER_ANONYMOUS) {		
			$data = $this->Users_Model->get($userId);
		}

		$commentUserEmail = element('userEmail', $data);
		if (valid_email($commentUserEmail) == false) {
			$commentUserEmail = '';
		}

		$form = array(
			'frmId'		=> 'frmCommentEdit',
			'callback' 	=> 'function(response) { $.Feedback.onSaveFeedback(); };',
			'messages' 	=> getCrFormRulesMessages(),
			'fields' => array( 
				'commentId' => array(
					'type'	=> 'hidden', 
					'value'	=> element('commentId', $data, 0)
				),
				'commentUserName' => array(
					'type' 		=> 'text',
					'label'		=> $this->lang->line('Name'), 
					'value'		=> element('userFirstName', $data).' '.element('userLastName', $data),
				),						
				'commentUserEmail' => array(
					'type' 		=> 'text',
					'label'		=> $this->lang->line('Email'), 
					'value'		=> $commentUserEmail
				),										
				'commentDesc' => array(
					'type'		=> 'textarea',
					'label'		=> $this->lang->line('Comment'), 
					'value'		=> ''
				),
			),
			'buttons'		=> array( '<button type="submit" class="btn btn-primary"><i class="icon-comment"></i> '.$this->lang->line('Send').'</button> '),
		);
		
		$form['rules'] = array(
			array(
				'field' => 'commentUserName',
				'label' => $form['fields']['commentUserName']['label'],
				'rules' => 'required'
			),
			array(
				'field' => 'commentUserEmail',
				'label' => $form['fields']['commentUserEmail']['label'],
				'rules' => 'required|valid_email'
			),			
			array(
				'field' => 'commentDesc',
				'label' => $form['fields']['commentDesc']['label'],
				'rules' => 'required'
			),
		);	

		$this->form_validation->set_rules($form['rules']);
		$this->form_validation->set_message($form['messages']);
		
		if ($this->input->is_ajax_request()) { // save data			
			return $this->load->view('ajax', array(
				'code'		=> $this->Comments_Model->saveFeedback($this->input->post()), 
				'result' 	=> validation_errors() 
			));
		}
				
		$this->load->view('includes/template', array(
			'view'		=> 'includes/crForm', 
			'title'		=> $this->lang->line('Feedback'),
			'form'		=> $form,
			'aJs'		=> array('feedback.js'),
			'langs'		=> array( 'Thanks for contacting us' )
		));		
	}
}