<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once MODPATH.'core/controllers/nova_write.php';

class Write extends Nova_write {

	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Put your own methods below this...
	 */
	
	public function missionpost($id = false)
	{
		Auth::check_access();
		
		// sanity check
		$id = (is_numeric($id)) ? $id : false;
		
		// load the resources
		$this->load->model('posts_model', 'posts');
		$this->load->model('missions_model', 'mis');
		
		if ($this->options['system_email'] == 'off')
		{
			$flash['status'] = 'info';
			$flash['message'] = lang_output('flash_system_email_off');
			
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		$data['key'] = array(
			'all' => '0',
			'missions' => ''
		);
		
		$data['to'] = '0';
		
		$content = false;
		$title = false;
		$tags = false;
		$timeline = false;
		$location = false;
		$mission = false;
		
		if (isset($_POST['submit']))
		{
			// define the POST variables
			$tags = $this->input->post('tags', true);
			$title = $this->input->post('title', true);
			$content = $this->input->post('content', true);
			$mission = $this->input->post('mission', true);
			$timeline = $this->input->post('timeline', true);
			$location = $this->input->post('location', true);
			$authors = $this->input->post('authors', true);
			
			$action = strtolower($this->input->post('submit', true));
			$status = false;
			$flash = false;
			$illegalpost = false;
			
			if ($this->uri->segment(3) != 'missionCreate')
			{
				if (empty($authors))
				{
					$flash['status'] = 'error';
					$flash['message'] = lang_output('flash_missionposts_no_author');
					
					$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
				}
				else
				{
					$users = array();
					
					foreach ($authors as $key => $value)
					{
						if ( ! is_numeric($value) or $value < 1)
						{
							unset($authors[$key]);
						}
						
						// get the user ID
						$pid = $this->sys->get_item('characters', 'charid', $value, 'user');
						
						// put the users into an array
						$users[] = ($pid !== false) ? $pid : NULL;
					}
					
					foreach ($users as $a => $b)
					{
						if ( ! is_numeric($b) or $b < 1)
						{
							unset($users[$a]);
						}
					}
					
					// make sure the array doesn't the same ID multiple times
					$users = array_unique($users);
					
					// set the authors string
					$authors_string = implode(',', $authors);
					$users_string = implode(',', $users);
					
					// make sure the person posting is actually part of the post
					if ( ! in_array($this->session->userdata('userid'), $users))
					{
						$illegalpost = true;
						$action = lang('actions_save');
					}
					
					switch ($action)
					{
						case 'delete':
							$row = $this->posts->get_post($id);
							
							if ($row !== false)
							{
								if ($row->post_status == 'saved')
								{
									$valid = array();
				
									foreach ($this->session->userdata('characters') as $check)
									{
										if (strstr($row->post_authors, $check) === false)
										{
											$valid[] = false;
										}
										else
										{
											$valid[] = true;
										}
									}
									
									if ( ! in_array(true, $valid))
									{
										redirect('admin/error/4');
									}
									
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
										
										if (count($authors) > 1)
										{
											// set the array of data for the email
											$email_data = array(
												'authors' => $authors_string,
												'title' => $title
											);
											
											// send the email
											$email = ($this->options['system_email'] == 'on') ? $this->_email('post_delete', $email_data) : false;
										}
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
								}
								
								// add an automatic redirect
								$this->_regions['_redirect'] = Template::add_redirect('write/index');
							}
						break;
							
						case 'save':
							// set the participants field to be blank by default
							$participants = '';
							
							if ($this->options['use_post_participants'] == 'y')
							{
								if ($id)
								{
									// get the participants
									$participants = $this->posts->get_post($id, 'post_participants');
									
									// make an array out of the participants
									$participant_array = explode(',', $participants);
									
									// add the current user
									$participant_array[] = $this->session->userdata('userid');
									
									// make sure we have unique values
									$participant_array_final = array_unique($participant_array);
									
									// get a string
									$participants = implode(',', $participant_array_final);
								}
								else
								{
									$participants = $this->session->userdata('userid');
								}
							}
							
							if ($id !== false)
							{
								$update_array = array(
									'post_authors' => $authors_string,
									'post_authors_users' => $users_string,
									'post_date' => now(),
									'post_title' => $title,
									'post_content' => preg_replace('#<br\s*\/?>#i', '', $content),
									'post_tags' => $tags,
									'post_status' => 'saved',
									'post_timeline' => $timeline,
									'post_location' => $location,
									'post_mission' => $mission,
									'post_saved' => $this->session->userdata('main_char'),
									'post_participants' => $participants,
									'post_lock_user' => 0,
									'post_lock_date' => 0,
								);
								
								$update = $this->posts->update_post($id, $update_array);
								
								if ($update > 0)
								{
									$message = sprintf(
										lang('flash_success'),
										ucfirst(lang('global_missionpost')),
										lang('actions_saved'),
										($illegalpost === true) ? ' '. lang('error_illegal_post') : ''
									);
	
									$flash['status'] = 'success';
									$flash['message'] = text_output($message);
								}
								else
								{
									$message = sprintf(
										lang('flash_failure'),
										ucfirst(lang('global_missionpost')),
										lang('actions_saved'),
										($illegalpost === true) ? ' '. lang('error_illegal_post') : ''
									);
	
									$flash['status'] = 'error';
									$flash['message'] = text_output($message);
								}
							}
							else
							{
								// build the insert array
								$insert_array = array(
									'post_authors' => $authors_string,
									'post_authors_users' => $users_string,
									'post_date' => now(),
									'post_title' => $title,
									'post_content' => preg_replace('#<br\s*\/?>#i', '', $content),
									'post_tags' => $tags,
									'post_status' => 'saved',
									'post_timeline' => $timeline,
									'post_location' => $location,
									'post_mission' => $mission,
									'post_saved' => $this->session->userdata('main_char'),
									'post_participants' => $participants,
									'post_lock_user' => 0,
									'post_lock_date' => 0,
								);
								
								$insert = $this->posts->create_mission_entry($insert_array);
								
								// grab the insert id
								$insert_id = $this->db->insert_id();
								
								$this->sys->optimize_table('posts');
								
								if ($insert > 0)
								{
									$message = sprintf(
										lang('flash_success'),
										ucfirst(lang('global_missionpost')),
										lang('actions_saved'),
										($illegalpost === true) ? ' '. lang('error_illegal_post') : ''
									);
	
									$flash['status'] = 'success';
									$flash['message'] = text_output($message);
								}
								else
								{
									$message = sprintf(
										lang('flash_failure'),
										ucfirst(lang('global_missionpost')),
										lang('actions_saved'),
										($illegalpost === true) ? ' '. lang('error_illegal_post') : ''
									);
	
									$flash['status'] = 'error';
									$flash['message'] = text_output($message);
								}
								
								// add a quick redirect
								$this->_regions['_redirect'] = Template::add_redirect('write/missionpost/'.$insert_id);
							}
							
							if (count($authors) > 1)
							{
								// set the array of data for the email
								$email_data = array(
									'authors' => $authors_string,
									'title' => $title,
									'timeline' => $timeline,
									'location' => $location,
									'content' => preg_replace('#<br\s*\/?>#i', '', $content),
									'mission' => $this->mis->get_mission($mission, 'mission_title')
								);
								
								// send the email
								$email = ($this->options['system_email'] == 'on') ? $this->_email('post_save', $email_data) : false;
							}
							
							$content = false;
							$title = false;
							$tags = false;
							$timeline = false;
							$location = false;
							$mission = false;
						break;
							
						case 'post':
							// check the moderation status
							$status = $this->user->checking_moderation('post', $authors_string);
							
							// set the participants field to be blank
							$participants = '';
							
							if ($this->options['use_post_participants'] == 'y')
							{
								if ($id)
								{
									// get the participants
									$participants = $this->posts->get_post($id, 'post_participants');
									
									// make an array out of the participants
									$participant_array = explode(',', $participants);
									
									// add the current user
									$participant_array[] = $this->session->userdata('userid');
									
									// make sure we have unique values
									$participant_array_final = array_unique($participant_array);
									
									// get a string
									$participants = implode(',', $participant_array_final);
								}
								else
								{
									$participants = $this->session->userdata('userid');
								}
								
								// get an array of actual participants
								$actual_participants = explode(',', $participants);
								
								// get an array of who is supposed to be on the post
								$author_participants = explode(',', $users_string);
								
								// get an array of people who need to be removed
								$diffs = array_diff($author_participants, $actual_participants);
								
								// set the new user string
								$users_string = implode(',', $actual_participants);
								
								// get an array of characters on the post
								$author_array = explode(',', $authors_string);
								
								foreach ($author_array as $key => $value)
								{
									$userID = $this->char->get_character($value, 'user');
									
									if ($userID !== null and $userID > 0 and in_array($userID, $diffs))
									{
										unset($author_array[$key]);
									}
								}
								
								// set the new authors string
								$authors_string = implode(',', $author_array);
							}
							
							if ($id !== false)
							{
								$update_array = array(
									'post_authors' => $authors_string,
									'post_authors_users' => $users_string,
									'post_date' => now(),
									'post_title' => $title,
									'post_content' => preg_replace('#<br\s*\/?>#i', '', $content),
									'post_tags' => $tags,
									'post_status' => $status,
									'post_timeline' => $timeline,
									'post_location' => $location,
									'post_mission' => $mission,
									'post_saved' => $this->session->userdata('main_char'),
									'post_lock_user' => 0,
									'post_lock_date' => 0,
								);
								
								$update = $this->posts->update_post($id, $update_array);
								
								if ($update > 0)
								{
									$string = explode(',', $authors_string);
									
									foreach ($string as $s)
									{
										$userid = $this->char->get_character($s, 'user');
										
										$array = array('last_post' => now());
										$this->user->update_user($userid, $array);
										$this->char->update_character($s, $array);
									}
									
									$message = sprintf(
										lang('flash_success'),
										ucfirst(lang('global_missionpost')),
										lang('actions_posted'),
										''
									);
	
									$flash['status'] = 'success';
									$flash['message'] = text_output($message);
									
									// set the array of data for the email
									$email_data = array(
										'authors' => $authors_string,
										'title' => $title,
										'timeline' => $timeline,
										'location' => $location,
										'content' => preg_replace('#<br\s*\/?>#i', '', $content),
										'mission' => $this->mis->get_mission($mission, 'mission_title')
									);
									
									if ($status == 'pending')
									{
										// send the email
										$email = ($this->options['system_email'] == 'on') ? $this->_email('post_pending', $email_data) : false;
									}
									else
									{
										// send the email
										$email = ($this->options['system_email'] == 'on') ? $this->_email('post', $email_data) : false;
									}
								}
								else
								{
									$message = sprintf(
										lang('flash_failure'),
										ucfirst(lang('global_missionpost')),
										lang('actions_posted'),
										''
									);
	
									$flash['status'] = 'error';
									$flash['message'] = text_output($message);
								}
							}
							else
							{
								// build the insert array
								$insert_array = array(
									'post_authors' => $authors_string,
									'post_authors_users' => $users_string,
									'post_date' => now(),
									'post_title' => $title,
									'post_content' => preg_replace('#<br\s*\/?>#i', '', $content),
									'post_tags' => $tags,
									'post_status' => $status,
									'post_timeline' => $timeline,
									'post_location' => $location,
									'post_mission' => $mission,
									'post_lock_user' => 0,
									'post_lock_date' => 0,
								);
								
								$insert = $this->posts->create_mission_entry($insert_array);
								
								if ($insert > 0)
								{
									$string = explode(',', $authors_string);
									
									foreach ($string as $s)
									{
										$userid = $this->char->get_character($s, 'user');
										
										$array = array('last_post' => now());
										$this->user->update_user($userid, $array);
										$this->char->update_character($s, $array);
									}
									
									$message = sprintf(
										lang('flash_success'),
										ucfirst(lang('global_missionpost')),
										lang('actions_posted'),
										''
									);
	
									$flash['status'] = 'success';
									$flash['message'] = text_output($message);
									
									// set the array of data for the email
									$email_data = array(
										'authors' => $authors_string,
										'title' => $title,
										'timeline' => $timeline,
										'location' => $location,
										'content' => preg_replace('#<br\s*\/?>#i', '', $content),
										'mission' => $this->mis->get_mission($mission, 'mission_title')
									);
									
									if ($status == 'pending')
									{
										// send the email
										$email = ($this->options['system_email'] == 'on') ? $this->_email('post_pending', $email_data) : false;
									}
									else
									{
										// send the email
										$email = ($this->options['system_email'] == 'on') ? $this->_email('post', $email_data) : false;
									}
									
									$content = false;
									$title = false;
									$tags = false;
									$timeline = false;
									$location = false;
								}
								else
								{
									$message = sprintf(
										lang('flash_failure'),
										ucfirst(lang('global_missionpost')),
										lang('actions_posted'),
										''
									);
	
									$flash['status'] = 'error';
									$flash['message'] = text_output($message);
								}
							}
						break;
							
						default:
							$flash['status'] = 'error';
							$flash['message'] = lang_output('error_generic', '');
						break;
					}
				}
				
				// set the flash message
				$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
			}
		}
		
		// grab all the characters
		$all = $this->char->get_all_characters('user_npc', array('rank' => 'asc'));
		
		// get the current missions
		$missions = $this->mis->get_all_missions('current');
		
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
		
		// get the post
		$row = ($id !== false) ? $this->posts->get_post($id) : false;
		
		$data['authors_selected'] = array();
		
		if ($row !== false)
		{
			$valid = array();
			
			foreach ($this->session->userdata('characters') as $check)
			{
				if (strstr($row->post_authors, $check) === false)
				{
					$valid[] = false;
				}
				else
				{
					$valid[] = true;
				}
			}
			
			if ( ! in_array(true, $valid))
			{
				redirect('admin/error/4');
			}
			
			if ( ! isset($action) and ($row->post_status == 'pending' or $row->post_status == 'activated'))
			{
				redirect('admin/error/5');
			}
			
			// set the list of selected authors
			$data['authors_selected'] = explode(',', $row->post_authors);
			
			// fill the content in
			$title = $row->post_title;
			$content = nl2br($row->post_content);
			$tags = $row->post_tags;
			$timeline = $row->post_timeline;
			$location = $row->post_location;
		}
		
		$data['inputs'] = array(
			'title' => array(
				'name' => 'title',
				'id' => 'title',
				'value' => $title),
			'content' => array(
				'name' => 'content',
				'id' => 'content-textarea',
				'rows' => 20,
				'value' => $content),
			'tags' => array(
				'name' => 'tags',
				'id' => 'tags',
				'value' => $tags),
			'timeline' => array(
				'name' => 'timeline',
				'id' => 'timeline',
				'value' => $timeline),
			'location' => array(
				'name' => 'location',
				'id' => 'location',
				'value' => $location),
			'mission' => ($id !== false and $row !== false) ? $row->post_mission : false,
			'post' => array(
				'type' => 'submit',
				'class' => 'button-main',
				'name' => 'submit',
				'value' => 'post',
				'id' => 'submitPost',
				'content' => ucwords(lang('actions_post'))),
			'save' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'save',
				'content' => ucwords(lang('actions_save'))),
			'delete' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'delete',
				'id' => 'submitDelete',
				'content' => ucwords(lang('actions_delete'))),
			'locked' => ($row !== false and (int) $row->post_lock_user !== 0 and (int) $row->post_lock_date !== 0),
		);
	
		// set the initial note update check
		$data['missionNotesUpdate'] = false;

		// an array of note update times
		$note_times = array();

		// make sure we have something so we don't throw an error
		$last_note_timespan = false;
		
		if ($missions->num_rows() > 0)
		{
			$hours72 = now() - (3 * 86400);

			if ($missions->num_rows() > 1)
			{
				foreach ($missions->result() as $mission)
				{
					$data['missions'][$mission->mission_id] = $mission->mission_title;
					$data['mission_notes'][$mission->mission_id]['title'] = $mission->mission_title;
					$data['mission_notes'][$mission->mission_id]['notes'] = $mission->mission_notes;

					if ($mission->mission_notes_updated >= $hours72)
					{
						// update the note update check
						$data['missionNotesUpdate'] = true;
					}

					// add the time to the tracking array
					if ($mission->mission_notes_updated !== null and $mission->mission_notes_updated > 0)
					{
						$note_times[] = $mission->mission_notes_updated;
					}
				}
				
				$js_data['missionCount'] = $missions->num_rows();
			}
			else
			{
				$row = $missions->row();
				
				$data['mission']['id'] = $row->mission_id;
				$data['mission']['title'] = $row->mission_title;
				$data['mission']['notes'] = $row->mission_notes;

				if ($row->mission_notes_updated >= $hours72)
				{
					// update the note update check
					$data['missionNotesUpdate'] = true;
				}

				// add the time to the tracking array
				if ($row->mission_notes_updated !== null and $row->mission_notes_updated > 0)
				{
					$note_times[] = $row->mission_notes_updated;
				}

				$js_data['missionCount'] = 1;
			}

			// sort the note time array
			arsort($note_times);

			if (count($note_times) > 0)
			{
				// get the first item in the array
				$last_note_update = reset($note_times);

				// figure out the timespan since the last note update
				$last_note_timespan = timespan_short($last_note_update, now());
			}
		}
		else
		{
			$data['missions'] = false;
			$data['inputs']['post']['disabled'] = 'yes';
			$data['inputs']['save']['disabled'] = 'yes';
			$data['inputs']['delete']['disabled'] = 'yes';
			
			$js_data['missionCount'] = 0;
		}
		
		$js_data['missionNotesUpdate'] = $data['missionNotesUpdate'];
		$js_data['authorized'] = Auth::check_access('manage/missions', false);
		
		$nomission = sprintf(
			lang('error_no_mission_fail'),
			lang('global_missions'),
			lang('global_missionpost'),
			anchor('manage/missions', lang('global_mission'))
		);
		
		$data['header'] = ucwords(lang('actions_write').' '.lang('global_missionpost'));
		
		$data['form_action'] = ($id) ? 'write/missionpost/'.$id.'/view' : 'write/missionpost';
		
		$data['images'] = array(
			'excl' => array(
				'src' => Location::img('exclamation-32.png', $this->skin, 'admin'),
				'alt' => '',
			),
			'help' => array(
				'src' => Location::img('help.png', $this->skin, 'admin'),
				'alt' => '',
				'class' => 'image',
				'rel' => 'tooltip',
				'title' => lang('tags_explain'),
			),
		);
		
		$data['label'] = array(
			'addauthor' => ucwords(lang('actions_add') .' '. lang('labels_author')),
			'authors' => ucfirst(lang('labels_authors')),
			'back_wcp' => LARROW.' '.ucfirst(lang('actions_back')).' '.lang('labels_to').' '.ucwords(lang('labels_writing').' '.lang('labels_controlpanel')),
			'content' => ucfirst(lang('labels_content')),
			'location' => ucfirst(lang('labels_location')),
			'mission' => ucfirst(lang('global_mission')),
			'mission_notes' => ucwords(lang('global_mission') .' '. lang('labels_notes')),
			'more_edits' => ucwords(lang('actions_keep').' '.lang('actions_editing')).' '.RARROW,
			'myauthor' => ucwords(lang('labels_my') .' '. lang('labels_author')),
			'no_mission' => $nomission,
			'otherauthors' => ucwords(lang('labels_other') .' '. lang('labels_authors')),
			'showhide' => ucfirst(lang('actions_show')) .'/'. ucfirst(lang('actions_hide')),
			'tags' => ucfirst(lang('labels_tags')),
			'tags_sep' => lang('tags_separated'),
			'timeline' => ucfirst(lang('labels_timeline')),
			'title' => ucfirst(lang('labels_title')),
			'select' => ucwords(lang('labels_please').' '.lang('actions_select')).' '.lang('labels_the').' '.ucfirst(lang('labels_authors')),
			'chosen_incompat' => lang('chosen_incompat'),
			'locked' => sprintf(lang('post_locked'), lang('global_missionpost'), lang('global_user')),
			'updated' => strtoupper(lang('actions_updated')),
			'note_last_updated' => ($last_note_timespan !== false) ? ucfirst(lang('actions_updated')).': '.$last_note_timespan : '',
		);
		
		$this->_regions['content'] = Location::view('write_missionpost', $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('write_missionpost_js', $this->skin, 'admin', $js_data);
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
	
	public function newsitem($id = false)
	{
		Auth::check_access();
		
		// load the resources
		$this->load->model('news_model', 'news');
		
		// sanity check
		$id = (is_numeric($id)) ? $id : false;
		
		if ($this->options['system_email'] == 'off')
		{
			$flash['status'] = 'info';
			$flash['message'] = lang_output('flash_system_email_off');
			
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		// set the variables
		$data['key'] = array('private' => 'n', 'cat' => 0);
		$content = false;
		$title = false;
		$tags = false;
		$private = false;
		
		if (isset($_POST['submit']))
		{
			$title = $this->input->post('title', true);
			$content = $this->input->post('content', true);
			$author = $this->input->post('author', true);
			$tags = $this->input->post('tags', true);
			$action = strtolower($this->input->post('submit', true));
			$category = $this->input->post('newscat', true);
			$private = $this->input->post('private', true);
			$status = false;
			$flash = false;
			
			switch ($action)
			{
				case 'delete':
					$row = $this->news->get_news_item($id);
					
					if ($row !== false)
					{
						if ($row->news_status == 'saved' and
								$row->news_author_user == $this->session->userdata('userid'))
						{
							$delete = $this->news->delete_news_item($id);
							
							if ($delete > 0)
							{
								$message = sprintf(
									lang('flash_success'),
									ucfirst(lang('global_newsitem')),
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
									ucfirst(lang('global_newsitem')),
									lang('actions_deleted'),
									''
								);

								$flash['status'] = 'error';
								$flash['message'] = text_output($message);
							}
						}
						else
						{
							redirect('admin/error/4');
						}
						
						// add an automatic redirect
						$this->_regions['_redirect'] = Template::add_redirect('write/index');
					}
				break;
					
				case 'save':
					if ($id !== false)
					{
						$update_array = array(
							'news_author_user' => $this->session->userdata('userid'),
							'news_author_character' => $this->session->userdata('main_char'),
							'news_date' => now(),
							'news_title' => $title,
							'news_content' => preg_replace('#<br\s*\/?>#i', '', $content),
							'news_tags' => $tags,
							'news_status' => 'saved',
							'news_cat' => $category,
							'news_private' => $private
						);
						
						// do the update
						$update = $this->news->update_news_item($id, $update_array);
						
						if ($update > 0)
						{
							$message = sprintf(
								lang('flash_success'),
								ucfirst(lang('global_newsitem')),
								lang('actions_saved'),
								''
							);

							$flash['status'] = 'success';
							$flash['message'] = text_output($message);
						}
						else
						{
							$message = sprintf(
								lang('flash_failure'),
								ucfirst(lang('global_newsitem')),
								lang('actions_saved'),
								''
							);

							$flash['status'] = 'error';
							$flash['message'] = text_output($message);
						}
					}
					else
					{
						$insert_array = array(
							'news_author_user' => $this->session->userdata('userid'),
							'news_author_character' => $this->session->userdata('main_char'),
							'news_date' => now(),
							'news_title' => $title,
							'news_content' => preg_replace('#<br\s*\/?>#i', '', $content),
							'news_tags' => $tags,
							'news_status' => 'saved',
							'news_cat' => $category,
							'news_private' => $private
						);
						
						$insert = $this->news->create_news_item($insert_array);
						
						// grab the insert id
						$insert_id = $this->db->insert_id();
						
						$this->sys->optimize_table('news');
						
						if ($insert > 0)
						{
							$message = sprintf(
								lang('flash_success'),
								ucfirst(lang('global_newsitem')),
								lang('actions_saved'),
								''
							);

							$flash['status'] = 'success';
							$flash['message'] = text_output($message);
							
							// reset the fields if everything worked
							$content = false;
							$title = false;
							$tags = false;
							$data['key'] = array('private' => 'n', 'cat' => 0);
						}
						else
						{
							$message = sprintf(
								lang('flash_failure'),
								ucfirst(lang('global_newsitem')),
								lang('actions_saved'),
								''
							);

							$flash['status'] = 'error';
							$flash['message'] = text_output($message);
						}
						
						// add an automatic redirect
						$this->_regions['_redirect'] = Template::add_redirect('write/newsitem/'.$insert_id);
					}
				break;
					
				case 'post':
					// check the moderation status
					$status = $this->user->checking_moderation('news', $this->session->userdata('userid'));
					
					if ($id !== false)
					{
						$update_array = array(
							'news_author_user' => $this->session->userdata('userid'),
							'news_author_character' => $this->session->userdata('main_char'),
							'news_date' => now(),
							'news_title' => $title,
							'news_content' => preg_replace('#<br\s*\/?>#i', '', $content),
							'news_tags' => $tags,
							'news_status' => $status,
							'news_cat' => $category,
							'news_private' => $private
						);
						
						$update = $this->news->update_news_item($id, $update_array);
						
						if ($update > 0)
						{
							$message = sprintf(
								lang('flash_success'),
								ucfirst(lang('global_newsitem')),
								lang('actions_posted'),
								''
							);

							$flash['status'] = 'success';
							$flash['message'] = text_output($message);
							
							// set the array of data for the email
							$email_data = array(
								'author' => $this->session->userdata('main_char'),
								'title' => $title,
								'category' => $this->news->get_news_category($category, 'newscat_name'),
								'content' => $content
							);
							
							if ($status == 'pending')
							{
								// send the email
								$email = ($this->options['system_email'] == 'on') ? $this->_email('news_pending', $email_data) : false;
							}
							else
							{
								// send the email
								$email = ($this->options['system_email'] == 'on') ? $this->_email('news', $email_data) : false;
							}
						}
						else
						{
							$message = sprintf(
								lang('flash_failure'),
								ucfirst(lang('global_newsitem')),
								lang('actions_posted'),
								''
							);

							$flash['status'] = 'error';
							$flash['message'] = text_output($message);
						}
					}
					else
					{
						$insert_array = array(
							'news_author_user' => $this->session->userdata('userid'),
							'news_author_character' => $this->session->userdata('main_char'),
							'news_date' => now(),
							'news_title' => $title,
							'news_content' => preg_replace('#<br\s*\/?>#i', '', $content),
							'news_tags' => $tags,
							'news_status' => $status,
							'news_cat' => $category,
							'news_private' => $private
						);
						
						$insert = $this->news->create_news_item($insert_array);
						
						if ($insert > 0)
						{
							$message = sprintf(
								lang('flash_success'),
								ucfirst(lang('global_newsitem')),
								lang('actions_posted'),
								''
							);

							$flash['status'] = 'success';
							$flash['message'] = text_output($message);
							
							// set the array of data for the email
							$email_data = array(
								'author' => $this->session->userdata('main_char'),
								'title' => $title,
								'category' => $this->news->get_news_category($category, 'newscat_name'),
								'content' => $content
							);
							
							if ($status == 'pending')
							{
								// send the email
								$email = ($this->options['system_email'] == 'on') ? $this->_email('news_pending', $email_data) : false;
							}
							else
							{
								// send the email
								$email = ($this->options['system_email'] == 'on') ? $this->_email('news', $email_data) : false;
							}
							
							// reset the fields if everything worked
							$content = false;
							$title = false;
							$tags = false;
							$data['key'] = array('private' => 'n', 'cat' => 0);
						}
						else
						{
							$message = sprintf(
								lang('flash_failure'),
								ucfirst(lang('global_newsitem')),
								lang('actions_posted'),
								''
							);

							$flash['status'] = 'error';
							$flash['message'] = text_output($message);
						}
					}
				break;
					
				default:
					$flash['status'] = 'error';
					$flash['message'] = lang_output('error_generic', '');
				break;
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		// set the ID and name
		$data['character']['id'] = $this->session->userdata('main_char');
		$data['character']['name'] = $this->char->get_character_name($this->session->userdata('main_char'), true);
		
		// get the data
		$row = ($id !== false) ? $this->news->get_news_item($id) : false;
		
		if ($row !== false)
		{
			if ($row->news_author_user != $this->session->userdata('userid'))
			{
				redirect('admin/error/4');
			}
			
			if ( ! isset($action) and ($row->news_status == 'pending' or $row->news_status == 'activated'))
			{
				redirect('admin/error/5');
			}
			
			// fill the content in
			$title = $row->news_title;
			$content = nl2br($row->news_content);
			$tags = $row->news_tags;
			$data['key']['cat'] = $row->news_cat;
			$data['key']['private'] = $row->news_private;
		}
		
		$data['inputs'] = array(
			'title' => array(
				'name' => 'title',
				'id' => 'title',
				'value' => $title),
			'content' => array(
				'name' => 'content',
				'id' => 'content-textarea',
				'rows' => 20,
				'value' => $content),
			'tags' => array(
				'name' => 'tags',
				'id' => 'tags',
				'value' => $tags),
			'post' => array(
				'type' => 'submit',
				'class' => 'button-main',
				'name' => 'submit',
				'value' => 'post',
				'id' => 'submitPost',
				'content' => ucwords(lang('actions_post'))),
			'save' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'save',
				'content' => ucwords(lang('actions_save'))),
			'delete' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'delete',
				'id' => 'submitDelete',
				'content' => ucwords(lang('actions_delete')))
		);
		
		$data['values']['private'] = array(
			'n' => 'Public',
			'y' => 'Private'
		);
		
		// grab the categories
		$cats = $this->news->get_news_categories();
		
		if ($cats->num_rows() > 0)
		{
			foreach ($cats->result() as $cat)
			{
				$data['values']['category'][$cat->newscat_id] = $cat->newscat_name;
			}
		}
		
		$data['header'] = ucwords(lang('actions_write') .' '. lang('global_newsitem'));
		
		$data['form_action'] = ($id !== false) ? 'write/newsitem/'. $id : 'write/newsitem';

		$data['images'] = array(
			'help' => array(
				'src' => Location::img('help.png', $this->skin, 'admin'),
				'alt' => '',
				'class' => 'image',
				'rel' => 'tooltip',
				'title' => lang('tags_explain'),
			),
		);
		
		$data['label'] = array(
			'author' => ucfirst(lang('labels_author')),
			'category' => ucfirst(lang('labels_category')),
			'content' => ucfirst(lang('labels_content')),
			'tags' => ucfirst(lang('labels_tags')),
			'tags_sep' => lang('tags_separated'),
			'title' => ucfirst(lang('labels_title')),
			'type' => ucfirst(lang('labels_type')),
		);
		
		$this->_regions['content'] = Location::view('write_newsitem', $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('write_newsitem_js', $this->skin, 'admin');
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
	
	public function personallog($id = false)
	{
		Auth::check_access();
		
		// sanity check
		$id = (is_numeric($id)) ? $id : false;
		
		// load the resources
		$this->load->model('personallogs_model', 'logs');
		
		if ($this->options['system_email'] == 'off')
		{
			$flash['status'] = 'info';
			$flash['message'] = lang_output('flash_system_email_off');
			
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		// set the variables
		$data['key'] = '';
		$content = false;
		$title = false;
		$tags = false;
		
		if (isset($_POST['submit']))
		{
			$title = $this->input->post('title', true);
			$content = $this->input->post('content', true);
			$author = $this->input->post('author', true);
			$tags = $this->input->post('tags', true);
			$action = strtolower($this->input->post('submit', true));
			$status = false;
			$flash = false;
			
			if ($author == 0)
			{
				$flash['status'] = 'error';
				$flash['message'] = lang_output('flash_personallogs_no_author');
			}
			else
			{
				switch ($action)
				{
					case 'delete':
						$row = $this->logs->get_log($id);
						
						if ($row !== false)
						{
							if ($row->log_status == 'saved' and
									$row->log_author_user == $this->session->userdata('userid'))
							{
								$delete = $this->logs->delete_log($id);
								
								if ($delete > 0)
								{
									$message = sprintf(
										lang('flash_success'),
										ucfirst(lang('global_personallog')),
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
										ucfirst(lang('global_personallog')),
										lang('actions_deleted'),
										''
									);

									$flash['status'] = 'error';
									$flash['message'] = text_output($message);
								}
							}
							else
							{
								redirect('admin/error/4');
							}
							
							// add an automatic redirect
							$this->_regions['_redirect'] = Template::add_redirect('write/index');
						}
					break;
						
					case 'save':
						if ($id !== false)
						{
							$update_array = array(
								'log_author_user' => $this->session->userdata('userid'),
								'log_author_character' => $author,
								'log_title' => $title,
								'log_content' => preg_replace('#<br\s*\/?>#i', '', $content),
								'log_tags' => $tags,
								'log_status' => 'saved',
								'log_last_update' => now(),
								'log_date' => now(),
							);
							
							$update = $this->logs->update_log($id, $update_array);
							
							if ($update > 0)
							{
								$message = sprintf(
									lang('flash_success'),
									ucfirst(lang('global_personallog')),
									lang('actions_saved'),
									''
								);

								$flash['status'] = 'success';
								$flash['message'] = text_output($message);
							}
							else
							{
								$message = sprintf(
									lang('flash_failure'),
									ucfirst(lang('global_personallog')),
									lang('actions_saved'),
									''
								);

								$flash['status'] = 'error';
								$flash['message'] = text_output($message);
							}
						}
						else
						{
							$insert_array = array(
								'log_author_user' => $this->session->userdata('userid'),
								'log_author_character' => $author,
								'log_title' => $title,
								'log_content' => preg_replace('#<br\s*\/?>#i', '', $content),
								'log_tags' => $tags,
								'log_status' => 'saved',
								'log_last_update' => now(),
								'log_date' => now(),
							);
							
							$insert = $this->logs->create_personal_log($insert_array);
							
							// grab the insert id
							$insert_id = $this->db->insert_id();
							
							$this->sys->optimize_table('personallogs');
							
							if ($insert > 0)
							{
								$message = sprintf(
									lang('flash_success'),
									ucfirst(lang('global_personallog')),
									lang('actions_saved'),
									''

								);

								$flash['status'] = 'success';
								$flash['message'] = text_output($message);
								
								// reset the fields if everything worked
								$content = false;
								$title = false;
								$tags = false;
							}
							else
							{
								$message = sprintf(
									lang('flash_failure'),
									ucfirst(lang('global_personallog')),
									lang('actions_saved'),
									''
								);

								$flash['status'] = 'error';
								$flash['message'] = text_output($message);
							}
							
							// add an automatic redirect
							$this->_regions['_redirect'] = Template::add_redirect('write/personallog/'.$insert_id);
						}
					break;
						
					case 'post':
						// check the moderation status
						$status = $this->user->checking_moderation('log', $this->session->userdata('userid'));
						
						if ($id !== false)
						{
							$update_array = array(
								'log_author_user' => $this->session->userdata('userid'),
								'log_author_character' => $author,
								'log_date' => now(),
								'log_title' => $title,
								'log_content' => preg_replace('#<br\s*\/?>#i', '', $content),
								'log_tags' => $tags,
								'log_status' => $status,
								'log_last_update' => now()
							);
							
							$update = $this->logs->update_log($id, $update_array);
							
							if ($update > 0)
							{
								$array = array('last_post' => now());
								$this->user->update_user($this->session->userdata('userid'), $array);
								$this->char->update_character($author, $array);
								
								$message = sprintf(
									lang('flash_success'),
									ucfirst(lang('global_personallog')),
									lang('actions_posted'),
									''
								);

								$flash['status'] = 'success';
								$flash['message'] = text_output($message);
								
								// set the array of data for the email
								$email_data = array(
									'author' => $author,
									'title' => $title,
									'content' => preg_replace('#<br\s*\/?>#i', '', $content)
								);
								
								if ($status == 'pending')
								{
									// send the email
									$email = ($this->options['system_email'] == 'on') ? $this->_email('log_pending', $email_data) : false;
								}
								else
								{
									// send the email
									$email = ($this->options['system_email'] == 'on') ? $this->_email('log', $email_data) : false;
								}
							}
							else
							{
								$message = sprintf(
									lang('flash_failure'),
									ucfirst(lang('global_personallog')),
									lang('actions_posted'),
									''
								);

								$flash['status'] = 'error';
								$flash['message'] = text_output($message);
							}
						}
						else
						{
							$insert_array = array(
								'log_author_user' => $this->session->userdata('userid'),
								'log_author_character' => $author,
								'log_date' => now(),
								'log_title' => $title,
								'log_content' => preg_replace('#<br\s*\/?>#i', '', $content),
								'log_tags' => $tags,
								'log_status' => $status,
								'log_last_update' => now()
							);
							
							$insert = $this->logs->create_personal_log($insert_array);
							
							if ($insert > 0)
							{
								$array = array('last_post' => now());
								$this->user->update_user($this->session->userdata('userid'), $array);
								$this->char->update_character($author, $array);
								
								$message = sprintf(
									lang('flash_success'),
									ucfirst(lang('global_personallog')),
									lang('actions_posted'),
									''
								);

								$flash['status'] = 'success';
								$flash['message'] = text_output($message);
								
								// set the array of data for the email
								$email_data = array(
									'author' => $author,
									'title' => $title,
									'content' => preg_replace('#<br\s*\/?>#i', '', $content)
								);
								
								if ($status == 'pending')
								{
									// send the email
									$email = ($this->options['system_email'] == 'on') ? $this->_email('log_pending', $email_data) : false;
								}
								else
								{
									// send the email
									$email = ($this->options['system_email'] == 'on') ? $this->_email('log', $email_data) : false;
								}
								
								// reset the fields if everything worked
								$content = false;
								$title = false;
								$tags = false;
							}
							else
							{
								$message = sprintf(
									lang('flash_failure'),
									ucfirst(lang('global_personallog')),
									lang('actions_posted'),
									''
								);

								$flash['status'] = 'error';
								$flash['message'] = text_output($message);
							}
						}
					break;
						
					default:
						$flash['status'] = 'error';
						$flash['message'] = lang_output('error_generic', '');
					break;
				}
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		// run the methods
		$char = $this->session->userdata('characters');
		
		if (count($char) > 1)
		{
			$data['characters'][0] = ucwords(lang('labels_please').' '.lang('actions_select'))
				.' '.lang('labels_an').' '.ucfirst(lang('labels_author'));
			
			foreach ($char as $item)
			{
				$type = $this->char->get_character($item, 'crew_type');
				
				if ($type == 'active' or $type == 'npc')
				{
					if ($type == 'active')
					{
						$label = ucwords(lang('status_playing') .' '. lang('global_characters'));
					}
					else
					{
						$label = ucwords(lang('abbr_npcs'));
					}
					
					$data['characters'][$label][$item] = $this->char->get_character_name($item, true);
				}
			}
		}
		else
		{
			// set the ID and name
			$data['character']['id'] = $char[0];
			$data['character']['name'] = $this->char->get_character_name($char[0], true);
		}
		
		$row = ($id !== false) ? $this->logs->get_log($id) : false;
		
		if ($row !== false)
		{
			if ($row->log_author_user != $this->session->userdata('userid'))
			{
				redirect('admin/error/4');
			}
			
			if ( ! isset($action) and ($row->log_status == 'pending' or $row->log_status == 'activated'))
			{
				redirect('admin/error/5');
			}
			
			// fill the content in
			$title = $row->log_title;
			$content = nl2br($row->log_content);
			$tags = $row->log_tags;
		
			// set the key in prep for searching
			$data['key'] = 0;
			
			if (isset($data['characters']) and $data['key'] == 0)
			{
				foreach ($data['characters'] as $a)
				{
					if (is_array($a))
					{
						$data['key'] = (array_key_exists($row->log_author_character, $a)) ? $row->log_author_character : 0;
					}
				}
			}
		}
		
		$data['inputs'] = array(
			'title' => array(
				'name' => 'title',
				'id' => 'title',
				'value' => $title),
			'content' => array(
				'name' => 'content',
				'id' => 'content-textarea',
				'rows' => 20,
				'value' => $content),
			'tags' => array(
				'name' => 'tags',
				'id' => 'tags',
				'value' => $tags),
			'post' => array(
				'type' => 'submit',
				'class' => 'button-main',
				'name' => 'submit',
				'value' => 'post',
				'id' => 'submitPost',
				'content' => ucwords(lang('actions_post'))),
			'save' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'save',
				'content' => ucwords(lang('actions_save'))),
			'delete' => array(
				'type' => 'submit',
				'class' => 'button-sec',
				'name' => 'submit',
				'value' => 'delete',
				'id' => 'submitDelete',
				'content' => ucwords(lang('actions_delete')))
		);
		
		$data['header'] = ucwords(lang('actions_write') .' '. lang('global_personallog'));
		
		$data['form_action'] = ($id !== false) ? 'write/personallog/'. $id : 'write/personallog';

		$data['images'] = array(
			'help' => array(
				'src' => Location::img('help.png', $this->skin, 'admin'),
				'alt' => '',
				'class' => 'image',
				'rel' => 'tooltip',
				'title' => lang('tags_explain'),
			),
		);
		
		$data['label'] = array(
			'author' => ucwords(lang('labels_author')),
			'content' => ucwords(lang('labels_content')),
			'tags' => ucwords(lang('labels_tags')),
			'tags_sep' => lang('tags_separated'),
			'title' => ucwords(lang('labels_title')),
			'select' => ucwords(lang('labels_please').' '.lang('actions_select')).' '.lang('labels_an').' '.ucfirst(lang('labels_author')),
		);
		
		$this->_regions['content'] = Location::view('write_personallog', $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('write_personallog_js', $this->skin, 'admin');
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
}
