<?php

class Issue extends Controller
{
	var $VIEW_ERROR = 'error';
	var $VIEW_HOME = 'issue_home';
	var $VIEW_SHOW = 'issue_show';
	var $VIEW_EDIT = 'issue_edit';
	var $VIEW_DELETE = 'issue_delete';

	function Issue ()
	{
		parent::Controller ();

		$this->load->helper ('url');
		$this->load->helper ('form');
		$this->load->library ('Converter', 'converter');
		$this->load->model (CODEPOT_LOGIN_MODEL, 'login');

		$this->load->library ('Language', 'lang');
		$this->load->library ('IssueHelper', 'issuehelper');
		$this->lang->load ('common', CODEPOT_LANG);
		$this->lang->load ('issue', CODEPOT_LANG);
	}

	function home ($projectid = '', $filter = '', $offset = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');
	
		$login = $this->login->getUser ();
		if (CODEPOT_SIGNIN_COMPULSORY && $login['id'] == '')
			redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
		$data['login'] = $login;

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			if ($project->public !== 'Y' && $login['id'] == '')
			{
				// non-public projects require sign-in.
				redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
			}

			if ($filter == '')
			{
				$search = new stdClass();
				$search->type = '';
				$search->status = '';
				$search->priority = '';
				$search->owner = '';
				$search->summary = '';
			}
			else
			{
				parse_str ($this->converter->HexToAscii($filter), $search);
				if (!array_key_exists ('type', $search)) $search['type'] = '';
				if (!array_key_exists ('status', $search)) $search['status'] = '';
				if (!array_key_exists ('priority', $search)) $search['priority'] = '';
				if (!array_key_exists ('owner', $search)) $search['owner'] = '';
				if (!array_key_exists ('summary', $search)) $search['summary'] = '';

				$search = (object) $search;
			}

			$data['search'] = $search;

			$this->load->library ('pagination');

			if ($filter == '' && $offset == '')
			{
				$offset = 0;
				$pagecfg['base_url'] = site_url() . "/issue/home/{$projectid}/";
				$pagecfg['uri_segment'] = 4;
			}
			else if ($filter != '' && $offset == '')
			{
				if (is_numeric($filter))
				{
					$offset = (integer) $filter;
					$pagecfg['base_url'] = site_url() . "/issue/home/{$projectid}/";
					$pagecfg['uri_segment'] = 4;
				}
				else
				{
					$offset = 0;
					$pagecfg['base_url'] = site_url() . "/issue/home/{$projectid}/{$filter}/";
					$pagecfg['uri_segment'] = 5;
				}
			}
			else 
			{
				$offset = (integer) $offset;
				$pagecfg['base_url'] = site_url() . "/issue/home/{$projectid}/{$filter}/";
				$pagecfg['uri_segment'] = 5;
			}

			$num_entries = $this->issues->getNumEntries ($login['id'], $project, $search);
			if ($num_entries === FALSE)
			{
				$data['project'] = $project;
				$data['message'] = 'DATABASE ERROR';
				$this->load->view ($this->VIEW_ERROR, $data);
				return;
			}

			$pagecfg['total_rows'] = $num_entries;
			$pagecfg['per_page'] = CODEPOT_MAX_ISSUES_PER_PAGE;
			$pagecfg['first_link'] = $this->lang->line('First');
			$pagecfg['last_link'] = $this->lang->line('Last');

			//$issues = $this->issues->getAll ($login['id'], $project);
			$issues = $this->issues->getEntries ($login['id'], $offset, $pagecfg['per_page'], $project, $search);
			if ($issues === FALSE)
			{
				$data['project'] = $project;
				$data['message'] = 'DATABASE ERROR';
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else
			{
				$this->pagination->initialize ($pagecfg);
				$data['page_links'] = $this->pagination->create_links ();
				$data['issue_type_array'] = $this->issuehelper->_get_type_array($this->lang);
				$data['issue_status_array'] = $this->issuehelper->_get_status_array($this->lang);
				$data['issue_priority_array'] = $this->issuehelper->_get_priority_array($this->lang);
				$data['total_num_issues'] = $num_entries;
				$data['project'] = $project;
				$data['issues'] = $issues;
				$this->load->view ($this->VIEW_HOME, $data);
			}
		}
	}

	function show ($projectid = '', $hexid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$login = $this->login->getUser ();
		if (CODEPOT_SIGNIN_COMPULSORY && $login['id'] == '')
			redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
		$data['login'] = $login;

		if ($hexid == '')
		{
			$data['message'] = 'INVALID PARAMETERS';
			$this->load->view ($this->VIEW_ERROR, $data);
			return;
		}

		$id = $this->converter->HexToAscii ($hexid);

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			$change_post = $this->input->post('issue_change');
			if ($change_post == 'change')
			{
				$change = new stdClass();
				$change->type = $this->input->post('issue_change_type');
				$change->status = $this->input->post('issue_change_status');
				$change->owner = $this->input->post('issue_change_owner');
				$change->priority = $this->input->post('issue_change_priority');
				$change->comment = $this->input->post('issue_change_comment');

				if (!$login['sysadmin?'] && 
				    $this->projects->projectHasMember($project->id, $login['id']) === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_PROJECT_MEMBERSHIP_REQUIRED'), $projectid);
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else if ($this->issues->change ($login['id'], $project, $id, $change) === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = 'DATABASE ERROR';
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else
				{
					redirect ("/issue/show/{$projectid}/{$hexid}");
				}
				return;
			}
			else if ($change_post == 'undo')
			{
				if (!$login['sysadmin?'] && 
				    $this->projects->projectHasMember($project->id, $login['id']) === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_PROJECT_MEMBERSHIP_REQUIRED'), $projectid);
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else if ($this->issues->undo_last_change ($login['id'], $project, $id) === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = 'DATABASE ERROR';
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else
				{
					redirect ("/issue/show/{$projectid}/{$hexid}");
				}
				return;
			}

			$issue = $this->issues->get ($login['id'], $project, $id);
			if ($issue === FALSE)
			{
				$data['project'] = $project;
				$data['message'] = 'DATABASE ERROR';
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else if ($issue === NULL)
			{
				$data['project'] = $project;
				$data['message'] = sprintf (
					$this->lang->line('ISSUE_MSG_NO_SUCH_ISSUE'), $id);
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else
			{
				$data['issue_type_array'] = $this->issuehelper->_get_type_array($this->lang);
				$data['issue_status_array'] = $this->issuehelper->_get_status_array($this->lang);
				$data['issue_priority_array'] = $this->issuehelper->_get_priority_array($this->lang);
				$data['project'] = $project;
				$data['issue'] = $issue;
				$this->load->view ($this->VIEW_SHOW, $data);
			}
		}
	}

/*
DEPRECATED
	function _edit_issue ($projectid, $hexid, $mode)
	{
		$this->load->helper ('form');
		$this->load->library ('form_validation');
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$login = $this->login->getUser ();
		if ($login['id'] == '')
			redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
		$data['login'] = $login;

		$id = $this->converter->HexToAscii ($hexid);

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if (!$login['sysadmin?'] && $mode != 'create' &&
		         $this->projects->projectHasMember($project->id, $login['id']) === FALSE)
		{
			$data['project'] = $project;
			$data['message'] = sprintf (
				$this->lang->line('MSG_PROJECT_MEMBERSHIP_REQUIRED'), $projectid);
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			$this->form_validation->set_rules (
				'issue_projectid', 'project ID', 'required|alpha_dash|max_length[32]');
			$this->form_validation->set_rules (
				'issue_summary', 'summary', 'required|max_length[255]');
			$this->form_validation->set_rules (
				'issue_description', 'description', 'required');
			$this->form_validation->set_rules (
				'issue_type', 'type', 'required');
			$this->form_validation->set_rules (
				'issue_type', 'status', 'required');
			$this->form_validation->set_rules (
				'issue_type', 'priority', 'required');
			$this->form_validation->set_error_delimiters (
				'<span class="form_field_error">','</span>');

			$data['mode'] = $mode;
			$data['message'] = '';
			$data['project'] = $project;
			$data['issue_type_array'] = $this->issuehelper->_get_type_array($this->lang);
			$data['issue_status_array'] = $this->issuehelper->_get_status_array($this->lang);
			$data['issue_priority_array'] = $this->issuehelper->_get_priority_array($this->lang);

			if ($this->input->post('issue'))
			{
				$issue = new stdClass();
				$issue->projectid = $this->input->post('issue_projectid');
				$issue->id = $this->input->post('issue_id');
				$issue->summary = $this->input->post('issue_summary');
				$issue->description = $this->input->post('issue_description');
				$issue->type = $this->input->post('issue_type');
				$issue->status = $this->input->post('issue_status');
				$issue->priority = $this->input->post('issue_priority');
				$issue->owner = $this->input->post('issue_owner');

				if ($this->form_validation->run())
				{
					$id = ($mode == 'update')?
						$this->issues->update_partial ($login['id'], $issue):
						$this->issues->create ($login['id'], $issue);
					if ($id === FALSE)
					{
						$data['message'] = 'DATABASE ERROR';
						$data['issue'] = $issue;
						$this->load->view ($this->VIEW_EDIT, $data);
					}
					else
					{
						redirect ("issue/show/{$project->id}/" . 
							$this->converter->AsciiToHex((string)$id));
					}
				}
				else
				{
					$data['message'] = $this->lang->line('MSG_FORM_INPUT_INCOMPLETE');
					$data['issue'] = $issue;
					$this->load->view ($this->VIEW_EDIT, $data);
				}
			}
			else
			{
				if ($mode == 'update')
				{
					$issue = $this->issues->get ($login['id'], $project, $id);
					if ($issue === FALSE)
					{
						$data['message'] = 'DATABASE ERROR';
						$this->load->view ($this->VIEW_ERROR, $data);
					}
					else if ($issue == NULL)
					{
						$data['message'] = sprintf (
							$this->lang->line('ISSUE_MSG_NO_SUCH_ISSUE'), $id);
						$this->load->view ($this->VIEW_ERROR, $data);
					}
					else
					{
						$data['issue'] = $issue;
						$this->load->view ($this->VIEW_EDIT, $data);
					}
				}
				else
				{
					$issue = new stdClass();
					$issue->projectid = $projectid;
					$issue->id = $id;
					$issue->summary = '';
					$issue->description = '';
					$issue->type = $this->issuehelper->TYPE_DEFECT;
					$issue->status = $this->issuehelper->STATUS_NEW;
					$issue->priority = $this->issuehelper->PRIORITY_OTHER;
					if ($this->projects->projectHasMember($project->id, $login['id']))
					{
						// let the current user be the issue owner if he/she is a
						// project memeber.
						$issue->owner = $login['id'];
					}
					else
					{
						// if not, assign the issue to the first member.
						$issue->owner = (count($project->members) > 0)? $project->members[0]: '';
					}

					$data['issue'] = $issue;
					$this->load->view ($this->VIEW_EDIT, $data);
				}
			}

		}
	}

	function create ($projectid = '')
	{
		return $this->_edit_issue ($projectid, '', 'create');
	}

	function update ($projectid = '', $hexid = '')
	{
		return $this->_edit_issue ($projectid, $hexid, 'update');
	}


	function delete ($projectid = '', $hexid = '')
	{
		$this->load->helper ('form');
		$this->load->library ('form_validation');
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$login = $this->login->getUser ();
		if ($login['id'] == '')
			redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
		$data['login'] = $login;

		$id = $this->converter->HexToAscii ($hexid);

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if (!$login['sysadmin?'] && 
		         $this->projects->projectHasMember($project->id, $login['id']) === FALSE)
		{
			$data['project'] = $project;
			$data['message'] = sprintf (
				$this->lang->line('MSG_PROJECT_MEMBERSHIP_REQUIRED'), $projectid);
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			$data['message'] = '';
			$data['project'] = $project;

			$this->form_validation->set_rules ('issue_confirm', 'confirm', 'alpha');
			$this->form_validation->set_error_delimiters('<span class="form_field_error">','</span>');

			if($this->input->post('issue'))
			{
				$issue = new stdClass();
				$issue->projectid = $this->input->post('issue_projectid');
				$issue->id = $this->input->post('issue_id');
				$data['issue_confirm'] = $this->input->post('issue_confirm');

				if ($this->form_validation->run())
				{
					if ($data['issue_confirm'] == 'yes')
					{
						$result = $this->issues->delete ($login['id'], $issue);
						if ($result === FALSE)
						{
							$data['message'] = 'DATABASE ERROR';
							$data['issue'] = $issue;
							$this->load->view ($this->VIEW_DELETE, $data);
						}
						else
						{
							redirect ("issue/home/{$project->id}");
						}
					}
					else 
					{
						redirect ("issue/show/{$project->id}/{$hexid}");
					}
				}
				else
				{
					$data['message'] = $this->lang->line('MSG_FORM_INPUT_INCOMPLETE');
					$data['issue'] = $issue;
					$this->load->view ($this->VIEW_DELETE, $data);
				}
			}
			else
			{
				$issue = $this->issues->get ($login['id'], $project, $id);
				if ($issue === FALSE)
				{
					$data['message'] = 'DATABASE ERROR';
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else if ($issue === NULL)
				{
					$data['message'] = sprintf (
						$this->lang->line('ISSUE_MSG_NO_SUCH_ISSUE'), $id);
					$this->load->view ($this->VIEW_ERROR, $data);
				}
				else
				{
					$data['issue_confirm'] = 'no';
					$data['issue'] = $issue;
					$this->load->view ($this->VIEW_DELETE, $data);
				}
			}

		}
	}
*/

	function xhr_create ($projectid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');
		$this->load->library ('upload');

		$login = $this->login->getUser ();
		$revision_saved = -1;

		if ($login['id'] == '')
		{
			$status = 'error - anonymous user';
		}
		else
		{
			$project = $this->projects->get ($projectid);
			if ($project === FALSE)
			{
				$status = "error - failed to get the project {$projectid}";
			}
			else if ($project === NULL)
			{
				$status = "error - no such project {$projectid}";
			}
			// By default, any logged-in user can create an issue.
			// TODO: add a project option to accept an issue from anonymous users, logged-in users or just members.
			//else if (!$login['sysadmin?'] && 
			//         $this->projects->projectHasMember($projectid, $login['id']) === FALSE)
			//{
			//	$status = "error - not a member {$login['id']}";
			//}
			else
			{
				$issue = new stdClass();
				$issue->projectid = $projectid;
				$issue->summary = $this->input->post('issue_new_summary');
				$issue->description = $this->input->post('issue_new_description');
				$issue->type = $this->input->post('issue_new_type');
				$issue->status = $this->issuehelper->STATUS_NEW;
				$issue->priority = $this->issuehelper->PRIORITY_OTHER;
				if ($this->projects->projectHasMember($project->id, $login['id']))
				{
					// let the current user be the issue owner if he/she is a
					// project memeber.
					$issue->owner = $login['id'];
				}
				else
				{
					// if not, assign the issue to the first member.
					$issue->owner = (count($project->members) > 0)? $project->members[0]: '';
				}

				$post_new_file_count = $this->input->post('issue_new_file_count');

				if ($issue->type === FALSE || ($issue->type = trim($issue->type)) == '')
				{
					$status = 'error - no type';
				}
				else if ($issue->summary === FALSE || ($issue->summary = trim($issue->summary)) == '')
				{
					$status = 'error - no summary';
				}
				else if ($issue->description === FALSE || ($issue->description = trim($issue->description)) == '')
				{
					$status = 'error - no description';
				}
				else
				{
					if ($post_new_file_count === FALSE || $post_new_file_count <= 0) $post_new_file_count = 0;

					$status = '';
					$attached_files = array ();
					for ($i = 0; $i < $post_new_file_count; $i++)
					{
						$fid = "issue_new_file_{$i}";
						if (array_key_exists($fid, $_FILES) && $_FILES[$fid]['name'] != '')
						{
							$d = $this->input->post("issue_new_file_desc_{$i}");
							if ($d === FALSE || ($d = trim($d)) == '') $d = ''; // description optional

							if (strpos($_FILES[$fid]['name'], ':') !== FALSE ||
							    strpos($_FILES[$fid]['name'], '/') !== FALSE)
							{
								// prevents these letters for wiki creole 
								$status = "error - colon or slash not allowed - {$_FILES[$fid]['name']}";
								break;
							}

							array_push ($attached_files, array ('fid' => $fid, 'name' => $_FILES[$fid]['name'], 'desc' => $d));
						}
					}

					if ($status == '')
					{
						if ($this->issues->createWithFiles ($login['id'], $issue, $attached_files, $this->upload) === FALSE)
						{
							$status = 'error - ' . $this->issues->getErrorMessage();
						}
						else
						{
							$status = 'ok';
						}
					}
				}
			}
		}

		print $status;
	}

	function xhr_update ($projectid = '', $issueid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');
		$this->load->library ('upload');

		$login = $this->login->getUser ();
		$revision_saved = -1;

		if ($login['id'] == '')
		{
			$status = 'error - anonymous user';
		}
		else
		{
			$issueid = $this->converter->HexToAscii ($issueid);

			$project = $this->projects->get ($projectid);
			if ($project === FALSE)
			{
				$status = "error - failed to get the project {$projectid}";
			}
			else if ($project === NULL)
			{
				$status = "error - no such project {$projectid}";
			}
			// By default, any logged-in user can edit an issue text.
			// TODO: add a project option to accept an issue from anonymous users, logged-in users or just members.
			else if (!$login['sysadmin?'] && 
			         $this->projects->projectHasMember($projectid, $login['id']) === FALSE &&
			         $this->issues->isCreatedBy($projectid, $issueid, $login['id']) === FALSE)
			{
				$status = "error - not a member nor a creator - {$login['id']}";
			}
			else
			{
				$issue = new stdClass();
				$issue->projectid = $projectid;
				$issue->id = $issueid;
				$issue->summary = $this->input->post('issue_edit_summary');
				$issue->description = $this->input->post('issue_edit_description');
				//$issue->type = $this->input->post('issue_edit_type');

				if ($issue->id === FALSE || ($issue->id = trim($issue->id)) == '')
				{
					$status = 'error - no ID';
				}
				else if ($issue->summary === FALSE || ($issue->summary = trim($issue->summary)) == '')
				{
					$status = 'error - no summary';
				}
				else if ($issue->description === FALSE || ($issue->description = trim($issue->description)) == '')
				{
					$status = 'error - no description';
				}
				else
				{
					$status = '';

					if ($status == '')
					{
						if ($this->issues->updateSummaryAndDescription ($login['id'], $issue) === FALSE)
						{
							$status = 'error - ' . $this->issues->getErrorMessage();
						}
						else
						{
							$status = 'ok';
						}
					}
				}
			}
		}

		print $status;
	}

	function xhr_delete ($projectid = '', $issueid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$login = $this->login->getUser ();

		if ($login['id'] == '')
		{
			$status = 'error - anonymous user';
		}
		else
		{
			$issueid = $this->converter->HexToAscii ($issueid);

			$project = $this->projects->get ($projectid);
			if ($project === FALSE)
			{
				$status = "error - failed to get the project {$projectid}";
			}
			else if ($project === NULL)
			{
				$status = "error - no such project {$projectid}";
			}
			else if (!$login['sysadmin?'] && 
			         $this->projects->projectHasMember($projectid, $login['id']) === FALSE)
			{
				$status = "error - not a member {$login['id']}";
			}
			else
			{
				$post_delete_confirm = $this->input->post('issue_delete_confirm');

				if ($post_delete_confirm !== FALSE && $post_delete_confirm == 'Y')
				{
					if ($this->issues->deleteWithFiles ($login['id'], $projectid, $issueid) === FALSE)
					{
						$status = 'error - ' . $this->issues->getErrorMessage();
					}
					else
					{
						$status = 'ok';
					}
				}
				else
				{
					$status = 'error - not confirmed';
				}
			}
		}

		print $status;
	}

	function xhr_add_file ($projectid = '', $issueid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');
		$this->load->library ('upload');

		$login = $this->login->getUser ();
		$revision_saved = -1;

		if ($login['id'] == '')
		{
			$status = 'error - anonymous user';
		}
		else
		{
			$issueid = $this->converter->HexToAscii ($issueid);

			$project = $this->projects->get ($projectid);
			if ($project === FALSE)
			{
				$status = "error - failed to get the project {$projectid}";
			}
			else if ($project === NULL)
			{
				$status = "error - no such project {$projectid}";
			}
			// By default, any logged-in user can attach a file to an issue body.
			// TODO: add a project option to accept an issue from anonymous users, logged-in users or just members.
			else if (!$login['sysadmin?'] && 
			         $this->projects->projectHasMember($projectid, $login['id']) === FALSE &&
			         $this->issues->isCreatedBy($projectid, $issueid, $login['id']) === FALSE)
			{
				$status = "error - not a member nor a creator - {$login['id']}";
			}
			else
			{
				$post_add_file_count = $this->input->post('issue_add_file_count');
				if ($post_add_file_count === FALSE || $post_add_file_count <= 0) $post_add_file_count = 0;

				$status = '';
				$add_files = array ();
				for ($i = 0; $i < $post_add_file_count; $i++)
				{
					$fid = "issue_add_file_{$i}";
					if (array_key_exists($fid, $_FILES) && $_FILES[$fid]['name'] != '')
					{
						$d = $this->input->post("issue_add_file_desc_{$i}");
						if ($d === FALSE || ($d = trim($d)) == '') $d = ''; 

						if (strpos($_FILES[$fid]['name'], ':') !== FALSE ||
						    strpos($_FILES[$fid]['name'], '/') !== FALSE)
						{
							// prevents these letters for wiki creole
							$status = "error - colon or slash not allowed - {$_FILES[$fid]['name']}";
							break;
						}

						array_push ($add_files, array ('fid' => $fid, 'name' => $_FILES[$fid]['name'], 'desc' => $d));
					}
				}

				if ($status == '')
				{
					if (count($add_files) <= 0)
					{
						$status = 'error - no files uploaded';
					}
					else if ($this->issues->addFiles ($login['id'], $projectid, $issueid, $add_files, $this->upload) === FALSE)
					{
						$status = 'error - ' . $this->issues->getErrorMessage();
					}
					else
					{
						$status = 'ok';
					}
				}
			}
		}

		print $status;
	}

	function xhr_edit_file ($projectid = '', $issueid = '')
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$login = $this->login->getUser ();
		$revision_saved = -1;

		if ($login['id'] == '')
		{
			$status = 'error - anonymous user';
		}
		else
		{
			$issueid = $this->converter->HexToAscii ($issueid);

			$project = $this->projects->get ($projectid);
			if ($project === FALSE)
			{
				$status = "error - failed to get the project {$projectid}";
			}
			else if ($project === NULL)
			{
				$status = "error - no such project {$projectid}";
			}
			// By default, any logged-in user can edit attached files.
			// TODO: add a project option to accept an issue from anonymous users, logged-in users or just members.
			else if (!$login['sysadmin?'] && 
			         $this->projects->projectHasMember($projectid, $login['id']) === FALSE &&
			         $this->issues->isCreatedBy($projectid, $issueid, $login['id']) === FALSE)
			{
				$status = "error - not a member nor a creator - {$login['id']}";
			}
			else
			{
				$post_edit_file_count = $this->input->post('issue_edit_file_count');
				if ($post_edit_file_count === FALSE || $post_edit_file_count <= 0) $post_edit_file_count = 0;

				$status = '';
				$edit_files = array ();
				for ($i = 0; $i < $post_edit_file_count; $i++)
				{
					$n = $this->input->post("issue_edit_file_name_{$i}");
					$k = $this->input->post("issue_edit_file_kill_{$i}");
					$d = $this->input->post("issue_edit_file_desc_{$i}");

					if ($n != '')
					{
						if ($k == 'yes')
						{
							array_push ($edit_files, array ('name' => $n, 'kill' => $k));
						}
						else if ($d !== FALSE)
						{
							if (($d = trim($d)) == '')
							{
								$status = "error - no short description for {$n}";
								break;
							}

							array_push ($edit_files, array ('name' => $n, 'desc' => $d));
						}
					}
				}

				if ($status == '')
				{
					if (count($edit_files) <= 0)
					{
						//$status = 'error - no input avaialble';
						$status = 'ok';
					}
					else if ($this->issues->editFiles ($login['id'], $projectid, $issueid, $edit_files) === FALSE)
					{
						$status = 'error - ' . $this->issues->getErrorMessage();
					}
					else
					{
						$status = 'ok';
					}
				}
			}
		}

		print $status;
	}

	////////////////////////////////////////////////////////////////////////
	// Handling of attached files share the (almost) same code 
	// between issue.php and wiki.php. It would be way better
	// to put the common code into a parent class and use inheritance.
	// Makre sure to apply changes to both files if any.

	private function _handle_wiki_attachment ($login, $projectid, $wikiname, $name)
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('WikiModel', 'wikis');

		$data['login'] = $login;

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			if ($project->public !== 'Y' && $login['id'] == '')
			{
				// non-public projects require sign-in.
				redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
			}

			$att = $this->wikis->getAttachment ($login['id'], $project, $wikiname, $name);
			if ($att === FALSE)
			{
				$data['project'] = $project;
				$data['message'] = 'DATABASE ERROR';
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else if ($att === NULL)
			{
				$data['project'] = $project;
				$data['message'] = sprintf (
					$this->lang->line('MSG_NO_SUCH_FILE'), $name);
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else
			{
				$path = CODEPOT_ATTACHMENT_DIR . "/{$att->encname}";

				$stat = @stat($path);
				if ($stat === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_FAILED_TO_READ_FILE'), $name);
					$this->load->view ($this->VIEW_ERROR, $data);
					return;
				}

				$etag = sprintf ('%x-%x-%x-%x', $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime']);
				$lastmod = gmdate ('D, d M Y H:i:s', $stat['mtime']);

				header ('Last-Modified: ' . $lastmod . ' GMT');
				header ('Etag: ' . $etag);

				if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
				    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']))
				{
					header('Not Modified', true, 304);
					flush ();
					return;
				}

				header ('Content-Type: ' . mime_content_type($path));
				header ('Content-Length: ' . $stat['size']);
				header ('Content-Disposition: inline; filename=' . $name);
				flush ();

				$x = @readfile($path);
				if ($x === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_FAILED_TO_READ_FILE'), $name);
					$this->load->view ($this->VIEW_ERROR, $data);
				}
			}
		}
	}

	private function _handle_issue_file ($login, $projectid, $issueid, $filename)
	{
		$this->load->model ('ProjectModel', 'projects');
		$this->load->model ('IssueModel', 'issues');

		$data['login'] = $login;

		$project = $this->projects->get ($projectid);
		if ($project === FALSE)
		{
			$data['message'] = 'DATABASE ERROR';
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else if ($project === NULL)
		{
			$data['message'] = 
				$this->lang->line('MSG_NO_SUCH_PROJECT') . 
				" - {$projectid}";
			$this->load->view ($this->VIEW_ERROR, $data);
		}
		else
		{
			if ($project->public !== 'Y' && $login['id'] == '')
			{
				// non-public projects require sign-in.
				redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));
			}

			$att = $this->issues->getFile ($login['id'], $project, $issueid, $filename);
			if ($att === FALSE)
			{
				$data['project'] = $project;
				$data['message'] = 'DATABASE ERROR';
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else if ($att === NULL)
			{
				$data['project'] = $project;
				$data['message'] = sprintf (
					$this->lang->line('MSG_NO_SUCH_FILE'), $filename);
				$this->load->view ($this->VIEW_ERROR, $data);
			}
			else
			{
				$path = CODEPOT_ISSUE_FILE_DIR . "/{$att->encname}";

				$stat = @stat($path);
				if ($stat === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_FAILED_TO_READ_FILE'), $filename);
					$this->load->view ($this->VIEW_ERROR, $data);
					return;
				}

				$etag = sprintf ('%x-%x-%x-%x', $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime']);
				$lastmod = gmdate ('D, d M Y H:i:s', $stat['mtime']);

				header ('Last-Modified: ' . $lastmod . ' GMT');
				header ('Etag: ' . $etag);

				if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) ||
				    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $stat['mtime']))
				{
					header('Not Modified', true, 304);
					flush ();
					return;
				}

				header ('Content-Type: ' . mime_content_type($path));
				header ('Content-Length: ' . $stat['size']);
				header ('Content-Disposition: inline; filename=' . $filename);
				flush ();

				$x = @readfile($path);
				if ($x === FALSE)
				{
					$data['project'] = $project;
					$data['message'] = sprintf (
						$this->lang->line('MSG_FAILED_TO_READ_FILE'), $filename);
					$this->load->view ($this->VIEW_ERROR, $data);
				}
			}
		}
	}

	function file ($projectid = '', $issueid = '', $filename = '')
	{
		// this function is for handling a file name for the following format:
		//    1. filename
		//    2. projectid:issueid:filename
		//    3. projectid:wikiname:filename
		//
		$login = $this->login->getUser ();
		if (CODEPOT_SIGNIN_COMPULSORY && $login['id'] == '')
			redirect ("main/signin/" . $this->converter->AsciiTohex(current_url()));

		if ($issueid == '' || $filename == '')
		{
			$data['login'] = $login;
			$data['message'] = 'INVALID PARAMETERS';
			$this->load->view ($this->VIEW_ERROR, $data);
			return;
		}

		$filename = $this->converter->HexToAscii ($filename);

		$wikiname = '';
		$part = explode (':', $filename);
		if (count($part) == 3)
		{
			if ($part[0] != '') $projectid = $part[0];
			if ($part[1] != '') 
			{
				if ($part[1][0] == '#' && $part[1][1] == 'I')
				{
					$issueid = substr ($part[1],2);
					$wikiname = '';
				}
				else
				{
					$wikiname = $part[1];
					$issueid = '';
				}
			}
			if ($part[2] != '') $filename = $part[2];
		}

		if ($wikiname != '')
			$this->_handle_wiki_attachment ($login, $projectid, $wikiname, $filename);
		else
			$this->_handle_issue_file ($login, $projectid, $issueid, $filename);
	}
}
