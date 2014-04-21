
function WsalLogRefresher(url, tkn){
	var WsalAjx = null;
	var WsalTkn = tkn;
	
	var WsalChk = function(url){
		if(WsalAjx)WsalAjx.abort();
		WsalAjx = jQuery.post(url + WsalTkn, function(data){
			WsalAjx = null;
			if(data && data !== 'false'){
				WsalTkn = data;
				jQuery('#audit-log-viewer').load(location.href + ' #audit-log-viewer');
			}
			WsalChk(url);
		});
	};
	
	setInterval(function(){ WsalChk(url); }, 40000);
	WsalChk(url);
}
