<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');?>

<script type="text/javascript">
	$(document).ready(function(){
		$('#submitDelete').click(function(){
			return confirm('<?php echo lang('confirm_delete_newsitem');?>');
		});
		
		$('#submitPost').click(function(){
			return confirm('<?php echo lang('confirm_post_newsitem');?>');
		});

		$('[rel=tooltip]').twipsy({
			animate: false,
			offset: 5,
			placement: 'right'
		});
		
		CKEDITOR.replace( 'content-textarea', {
			customConfig: '/application/config/ckeditor/news_messages.js'
		});
	});
</script>