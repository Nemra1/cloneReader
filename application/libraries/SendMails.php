<?php
class SendMails {
	/*
	 *
	 * Libreria de emails, cada metodo debe corresponder a un email determinado.
	 * El metodo solo recibe un array con los datos necesarios  para el email especifico.
	 *
	 */

	//Para definir la instancia de la app
	private $CI = null;

	function __construct() {
		$this->CI = &get_instance();
		$this->CI->load->library('email');
		$this->CI->load->helper('email');
	}

	/**
	 * Setea el mail por default si env != prod
	 */
	function _addEmailTo($email) {
		if (ENVIRONMENT == 'production') {
			$this->CI->email->to($email);
			return;
		}

		$this->CI->email->to(config_item('emailDebug'));
	}

	function _sendEmail($emailTo, $subject, $message, $emailCc = null, $emailFrom = null, $emailReplyTo = null) {
		if ($emailFrom != null) {
			$this->CI->email->from($emailFrom['email'], $emailFrom['name']);
		}
		else {
			$this->CI->email->from(config_item('emailFrom'), config_item('siteName'));
		}
		$this->_addEmailTo($emailTo);

		if ($emailCc != null) {
			$this->CI->email->cc($emailCc);
		}
		if ($emailReplyTo != null) {
			$this->CI->email->reply_To($emailReplyTo['email'], $emailReplyTo['name']);
		}

		$this->CI->email->subject($subject);
		$this->CI->email->message($message);
		if ($this->CI->email->send()) {
			return true;
		}
		//echo $this->CI->email->print_debugger();	die;
		return false;
	}

	function sendEmailWelcome($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}
		$this->CI->load->model('Users_Model');
		$user  = $this->CI->Users_Model->get($params['userId']);
		$url   = ($user['confirmEmailKey'] != null ? base_url('confirmEmail?key='.$user['confirmEmailKey']) : null);
		$message         = $this->CI->load->view('pageEmail',
			array(
				'view'   => 'email/welcome.php',
				'user'   => $user,
				'url'    => $url
			), true);


		return $this->_sendEmail($user['userEmail'], sprintf(lang('Welcome to %s'), ucfirst(config_item('siteName'))), $message);
	}

	function sendEmailToResetPassword($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}
		$this->CI->load->model('Users_Model');

		$user = $this->CI->Users_Model->get($params['userId']);

		$userEmail          = $user['userEmail'];
		$resetPasswordKey   = $user['resetPasswordKey'];
		$url                = base_url('resetPassword?key='.$resetPasswordKey);
		$message            = $this->CI->load->view('pageEmail',
			array(
				'view'  => 'email/resetPassword.php',
				'user'  => $user,
				'url'   => $url,
			),
			true);

		return $this->_sendEmail($userEmail, sprintf(lang('Reset password in %s'), config_item('siteName')), $message);
	}

	function sendEmailToChangeEmail($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}

		$this->CI->load->model('Users_Model');
		$userId          = $params['userId'];
		$user            = $this->CI->Users_Model->get($userId);
		$userEmail       = $user['confirmEmailValue'];
		$confirmEmailKey = $user['confirmEmailKey'];
		$url             = base_url('confirmEmail?key='.$confirmEmailKey);
		$message         = $this->CI->load->view('pageEmail',
			array(
				'view'   => 'email/changeEmail.php',
				'user'   => $user,
				'url'    => $url
			), true);

		return $this->_sendEmail($userEmail, sprintf(lang('Change email in %s'), config_item('siteName')), $message);
	}

	function sendFeedback($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}

		$message = $this->CI->load->view('pageEmail',
			array(
				'view'                  => 'email/feedback.php',
				'feedbackUserName'      => element('feedbackUserName', $params),
				'feedbackUserEmail'     => element('feedbackUserEmail', $params),
				'feedbackDesc'          => element('feedbackDesc', $params),
				'feedbackDate'          => element('feedbackDate', $params),
				'url'                   => null,
			),
			true);

		return $this->_sendEmail(config_item('emailDebug'), 'Comentario de '.element('feedbackUserName', $params), $message, null, null, array('email' => element('feedbackUserEmail', $params), 'name' => element('feedbackUserName', $params)));
	}

	function shareByEmail($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}

		$this->CI->load->model(array('Users_Model', 'Entries_Model'));

		$userId                = $params['userId'];
		$entryId               = $params['entryId'];
		$userFriendEmail       = $params['userFriendEmail'];
		$sendMeCopy            = $params['sendMeCopy'];
		$shareByEmailComment   = $params['shareByEmailComment'];
		$entry                 = $this->CI->Entries_Model->get($entryId, false);
		$user                  = $this->CI->Users_Model->get($userId);
		$userFullName          = $user['userFirstName'].' '.$user['userLastName'];

		if ($entry['entryAuthor'] == '') {
			$entryOrigin = sprintf(lang('From %s'), '<a href="'.$entry['entryUrl'].'" >' . $entry['feedName'] . '</a>');
		}
		else {
			$entryOrigin = sprintf(lang('From %s by %s'), '<a href="'.$entry['entryUrl'].'" >' . $entry['feedName'] . '</a>', $entry['entryAuthor']);
		}

		$message = $this->CI->load->view('pageEmail',
			array(
				'view'                  => 'email/shareEntry.php',
				'shareByEmailComment'   => $shareByEmailComment,
				'userFullName'          => $userFullName,
				'entry'                 => $entry,
				'entryOrigin'           => $entryOrigin,
				'url'                   => null,
			),
			true);
		//echo $message; die;

		$emailCc = null;
		if ($sendMeCopy == true) {
			$emailCc = $user['userEmail'];
		}
		return $this->_sendEmail($userFriendEmail, $entry['entryTitle'], $message, $emailCc, null, array('email' => $user['userEmail'], 'name' => $userFullName));
	}

	function changeFeedStatus($params = array()) {
		if(empty($params) || !is_array($params)){
			return false;
		}

		$feedId = $params['feedId'];

		$this->CI->load->model(array('Feeds_Model'));
		$feed = $this->CI->Feeds_Model->get($feedId);

		if (element('newStatus', $params) == 'feedFound') {
			$subject = 'Feed '.$feed['feedName'].' ('.$feed['feedId'].') activado automáticamente';
		}
		else {
			$subject = 'Feed '.$feed['feedName'].' ('.$feed['feedId'].') desactivado automáticamente';
		}

		$message = $this->CI->load->view('pageEmail',
			array(
				'view'       => 'email/changeFeedStatus.php',
				'feed'       => $feed,
				'newStatus'  => element('newStatus', $params),
				'url'        => null,
			),
			true);

		return $this->_sendEmail(config_item('emailDebug'), $subject, $message);
	}
}
