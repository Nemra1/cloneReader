<?php
class News_Model extends CI_Model {
	function selectToList($pageCurrent = null, $pageSize = null, array $filters = array()){
		$this->db
			->select('SQL_CALC_FOUND_ROWS news.newId, newTitle, newSef, newDate, CONCAT(userFirstName, \' \', userLastName) AS userFullName ', false)
			->from('news')
			->join('users', 'news.userId = users.userId', 'inner');
			
		if (element('search', $filters) != null) {
			$this->db->like('newTitle', $filters['search']);
		}
		
		$this->Commond_Model->appendLimitInQuery($pageCurrent, $pageSize);
		
		$query = $this->db
			->order_by('news.newId')
			->get();

		return array('data' => $query->result_array(), 'foundRows' => $this->Commond_Model->getFoundRows());
	}

	function get($newId, $isForm = false){
		$query = $this->db
				->select('news.*', true)
				->where('newId', $newId)
				->get('news')->row_array();
				
		if (!empty($query) && $isForm == true) {
			$userId = element('userId', $query);
			if ($userId == null) {
				$userId = $this->session->userdata('userId');
			}
				
			$user = $this->Users_Model->get($userId);
			$query['userId'] = array( 'id' => $user['userId'], 'text' => $user['userFirstName'].' '.$user['userLastName']);
		}
		
		return $query;
	}
	
	function getByNewSef($newSef){
		$query = $this->db
				->select('news.*', true)
				->where('newSef', $newSef)
				->get('news')->row_array();
		return $query;
	}	
	
	function save($data){
		$newId = $data['newId'];

		$values = array(
			'newTitle'   => element('newTitle', $data),
			'newContent' => element('newContent', $data),
			'userId'     => element('userId', $data),
			'newDate'    => element('newDate', $data),
		);

		if ((int)$newId != 0) {		
			$this->db->where('newId', $newId)->update('news', $values);
		}
		else {
			$values['newSef'] = url_title($data['newTitle'], '_', true);
			
			$this->db->insert('news', $values);
			$newId = $this->db->insert_id();
		}
		//pr($this->db->last_query()); 

		return true;
	}
	
	function delete($newId) {
		$this->db->delete('news', array('newId' => $newId));
		return true;
	}	

	function selectToRss(){
		$query = $this->db
			->select(' news.newId, newTitle, newContent, newSef, newDate, CONCAT(userFirstName, \' \', userLastName) AS userFullName ', false)
			->join('users', 'news.userId = users.userId', 'inner')
			->order_by('newDate DESC')
			->get('news', 30, 0);

		return array('data' => $query->result_array(), 'foundRows' => $this->Commond_Model->getFoundRows());
	}
}
