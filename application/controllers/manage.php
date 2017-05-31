<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once MODPATH.'core/controllers/nova_manage.php';

class Manage extends Nova_manage {

	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Put your own methods below this...
	 */
	public function logs($section = 'activated', $offset = 0)
	{
		Auth::check_access();
		$level = Auth::get_access_level();
		
		$this->load->model('personallogs_model', 'logs');
		
		// arrays to check uri against
		$values = array('activated', 'saved', 'pending', 'edit');
		
		// sanity checks
		$section = (in_array($section, $values)) ? $section : 'activated';
		$offset = (is_numeric($offset)) ? $offset : 0;
		
		if (isset($_POST['submit']))
		{
			switch ($this->uri->segment(5))
			{
				case 'approve':
					if ($level == 2)
					{
						$id = $this->input->post('id', true);
						$id = (is_numeric($id)) ? $id : false;
						
						// set the array data
						$approve_array = array('log_status' => 'activated');
						
						// approve the post
						$approve = $this->logs->update_log($id, $approve_array);
						
						$message = sprintf(
							($approve > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_personallog')),
							lang('actions_approved'),
							''
						);
						$flash['status'] = ($approve > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
						
						if ($approve > 0)
						{
							// grab the post details
							$row = $this->logs->get_log($id);
							
							// set the array of data for the email
							$email_data = array(
								'author' => $row->log_author_character,
								'title' => $row->log_title,
								'content' => $row->log_content
							);
							
							// send the email
							$email = ($this->options['system_email'] == 'on') ? $this->_email('log', $email_data) : false;
						}
					}
				break;
					
				case 'delete':
					$id = $this->input->post('id', true);
					$id = (is_numeric($id)) ? $id : false;
					
					// get the log we're trying to delete
					$item = $this->logs->get_log($id);
					
					// make sure the user is allowed to be deleting the log
					if (($level == 1 and ($item->log_author_user == $this->session->userdata('userid'))) or $level == 2)
					{
						$delete = $this->logs->delete_log($id);
						
						$message = sprintf(
							($delete > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_personallog')),
							lang('actions_deleted'),
							''
						);
						$flash['status'] = ($delete > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
					}
				break;
					
				case 'update':
					$id = $this->uri->segment(4, 0, true);
					
					// get the log we're trying to delete
					$item = $this->logs->get_log($id);
					
					// make sure the user is allowed to be deleting the log
					if (($level == 1 and ($item->log_author_user == $this->session->userdata('userid'))) or $level == 2)
					{
						$update_array = array(
							'log_title' => $this->input->post('log_title', true),
							'log_tags' => $this->input->post('log_tags', true),
							'log_content' => $this->input->post('log_content', true),
							'log_status' => $this->input->post('log_status', true),
							'log_author_user' => $this->user->get_userid($this->input->post('log_author')),
							'log_author_character' => $this->input->post('log_author', true),
							'log_last_update' => now()
						);
						
						$update = $this->logs->update_log($id, $update_array);
						
						$message = sprintf(
							($update > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_personallog')),
							lang('actions_updated'),
							''
						);
						$flash['status'] = ($update > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
					}
				break;
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		if ($section == 'edit')
		{
			// grab the ID from the URL
			$id = $this->uri->segment(4, 0, true);
			
			// grab the post data
			$row = $this->logs->get_log($id);
			
			if ($level < 2)
			{
				if ($this->session->userdata('userid') != $row->log_author_user or $row->log_status == 'pending')
				{
					redirect('admin/error/6');
				}
			}
			
			// get all characters
			$all = $this->char->get_all_characters('user_npc');
			
			if ($all->num_rows() > 0)
			{
				foreach ($all->result() as $a)
				{
					if ($a->crew_type == 'active' or $a->crew_type == 'npc')
					{
						if ($a->crew_type == 'active')
						{
							$label = ucwords(lang('status_playing') .' '. lang('global_characters'));
						}
						else
						{
							$label = ucwords(lang('abbr_npcs'));
						}
						
						// toss them in the array
						$data['all'][$label][$a->charid] = $this->char->get_character_name($a->charid, true);
					}
				}
			}
			
			// set the data used by the view
			$data['inputs'] = array(
				'title' => array(
					'name' => 'log_title',
					'value' => $row->log_title),
				'content' => array(
					'name' => 'log_content',
					'id' => 'content-textarea',
					'rows' => 20,
					'value' => nl2br($row->log_content)),
				'tags' => array(
					'name' => 'log_tags',
					'value' => $row->log_tags),
				'author' => $row->log_author_character,
				'character' => $this->char->get_character_name($row->log_author_character, true),
				'status' => $row->log_status,
			);
			
			$data['status'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'saved' => ucfirst(lang('status_saved')),
				'pending' => ucfirst(lang('status_pending')),
			);
			
			$data['buttons'] = array(
				'update' => array(
					'type' => 'submit',
					'class' => 'button-main',
					'name' => 'submit',
					'value' => 'update',
					'content' => ucfirst(lang('actions_update'))),
			);
			
			$data['header'] = ucwords(lang('actions_edit') .' '. lang('global_personallogs'));
			$data['id'] = $id;
			
			$data['label'] = array(
				'back' => LARROW .' '. ucfirst(lang('actions_back')) .' '. lang('labels_to')
					.' '. ucwords(lang('global_personallogs')),
				'status' => ucfirst(lang('labels_status')),
				'title' => ucfirst(lang('labels_title')),
				'content' => ucfirst(lang('labels_content')),
				'tags' => ucfirst(lang('labels_tags')),
				'tags_inst' => ucfirst(lang('tags_separated')),
				'addauthor' => ucwords(lang('actions_add') .' '. lang('labels_author')),
				'author' => ucwords(lang('labels_author'))
			);
			
			$js_data['tab'] = 0;
			
			// figure out where the view should be coming from
			$view_loc = 'manage_logs_edit';
		}
		else
		{
			switch ($section)
			{
				case 'activated':
				default:
					$js_data['tab'] = 0;
				break;
					
				case 'saved':
					$js_data['tab'] = 1;
				break;
					
				case 'pending':
					$js_data['tab'] = 2;
				break;
			}
			
			$offset_activated = ($section == 'activated') ? $offset : 0;
			$offset_saved = ($section == 'saved') ? $offset : 0;
			$offset_pending = ($section == 'pending') ? $offset : 0;
			
			$data['activated'] = $this->_entries_ajax($offset_activated, 'activated', 'logs');
			$data['saved'] = $this->_entries_ajax($offset_saved, 'saved', 'logs');
			$data['pending'] = $this->_entries_ajax($offset_pending, 'pending', 'logs');
	
		    $data['label'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'pending' => ucfirst(lang('status_pending')),
				'saved' => ucfirst(lang('status_saved')),
			);
			
			$data['header'] = ucwords(lang('actions_manage') .' '. lang('global_personallogs'));
			
			// figure out where the view should be coming from
			$view_loc = 'manage_logs';
		}
		
		$this->_regions['content'] = Location::view($view_loc, $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('manage_logs_js', $this->skin, 'admin', $js_data);
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}

	public function news($section = 'activated', $offset = 0)
	{
		Auth::check_access();
		$level = Auth::get_access_level();
		
		$this->load->model('news_model', 'news');
		
		// array to check the values in the uri against
		$values = array('activated', 'saved', 'pending', 'edit');
		
		// sanity checks
		$section = (in_array($section, $values)) ? $section : false;
		$offset = (is_numeric($offset)) ? $offset : 0;
		
		if (isset($_POST['submit']))
		{
			switch ($this->uri->segment(5))
			{
				case 'approve':
					if ($level == 2)
					{
						$id = $this->input->post('id', true);
						$id = (is_numeric($id)) ? $id : false;
						
						// set the array data
						$approve_array = array('news_status' => 'activated');
						
						// approve the post
						$approve = $this->news->update_news_item($id, $approve_array);
						
						$message = sprintf(
							($approve > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_newsitem')),
							lang('actions_approved'),
							''
						);
						$flash['status'] = ($approve > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
						
						if ($approve > 0)
						{
							// grab the post details
							$row = $this->news->get_news_item($id);
							
							// set the array of data for the email
							$email_data = array(
								'author' => $row->news_author_character,
								'title' => $row->news_title,
								'category' => $this->news->get_news_category($row->news_cat, 'newscat_name'),
								'content' => $row->news_content
							);
							
							// send the email
							$email = ($this->options['system_email'] == 'on') ? $this->_email('news', $email_data) : false;
						}
					}
				break;
					
				case 'delete':
					$id = $this->input->post('id', true);
					$id = (is_numeric($id)) ? $id : false;
					
					// get the news item
					$item = $this->news->get_news_item($id);
					
					if (($level == 1 and ($item->news_author_user == $this->session->userdata('userid'))) or $level == 2)
					{
						$delete = $this->news->delete_news_item($id);
						
						$message = sprintf(
							($delete > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_newsitem')),
							lang('actions_deleted'),
							''
						);
						$flash['status'] = ($delete > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
					}
				break;
					
				case 'update':
					$id = $this->uri->segment(4, 0, true);
					
					// get the news item
					$item = $this->news->get_news_item($id);
					
					if (($level == 1 and ($item->news_author_user == $this->session->userdata('userid'))) or $level == 2)
					{
						$update_array = array(
							'news_title' => $this->input->post('news_title', true),
							'news_tags' => $this->input->post('news_tags', true),
							'news_content' => $this->input->post('news_content', true),
							'news_author_character' => $this->input->post('news_author', true),
							'news_author_user' => $this->user->get_userid($this->input->post('news_author')),
							'news_status' => $this->input->post('news_status', true),
							'news_cat' => $this->input->post('news_cat', true),
							'news_private' => $this->input->post('news_private', true),
							'news_last_update' => now()
						);
						
						$update = $this->news->update_news_item($id, $update_array);
						
						$message = sprintf(
							($update > 0) ? lang('flash_success') : lang('flash_failure'),
							ucfirst(lang('global_newsitem')),
							lang('actions_updated'),
							''
						);
						$flash['status'] = ($update > 0) ? 'success' : 'error';
						$flash['message'] = text_output($message);
					}
				break;
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		if ($section == 'edit')
		{
			// grab the ID from the URL
			$id = $this->uri->segment(4, 0, true);
			
			// grab the post data
			$row = $this->news->get_news_item($id);
			$cats = $this->news->get_news_categories();
			
			if ($level < 2)
			{
				if ($this->session->userdata('userid') != $row->news_author_user or $row->news_status == 'pending')
				{
					redirect('admin/error/6');
				}
			}
			
			if ($cats->num_rows() > 0)
			{
				foreach ($cats->result() as $c)
				{
					$data['categories'][$c->newscat_id] = $c->newscat_name;
				}
			}
			
			// get all characters
			$all = $this->char->get_all_characters('active');
			
			if ($all->num_rows() > 0)
			{
				foreach ($all->result() as $a)
				{
					$data['all'][$a->charid] = $this->char->get_character_name($a->charid, true);
				}
			}
			
			// set the data used by the view
			$data['inputs'] = array(
				'title' => array(
					'name' => 'news_title',
					'value' => $row->news_title),
				'content' => array(
					'name' => 'news_content',
					'id' => 'content-textarea',
					'rows' => 20,
					'value' => nl2br($row->news_content)),
				'tags' => array(
					'name' => 'news_tags',
					'value' => $row->news_tags),
				'author' => $row->news_author_character,
				'character' => $this->char->get_character_name($row->news_author_character, true),
				'status' => $row->news_status,
				'category' => $row->news_cat,
				'category_name' => $this->news->get_news_category($row->news_cat, 'newscat_name'),
				'private' => $row->news_private,
				'private_long' => ($row->news_private == 'y') ? ucfirst(lang('labels_yes')) : ucfirst(lang('labels_no'))
			);
			
			$data['status'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'saved' => ucfirst(lang('status_saved')),
				'pending' => ucfirst(lang('status_pending')),
			);
			
			$data['private'] = array(
				'y' => ucfirst(lang('labels_yes')),
				'n' => ucfirst(lang('labels_no')),
			);
			
			$data['buttons'] = array(
				'update' => array(
					'type' => 'submit',
					'class' => 'button-main',
					'name' => 'submit',
					'value' => 'update',
					'content' => ucfirst(lang('actions_update'))),
			);
			
			$data['header'] = ucwords(lang('actions_edit') .' '. lang('global_newsitem'));
			$data['id'] = $id;
			
			$data['label'] = array(
				'back' => LARROW .' '. ucfirst(lang('actions_back')) .' '. lang('labels_to')
					.' '. ucwords(lang('global_newsitems')),
				'status' => ucfirst(lang('labels_status')),
				'title' => ucfirst(lang('labels_title')),
				'content' => ucfirst(lang('labels_content')),
				'tags' => ucfirst(lang('labels_tags')),
				'tags_inst' => ucfirst(lang('tags_separated')),
				'author' => ucwords(lang('labels_author')),
				'category' => ucfirst(lang('labels_category')),
				'private' => ucfirst(lang('labels_private'))
			);
			
			$js_data['tab'] = 0;
			
			// figure out where the view should be coming from
			$view_loc = 'manage_news_edit';
		}
		else
		{
			switch ($section)
			{
				case 'activated':
				default:
					$js_data['tab'] = 0;
				break;
					
				case 'saved':
					$js_data['tab'] = 1;
				break;
					
				case 'pending':
					$js_data['tab'] = 2;
				break;
			}
			
			$offset_activated = ($section == 'activated') ? $offset : 0;
			$offset_saved = ($section == 'saved') ? $offset : 0;
			$offset_pending = ($section == 'pending') ? $offset : 0;
			
			$data['activated'] = $this->_entries_ajax($offset_activated, 'activated', 'news');
			$data['saved'] = $this->_entries_ajax($offset_saved, 'saved', 'news');
			$data['pending'] = $this->_entries_ajax($offset_pending, 'pending', 'news');
	
		    $data['label'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'pending' => ucfirst(lang('status_pending')),
				'saved' => ucfirst(lang('status_saved')),
			);
			
			$data['header'] = ucwords(lang('actions_manage') .' '. lang('global_newsitems'));
			
			// figure out where the view should be coming from
			$view_loc = 'manage_news';
		}
		
		$this->_regions['content'] = Location::view($view_loc, $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('manage_news_js', $this->skin, 'admin', $js_data);
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
	
	public function posts()
	{
		Auth::check_access();
		$level = Auth::get_access_level();
		
		$this->load->model('posts_model', 'posts');
		$this->load->model('missions_model', 'mis');
		
		$values = array('activated', 'saved', 'pending', 'edit');
		$section = $this->uri->segment(3, 'activated', false, $values);
		$offset = $this->uri->segment(4, 0, true);
		
		if (isset($_POST['submit']))
		{
			switch ($this->uri->segment(5))
			{
				case 'approve':
					if ($level == 2)
					{
						$id = $this->input->post('id', true);
						$id = (is_numeric($id)) ? $id : false;
						
						// set the array data
						$approve_array = array('post_status' => 'activated');
						
						// approve the post
						$approve = $this->posts->update_post($id, $approve_array);
						
						if ($approve > 0)
						{
							$message = sprintf(
								lang('flash_success'),
								ucfirst(lang('global_missionpost')),
								lang('actions_approved'),
								''
							);
	
							$flash['status'] = 'success';
							$flash['message'] = text_output($message);
							
							// grab the post details
							$row = $this->posts->get_post($id);
							
							// set the array of data for the email
							$email_data = array(
								'authors' => $row->post_authors,
								'title' => $row->post_title,
								'timeline' => $row->post_timeline,
								'location' => $row->post_location,
								'content' => $row->post_content,
								'mission' => $this->mis->get_mission($row->post_mission, 'mission_title')
							);
							
							// send the email
							$email = ($this->options['system_email'] == 'on') ? $this->_email('post', $email_data) : false;
						}
						else
						{
							$message = sprintf(
								lang('flash_failure'),
								ucfirst(lang('global_missionpost')),
								lang('actions_approved'),
								''
							);
	
							$flash['status'] = 'error';
							$flash['message'] = text_output($message);
						}
					}
				break;
					
				case 'delete':
					$id = $this->input->post('id', true);
					$id = (is_numeric($id)) ? $id : false;
					
					$delete = $this->posts->delete_post($id);
					
					if ($delete > 0)
					{
						$message = sprintf(
							lang('flash_success'),
							ucfirst(lang('global_missionpost')),
							lang('actions_deleted'),
							''
						);

						$flash['status'] = 'success';
						$flash['message'] = text_output($message);
					}
					else
					{
						$message = sprintf(
							lang('flash_failure'),
							ucfirst(lang('global_missionpost')),
							lang('actions_deleted'),
							''
						);

						$flash['status'] = 'error';
						$flash['message'] = text_output($message);
					}
				break;
					
				case 'update':
					$id = $this->uri->segment(4, 0, true);
					
					$update_array = array(
						'post_title' => $this->input->post('post_title', true),
						'post_location' => $this->input->post('post_location', true),
						'post_timeline' => $this->input->post('post_timeline', true),
						'post_tags' => $this->input->post('post_tags', true),
						'post_content' => $this->input->post('post_content', true),
						'post_mission' => $this->input->post('post_mission', true),
						'post_status' => $this->input->post('post_status', true),
						'post_last_update' => now(),
					);
					
					$authors = $this->input->post('authors', true);
					
					foreach ($authors as $a => $b)
					{
						if (empty($b))
						{
							unset($authors[$a]);
						}
						
						// get the user ID
						$uid = $this->sys->get_item('characters', 'charid', $b, 'user');
						
						// put the users into an array
						$users[] = ($uid !== false) ? $uid : null;
					}
					
					foreach ($users as $k => $v)
					{
						if ( ! is_numeric($v) or $v < 1)
						{
							unset($users[$k]);
						}
					}
					
					$authors = implode(',', $authors);
					$authors_users = implode(',', $users);
					
					$update_array['post_authors'] = $authors;
					$update_array['post_authors_users'] = $authors_users;
					
					$update = $this->posts->update_post($id, $update_array);
					
					if ($update > 0)
					{
						$message = sprintf(
							lang('flash_success'),
							ucfirst(lang('global_missionpost')),
							lang('actions_updated'),
							''
						);

						$flash['status'] = 'success';
						$flash['message'] = text_output($message);
					}
					else
					{
						$message = sprintf(
							lang('flash_failure'),
							ucfirst(lang('global_missionpost')),
							lang('actions_updated'),
							''
						);

						$flash['status'] = 'error';
						$flash['message'] = text_output($message);
					}
				break;
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		if ($section == 'edit')
		{
			// grab the ID from the URL
			$id = $this->uri->segment(4, 0, true);
			
			// grab the post data
			$row = $this->posts->get_post($id);
			
			if ((int) Auth::get_access_level() < 2)
			{
				$valid = array();
				
				foreach ($this->session->userdata('characters') as $check)
				{
					// make an array of the post authors
					$authors = explode(',', $row->post_authors);
					
					if ( ! in_array($check, $authors))
					{
						$valid[] = false;
					}
					else
					{
						$valid[] = true;
					}
				}
				
				if ( ! in_array(true, $valid) or $row->post_status == 'pending')
				{
					redirect('admin/error/6');
				}
			}
			
			// get all characters
			$all = $this->char->get_all_characters('user_npc', array('rank' => 'asc'));
			
			// get the current missions
			$missions = $this->mis->get_all_missions();
			
			if ($all->num_rows() > 0)
			{
				foreach ($all->result() as $a)
				{
					if (in_array($a->charid, $this->session->userdata('characters')))
					{
						$label = ucwords(lang('labels_my') .' '. lang('global_characters'));
					}
					else
					{
						if ($a->crew_type == 'active' or $a->crew_type == 'npc')
						{
							if ($a->crew_type == 'active' and !in_array($a->charid, $this->session->userdata('characters')))
							{
								$label = ucwords(lang('status_playing') .' '. lang('global_characters'));
							}
							else
							{
								if ($a->user > 0)
								{
									$label = ucwords(lang('labels_linked') .' '. lang('abbr_npcs'));
								}
								else
								{
									$label = ucwords(lang('labels_unlinked') .' '. lang('abbr_npcs'));
								}
							}
						}
					}
					
					// if it's a linked NPC, show the main character that owns the NPC
					$add = ($label == ucwords(lang('labels_linked') .' '. lang('abbr_npcs')))
						? " (".ucfirst(lang('labels_linked').' '.lang('labels_to').' ').$this->char->get_character_name($this->user->get_main_character($a->user), true).")"
						: false;
					
					// toss them in the array
					$allchars[$label][$a->charid] = $this->char->get_character_name($a->charid, true).$add;
				}
				
				$data['all_characters'] = array();
				
				$key = ucwords(lang('labels_my') .' '. lang('global_characters'));
				if (isset($allchars[$key]))
				{
					$data['all_characters'][$key] = $allchars[$key];
				}
				
				$key = ucwords(lang('status_playing') .' '. lang('global_characters'));
				if (isset($allchars[$key]))
				{
					$data['all_characters'][$key] = $allchars[$key];
				}
				
				$key = ucwords(lang('labels_linked') .' '. lang('abbr_npcs'));
				if (isset($allchars[$key]))
				{
					$data['all_characters'][$key] = $allchars[$key];
				}
				
				$key = ucwords(lang('labels_unlinked') .' '. lang('abbr_npcs'));
				if (isset($allchars[$key]))
				{
					$data['all_characters'][$key] = $allchars[$key];
				}
			}
			else
			{
				$data['all_characters'] = false;
			}
			
			// prep the data for sending to the js view
			$js_data['tab'] = 0;
			
			$data['authors_selected'] = array();
			
			if ($row !== false)
			{
				// set the list of selected authors
				$data['authors_selected'] = explode(',', $row->post_authors);
			}
			
			// set the data used by the view
			$data['inputs'] = array(
				'title' => array(
					'name' => 'post_title',
					'value' => $row->post_title),
				'content' => array(
					'name' => 'post_content',
					'id' => 'content-textarea',
					'rows' => 20,
					'value' => nl2br($row->post_content)),
				'tags' => array(
					'name' => 'post_tags',
					'value' => $row->post_tags),
				'timeline' => array(
					'name' => 'post_timeline',
					'value' => $row->post_timeline),
				'location' => array(
					'name' => 'post_location',
					'value' => $row->post_location),
				'mission' => $row->post_mission,
				'mission_name' => $this->mis->get_mission($row->post_mission, 'mission_title'),
				'status' => $row->post_status,
			);
			
			if ($missions->num_rows() > 0)
			{
				foreach ($missions->result() as $mission)
				{
					$data['missions'][$mission->mission_id] = $mission->mission_title;
				}
			}
			
			$data['status'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'saved' => ucfirst(lang('status_saved')),
				'pending' => ucfirst(lang('status_pending')),
			);
			
			$data['buttons'] = array(
				'update' => array(
					'type' => 'submit',
					'class' => 'button-main',
					'name' => 'submit',
					'value' => 'update',
					'content' => ucfirst(lang('actions_update'))),
			);
			
			$data['header'] = ucwords(lang('actions_edit') .' '. lang('global_missionpost'));
			$data['id'] = $id;
			
			$data['label'] = array(
				'back' => LARROW .' '. ucfirst(lang('actions_back')) .' '. lang('labels_to')
					.' '. ucwords(lang('global_missionposts')),
				'mission' => ucfirst(lang('global_mission')),
				'status' => ucfirst(lang('labels_status')),
				'title' => ucfirst(lang('labels_title')),
				'location' => ucfirst(lang('labels_location')),
				'timeline' => ucfirst(lang('labels_timeline')),
				'content' => ucfirst(lang('labels_content')),
				'tags' => ucfirst(lang('labels_tags')),
				'tags_inst' => ucfirst(lang('tags_separated')),
				'addauthor' => ucwords(lang('actions_add') .' '. lang('labels_author')),
				'authors' => ucfirst(lang('labels_authors')),
				'date' => ucfirst(lang('labels_date')),
				'chosen_incompat' => lang('chosen_incompat'),
				'select' => ucwords(lang('labels_please').' '.lang('actions_select')).' '.lang('labels_the').' '.ucfirst(lang('labels_authors')),
			);
			
			// figure out where the view should be coming from
			$view_loc = 'manage_posts_edit';
		}
		else
		{
			switch ($section)
			{
				case 'activated':
				default:
					$js_data['tab'] = 0;
				break;
					
				case 'saved':
					$js_data['tab'] = 1;
				break;
					
				case 'pending':
					$js_data['tab'] = 2;
				break;
			}
			
			$offset_activated = ($section == 'activated') ? $offset : 0;
			$offset_saved = ($section == 'saved') ? $offset : 0;
			$offset_pending = ($section == 'pending') ? $offset : 0;
			
			$data['activated'] = $this->_entries_ajax($offset_activated, 'activated', 'posts');
			$data['saved'] = $this->_entries_ajax($offset_saved, 'saved', 'posts');
			$data['pending'] = $this->_entries_ajax($offset_pending, 'pending', 'posts');
	
		    $data['label'] = array(
				'activated' => ucfirst(lang('status_activated')),
				'pending' => ucfirst(lang('status_pending')),
				'saved' => ucfirst(lang('status_saved')),
			);
			
			$data['header'] = ucwords(lang('actions_manage') .' '. lang('global_missionposts'));
			
			$js_data['remove'] = false;
			
			// figure out where the view should be coming from
			$view_loc = 'manage_posts';
		}
		
		$this->_regions['content'] = Location::view($view_loc, $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('manage_posts_js', $this->skin, 'admin', $js_data);
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
}
