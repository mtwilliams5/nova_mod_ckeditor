<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once MODPATH.'core/controllers/nova_messages.php';

class Messages extends Nova_messages {

	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Put your own methods below this...
	 */
	
	public function write($action = false, $id = false)
	{
		Auth::check_access('messages/index');
		
		if ($this->options['system_email'] == 'off')
		{
			$flash['status'] = 'info';
			$flash['message'] = lang_output('flash_system_email_off');
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
		}
		
		// set the action array
		$action_array = array('reply', 'replyall', 'forward');
		
		// sanity checks
		$action = (in_array($action, $action_array)) ? $action : false;
		$id = (is_numeric($id)) ? $id : false;
		
		// set the variables
		$data['key'] = '';
		$message = false;
		$subject = false;
		
		if (isset($_POST['submit']))
		{
			// define the POST variables
			$subject = $this->input->post('subject', true);
			$message = $this->input->post('message', true);
			$recipients = $this->input->post('recipients', true);
			
			if ( ! $recipients or ( ! is_array($recipients) and count($recipients) == 0))
			{
				$flash['status'] = 'error';
				$flash['message'] = lang_output('flash_privmsgs_no_recipient');
			}
			else
			{
				foreach ($recipients as $key => $value)
				{
					if ( ! is_numeric($value) || $value < 1)
					{
						unset($recipients[$key]);
					}
				}
				
				$insert_array = array(
					'privmsgs_author_user' => $this->session->userdata('userid'),
					'privmsgs_author_character' => $this->session->userdata('main_char'),
					'privmsgs_date' => now(),
					'privmsgs_subject' => $subject,
					'privmsgs_content' => preg_replace('#<br\s*\/?>#i', '', $message)
				);
				
				// do the insert
				$insert = $this->pm->insert_private_message($insert_array);
				
				// get the message ID
				$msgid = $this->db->insert_id();
				
				$this->sys->optimize_table('privmsgs');
				
				foreach ($recipients as $value)
				{
					$insert_array = array(
						'pmto_message' => $msgid,
						'pmto_recipient_user' => $value,
						'pmto_recipient_character' => $this->user->get_main_character($value)
					);
					
					$insert2 = $this->pm->insert_pm_recipients($insert_array);
				}
				
				if ($insert > 0)
				{
					$flashmsg = sprintf(
						lang('flash_success'),
						ucfirst(lang('global_privatemessage')),
						lang('actions_sent'),
						''
					);
					
					$flash['status'] = 'success';
					$flash['message'] = text_output($flashmsg);
					
					// set the array of data for the email
					$email_data = array(
						'author' => $this->session->userdata('main_char'),
						'subject' => $subject,
						'to' => implode(',', $recipients),
						'message' => preg_replace('#<br\s*\/?>#i', '', $message)
					);
					
					// send the email
					$email = ($this->options['system_email'] == 'on') ? $this->_email($email_data) : false;
				}
				else
				{
					$message = sprintf(
						lang('flash_failure'),
						ucfirst(lang('global_privatemessage')),
						lang('actions_sent'),
						''
					);
					
					$flash['status'] = 'error';
					$flash['message'] = text_output($message);
				}
			}
			
			// set the flash message
			$this->_regions['flash_message'] = Location::view('flash', $this->skin, 'admin', $flash);
			
			// reset the message and subject variables
			$message = false;
			$subject = false;
		}
		
		// run the methods
		$characters = $this->user->get_main_characters();
		
		if ($characters->num_rows() > 0)
		{
			foreach ($characters->result() as $item)
			{
				if ($item->crew_type == 'active')
				{
					$data['characters'][$item->userid] = $this->char->get_character_name($item->main_char, true);
				}
			}
		}
		
		$data['inputs'] = array(
			'subject' => array(
				'name' => 'subject',
				'id' => 'subject',
				'value' => $subject),
			'message' => array(
				'name' => 'message',
				'id' => 'message-textarea',
				'rows' => 20,
				'value' => $message),
			'submit' => array(
				'type' => 'submit',
				'class' => 'button-main',
				'name' => 'submit',
				'value' => 'submit',
				'content' => ucwords(lang('actions_submit')))
		);
		
		// get the data if it is not a new PM
		$info = ($action !== false) ? $this->pm->get_message($id) : false;
		$row = ($info !== false and $info->num_rows() > 0) ? $info->row() : false;
		$recipient_list = ($action == 'reply' or $action == 'replyall') ? $this->pm->get_message_recipients($id) : false;
		
		// make sure the person is allowed to be replying
		if ($recipient_list !== false)
		{
			if ( ! in_array($this->session->userdata('userid'), $recipient_list) and
					! ($this->session->userdata('userid') == $row->privmsgs_author_user))
			{
				redirect('admin/error/3');
			}
		}
		
		$data['recipient_list'] = array();
		
		switch ($action)
		{
			case 'reply':
				// how many times does the RE: string appear in the subject?
				$re_count = substr_count($row->privmsgs_subject, lang('abbr_reply'));
				
				// make sure the subject is set right
				$subj = ($re_count == 0) ? lang('abbr_reply').': '.$row->privmsgs_subject : $row->privmsgs_subject;
				
				// set the subject value
				$data['inputs']['subject']['value'] = $subj;
				
				// set the user
				$selected = $row->privmsgs_author_user;
 				
				// check if you're replying to yourself
				$selected = ($selected == $this->session->userdata('userid')) ? $recipient_list[0] : $selected;
				
				// grab the key for the array
				$key = (array_key_exists($selected, $data['characters'])) ? $selected : 0;
				
				// set the key
				$data['recipient_list'] = $key;
				
				$data['header'] = ucfirst(lang('actions_reply')).' '.lang('labels_to').' '.ucwords(lang('global_privatemessage'));
				
				$date = gmt_to_local($row->privmsgs_date, $this->timezone, $this->dst);
				
				// set the data for the previous PM
				$data['previous'] = array(
					'from' => $this->char->get_character_name($row->privmsgs_author_character, false, false, true),
					'date' => mdate($this->options['date_format'], $date),
					'content' => $row->privmsgs_content
				);
			break;
			
			case 'replyall':
				// add the author to the recipients list
				$recipient_list[] = $row->privmsgs_author_user;
				
				// find if the current user is listed in the recipient list
				$key = array_search($this->session->userdata('userid'), $recipient_list);
				
				// drop the current user off the recipient list
				if ($key !== false)
				{
					unset($recipient_list[$key]);
				}
				
				// set the hidden TO field
				$data['recipient_list'] = $recipient_list;
				
				// how many times does the RE: string appear in the subject?
				$re_count = substr_count($row->privmsgs_subject, lang('abbr_reply'));
				
				// make sure the subject is set right
				$subj = ($re_count == 0) ? lang('abbr_reply').': '.$row->privmsgs_subject : $row->privmsgs_subject;
				
				// set the subject value
				$data['inputs']['subject']['value'] = $subj;
				
				$data['header'] = ucfirst(lang('actions_reply')).' '.lang('labels_to').' '.ucwords(lang('global_privatemessage'));
				
				$date = gmt_to_local($row->privmsgs_date, $this->timezone, $this->dst);
				
				// set the data for the previous PM
				$data['previous'] = array(
					'from' => $this->char->get_character_name($row->privmsgs_author_character, false, false, true),
					'date' => mdate($this->options['date_format'], $date),
					'content' => $row->privmsgs_content
				);
			break;
				
			case 'forward':
				// set the hidden TO field
				$data['to'] = 0;
				
				// build an array to hold the recipients
				$to_array = $this->pm->get_message_recipients($id);
				
				foreach ($to_array as $rec)
				{
					$array[] = $this->char->get_character_name($this->user->get_main_character($rec), true);
				}
				
				// create a string of character names
				$to = implode(' &amp; ', $array);
				
				$date = gmt_to_local($row->privmsgs_date, $this->timezone, $this->dst);
				
				// set the textarea value
				$data['inputs']['message']['value'] = nl2br("\r\n\r\n\r\n==========\r\n\r\n");
				$data['inputs']['message']['value'].= ucfirst(lang('time_from')) .': ';
				$data['inputs']['message']['value'].= $this->char->get_character_name($row->privmsgs_author_character, true);
				$data['inputs']['message']['value'].= nl2br("\r\n". ucfirst(lang('labels_to')).': '.str_replace(' &amp; ', ', ', $to));
				$data['inputs']['message']['value'].= nl2br("\r\n". ucfirst(lang('labels_on')) .' ');
				$data['inputs']['message']['value'].= mdate($this->options['date_format'], $date);
				$data['inputs']['message']['value'].= nl2br("\r\n\r\n". $row->privmsgs_content);
				
				// how many times does the FWD: string appear in the subject?
				$re_count = substr_count($row->privmsgs_subject, lang('abbr_forward'));
				
				// make sure the subject is set right
				$subj = ($re_count == 0) ? lang('abbr_forward').': '.$row->privmsgs_subject : $row->privmsgs_subject;
				
				// set the subject value
				$data['inputs']['subject']['value'] = $subj;
				
				$data['header'] = ucfirst(lang('actions_forward')) .' '. ucwords(lang('global_privatemessage'));
			break;
				
			default:
				$data['to'] = 0;
				$data['header'] = ucwords(lang('actions_write') .' '. lang('global_privatemessage'));
			break;
		}
		
		$data['label'] = array(
			'add' => ucwords(lang('actions_add') .' '. lang('labels_recipient')),
			'inbox' => LARROW.' '.ucfirst(lang('actions_back')).' '.lang('labels_to').' '.ucfirst(lang('labels_inbox')),
			'message' => ucfirst(lang('labels_message')),
			'on' => ucfirst(lang('labels_on')),
			'subject' => ucfirst(lang('labels_subject')),
			'to' => ucfirst(lang('labels_to')),
			'wrote' => lang('actions_wrote') .':',
			'select' => ucwords(lang('labels_please').' '.lang('actions_select')).' '.lang('labels_the').' '.ucfirst(lang('labels_recipients')),
			'chosen_incompat' => lang('chosen_incompat'),
		);
		
		$this->_regions['content'] = Location::view('messages_write', $this->skin, 'admin', $data);
		$this->_regions['javascript'] = Location::js('messages_write_js', $this->skin, 'admin');
		$this->_regions['title'].= $data['header'];
		
		Template::assign($this->_regions);
		
		Template::render();
	}
}
