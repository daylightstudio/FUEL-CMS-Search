<h1>Search Module Documentation</h1>
<p>This Search module documentation is for version <?=SEARCH_VERSION?>.</p>

<h2>Overview</h2>
<p>The Search module can be used to index the content of your site and provides views for you to customize for your own search result pages.</p>

<h2>Displaying Search Results</h2>
<p>After installing, copy over the <span class="file">fuel/modules/search/views/search.php</span> file to 
<span class="file">fuel/application/views/search.php</span>. Doing this allows you to make any necessary modifications to the view file
outside of the search module. It is also a good idea to copy over the <span class="file">fuel/modules/search/config/search.php</span> and place it 
<span class="file">fuel/application/config/search.php</span> so that you can add configuration settings without changing the search config file 
in the <span class="file">fuel/modules/search/config/search.php</span> folder.

<p>Aternatively, you can overwrite the <dfn>view</dfn> configuration value with an array syntax to point to a different module's view folder:</p>
<pre class="brush: php">
$config['search']['view'] = array('my_module' =>'search');
</pre>

<h3>Query Type</h3>
<p>You can specify several query types in the configuration which is used for searching the index table. 
Values can be either "like" which will do a %word% query, "match" which will use the MATCH / AGAINST syntax 
or "match boolean" which will do a match against in boolean mode. It is recommended that you use "match boolean" 
or "like" if you have a small number of records.</p>

<h3>sitemap.xml</h3>
<p>A search index creates a list of pages that can be easily used to generate a sitemap as well. To implement, create a route like so:</p>
<pre class="brush: php">
$route['sitemap.xml'] = 'search/sitemap';
</pre>


<h2>Indexing</h2>
<p>There are three different options when crawling a site and can be configured with the <dfn>index_method</dfn> configuration option:</p>
<ul>
	<li><strong>crawl</strong>: will scan the site for local links to index</li>
	<li><strong>sitemap</strong>: will use the sitemap.xml file if it exists</li>
	<li><strong>AUTO</strong>: will first check the sitemap.xml (because it's faster), then will default to the crawl</li>
</ul>

<h3>Excluding Pages</h3>
<p>Often times, there may be pages you want to exclude from the search index. You can use the <dfn>exclude</dfn> configuration parameter and
provide an array of page locations that you'd like to exclude. It excepts a similar syntax as CodeIgniter <a href="http://ellislab.com/codeigniter/user-guide/general/routing.html" target="_blank">routes</a> where you can use regular expression and
<dfn>:any</dfn> and <dfn>:num</dfn> for specifying a range of pages. The search module also honors pages specified in the robots.txt file.</p>

<h3>CLI</h3>
<p>Indexing the site can also be done via the CLI like so</p>
<pre class="brush: php">
&gt;php index.php fuel/tools/search/index_site
</pre>
<p class="important">If you run it via the CLI, you may need to change the search configurations <dfn>base_url</dfn> value by creating 
 a new configuration file at <span class="file">fuel/application/config/search.php</span> with the base URL value of your site:</p>

<pre class="brush: php">
...
$config['search']['base_url'] = 'http://localhost/';
</pre>

<h3>Using Delimiters</h3>
<p>You can specify different delimiters for the crawler to use when parsing information from the site. 
	To do so, create your own spefic configuration file at <span class="file">fuel/application/config/search.php</span> 
	if you haven't already and add one or more of the configuration values specified below to overwrite the defaults.
	The delimiters can be HTML tags or <a href="http://www.sitepoint.com/php-dom-using-xpath/" target="_blank">xpath</a>
	The default set is listed below. The first one, <dfn>delimiters</dfn>, is used to grab the general indexable content for the page.
	The second one, <dfn>title_page</dfn>, is used to grab the title associated with the search index.
	The third, <dfn>excerpt</dfn> is used for the search results page.
	The 4th, <dfn>language</dfn>, is the delimiter that is used for determining the language of the page with (multi-language sites):</p>

<pre class="brush: php">
...
// search page content delimiters. used for scraping page content. Can be an HTML node or xpath syntax (e.g. //div[@id="main"])
$config['search']['delimiters'] = array(
	'&lt;div id="main"&gt;', 
	'//meta[@name="keywords"]/@content',
);

// search page title tag (e.g. "title, h1")
$config['search']['title_tag'] = array('title', 'h1');

// search page for appropriate tag to save as excerpt tag (e.g. "p", "meta[@name="description"]/@content")
$config['search']['excerpt_tag'] = array('p', '//meta[@name="description"]/@content');

// search page for appropriate language value using the meta values or html lang attrubute (e.g. "p", "html[@lang]")
$config['search']['language_tag'] = array('html[@lang]/@lang');
</pre>

<p>To specify a new set of delimiters for your site, create a new configuration file at <span class="file">fuel/application/config/search.php</span> if you haven't already. This will overwrite any values
found in the <span class="file">fuel/modules/search/config/search.php</span>.
</p>

<h3>Hooks</h3>
<p>Indexing an entire site can be a time consuming process especially for bigger sites. To help with this issue, there is is a search <a href="<?=user_guide_url('modules/hooks')?>">module hook</a> that will run
on any module that has specified a <dfn>preview_path</dfn> after editing or creating a new module record. This will help keep your index up to date incrementally.
To incorporate, add the following to the <span class="file">fuel/application/config/hooks.php</span>:</p>

<pre class="brush: php">
// include hooks specific to FUEL
include(SEARCH_PATH.'config/search_hooks.php');
</pre>

<p class="important">TIP: The search module provides the the added bonus of sniffing out bad URLs.</p>




<?=generate_config_info()?>

<?=generate_toc()?>