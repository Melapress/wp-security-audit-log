
function WsalAuditLogInit(WsalData){
	var WsalTkn = WsalData.autorefresh.token;
	
	// list refresher
	var WsalAjx = null;
	var WsalChk = function(){
		if(WsalAjx)WsalAjx.abort();
		WsalAjx = jQuery.post(WsalData.ajaxurl, {
			action: 'AjaxRefresh',
			logcount: WsalTkn
		}, function(data){
			WsalAjx = null;
			if(data && data !== 'false'){
				WsalTkn = data;
				jQuery('#audit-log-viewer').load(location.href + ' #audit-log-viewer');
			}
			WsalChk();
		});
	};
	if(WsalData.autorefresh.enabled){
		setInterval(WsalChk, 40000);
		WsalChk();
	}
	
	var prev;
	jQuery('select.wsal-ipps')
		.focus(function(){
			prev = this.value;
		})
		.change(function(){
			var val = this.value;
			if(val===''){
				val = window.prompt(WsalData.tr8n.numofitems, prev);
				if(val === null || val === prev)return this.value = prev; // operation canceled
			}
			jQuery('select.wsal-ipps').attr('disabled', true);
			jQuery.post(WsalData.ajaxurl, {
				action: 'AjaxSetIpp',
				count: val
			}, function(){
				location.reload();
			});
		});
	
}

function WsalSsasChange(value){
	jQuery('#wsal-cbid').val(value);
	jQuery('#audit-log-viewer').submit();
}
