<?php
class Status_Model extends CI_Model {
	function select(){
		return $this->db->order_by('statusName')->get('status')->result_array();
	}

	function selectToDropdown() {
		$result = array();
		$query  = $this->db->get('status')->result_array();
		foreach ($query as $data) {
			$result[] = array(
				'id'   => $data['statusId'],
				'text' => lang(ucfirst($data['statusName'])),
			);
		}

		usort($result, function($a, $b) {
			return $a['text'] < $b['text'] ? -1 : 1;
		});

		return $result;
	}
}
