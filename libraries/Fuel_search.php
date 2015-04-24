<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * FUEL CMS
 * http://www.getfuelcms.com
 *
 * An open source Content Management System based on the 
 * Codeigniter framework (http://codeigniter.com)
 *
 * @package		FUEL CMS
 * @author		David McReynolds @ Daylight Studio
 * @copyright	Copyright (c) 2012, Run for Daylight LLC.
 * @license		http://docs.getfuelcms.com/general/license
 * @link		http://www.getfuelcms.com
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * FUEL search library
 * 
 * @package		FUEL CMS
 * @subpackage	Libraries
 * @category	Libraries
 * @author		David McReynolds @ Daylight Studio
 */

// --------------------------------------------------------------------

class Fuel_search extends Fuel_advanced_module {
	
	public $timeout = 20; // CURL timeout
	public $connect_timeout = 10; // CURL connection timeout
	public $title_limit = 100; // max character limit of the title of content
	public $q = ''; // search term
	public $auto_ignore = array('sitemap.xml', 'robots.txt', 'search'); // pages to ignore when determining if indexable
	public $depth = 0; // the depth in which to crawl
	public $base_url = ''; // the base URL value of where to pull page information from
	public $use_tmp_table = TRUE; // use a temp table while indexing results
	public static $crawled = array(); // used to capture crawled urls
	protected $_logs = array(); // log of items indexed
	
	const LOG_ERROR = 'error';
	const LOG_REMOVED = 'removed';
	const LOG_INDEXED = 'indexed';
	
	/**
	 * Constructor - Sets Fuel_search preferences and to any children
	 *
	 * The constructor can be passed an array of config values
	 */
	function __construct($params = array())
	{
		parent::__construct();

		if (empty($params))
		{
			$params['name'] = 'search';
		}
		$this->initialize($params);
		$this->load_model('search');
		$this->CI->load->library('curl');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Query the database
	 *
	 * @access	public
	 * @param	string
	 * @param	int
	 * @param	int
	 * @param	int
	 * @return	array
	 */	
	function query($q = '', $limit = 100, $offset = 0, $excerpt_limit = 200)
	{
		$results = $this->CI->search_model->find_by_keyword($q, $limit, $offset, $excerpt_limit);
//		$this->CI->search_model->debug_query();
		$this->q = $q;
		return $results;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get the count of the returned rows. If no parameter is passed then it will assume the last query.
	 *
	 * @access	public
	 * @param	string
	 * @return	array
	 */	
	function count($q = '')
	{
		if (empty($q))
		{
			$q = $this->q;
		}
		$count = $this->CI->search_model->find_by_keyword_count($q);
		return $count;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Hook for updating an index item after a module save
	 * 
	 * Used when something is saved in admin to automatically update the index
	 *
	 * @access	public
	 * @param	object	search record
	 * @return	boolean
	 */	
	function after_save_hook($posted)
	{
		// if indexing is disabled, then we just return and don't continue'
		if (!$this->config('indexing_enabled')) return;
		
		// grab the config values for what should be indexed on save
		$index_modules = $this->config('index_modules');
		$module = $this->CI->module;

		// check if modules can be indexed. If an array is provided, then we only index those in the array
		if (($index_modules === TRUE OR (is_array($index_modules) AND isset($index_modules[$module]))) AND $module != 'search')
		{
			$module_obj = $this->CI->fuel->modules->get($module, FALSE);
			$key_field = $module_obj->model()->key_field();
			
			if (!isset($posted[$key_field]))
			{
				return FALSE;
			}
			
			$data = $module_obj->model()->find_by_key($posted[$key_field], 'array');
			$location = $module_obj->url($data);
			
			if (!empty($location))
			{
				// now index the page... this takes too long
				if (is_ajax())
				{
					if (!$this->index_page($location))
					{
						$this->remove($location);
					}
				}
				else
				{
					// use ajax to speed things up
					$output = lang('data_saved').'{script}
						$(function(){
							$.get("'.fuel_url('tools/search/index_site').'?pages='.$location.'&format=raw")
						});
					{/script}';
					$this->fuel->admin->set_notification($output, Fuel_admin::NOTIFICATION_SUCCESS);
				}
			}
		}
	}	

	// --------------------------------------------------------------------
	
	/**
	 * Hook for removing an index item after a module save
	 * 
	 * Used when something is deleted in admin to automatically remove from the index
	 *
	 * @access	public
	 * @param	object	search record
	 * @return	boolean
	 */	
	function before_delete_hook($posted)
	{
		// grab the config values for what should be deleted
		$index_modules = $this->config('index_modules');
		$module = $this->CI->module;

		// check if modules can be indexed. If an array is provided, then we only delete those in the array
		if (($index_modules === TRUE OR (is_array($index_modules) AND isset($index_modules[$module])))  AND $module != 'search')
		{
			$module_obj = $this->CI->fuel->modules->get($module, FALSE);
			if (is_array($posted))
			{
				foreach($posted as $key => $val)
				{
					if (is_int($key))
					{
						$data = $module_obj->model()->find_by_key($val, 'array');
						$location = $module_obj->url($data);
						if (!empty($location))
						{
							$this->remove($location);
						}
					}
				}
			}
		}
	}	

	// --------------------------------------------------------------------
	
	/**
	 * Indexes the data for the search
	 *
	 * @access	public
	 * @return	mixed
	 */	
	function index($pages = array(), $scope = 'pages', $clear_all = FALSE)
	{
		// check if indexing is enabled first
		if ($this->config('indexing_enabled'))
		{

			$orig_table_name = $this->CI->search_model->table_name();

			// clear out the entire index
			if ($clear_all)
			{
				if (!$this->use_tmp_table)
				{
					$this->clear_all();
				}

				// set to temp table if TRUE and if $clear_all is set to TRUE so as to make the indexing not appear broken while indexing
				if ($this->use_tmp_table)
				{
					$this->create_temp_table();
					$this->CI->search_model->set_table($this->temp_table_name());
				}
			}
			
			$indexed = FALSE;

			if (empty($pages))
			{
				
				// if no pages provided, we load them all
				$index_method = $this->config('index_method');

				// figure out where to get the pages
				if ($index_method == 'sitemap')
				{
					$pages = $this->sitemap_pages();
				}
				else if ($index_method == 'crawl')
				{

					// if we crawl the page, then we automatically will save it's contents to save on CURL requests'
					$pages = $this->crawl_pages();
					$indexed = TRUE;
				}
				
				// default will check if there is a sitemap, and if not, will crawl
				else
				{
					$pages = $this->sitemap_pages();

					if (empty($pages))
					{
						$pages = $this->crawl_pages();
						$indexed = TRUE;
					}
				}
			}

			if (!$indexed)
			{
				$pages = (array) $pages;
				
				// render the pages then look for delimiters within to get the content
				foreach($pages as $location)
				{
					// find indexable content in the html and create the index in the database
					if (!$this->index_page($location))
					{
						// if not indexed then remove it if it exists
						$this->remove($location);
					}
				}
			}

			// set to temp table if TRUE and if $clear_all is set to TRUE so as to make the indexing not appear broken while indexing
			if ($this->use_tmp_table AND $clear_all AND !empty($pages))
			{
				$this->CI->search_model->set_table($orig_table_name);
				$this->switch_from_temp_table();
			}

			return $pages;
			
		}
		
		// if indexing isn't enabled, we'll add it to the errors list
		else
		{
			$this->_add_error(lang('search_indexing_disabled'));
			return FALSE;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Checks to see if the page is indexable
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 */	
	function is_indexable($location)
	{
		// sitemap.xml and robots.txt locations are automatically ignored
		if (in_array($location, $this->auto_ignore) OR !$this->is_local_url($location))
		{
			return FALSE;
		}
		
		// get pages to exclude
		$exclude = (array)$this->config('exclude');
		
		if (!empty($exclude))
		{
			// loop through the exclude array looking for wild-cards
			foreach ($exclude as $val)
			{
				// convert wild-cards to RegEx
				$val = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $val));
	
				// does the RegEx match? If so, it's not indexable'
				if (preg_match('#^'.$val.'$#', $location))
				{
					return FALSE;
				}
			}
		}
		
		// now check against the robots.txt
		if (!$this->check_robots_txt($location))
		{
			return FALSE;
		}
		return TRUE;
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Crawls the site for locations to be indexed
	 *
	 * @access	public
	 * @param	string
	 * @param	boolean
	 * @param	boolean
	 * @return	array
	 */	
	function crawl_pages($location = 'home', $index_content = TRUE, $depth = 0, $parent = NULL)
	{

		// start at the homepage if no root page is specified
		if (empty($location) OR $location == 'home')
		{
			$location = $this->site_url();
		}
		
		$html = '';
		
		// grab the HTML of the page to get all the links
		if ($this->is_local_url($location) AND $this->is_indexable($location))
		{
			$html = $this->scrape_page($location, FALSE, $parent);
		}
		
		// index the content at the same time to save on CURL bandwidth
		if (!empty($html))
		{
			$indexed = FALSE;
			
			if ($index_content)
			{
				$url = $this->get_location($location);
				if (!isset(self::$crawled[$url]))
				{
					$indexed = $this->index_page($url, $html, $parent);
					self::$crawled[$url] = $url;
				}
			}
		
			// the page must be properly indexed above to continue on and not contain a no follow meta tag
			if ($indexed AND !preg_match('#<head>.*<meta[^>]+name=([\'"])robots\\1[^>]+content=([\'"]).*nofollow.*\\2#Uims', $html))
			{
				// grab all the page links
				preg_match_all("/<a(?:[^>]*)href=([\'\"])([^\\1]*)(\\1)(?:[^>]*)>/Uis", $html, $matches);

				unset($html);
				if (!empty($matches[2]))
				{
					$depth++;
					foreach($matches[2] as $key => $url)
					{
						if (!preg_match('/nofollow=([\'\"])\w+/Uis', $matches[0][$key]))
						{
							// remove page anchors
							$url_arr = explode('#', $url);
							$url = $this->get_location($url_arr[0], $location);

							// check if the url is local AND whether it has already been indexed
							if (!isset(self::$crawled[$url]))
							{
								// now recursively crawl
								$config_depth = (int) $this->config('depth');
								if ($config_depth === 0 OR (is_int($depth) AND $depth < $config_depth))
								{
									$this->crawl_pages($url, $index_content = TRUE, $depth, $location, $parent);	
								}
								
								// add the url in the indexed array
								self::$crawled[$url] = $url;

							}
						}
					}
				}

				unset($matches);
			}
		}
		return array_values(self::$crawled);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Indexes a single page
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	array
	 */	
	function index_page($location, $html = NULL, $parent = NULL)
	{
		// check if the page is indexable before continuing
		if (!$this->is_indexable($location))
		{
			return FALSE;
		}
		
		// get the page HTML need to use CURL instead of render_page because one show_404 error will halt the indexing
		if (empty($html))
		{
			$html = $this->scrape_page($location, FALSE, $parent);

			if (!$html)
			{
				return FALSE;
			}
		}

		// check that there is not a noindex value in the head (will not check if "name" comes after "content"...)
		if ( preg_match('#<head>.*<meta[^>]+name=([\'"])robots\\1[^>]+content=([\'"]).*noindex.*\\2#Uims', $html))
		{
			return FALSE;
		}

		// get the proper scope for the page
		$scope = $this->get_location_scope($location);
		
		// get the xpath object so we can query the content of the page
		$xpath = $this->page_xpath($html);
		
		// get the content
		$content = $this->find_indexable_content($xpath);
		
		// get the title
		$title = $this->find_page_title($xpath);

		// get the excerpt
		$excerpt = $this->find_excerpt($xpath);

		// get the excerpt
		$language = $this->find_language($xpath);

		// create search record
		if (!empty($content) AND !empty($title))
		{
			$rec = array(
				'location' => $location,
				'scope'    => $scope,
				'title'    => $title,
				'content'  => $content,
				'excerpt'  => $excerpt,
				'language'  => $language,
				);

			if (!$this->create($rec))
			{
				$this->_add_error(lang('search_error_saving'));
				return FALSE;
			}
		}
		return TRUE;
		
	}

	// --------------------------------------------------------------------
	
	/**
	 * Parses the sitemap.xml file of the site and returns an array of pages to index
	 *
	 * @access	public
	 * @return	array
	 */	
	function sitemap_pages()
	{
		$locations = array();

		$sitemap_xml = $this->scrape_page('sitemap.xml');
		if (!$sitemap_xml)
		{
			return FALSE;
		}
		
		$dom = new DOMDocument; 
		$dom->preserveWhiteSpace = FALSE;
		
		// remove the opening xml tag to prevent parsing issues
		$sitemap_xml = preg_replace('#<\?xml.+\?>#U', '', $sitemap_xml);

		@$dom->loadXML($sitemap_xml); 
		$locs = $dom->getElementsByTagName('loc');
		
		$site_url = $this->site_url();
		foreach($locs as $node)
		{
			$loc = (string) $node->nodeValue;
			$locations[] = $loc;
		}
		return $locations;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Parses the sitemap.xml file of the site and returns an array of pages to index
	 *
	 * @access	public
	 * @param	string
	 * @return	boolean
	 */	
	function check_robots_txt($location)
	{
		if ($this->config('ignore_robots') == FALSE)
		{
			 // static so we only do it once per execution
			static $robot_txt;
			static $disallow;

			// if no robots.txt then we just return TRUE
			if ($robot_txt === FALSE) return TRUE;

			if (empty($robot_txt))
			{
				$robot_txt = $this->site_url('robots.txt');

				// we will scrape the page instead of file_get_contents because the page may be dynamically generated by FUEL
				$robot_txt = $this->scrape_page($robot_txt);
				if (!$robot_txt)
				{
					// again... no robots.txt, return TRUE
					return TRUE;
				}
			}

			if (empty($disallow))
			{
				$disallow = array();
				$lines = explode("\n", $robot_txt);
				$check = FALSE;
				foreach($lines as $l)
				{
					// # symbol is for comments in regex in case you were wondering
					if (preg_match('/^user-agent:([^#]+)/i', $l, $matches1))
					{
						$agent = trim($matches1[1]);
						$check = ($agent == '*' OR $agent == $this->config('user_agent')) ? TRUE : FALSE;
					}

					// check disallow
					if ($check AND preg_match('/disallow:([^#]+)/i', $l, $matches2))
					{
						$dis = trim($matches2[1]);
						if ($dis != '')
						{
							$disallow[] = $dis;
						}
					}
				}
			}

			// loop through the disallow and if it matches the location value, then we return FALSE
			foreach($disallow as $d)
			{
				$d = ltrim($d, '/'); // remove begining slash
				$d = str_replace('*', '__', $d); // escape wildcards with a character that won't be escaped by preg_quote'
				$d = preg_quote($d); // escape special regex characters (like periods)
				$d = str_replace('__', '.*', $d); // convert "__" (transformed from wildcard) to regex .* (0 or more of anything)
				if ($d == '/')
				{
					$d = '.*';
				}
				if (preg_match('#'.$d.'#', $location))
				{
					return FALSE;
				}
			}
			return TRUE;
		}
		else
		{
			return TRUE;
		}

	}
	
	// --------------------------------------------------------------------
	
	/**
	 * CURLs the page and gets the content
	 *
	 * @access	public
	 * @param	string
	 * @param	boolean
	 * @return	string
	 */	
	function scrape_page($url, $just_header = FALSE, $parent = NULL)
	{
		if (!is_http_path($url))
		{
			$url = $this->site_url($url);
		}

		$this->CI->curl->initialize();

		$opts = array(
						CURLOPT_URL => $url,
						CURLOPT_RETURNTRANSFER => TRUE,
						CURLOPT_TIMEOUT => $this->timeout,
						CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
						CURLOPT_BINARYTRANSFER => TRUE,
						CURLOPT_USERAGENT => $this->config('user_agent'),
					);
		if ($just_header)
		{
			$opts[CURLOPT_HEADER] = TRUE;
			$opts[CURLOPT_NOBODY] = TRUE;
		}
		else
		{
			$opts[CURLOPT_HEADER] = FALSE;
			$opts[CURLOPT_NOBODY] = FALSE;
		}

		// add a CURL session
		$this->CI->curl->add_session($url, $opts);
		
		// execut the CURL request to scrape the page
		$output = $this->CI->curl->exec();

		// get any errors
		$error = $this->CI->curl->error();
		if (!empty($error))
		{
			$this->_add_error($error);
		}
		
		// if the page doesn't return a 200 status, we don't scrape
		$http_code = $this->CI->curl->info('http_code');
		
		if ($http_code >= 400)
		{
			$m = 'HTTP Code '.$http_code.' for <a href="'.$this->site_url($url).'" target="_blank">'.$url.'</a>';
			if (!empty($parent))
			{
				$m .= ' found in <a href="'.$this->site_url($parent).'" target="_blank">'.$parent.'</a>';
			}
			$msg = lang('search_log_index_page_error', $m);
			$this->log_message($msg, self::LOG_ERROR);
			$this->_add_error($msg);
			return FALSE;
		}
		
		// remove javascript
		$output = strip_javascript($output);
		
		return $output;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the DomXpath 
	 *
	 * @access	public
	 * @param	string	page content to search
	 * @param	string
	 * @return	array
	 */	
	function page_xpath($content, $type = 'html')
	{
		// turn off errors for loading HTML into dom
		$old_setting = libxml_use_internal_errors(TRUE); 
		libxml_clear_errors();
		
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = FALSE;
		$dom->substituteEntities = FALSE;

		$content = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");

		if ($type == 'html')
		{
			$loaded = @$dom->loadHTML($content);
		}
		else
		{
			$loaded = $dom->loadXML($content);
		}
		if (!$loaded)
		{
			$this->_add_error(lang('search_error_parsing_content'));
		}
		
		// change errors back to original settings
		libxml_clear_errors();
		libxml_use_internal_errors($old_setting); 
		
		// create xpath object to do some querying
		$xpath = new DOMXPath($dom);
		return $xpath;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Searches for index related content within html content
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	 */	
	function find_indexable_content($xpath)
	{
		$content = '';
		
		// get delimiters
		$delimiters = $this->config('delimiters');

		if ( ! is_array($delimiters)) $delimiters = array($delimiters);

		foreach($delimiters as $d)
		{
			// get the xpath equation for querying if it is not already in xpath format
			$query = $d;
			if (preg_match('#^<.+>#', $query, $matches))
			{
				$query = $this->get_xpath_from_node($query);
			}

			$results = $xpath->query($query);
			if (!empty($results))
			{
				$content_arr = array();
				foreach($results as $r)
				{
					$val = (string)$r->nodeValue;

					// using DOM has added benefit of stripping tags out!
					if (!empty($val))
					{
						$content_arr[] = $val;
					}
				}
				$content .= implode('|', $content_arr);
			}
		}
		return $content;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Searches for title
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	 */	
	function find_page_title($xpath)
	{
		//return $this->_find_tag($xpath, $this->config('title_tag'));
		if ($this->config('preserve_title_html'))
		{
			$t = $this->_find_title_tag($xpath, $this->config('title_tag'));
		}
		else
		{
			$t = $this->_find_tag($xpath, $this->config('title_tag'));
		}

		return $t;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Searches for excerpt
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	 */	
	function find_excerpt($xpath)
	{
		return $this->_find_tag($xpath, $this->config('excerpt_tag'));

	}

	// --------------------------------------------------------------------
	
	/**
	 * Searches for language value
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	 */	
	function find_language($xpath)
	{
		return $this->_find_tag($xpath, $this->config('language_tag'));

	}

	// --------------------------------------------------------------------
	
	/**
	 * Searches for title
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	 */	
	function _find_tag($xpath, $tags)
	{
		if (is_string($tags))
		{
			$tags = preg_split('#,\s*#', $tags);
		}
			
		foreach ($tags as $tag)
		{
			// get the xpath equation for querying if it is not already in xpath format
			if (preg_match('#^<.+>#', $tag, $matches))
			{
				$tag = $this->get_xpath_from_node($tag);
			}

			// get the h1 value for the title
			$tag_results = $xpath->query('//'.$tag);
			
			if ($tag_results->length)
			{
				foreach($tag_results as $t)
				{
					$value = (string) $t->nodeValue;
					return $value;
				}
			}
			
		}
		
		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Searches for title preserving inner tags
	 *
	 * @access	public
	 * @param	object	DOMXpath
	 * @return	array
	*/
	function _find_title_tag($xpath, $tags)
	{
		if (is_string($tags))
		{
			$tags = preg_split('#,\s*#', $tags);
		}

		foreach ($tags as $tag)
		{
			// get the xpath equation for querying if it is not already in xpath format
			if (preg_match('#^<.+>#', $tag, $matches))
			{
				$tag = $this->get_xpath_from_node($tag);
			}

			// get the h1 value for the title
			$tag_results = $xpath->query('//'.$tag);

			if ($tag_results->length)
			{
				foreach($tag_results as $t)
				{
					//$value = (string) $t->nodeValue;
					$innerHTML = '';
					$children = $t->childNodes;
					foreach ($children as $child)
					{
						$innerHTML .= $child->ownerDocument->saveXML( $child );
					}
					return $innerHTML;
				}
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the xpath syntax based on the node string (e.g. <div id="main">)
	 *
	 * @access	public
	 * @param	string	node string
	 * @return	string
	 */	
	function get_xpath_from_node($node)
	{
		$node_trimmed = trim($node, '<>');
		$node_pieces = preg_split('#\s#', $node_trimmed);
		$xpath_str = '//'.$node_pieces[0];
		if (count($node_pieces) > 1)
		{
			for($i = 1; $i < count($node_pieces); $i++)
			{
				$xpath_str .= '[@'.$node_pieces[$i].']';
			}
		}
		return $xpath_str;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns scope based on the url by looking at the preview paths
	 *
	 * @access	public
	 * @param	string	url
	 * @return	string
	 */	
	function get_location_scope($location)
	{
		static $preview_paths;
		
		if (is_null($preview_paths))
		{
			// get all the preview paths
			$modules = $this->CI->fuel->modules->get();
			foreach($modules as $mod => $module)
			{
				//$info = $this->CI->fuel_modules->info($mod);
				$info = $module->info($mod);
				if (!empty($info['preview_path']))
				{
					$preview_paths[$mod] = $info['preview_path'];
				}
			}
		}
		
		if (is_array($preview_paths))
		{
			foreach($preview_paths as $mod => $path)
			{
				// ignore the pages preview path which will be assigned by default if no matches
				if ($path != '{location}')
				{
					$location = $this->get_location($location);
					$path_regex = preg_replace('#(.+/)\{.+\}(.*)#', '$1.+$2', $path);
					if (preg_match('#'.$path_regex.'#', $location))
					{
						return $mod;
					}
				}
			}
		}
		return 'pages';
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Creates a record in the search table. Will overwrite if the same location/model exist
	 *
	 * @access	public
	 * @param	mixed	can be a string or an array. If an array, it must contain the other values
	 * @param	string
	 * @param	string
	 * @return	boolean
	 */	
	function create($location, $content = NULL, $title = NULL, $excerpt = NULL, $scope = 'page')
	{
		$values = array();
		if (!is_array($location))
		{
			$values['location'] = $location;
			$values['scope'] = $scope;
			$values['title'] = $title;
			$values['content'] = $content;
			$values['excerpt'] = $excerpt;
		}
		else
		{
			$values = $location;
		}
		$values['location'] = $this->get_location($values['location']);
		if (!$this->config('preserve_title_html'))
		{
			$values['title'] = $this->format_title($values['title']);
		}
		$values['content'] = $this->clean($values['content'], false);
		$values['excerpt'] = $this->clean($values['excerpt'], false);
		$values['language'] = $this->clean($values['language']);
		if (empty($values['location']))
		{
			$values['location'] = 'home';
		}
		// do some checks here first to make sure it is valid content
		if (!$this->is_local_url($values['location']) OR !isset($values['content']) OR !isset($values['title']) OR !isset($values['excerpt']))
		{
			return FALSE;
		}

		$saved = $this->CI->search_model->save($values);
		if ($saved)
		{
			$msg = lang('search_log_index_created', '<a href="'.$this->site_url($values['location']).'" target="_blank">'.$values['location'].'</a>');
			$this->log_message($msg, self::LOG_INDEXED);
			return TRUE;
		}
		else if ($this->CI->search_model->has_error())
		{
			$msg = $this->CI->search_model->get_validation()->get_last_error().' - '.'<a href="'.$this->site_url($values['location']).'" target="_blank">'.$values['location'].'</a>';
			$this->log_message($msg, self::LOG_ERROR);
		}
		return FALSE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Removes a record in the search table
	 *
	 * @access	public
	 * @param	string	location
	 * @param	string	scope
	 * @return	boolean
	 */	
	function remove($location, $scope = NULL)
	{
		$location = $this->get_location($location);
		
		$where['location'] = $location;
		if (!empty($scope))
		{
			$where['scope'] = $scope;
		}
		$deleted = $this->CI->search_model->delete($where);
		
		if ($deleted)
		{
			$msg = lang('search_log_index_removed', '<a href="'.$this->site_url($location).'" target="_blank">'.$location.'</a>');
			$this->log_message($msg, self::LOG_REMOVED);
			return TRUE;
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Clears the entire search index
	 *
	 * @access	public
	 * @return	void
	 */	
	function clear_all()
	{
		$this->CI->search_model->truncate();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Cleans content to make it more searchable. 
	 * 
	 * The find_indexable_content already cleans up content in most cases
	 *
	 * @access	public
	 * @param	string	HTML content to clean for search index
	 * @return	boolean
	 */	
	function clean($content, $encode = true)
	{
		global $UNI;
		$content = $UNI->clean_string($content); // convert to UTF-8

		$cleaning_funcs = (array) $this->config('cleaning_funcs');
		foreach($cleaning_funcs as $key => $func)
		{
			if (!is_numeric($key))
			{
				$params = $func;
				$func = $key;
			}
			else
			{
				$params = array();
			}
			array_unshift($params, $content);
			$content = call_user_func_array($func, $params);
		}
		
		if ($encode)
		{
			$content = safe_htmlentities($content);	
		}
		else
		{
			// decode HTML entities so that they can be searched	
			$content = html_entity_decode($content);
		}
		
		$content = strip_tags($content);
		$content = trim(preg_replace('#(\s)\s+|(\n)\n+|(\r)\r+#m', '$1', $content));
		$content = trim($content);
		return $content;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Cleans and truncates the title if it's too long
	 * 
	 * @access	public
	 * @param	string	title
	 * @return	boolean
	 */	
	function format_title($title)
	{
		$title = character_limiter($this->clean($title), $this->title_limit);
		return $title;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Determines whether the url is local to the site or not
	 * 
	 * @access	public
	 * @param	string	url
	 * @return	boolean
	 */	
	function is_local_url($url)
	{
		if (!$this->is_normal_url($url))
		{
			return FALSE;
		}
		if (is_http_path($url))
		{
			return preg_match('#^'.preg_quote($this->site_url()).'#', $url);
		}
		else
		{
			return TRUE;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Determines whether the url contains a normal link
	 * 
	 * @access	public
	 * @param	string	url
	 * @return	boolean
	 */	
	function is_normal_url($url)
	{
		return !(strncasecmp($url, 'mailto:', 7) === 0 OR strncasecmp($url, 'tel:', 4) === 0 OR substr($url, 0, 1) == '#' OR strncasecmp($url, 'javascript:', 11) === 0);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Return the URI part of a URL
	 * 
	 * @access	public
	 * @param	string	url
	 * @param	string	relative to the page (optional)
	 * @return	string
	 */	
	
	function get_location($url, $relative = NULL)
	{
		// if it's determined to be a relative path... we tack it on to the relative
		if (!empty($url) AND !is_http_path($url) AND $this->is_normal_url($url) AND substr($url, 0, 1) != '/' AND !empty($relative))
		{
			$relative_parts = explode('/', $relative);
			array_pop($relative_parts);
			$relative = implode('/', $relative_parts);
			$url = $relative.'/'.$url;
		}

		$url = str_replace($this->site_url(), '', $url);

		// remove web path as well
		$web_path = trim(WEB_PATH, '/');
		$url = trim($url, '/');
		$url = preg_replace('#^'.$web_path.'/#', '', $url);

		// to help with multi language sites
		$url = str_replace('?lang='.$this->fuel->language->default_option(), '', $url);
		return $url;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Adds an item to the index log
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @param	string	Log message
	 * @param	string	Type of log message
	 * @return	void
	 */	
	function log_message($msg, $type = self::LOG_ERROR)
	{
		if (Fuel_search::is_cli())
		{
			$msg = strip_tags($msg);
			echo $msg."\n";
		}
		$this->_logs[$type][] = $msg;	
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of log messages
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @return	array
	 */	
	function logs()
	{
		return $this->_logs;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds an item to the index log
	 * 
	 * Used when printing out the index log informaiton
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	boolean
	 * @return	string
	 */	
	function display_log($type = 'all', $tag = 'span', $return = FALSE)
	{
		$str = '';
		$types = array(self::LOG_ERROR, self::LOG_REMOVED, self::LOG_INDEXED);
		
		if (is_string($type))
		{
			if (empty($type) OR !in_array($type, $types))
			{
				$type = $types;
			}
			else
			{
				$type = (array) $type;
			}
		}
		foreach($types as $t)
		{
			if (isset($this->_logs[$t]))
			{
				foreach($this->_logs[$t] as $l)
				{
					if (!empty($tag))
					{
						$str .= '<'.$tag.' class="'.$t.'">';
					}
					$str .= $l."\n";
					if (!empty($tag))
					{
						$str .= '</'.$tag.'>';
					}
				}
				if (!$return)
				{
					echo $str;
				}
			}
		}
		
		return $str;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Generates the proper URL taking into account in base_url value specified
	 * 
	 * @access	public
	 * @param	string
	 * @return	string
	 */	
	function site_url($url = '')
	{
		$base_url = $this->config('base_url');
		
		if (!empty($base_url))
		{
			$url = trim($url, '/');
			$base_url = rtrim($base_url, '/');
			$url = $base_url.'/'.$url;
		}
		else
		{
			$url = site_url($url);
		}
		return $url;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Creates a temp table to store results
	 * 
	 * @access	public
	 * @return	void
	 */	
	function create_temp_table()
	{
		$this->CI->load->dbforge();

		$tmp_table_name = $this->temp_table_name();

		// drop temp table
		$this->CI->dbforge->drop_table($tmp_table_name);

		$install_path = SEARCH_PATH.'install/fuel_search_install.sql';
		$install_sql = file_get_contents($install_path);

		$search_table = $this->CI->search_model->table_name();
		$install_sql = str_replace('`'.$search_table.'`', '`'.$tmp_table_name.'`', $install_sql);
		$this->CI->db->load_sql($install_sql);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns temp table name
	 * 
	 * @access	public
	 * @return	string
	 */	
	function temp_table_name()
	{
		return $this->CI->search_model->table_name().'_tmp';
	}

	// --------------------------------------------------------------------
	
	/**
	 * Drops the temp table
	 * 
	 * @access	public
	 * @return	void
	 */	
	function switch_from_temp_table()
	{
		$this->CI->load->dbforge();

		// rename current table to backup
		$search_table = $this->CI->search_model->table_name();
		$new_table_bak = $this->CI->search_model->table_name().'_bak';
		$tmp_table_name = $this->temp_table_name();
		$this->CI->dbforge->rename_table($search_table, $new_table_bak);

		// rename temp table to new table
		$this->CI->dbforge->rename_table($tmp_table_name, $search_table);

		// drop backup table
		$this->CI->dbforge->drop_table($new_table_bak);

		// drop temp table
		$this->CI->dbforge->drop_table($tmp_table_name);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Convenience static method for determining whether the script is being run via CLI
	 * 
	 * @access	public
	 * @return	boolean
	 */	
	static public function is_cli()
	{
		$is_cli = (php_sapi_name() == 'cli' or defined('STDIN')) ? TRUE : FALSE;
		return $is_cli;
	}
}

/* End of file Fuel_search.php */
/* Location: ./modules/search/libraries/Fuel_search.php */