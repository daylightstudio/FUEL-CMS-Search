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
				var csrf = $('#<?php echo $this->config->item('csrf_token_name'); ?>').val();
				var params = {pages: $('#pages').val(), <?php echo $this->config->item('csrf_token_name'); ?>: csrf };
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