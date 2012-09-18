<h1>Search Results</h1>
<p><?=$count?> <?=pluralize($count, 'Result')?> for &ldquo;<?=$q?>&rdquo;</p>
<ul id="search_results">

<?php if (!empty($results)) : ?>

	<?php foreach($results as $result): ?>
	<li>
		<h3><a href="<?=$result->url?>"><?=highlight_phrase($result->title, $q, '<span class="search_highlight">', '</span>')?></a></h3>
		<p><?=highlight_phrase($result->excerpt, $q, '<span class="search_highlight">', '</span>')?></p>
		<a href="<?=$result->url?>" class="page_link"><?=$result->url?></a>
	</li>
	<?php endforeach; ?>

<?php else : ?>

	<li>
		<p>No search results found.</p>
	</li>

<?php endif; ?>

</ul>

<?=$pagination?>
