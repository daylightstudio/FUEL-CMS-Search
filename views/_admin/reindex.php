<div id="fuel_main_content_inner">

	<p class="instructions"><?=lang('search_reindex_instructions')?></p>
	
	<?=$form?>
	<br />
	<div class="loader hidden"></div>
	<div id="search_index_results"></div>
	
	<script type="text/javascript">
	//<![CDATA[
		$(function(){
			$('#Index').on('click', function(){
				$('.loader').show();
				var params = {pages: $('#pages').val() };
				$.post('<?=fuel_url('tools/search/index_site')?>', params, function(html){
					$('#search_index_results').html(html);
					$('.loader').hide();
				});
				return false;
			})
		})
	//]]>
	</script>
</div>