<?php
/*
|--------------------------------------------------------------------------
| FUEL NAVIGATION: An array of navigation items for the left menu
|--------------------------------------------------------------------------
*/
$config['nav']['tools']['tools/search'] = lang('module_search');


/*
|--------------------------------------------------------------------------
| TOOL SETTING: Search
|--------------------------------------------------------------------------
*/
$config['tables']['search'] = 'fuel_search';


$config['search'] = array();

// the base url in which to look. By default it will assume the same value as site_url()
$config['search']['base_url'] = '';

// whether to enable search indexing
$config['search']['indexing_enabled'] = TRUE;

// the user agent used when indexing
$config['search']['user_agent'] = 'FUEL';

// value can be either "like" which will do a %word% query, 
// "match" which will use the MATCH / AGAINST syntax 
// OR "match boolean" which will do a match against in boolean mode. 
// Use "match boolean" OR "like" if you have a small number of records.
$config['search']['query_type'] = 'match boolean';

// search page content delimiters. used for scraping page content. Can be an HTML node or xpath syntax (e.g. //div[@id="main"])
$config['search']['delimiters'] = array(
	'<div id="main">', 
	'//meta[@name="keywords"]/@content',
);

// search page title tag (e.g. "title, h1")
$config['search']['title_tag'] = array('title', 'h1');

// keep the inner HTML for the title tag (useful if you need to keep italics - for example <h1>Species = <i>Abra Alba</i></h1>
$config['search']['preserve_title_html'] = FALSE;  //set TRUE to keep HTML

// Ignore what the robots.txt says should not be indexed and do it anyway
$config['search']['ignore_robots'] = FALSE;  // set TRUE to ignore

// search page for appropriate tag to save as excerpt tag (e.g. "p", "meta[@name="description"]/@content")
$config['search']['excerpt_tag'] = array('p', '//meta[@name="description"]/@content');

// search page for appropriate language value using the meta values or html lang attrubute (e.g. "p", "html[@lang]")
$config['search']['language_tag'] = array('html[@lang]/@lang');

// the URI locations of pages to exclude from the index. 
// You can also add them to the "robots.txt" file for your site
$config['search']['exclude'] = array();

// can be AUTO, "crawl" or "sitemap"
// crawl, will scan the site for local links to index
// sitemap will use the sitemap if it exists
// AUTO will first check the sitemap (because it's faster), then will default to the crawl
$config['search']['index_method'] = 'crawl';

// whether to automatically index modules that have a preview_path specified. 
// Default is TRUE and will automatically do it for all modules. If an array
// is specified, then it will only index those in the array
$config['search']['index_modules'] = TRUE;

// the view file to use to display the results. An array can be used to point to a different module (e.g. array('my_module' => 'search'))
$config['search']['view'] = 'search';

// minimum length of the search term
$config['search']['min_length_search'] = 3;

// maximum search depth. 0 means there is no limit
$config['search']['depth'] = 0;

// use a temp table to store crawled information before switching to live table
$config['search']['use_tmp_table'] = TRUE;

// functions to call during the cleaning process of the content
$config['search']['cleaning_funcs'] = array('zap_gremlins');

// pagination
$config['search']['pagination'] = array(
		'per_page' => 10,
		'num_links' => 2,
		'prev_link' => lang('search_prev_page'),
		'next_link' => lang('search_next_page'),
		'first_link' => lang('search_first_link'),
		'last_link' => lang('search_last_link'),
	);


/* To allow users to modify under the settings section of the CMS, uncomment this area
$config['search']['settings'] = array();
$config['search']['settings']['user_agent'] = array();
$config['search']['settings']['delimiters'] = array('default' => '<div id="main">');
*/
$config['search']['settings']['query_type'] = array('type' => 'select', 'options' => array('match' => 'match', 'match boolean' => 'match boolean', 'like' => 'like'));