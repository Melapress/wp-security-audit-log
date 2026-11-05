/*
 * DarkTooltip v0.4.0
 * Simple customizable tooltip with confirm option and 3d effects
 * (c)2014 Rubén Torres - rubentdlh@gmail.com
 * Released under the MIT license
 */

(function($) {

	function DarkTooltip(element, options){
		this.bearer = element;
		this.options = options;
		this.hideEvent;
		this.mouseOverMode=(this.options.trigger == "hover" || this.options.trigger == "mouseover" || this.options.trigger == "onmouseover");
	}

	DarkTooltip.prototype = {

		show: function(){
			var dt = this;
			if(this.options.modal){
				this.modalLayer.css('display', 'block');
			}
			//Close all other tooltips
			this.tooltip.css('display', 'block');
			//Set event to prevent tooltip from closig when mouse is over the tooltip
			if(dt.mouseOverMode){
				this.tooltip.mouseover( function(){
					clearTimeout(dt.hideEvent);
				});
				this.tooltip.mouseout( function(){
					clearTimeout(dt.hideEvent);
					dt.hide();
				});
			}
			else {
				if (this.options.autoClose) {
					if (this.showEvent) {
						clearTimeout(dt.showEvent);
					}
					dt.showEvent = setTimeout(function(){
						dt.tooltip.hide();
					}, this.options.autoCloseDuration);
				}
			}
		},

		hide: function(){
			var dt=this;
			this.hideEvent = setTimeout( function(){
				dt.tooltip.hide();
			}, 100);
			if(dt.options.modal){
				dt.modalLayer.hide();
			}
			this.options.onClose();
		},

		toggle: function(){
			if(this.tooltip.is(":visible")){
				this.hide();
			}else{
				this.show();
			}
		},

		addAnimation: function(){
			switch(this.options.animation){
				case 'none':
					break;
				case 'fadeIn':
					this.tooltip.addClass('animated');
					this.tooltip.addClass('fadeIn');
					break;
				case 'flipIn':
					this.tooltip.addClass('animated');
					this.tooltip.addClass('flipIn');
					break;
			}
		},

		/**
		 * Rebuild tooltip content with text, bold labels and line breaks only.
		 * Parse in an inert template, then copy safe nodes without any attributes.
		 * Never serialize the result back to HTML or decode text a second time.
		 */
		createSafeContent: function( content ) {
			const template = document.createElement( 'template' );
			const fragment = document.createDocumentFragment();

			template.innerHTML = String( content ?? '' );

			const copyNodes = ( source, target ) => {
				source.childNodes.forEach( ( node ) => {
					if ( node.nodeType === Node.TEXT_NODE ) {
						target.appendChild( document.createTextNode( node.textContent ) );
					} else if ( node.nodeType === Node.ELEMENT_NODE ) {
						if ( node.nodeName === 'STRONG' || node.nodeName === 'BR' ) {
							const element = document.createElement( node.nodeName.toLowerCase() );

							copyNodes( node, element );
							target.appendChild( element );
						} else {
							target.appendChild( document.createTextNode( node.textContent ) );
						}
					}
				} );
			};

			copyNodes( template.content, fragment );

			return fragment;
		},

		setContent: function(){
			$(this.bearer).css('cursor', 'pointer');
			//Get tooltip content
			if(this.options.content){
				this.content = this.options.content;
			}else if(this.bearer.attr("data-darktooltip")){
				this.content = this.bearer.attr("data-darktooltip");
			}else{
				// console.log("No content for tooltip: " + this.bearer.selector);
				return;
			}
			if(this.content.charAt(0) == '#'){
				if (this.options.delete_content){
					const content = $(this.content).html();
					$(this.content).empty();
					this.content = content;
				}
				else{
					$(this.content).hide();
					this.content = $(this.content).html();
				}
				this.contentType='html';
			}else{
				this.contentType='text';
			}
			// Create containers without interpolating content, IDs or options into HTML.
			this.modalLayer = $( '<ins>' ).addClass( 'darktooltip-modal-layer' );
			this.tooltip = $( '<ins>' ).addClass( `dark-tooltip ${this.options.theme} ${this.options.size} ${this.options.gravity}` );

			const bearerId = this.bearer.attr( 'id' );

			if ( bearerId ) {
				this.tooltip.attr( 'id', `darktooltip-${bearerId}` );
			}

			this.tip = $( '<div>' ).addClass( 'tip' );
			this.tooltip.append( $( '<div>' ).append( this.createSafeContent( this.content ) ), this.tip );

			$("body").append(this.modalLayer);
			$("body").append(this.tooltip);

			//Adjust size for html tooltip
			if(this.contentType == 'html'){
				this.tooltip.css('max-width','none');
			}
			this.tooltip.css('opacity', this.options.opacity);
			this.addAnimation();
			if(this.options.confirm){
				this.addConfirm();
			}
		},

		setPositions: function(){
			var leftPos = this.bearer.offset().left;
			var topPos = this.bearer.offset().top;

			switch(this.options.gravity){
				case 'south':
					leftPos += this.bearer.outerWidth()/2 - this.tooltip.outerWidth()/2;
					topPos += -this.tooltip.outerHeight() - this.tip.outerHeight()/2;
					break;
				case 'west':
					leftPos += this.bearer.outerWidth() + this.tip.outerWidth()/2;
					topPos += this.bearer.outerHeight()/2 - (this.tooltip.outerHeight()/2);
					break;
				case 'north':
					leftPos += this.bearer.outerWidth()/2 - (this.tooltip.outerWidth()/2);
					topPos += this.bearer.outerHeight() + this.tip.outerHeight()/2;
					break;
				case 'east':
					leftPos += -this.tooltip.outerWidth() - this.tip.outerWidth()/2;
					topPos += this.bearer.outerHeight()/2 - this.tooltip.outerHeight()/2;
					break;
			}
			if(this.options.autoLeft){
				this.tooltip.css('left', leftPos);
			}
			if(this.options.autoTop){
				this.tooltip.css('top', topPos);
			}
		},

		setEvents: function(){
			var dt = this;
	 		var delay = dt.options.hoverDelay;
	  		var setTimeoutConst;
			if(dt.mouseOverMode){
				this.bearer.mouseenter( function(){
					//Timeout for hover mouse delay
					setTimeoutConst = setTimeout( function(){
						dt.setPositions();
						dt.show();
					}, delay);
				}).mouseleave( function(){
					clearTimeout(setTimeoutConst );
					dt.hide();
				});
			}else if(this.options.trigger == "click" || this.options.trigger == "onclick"){
				this.tooltip.click( function(e){
					e.stopPropagation();
				});
				this.bearer.click( function(e){
					e.preventDefault();
					dt.setPositions();
					dt.toggle();
					e.stopPropagation();
				});
				$('html').click(function(){
					dt.hide();
				})
			} else if(this.options.trigger == "show") {
				dt.setPositions();
				dt.toggle();
			}

			//$(document).bind("DOMNodeRemoved", function(e)
			//{
				if(dt.options.autoPosition) {
					const intervalID = setInterval(myCallback, 300);

					function myCallback() {
						dt.setPositions()
					}
				}
			//});
		},

		activate: function(){
			this.setContent();
			if(this.content){
				this.setEvents();
			}
		},

		addConfirm: function(){
			this.tooltip.append(
				$( '<ul>' ).addClass( 'confirm' ).append(
					$( '<li>' ).addClass( 'darktooltip-yes' ).text( this.options.yes ),
					$( '<li>' ).addClass( 'darktooltip-no' ).text( this.options.no )
				)
			);
			this.setConfirmEvents();
		},

		setConfirmEvents: function(){
			var dt = this;
			this.tooltip.find('li.darktooltip-yes').click( function(e){
				dt.onYes();
				e.stopPropagation();
			});
			this.tooltip.find('li.darktooltip-no').click( function(e){
				dt.onNo();
				e.stopPropagation();
			});
		},

		finalMessage: function(){
			if(this.options.finalMessage){
				this.tooltip.find( 'div:first' ).empty().append( this.createSafeContent( this.options.finalMessage ) );
				this.tooltip.find( 'ul' ).remove();
				this.setPositions();

				setTimeout( () => {
					this.hide();
					this.setContent();
				}, this.options.finalMessageDuration );
			}else{
				this.hide();
			}
		},

		onYes: function(){
			this.options.onYes(this.bearer, this);
			this.finalMessage();
		},

		onNo: function(){
			this.options.onNo(this.bearer);
			this.hide();
		}
	}

	$.fn.darkTooltip = function(options) {
		return this.each(function(){
			options = $.extend({}, $.fn.darkTooltip.defaults, options);
			var tooltip = new DarkTooltip($(this), options);
			tooltip.activate();
		});
	}

	$.fn.darkTooltip.defaults = {
		animation: 'none',
		confirm: false,
		content:'',
		finalMessage: '',
		finalMessageDuration: 1000,
		gravity: 'south',
		hoverDelay: 0,
		modal: false,
		no: 'No',
		onNo: function(){},
		onYes: function(){},
		opacity: 0.9,
		size: 'medium',
		theme: 'dark',
		trigger: 'hover',
		yes: 'Yes',
		autoTop: true,
		autoLeft: true,
		autoClose: false,
		autoCloseDuration: 3000,
		autoPosition: false,
		onClose: function(){}
	};

})(jQuery);