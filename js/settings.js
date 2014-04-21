jQuery(document).ready(function(){
	var RemoveSecToken = function(){
		var $this = jQuery(this).parents('span:first');
		$this.addClass('sectoken-del').fadeOut('fast', function(){
			$this.remove();
		});
	};
	
	jQuery('#ViewerQueryBox, #EditorQueryBox').keydown(function(event){
		if(event.keyCode === 13) {
			var type = jQuery(this).attr('id').substr(0, 6);
			jQuery('#'+type+'QueryAdd').click();
			return false;
		}
	});
	
	jQuery('#ViewerQueryAdd, #EditorQueryAdd').click(function(){
		var type = jQuery(this).attr('id').substr(0, 6);
		var value = jQuery.trim(jQuery('#'+type+'QueryBox').val());
		var existing = jQuery('#'+type+'List input').filter(function() { return this.value === value; });
		
		if(!value || existing.length)return; // if value is empty or already used, stop here
		
		jQuery('#'+type+'QueryBox, #'+type+'QueryAdd').attr('disabled', true);
		jQuery.post(jQuery('#ajaxurl').val(), {action: 'AjaxCheckSecurityToken', token: value}, function(data){
			jQuery('#'+type+'QueryBox, #'+type+'QueryAdd').attr('disabled', false);
			if(data==='other' && !confirm('The specified token is not a user nor a role, do you still want to add it?'))return;
			jQuery('#'+type+'QueryBox').val('');
			jQuery('#'+type+'List').append(jQuery('<span class="sectoken-'+data+'"/>').text(value).append(
				jQuery('<input type="hidden" name="'+type+'s[]"/>').val(value),
				jQuery('<a href="javascript:;" title="Remove">&times;</a>').click(RemoveSecToken)
			));
		});
	});
	
	jQuery('#ViewerList>span>a, #EditorList>span>a').click(RemoveSecToken);
});