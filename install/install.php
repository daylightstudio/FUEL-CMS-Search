<?php 
$config['name'] = 'Search Module';
$config['version'] = SEARCH_VERSION;
$config['author'] = 'David McReynolds';
$config['company'] = 'Daylight Studio';
$config['license'] = 'Apache 2';
$config['copyright'] = '2014';
$config['author_url'] = 'http://www.thedaylightstudio.com';
$config['description'] = 'The FUEL Search Module can be used create a search index to query against for your site.';
$config['compatibility'] = '1.0';
$config['instructions'] = '';
$config['permissions'] = array('tools/search' => 'Search');
$config['migration_version'] = 0;
$config['install_sql'] = 'fuel_search_install.sql';
$config['uninstall_sql'] = 'fuel_search_uninstall.sql';
$config['repo'] = 'git://github.com/daylightstudio/FUEL-CMS-Search-Module.git';