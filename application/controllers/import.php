<?php
class Import extends CI_Controller {

	function __construct() {
		parent::__construct();
	}

	function index() {}

	function feeds() {
		if (! $this->safety->allowByControllerName(__METHOD__) ) { return errorForbidden(); }

		$form = array(
			'rules'   => array(),
			'frmName' => 'frmImportFeeds',
			'action'  => base_url('import/doImportFeeds'),
			'fields'  => array(
				'tagName' => array(
					'type'  => 'upload',
					'label' => lang('Choose the subscriptions.xml file from gReader or a standard OPML file'),
				),
			),
			'buttons' => array()
		);

		$this->load->view('pageHtml', array(
			'view'   => 'includes/crForm',
			'meta'   => array( 'title' => lang('Import feeds')),
			'form'   => $form,
		));
	}

	function doImportFeeds() {
		if (! $this->safety->allowByControllerName('import/feeds') ) { return errorForbidden(); }

		set_time_limit(0);

		$this->load->model('Entries_Model');

		$userId = $this->session->userdata('userId');

		$config	= array(
			'upload_path'   => './application/cache',
			'allowed_types' => 'xml|opml',
			'max_size'      => 1024 * 8,
			'encrypt_name'  => false,
			'is_image'      => false,
			'overwrite'     => true,
			'file_name'     => 'import_feeds_'.$userId
		);

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload()) {
			return loadViewAjax(false, $this->upload->display_errors());
		}

		$this->db->trans_start();

		$fileName = $config['upload_path'].'/'.$config['file_name'].$this->upload->file_ext;
		$xml      = simplexml_load_file($fileName);

		foreach ($xml->xpath('//body/outline') as $tag) {
			if (count($tag->children()) > 0) {
				$tagName = (string)$tag['title'];

				foreach ($tag->children() as $feed) {

					$feed = array(
						'feedName'    => (string)$feed->attributes()->title,
						'feedUrl'     => (string)$feed->attributes()->xmlUrl,
						'feedLink'    => (string)$feed->attributes()->htmlUrl,
						'feedSuggest' => true,
					);
					$feedId =  $this->Entries_Model->addFeed($userId, $feed);
					$this->Entries_Model->addTag($tagName, $userId, $feedId);
				}
			}
			else {
				$feed = array(
					'feedName'    => (string)$tag->attributes()->title,
					'feedUrl'     => (string)$tag->attributes()->xmlUrl,
					'feedLink'    => (string)$tag->attributes()->htmlUrl,
					'feedSuggest' => true,
				);
				$this->Entries_Model->addFeed($userId, $feed);
			}
		}

		$this->db->trans_complete();

		return loadViewAjax(true, array('msg' => lang('The import was successful'), 'goToUrl' => base_url(''), 'skipAppLink' => true));
	}

	function starred() {
		if (! $this->safety->allowByControllerName(__METHOD__) ) { return errorForbidden(); }

		$form = array(
			'frmName' => 'frmImportStarred',
			'rules'   => array(),
			'action'  => base_url('import/doImportStarred'),
			'fields'  => array(
				'tagName' => array(
					'type'  => 'upload',
					'label' => sprintf(lang('Choose %s'), 'starred.json'),
				),
			),
			'buttons'	=> array()
		);

		$this->load->view('pageHtml', array(
			'view'   => 'includes/crForm',
			'meta'   => array( 'title' => lang('Import starred')),
			'form'   => $form
		));
	}

	function doImportStarred() {
		if (! $this->safety->allowByControllerName('import/starred') ) { return errorForbidden(); }

		set_time_limit(0);

		$this->load->model('Entries_Model');

		$userId = $this->session->userdata('userId');

		$config	= array(
			'upload_path'   => './application/cache',
			'allowed_types' => 'json',
			'max_size'      => 1024 * 8,
			'encrypt_name'  => false,
			'is_image'      => false,
			'overwrite'     => true,
			'file_name'     => 'import_starred_'.$userId.'.json'
		);

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload()) {
			return loadViewAjax(false, $this->upload->display_errors());
		}

		$this->db->trans_start();

		$fileName 	= './application/cache/import_starred_'.$userId.'.json';
		$json 		= (array)json_decode(file_get_contents($fileName), true);

		foreach ($json['items'] as $data) {
			$entryContent = '';
			if (element('summary', $data) != null) {
				$entryContent = $data['summary']['content'];
			}
			else if (element('content', $data) != null) {
				$entryContent = $data['content']['content'];
			}

			$entry = array(
				'entryTitle'   => element('title', $data, '(title unknown)'),
				'entryUrl'     => (string)$data['alternate'][0]['href'],
				'entryAuthor'  => element('author', $data, null),
				'entryDate'    => date('Y-m-d H:i:s', $data['published']),
				'entryContent' => (string)$entryContent,
			);

			$feed = array(
				'feedName'    => element('title', $data['origin']),
				'feedUrl'     => substr($data['origin']['streamId'], 5),
				'feedLink'    => $data['origin']['htmlUrl'],
				'feedName'    => element('title', $data['origin']),
				'feedSuggest' => true,
			);

			$entry['feedId']  = $this->Entries_Model->addFeed($userId, $feed);
			$entry['entryId'] = $this->Entries_Model->saveEntry($entry);
			if ($entry['entryId'] == null) {
				$entry['entryId'] = $this->Entries_Model->getEntryIdByFeedIdAndEntryUrl($entry['feedId'], $entry['entryUrl']);
			}

			$this->Entries_Model->saveUserEntries($userId, $entry['feedId'], $entry['entryId']);
			$this->Entries_Model->saveTmpUsersEntries($userId, array(array( 'userId' => $userId, 'entryId' => $entry['entryId'], 'entryStarred' => true,  'entryRead' => true )));
		}

		$this->db->trans_complete();

		$this->Entries_Model->pushTmpUserEntries($userId);

		return loadViewAjax(true, array('msg' => lang('The import was successful'), 'goToUrl' => base_url(''), 'skipAppLink' => true));
	}
}
