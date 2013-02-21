<?php
require_once(FUEL_PATH.'/controllers/module.php');

class Search_module extends Module {
	
	public $module = 'search'; // set here so the route can be tools/search
	protected $_is_cli = FALSE;
	
	function __construct()
	{
		// check if it is CLI or a web hook otherwise we need to validate
		$validate = (php_sapi_name() == 'cli' OR defined('STDIN')) ? FALSE : TRUE;
		parent::__construct($validate);

		// validate user has permission
		if ($validate)
		{
			$this->fuel->admin->check_login();
			$this->_validate_user('search');
		}
	}

	function reindex()
	{
		$crumbs = array('tools' => lang('section_tools'), 'tools/search' => lang('module_search'), 'Reindex');
		$this->fuel->admin->set_titlebar($crumbs, 'ico_tools_search');
		
		$vars = array();
		$this->fuel->admin->render('_admin/reindex', $vars, Fuel_admin::DISPLAY_DEFAULT, SEARCH_FOLDER);
		
	}
	
	function index_site()
	{
		$pages = $this->input->get_post('pages');
		$format = $this->input->get_post('output');
		
		if (!empty($pages))
		{
			if (!is_array($pages))
			{
				$pages = explode(',', $this->input->get_post('pages'));
			}
			$vars['crawled'] = $this->fuel->search->index($pages, 'pages', TRUE);
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