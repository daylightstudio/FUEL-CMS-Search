<h1>Search Module Documentation</h1>
<p>This Search module documentation is for version <?=SEARCH_VERSION?>.</p>

<h2>Overview</h2>
<p>The Search module can be used to index the content of your site and provides views for you to customize for your own search result pages.</p>

<p>Additionall, a route can be created to generate a sitemap based on the search index like so:</p>
<pre class="brush: php">
$route['sitemap.xml'] = 'search/sitemap';
</pre>

<?=generate_config_info()?>

<?=generate_toc()?>