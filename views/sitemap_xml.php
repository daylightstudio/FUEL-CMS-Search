<?php
$frequency = 'weekly';

if (empty($pages)) show_404();
header('Content-type: text/xml');
// needed because of the Loader class mistaking the end of the xml node as PHP in load->view
echo str_replace(';', '', '<?xml version="1.0" encoding="UTF-8"?>');
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
		xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
<?php foreach($pages as $page) : ?>
<?php if ($page != 'sitemap.xml') : ?> 
	<url>
		<loc><?=site_url($page)?></loc>
		<changefreq><?=$frequency?></changefreq>
	</url>	
<?php endif; ?>
<?php endforeach; ?>
</urlset>