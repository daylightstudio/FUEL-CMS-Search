<?php
require_once(FUEL_PATH.'/controllers/module.php');

class Search_module extends Module {
	
	public $module = 'search'; // set here so the route can be tools/search
	
	function __construct()
	{
		// don't validate initially because we need to handle it a little different since we can use web hooks
		parent::__construct(FALSE);

		$remote_ips = $this->fuel->config('webhook_romote_ip');
		$is_web_hook = ($this->fuel->auth->check_valid_ip($remote_ips));

		// check if it is CLI or a web hook otherwise we need to validate
		$validate = (php_sapi_name() == 'cli' OR defined('STDIN') OR $is_web_hook) ? FALSE : TRUE;

		// validate user has permission
		if ($validate)
		{
			$this->_validate_user('search');

			// to display the logout button in the top right of the admin
			$load_vars['user'] = $this->fuel->auth->user_data();
			$this->load->vars($load_vars);
		}
	}

	function reindex()
	{
		// so tooltips will show up
		$this->js_controller_params['method'] = 'add_edit';

		$this->load->library('form_builder');
		$this->form_builder->load_custom_fields(APPPATH.'config/custom_fields.php');

		$fields = array();
		$vars = array();

		$fields['pages'] = array('type' => 'multi', 'options' => $this->fuel->pages->options_list('all', TRUE, FALSE));

		$this->form_builder->set_fields($fields);
		$this->form_builder->set_field_values($_POST);
		$this->form_builder->submit_value = 'Index';
		$vars['form'] = $this->form_builder->render();
		$vars['page_title'] = $this->fuel->admin->page_title(array(lang('module_search')), FALSE);
		$vars['form_action'] = fuel_url('tools/search/index_site');
	

		$crumbs = array('tools' => lang('section_tools'), 'tools/search' => lang('module_search'), 'Reindex');
		$this->fuel->admin->set_titlebar($crumbs, 'ico_tools_search');
		
		$vars['pages'] = $this->fuel->pages->options_list();
		$this->fuel->admin->render('_admin/reindex', $vars, Fuel_admin::DISPLAY_DEFAULT, SEARCH_FOLDER);
		
	}
	
	function index_site()
	{
		$pages = $this->input->get_post('pages');
		$format = $this->input->get_post('output');
		$clear = $this->input->get_post('clear');

		if (!empty($pages) AND $pages != 'null')
		{
			if (!is_array($pages))
			{
				$pages = explode(',', $this->input->get_post('pages'));
			}
			$vars['crawled'] = $this->fuel->search->index($pages, 'pages', $clear);
		}
		else
		{
			$vars['crawled'] = $this->fuel->search->index(FALSE, 'pages', TRUE);
		}

		if (!Fuel_search::is_cli())
		{
			if ($format == 'raw')
			{
				$output = $this->fuel->search->display_log('all', '', TRUE);
				$this->output->set_output($output);
			}
			else
			{
				$vars['log_msg'] = $this->fuel->search->display_log('all', 'span', TRUE);
				$this->load->module_view(SEARCH_FOLDER, '_admin/index_results', $vars);
			}
		}
		
	}
	
}
/* End of file backup.php */
/* Location: ./fuel/modules/backup/controllers/backup.php */