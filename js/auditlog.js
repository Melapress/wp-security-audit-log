var WsalData;

function WsalAuditLogInit(_WsalData){
	WsalData = _WsalData;
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
}

var WsalIppsPrev;

function WsalIppsFocus(value){
	WsalIppsPrev = value;
}

function WsalIppsChange(value){
	if(value === ''){
		value = window.prompt(WsalData.tr8n.numofitems, WsalIppsPrev);
		if(value === null || value === WsalIppsPrev)return this.value = WsalIppsPrev; // operation canceled
	}
	jQuery('select.wsal-ipps').attr('disabled', true);
	jQuery.post(WsalData.ajaxurl, {
		action: 'AjaxSetIpp',
		count: value
	}, function(){
		location.reload();
	});
}

function WsalSsasChange(value){
	jQuery('select.wsal-ssas').attr('disabled', true);
	jQuery('#wsal-cbid').val(value);
	jQuery('#audit-log-viewer').submit();
}
