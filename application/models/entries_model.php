<?php
class Entries_Model extends CI_Model {
	function selectToList($num, $offset, $filter){
		$query = $this->db->select('SQL_CALC_FOUND_ROWS entries.entryId AS id, entryTitle AS \'Titulo\', entryUrl AS \'Url\' ', false)
						->like('entryTitle', $filter)
		 				->get('entries', $num, $offset);
						
		$query->foundRows = $this->Commond_Model->getFoundRows();
		return $query;
	}
	
	function select($userId, $userFilters){
		$this->updateUserFilters($userFilters, $userId);
		
		if (!isset($userFilters['page'])) {
			$userFilters['page'] = 1;
		}
		if ($userFilters['type'] == 'tag' && $userFilters['id'] == TAG_STAR) {
			$userFilters['onlyUnread'] = false;
		}

		$indexName = 'PRIMARY';
		$query = $this->db
			->select('users_entries.feedId, feedName, feedUrl, feedLInk, feedIcon, users_entries.entryId, entryTitle, entryUrl, entryContent, entries.entryDate, entryAuthor, IF(users_entries.tagId = '.TAG_STAR.', true, false) AS starred, entryRead', false)
			->join('entries', 'users_entries.entryId = entries.entryId AND users_entries.feedId = entries.feedId', 'inner')
			->join('feeds', 'entries.feedId = feeds.feedId', 'inner')
			->where('users_entries.userId', $userId);
		
		if ($userFilters['type'] == 'feed') {
			$indexName = 'indexFeed';
			$this->db->where('users_entries.feedId', (int)$userFilters['id']);
			$this->db->where('users_entries.tagId', TAG_ALL);
		}
		if ($userFilters['type'] == 'tag') {
			$indexName = 'indexTag';
			$this->db->where('users_entries.tagId', (int)$userFilters['id']);
		}
		if ($userFilters['onlyUnread'] == true) {
			$this->db->where('users_entries.entryRead <> true');
		}

		$query = $this->db
			->order_by('users_entries.entryDate', ($userFilters['sortDesc'] == 'true' ? 'desc' : 'asc'))
			->get('users_entries FORCE INDEX ('.$indexName.')', ENTRIES_PAGE_SIZE, ((int)$userFilters['page'] * ENTRIES_PAGE_SIZE) - ENTRIES_PAGE_SIZE)
			->result_array();
		//pr($this->db->last_query());
		
		return $query;
	}

	function selectFilters($userId) {
		$aFilters = array();
	
		$result = array(
			'tags'		=> $this->selectTagsByUserId($userId),
			'filters'	=> array(
				array(
					'type'		=> 'tag',
					'id'		=> TAG_HOME,
					'name'		=> 'home',
					'icon'		=> site_url().'css/img/default_feed.png', 
				),
				array(
					'type'		=> 'tag',
					'id'		=> TAG_STAR,
					'name'		=> 'starred', 
					'icon'		=> site_url().'css/img/star-on.png', 
				)
			)
		);

		$aFilters['tags'] = array(
			'type'		=> 'tag',
			'id'		=> TAG_ALL,		
			'name'		=> 'Subscriptions',
			'count'		=> 380,
			'expanded'	=> true,
			'childs'	=> array()				
		); 		
		
		$result['filters'][] = & $aFilters['tags'];

		$query = $this->db->select('feeds.feedId, feedName, feedUrl, tags.tagId, tagName, users_tags.expanded AS eee, IF(users_tags.expanded = 1, true, false) AS expanded, feeds.feedLink, feeds.feedIcon ', false)
						->join('users_feeds', 'users_feeds.feedId = feeds.feedId', 'left')
						->join('users_feeds_tags', 'users_feeds_tags.feedId = feeds.feedId AND users_feeds_tags.userId = users_feeds.userId', 'left')
						->join('tags', 'users_feeds_tags.tagId = tags.tagId', 'left')
						->join('users_tags', 'users_tags.userId = users_feeds.userId AND users_tags.tagId = tags.tagId', 'left')
						->where('users_feeds.userId', $userId)
//						->where('feeds.statusId IN ('.FEED_STATUS_PENDING.', '.FEED_STATUS_APPROVED.')')
						->order_by('tagName IS NULL, tagName asc, feedName asc')
		 				->get('feeds');
		//pr($this->db->last_query());				
		foreach ($query->result() as $row) {
			if ($row->tagId != null && !isset($aFilters[$row->tagId])) {
				$aFilters[$row->tagId] = array(
					'type'		=> 'tag',
					'id'		=> $row->tagId,
					'name'		=> $row->tagName,
					'expanded'	=> ($row->expanded == true),
					'childs'	=> array()				
				); 
				
				$aFilters['tags']['childs'][] = & $aFilters[$row->tagId];
			}
			
			$count = $this->getTotalByFeedIdAndUserId($row->feedId, $userId);

			$feed = array(
				'type'		=> 'feed',
				'id'		=> $row->feedId, 
				'name'		=> $row->feedName, 
				'url'		=> $row->feedUrl,
				'icon'		=> ($row->feedIcon == null ? site_url().'css/img/default_feed.png' : site_url().'img/'.$row->feedIcon), 
				'count'		=> $count,
			);

			if ($row->tagId != null) {
				$aFilters[$row->tagId]['childs'][] = $feed;
			}
			else {
				$aFilters['tags']['childs'][] = $feed;
			}
		}

		return $result;
	}

	function getTotalByFeedIdAndUserId($feedId, $userId) {
		$query = ' SELECT 
				COUNT(1) AS total FROM ( 
			    	SELECT 1 
			    	FROM users_entries FORCE INDEX (indexUnread)
			    	WHERE feedId 		= '.$feedId.'
					AND   userId	 	= '.$userId.'
					AND   tagId			= '.TAG_ALL.' 
			    	AND   entryRead 	= false 
					LIMIT '.(FEED_MAX_COUNT + 50).' 
			) AS tmp ';
		$query = $this->db->query($query)->result_array();					
		//pr($this->db->last_query());
		return $query[0]['total'];
	}
		
	function selectTagsByUserId($userId) {
		$query = $this->db->select('tags.tagId, tagName ', false)
			->join('users_tags', 'users_tags.tagId = tags.tagId', 'inner')
			->where('users_tags.userId', $userId)
			->where('tags.tagId NOT IN ('.TAG_ALL.', '.TAG_STAR.', '.TAG_HOME.')')
			->order_by('tagName asc')
			->get('tags');
		//pr($this->db->last_query());				
		return $query->result_array();
	}

	function get($entryId){
		$result = $this->db
				->where('entryId', $entryId)
				->get('entries')->row_array();
		return $result;
	}
	
	function getLastEntryDate($feedId) {
		$query = $this->db
				->where('feedId', $feedId)
				->order_by('entryDate', 'desc')
				//->get('entries FORCE INDEX (indexFeedIdEntryDate)', 1)->row_array();
				->get('entries', 1)->row_array();
		//pr($this->db->last_query());	
		if (!empty($query)) {
			return $query['entryDate'];
		}
		return null;
	}
	
	function save($data){
// TODO: usar el metodo saveEntry		
		$entryId = $data['entryId'];

		$values = array(
			'feedId'			=> $data['feedId'],
			'entryTitle'		=> $data['entryTitle'],
			'entryContent'		=> $data['entryContent'],
			'entryAuthor'		=> $data['entryAuthor'],
			'entryDate'			=> $data['entryDate'],
			'entryUrl'			=> $data['entryUrl'],
		);
		

		if ((int)$entryId != -1) {		
			$this->db->where('entryId', $entryId);
			$this->db->update('entries', $values);
		}
		else {
			$this->db
				->ignore()
				->insert('entries', $values);
			$entryId = $this->db->insert_id();
		}
		//pr($this->db->last_query());

		return true;
	}
	
	function saveEntry($data) {
		if (trim($data['entryUrl']) == '') {
			return null;
		}
		
		$this->db->ignore()->insert('entries', $data);
	}

	function saveUserEntries($userId, $entries) {
		foreach ($entries as $entry) {
			if ($entry['starred'] == true) {
				$query = ' INSERT IGNORE INTO users_entries (userId, entryId, feedId, tagId, entryRead, entryDate)  
					SELECT '.$userId.', entryId, feedId, '.TAG_STAR.', false, entryDate
					FROM entries 
					WHERE entryId = '.$entry['entryId'];
				$this->db->query($query);
				//pr($this->db->last_query());	 
			}
			else {
				$this->db->delete('users_entries', array(
					'userId'	=> $userId,
					'entryId'	=> $entry['entryId'],
					'tagId'		=> TAG_STAR
				));
			}
			
			$this->db
				->where(array(
					'userId'	=> $userId,
					'entryId'	=> $entry['entryId'])
				)
				->update('users_entries', array('entryRead' => (element('entryRead', $entry) == true)));
			//pr($this->db->last_query());	
		}
	}
	
	function saveUserTags($userId, $tags) {
		foreach ($tags as $tag) {
			$this->db->replace('users_tags', array('userId' => $userId, 'tagId' => $tag['tagId'], 'expanded' => element('expanded', $tag) == true));
			//pr($this->db->last_query());	
		}
	}

	function saveUserFeedTag($userId, $feedId, $tagId, $append) {
		$values = array('userId' => $userId, 'feedId' => $feedId, 'tagId' => $tagId);

		if ($append == false) {
			$this->db->delete('users_feeds_tags', $values);
			//pr($this->db->last_query());	
			
			$this->db->delete('users_entries', array(
				'userId' => $userId,
				'feedId' => $feedId,
				'tagId'	 => $tagId
			));
			//pr($this->db->last_query());
			
			return true;
		}

		$this->db
			->ignore()
			->insert('users_feeds_tags', $values);
		//pr($this->db->last_query());
		
		$query = ' INSERT IGNORE INTO users_entries (userId, entryId, feedId, tagId, entryRead, entryDate)  
						SELECT userId, entryId, feedId, '.$tagId.', entryRead, entryDate
						FROM users_entries
						WHERE users_entries.userId 	= '.$userId.'
						AND   users_entries.feedId 	= '.$feedId.'
						AND   users_entries.tagId	= '.TAG_ALL.' ';			
		$this->db->query($query);
		//pr($this->db->last_query());
								
		return true;
	}
	
	function updateFeedStatus($feedId, $statusId) {
		$this->db
			->where('feedId', $feedId)
			->update('feeds', array('statusId' => $statusId ));
		//pr($this->db->last_query());		
	}	

	function addFeed($userId, $feed) {
		$this->load->model('Feeds_Model');
		$feedId = $this->Feeds_Model->save($feed);

		$this->db->ignore()->insert('users_feeds', array( 'feedId'	=> $feedId, 'userId' => $userId ));
		//pr($this->db->last_query());
		
		return $feedId;
	}

	function addTag($tagName, $userId, $feedId = null) {
		$tagName = trim($tagName);

		$query = $this->db->where('tagName', $tagName)->get('tags')->result_array();
		//pr($this->db->last_query());
		if (!empty($query)) {
			$tagId = $query[0]['tagId'];
		}
		else {
			$this->db->insert('tags', array( 'tagName'	=> $tagName ));
			$tagId = $this->db->insert_id();
			//pr($this->db->last_query());
		}

		$this->db->ignore()->insert('users_tags', array( 'tagId'=> $tagId, 'userId' => $userId ));
		//pr($this->db->last_query());


		if ($feedId != null) {
			$this->db->replace('users_feeds_tags', array( 'tagId'=> $tagId, 'feedId'	=> $feedId, 'userId' => $userId ));
			//pr($this->db->last_query());
		}
		
		return array('tagId' => $tagId);
	}

	function unsubscribeFeed($feedId, $userId) {
		$this->db->delete('users_feeds', array('feedId' => $feedId, 'userId' => $userId));
		//pr($this->db->last_query());
		return true;		
	}
	
	
	function markAsReadFeed($feedId, $userId) {
		$this->db->update('users_entries', array('entryRead' => true), array('feedId' => $feedId, 'userId' => $userId));
		//pr($this->db->last_query());
		return true;		
	}	
	
	
	function updateUserFilters($userFilters, $userId){
		unset($userFilters['page']);
		$this->load->model('Users_Model');
		$this->Users_Model->updateUserFiltersByUserId($userFilters, (int)$userId);
	}	
	
	function saveFeedIcon($feedId, $feedLink, $feedIcon) {
		if (trim($feedLink) != '' && $feedIcon == null) {
			$this->load->spark('curl/1.2.1');
			$img 			= $this->curl->simple_get('https://plus.google.com/_/favicon?domain='.$feedLink);
			$parse 			= parse_url($feedLink);
			$feedIcon 	= $parse['host'].'.png'; 
			file_put_contents('./img/'.$feedIcon, $img);
			$this->db->update('feeds', array('feedIcon' => $feedIcon), array('feedId' => $feedId));	
		}				
	}	

	function getNewsEntries($userId = null, $feedId = null) {
		set_time_limit(0);
		
		$this->db
			->select('feeds.feedId, feedUrl, feedLink, feedIcon')
			->join('users_feeds', 'users_feeds.feedId = feeds.feedId', 'inner')
			->where('feedLastUpdate < DATE_ADD(NOW(), INTERVAL -'.FEED_TIME_SCAN.' MINUTE)')
			->where('feeds.statusId IN ('.FEED_STATUS_PENDING.', '.FEED_STATUS_APPROVED.')')
//->where('feeds.feedId IN (166)')			
			->order_by('feedLastUpdate ASC');

		if (is_null($userId) == false) {
			$this->db->where('users_feeds.userId', $userId);
		}
		if (is_null($feedId) == false) {
			$this->db->where('feeds.feedId', $feedId);			
		}
		 
		$query = $this->db->get('feeds');
		//pr($this->db->last_query()); 
		foreach ($query->result() as $row) {
			$this->parseRss($row->feedId, $row->feedUrl);
			$this->saveFeedIcon($row->feedId, $row->feedLink, $row->feedIcon);
		}
	}		

	// TODO: mover estos metodos de aca
	function parseRss($feedId, $feedUrl) {
		// vuelvo a preguntar si es momento de volver a scanner el feed, ya que pude haber sido scaneado recién al realizar multiples peticiones asyncronicas
		$query = $this->db
			->select('TIMESTAMPDIFF(MINUTE, feedLastUpdate, DATE_ADD(NOW(), INTERVAL -'.FEED_TIME_SCAN.' MINUTE)) AS minutes ', false)
			->where('feeds.feedId', $feedId)
			->get('feeds')->result_array();
		//pr($this->db->last_query()); 
		$feed = $query[0];
		if ($feed['minutes'] != null && (int)$feed['minutes'] > FEED_TIME_SCAN ) {  // si paso poco tiempo salgo, porque acaba de escanear el mismo feed otro proceso
			return;
		}

		$this->load->spark('ci-simplepie/1.0.1/');
		$this->cisimplepie->set_feed_url($feedUrl);
		$this->cisimplepie->enable_cache(false);
		$this->cisimplepie->init();
		$this->cisimplepie->handle_content_type();

		if ($this->cisimplepie->error() ) {
			return $this->updateFeedStatus($feedId, FEED_STATUS_NOT_FOUND);
		}
	
		$lastEntryDate = $this->getLastEntryDate($feedId);
			
		$rss = $this->cisimplepie->get_items();

		foreach ($rss as $item) {
			$entryAuthor = '';
			if ($author = $item->get_author()) {
				$entryAuthor = $author->get_name();
			}

			$data = array(
				'feedId' 		=> $feedId,
				'entryTitle'	=> $item->get_title(),
				'entryContent'	=> (string)$item->get_content(),
				'entryDate'		=> $item->get_date('Y-m-d H:i:s'),
				'entryUrl'		=> (string)$item->get_link(),
				'entryAuthor'	=> (string)$entryAuthor,
			);
			
			if ($data['entryDate'] == null) {
				return $this->updateFeedStatus($feedId, FEED_STATUS_INVALID_FORMAT);
			}
			
			if ($data['entryDate'] == $lastEntryDate) { // si no hay nuevas entries salgo del metodo
				$this->db->update('feeds', array(
					'statusId' 			=> FEED_STATUS_APPROVED,
					'feedLastUpdate' 	=> date("Y-m-d H:i:s")
				), array('feedId' => $feedId));	
				return;
			}
			
			$this->saveEntry($data);
		}

		$values = array( 
			'statusId'			=> FEED_STATUS_APPROVED,
			'feedLastUpdate' 	=> date("Y-m-d H:i:s")
		); 
		if (trim((string)$this->cisimplepie->get_title()) != '') {
			$values['feedName'] = (string)$this->cisimplepie->get_title(); 			
		}
		if (trim((string)$this->cisimplepie->get_link()) != '') {
			$values['feedLink'] = (string)$this->cisimplepie->get_link();
		}
		$this->db->update('feeds', $values, array('feedId' => $feedId));
	}
	
	function populateMillionsEntries() {
		ini_set('memory_limit', '-1');
				
		$query = $this->db->select('MAX(entryId) + 1 AS entryId', true)->get('entries')->result();
		//pr($this->db->last_query()); 
		$entryId = $query[0]->entryId;

		$query = $this->db
			->select('feeds.feedId')
			->join('users_feeds', 'users_feeds.feedId = feeds.feedId', 'inner')
//			->where('feeds.statusId IN ('.FEED_STATUS_PENDING.', '.FEED_STATUS_APPROVED.')')
			->get('feeds');
		//pr($this->db->last_query()); 
		foreach ($query->result() as $row) {		
			
			$this->db->trans_start();

			$data = array();
			for ($i=0; $i<10000; $i++) {
				$data[] = array(
					'feedId' 		=> $row->feedId,
					'entryTitle'	=> 'titulooooo '.$entryId,
					'entryContent'	=> 'contenido del entry <b><test/b>'.$entryId,
					'entryDate'		=> date('Y-m-d H:i:s'),
					'entryUrl'		=> 'http://saranga.com/dadadad/'.$entryId,
					'entryAuthor'	=> 'el autor',
				);
				
				
				if (($i % 100) == 0) { 
					$this->db->insert_batch('entries', $data);
					//$this->db->insert('entries', $data);
					unset($data);
					$data = array();
				}
				
				$entryId++;
			}
			$this->db->insert_batch('entries', $data);
			//pr($this->db->last_query());
			
			$this->db->trans_complete();
		}
	}
	
	function saveEntriesTagByUser($userId) {
		// TODO: paginar este proceso para que guarde TODAS las entradas nuevas sin tener que relodear
		// metiendo 20 millones de entradas nuevas hay que relodear bocha de veces hasta ver la mas nueva
		$entryId 	= null;
		$limit 		= 100000;
		
		$query = ' SELECT
						MAX(entryId) AS entryId
						FROM  users_entries  
						WHERE userId  = '.$userId.' ';
		$query = $this->db->query($query)->result_array();
		//pr($this->db->last_query());	
		if (!empty($query)) {
			$entryId = $query[0]['entryId'];
		}		

		// save TAG_ALL
		$query = ' INSERT INTO users_entries (userId, entryId, feedId, tagId, entryRead, entryDate) 
					SELECT users_feeds.userId, entries.entryId, entries.feedId, '.TAG_ALL.', false, entries.entryDate 
					FROM entries 
					INNER JOIN users_feeds 
						ON entries.feedId = users_feeds.feedId
						AND users_feeds.userId = '.$userId.' 
					LEFT JOIN users_entries
						ON    users_entries.userId 		= users_feeds.userId
						AND   users_entries.entryId 	= entries.entryId
						AND   users_entries.feedId 		= entries.feedId
						AND   users_entries.tagId 		= '.TAG_ALL.'
					WHERE users_entries.userId IS NULL
					'.($entryId != null ? ' AND entries.entryId > '.$entryId : '').'
				LIMIT '.$limit;
		$this->db->query($query);
		pr($this->db->last_query());
		
		// save Custom Tags
		$query = ' INSERT INTO users_entries (userId, entryId, feedId, tagId, entryRead, entryDate) 
					SELECT users_feeds_tags.userId, entries.entryId, entries.feedId, users_feeds_tags.tagId, false, entries.entryDate
					FROM entries 
					INNER JOIN users_feeds_tags FORCE INDEX (indexUserIdFeedId) 
						ON users_feeds_tags.feedId = entries.feedId 
						AND users_feeds_tags.userId = '.$userId.' 
					LEFT JOIN users_entries
						ON users_entries.userId  		= users_feeds_tags.userId
						AND   users_entries.entryId 	= entries.entryId
						AND   users_entries.feedId		= entries.feedId     
						AND   users_entries.tagId		= users_feeds_tags.tagId     
					WHERE users_entries.userId IS NULL 
					'.($entryId != null ? ' AND entries.entryId > '.$entryId : '').'
					LIMIT '.$limit;
		$this->db->query($query);
		pr($this->db->last_query());

		if ($this->db->affected_rows() == $limit) {
			sleep(2);
			$this->saveEntriesTagByUser($userId);
		}
	}
}
