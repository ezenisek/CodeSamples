//jQuery UI Stickies
//@VERSION 0.0.5

(function( factory ) {
	if ( typeof define === "function" && define.amd ) {

		// AMD. Register as an anonymous module.
		define([
			"jquery",
			"./core",
			"./widget",
			"./draggable",
			"./mouse",
			"./position",
			"./resizable"
		], factory );
	} else {

		// Browser globals
		factory( jQuery );
	}
}(function($) {
	
	return $.widget("ot.stickies", {
		options: {
			pinned: 0,
			locked: 0,
			shared: 0,
			stickyid: 0,
			width:200,
			height:150,
			ajaxurl: false,
			action: 'create',
			sendData: false,
			top:32,
			left:32,
			content:'',
			position: {
				my: "center top",
				at: "center top+150",
				of: window,
				collision: "fit",
				// Ensure the titlebar is always visible
				using: function( pos ) {
					var topOffset = $( this ).css( pos ).offset().top;
					if ( topOffset < 0 ) {
						$( this ).css( "top", pos.top - topOffset );
					}
				}
			}
		},
		_create: function() {
			var thiselement = this;
			this.requestTimer = '';
			this._createWrapper();
			this._createHeader();
			this._createLock();
			this._createShare();
			this._createCloseButton();
			
			this.element
				.addClass( "ui-sticky-text" )
				.appendTo( this.uiSticky )
				.bind('input propertychange',function(){
					thiselement.options.content = this.value;
					if(thiselement.requestTimer){
						window.clearTimeout(thiselement.requestTimer);
					}
					thiselement.requestTimer = setTimeout(function(){
						thiselement.options.action = 'update';
						thiselement._sendData();} ,2000);
				});
				
			this.uiSticky
			    .width(this.options.width)
			    .height(this.options.height)
				.resizable({
					stop: function(event,ui){
						thiselement.options.width = ui.size.width;
						thiselement.options.height = ui.size.height;
						thiselement.options.action = 'update';
						thiselement._sendData();
					}
				})
				.draggable({ 
					containment: "window",
					stop: function(event,ui){
				    	var stoppos = $(this).position();
				    	thiselement.options.position = stoppos;
				    	thiselement.options.top = stoppos.top;
				    	thiselement.options.left = stoppos.left;			    	
				    	thiselement.options.action = 'update';
				    	thiselement._sendData();
				    }
				})
				.mousedown(function(){
					$('div.ui-sticky').not(this).css('z-index',250);
					$(this).css('z-index','1000');
				})
				.position( this.options.position )
			
			this._createFooter();
			
			if(!this.options.stickyid){
				// This is a new one
				this.uiSticky.uniqueId();
				this.options.stickyid = this.uiSticky.attr('id');
				this.options.action = "create";
				this._sendData();
			} else {
				// This is an existing one
				this.options.sendData = false;
				if(this.options.locked){
					this._lock(true);
				}
				if(this.options.shared){
					this._share(true);
				}
				if(this.options.width && this.options.height){
					this.uiSticky.width(this.options.width)
					.height(this.options.height);
				}
				this.uiSticky.attr('id','popnote_'+this.options.stickyid);
				this.element.val(this.options.content);
				this.options.sendData = true;
				//console.log(this.options);
			}
		},
		_createCloseButton: function() {
			this.uiStickyCloseButton = $( "<span>" )
				.addClass( "ui-sticky-close-button ui-icon ui-icon-closethick" )
				.appendTo( this.uiStickyHeader );
				
			this._on( this.uiStickyCloseButton, {
				"click": function() {
				    this.options.action = 'delete';
			        this._sendData();
					this._destroy();
				}
			});
		},
		_createWrapper: function() {			
			this.uiSticky = $( "<div>" )
				.addClass( "ui-sticky ui-sticky-wrapper" )
				.appendTo( "body" );
		},
		_createHeader: function() {
			
			this.uiStickyHeader = $( "<div>" )
				.addClass( "ui-sticky-header" )
				.prependTo( this.uiSticky );
		},
		_createFooter: function(){
			this.uiStickyFooter = $("<div>")
				.addClass("ui-sticky-footer")
				.appendTo(this.uiSticky);
		},
		_createLock: function() {
			this.uiStickyLock = $( "<span>" )
			.addClass( "ui-sticky-lock ui-icon ui-icon-unlocked" )
			.prependTo( this.uiStickyHeader );
			
			this._on( this.uiStickyLock, {
				"click": this._lock
			});
		},
		_createShare: function(){
			this.uiStickyShare = $("<input type='button' value='Share'>")
			.addClass( "ui-sticky-share")
			.prependTo(this.uiStickyHeader);
			
			this._on(this.uiStickyShare, {
				"click": this._share
			});
		},
		_lock: function( value ){
			if ( typeof( value ) === "object" ) {
				this.options.locked = !this.options.locked;
				value = this.options.locked;
			}
			
			this.uiStickyLock
				.toggleClass( "ui-icon-unlocked", !value )
				.toggleClass( "ui-icon-locked", value );
			
			this.uiStickyHeader
				.toggleClass( "ui-sticky-locked", value );
			
			this.element
				.toggleClass( "ui-sticky-locked", value );
			
			this.uiSticky
			    .toggleClass( "ui-sticky-locked", value );
			
		    if(this.options.locked){
		    	this.uiSticky.draggable('disable');
		    	this.uiSticky.resizable('disable');
		    	this.element.prop('disabled','disabled');
		    	this.uiStickyShare.hide();
		    	this.uiStickyCloseButton.hide();
		    } else {
		    	this.uiSticky.draggable('enable');
		    	this.uiSticky.resizable('enable');
		    	this.element.prop('disabled',false);
		    	this.uiStickyShare.show();
		    	this.uiStickyCloseButton.show();
		    }
		    this.options.action = 'update';
		    this._sendData();
		},
		_share: function( value ){
			if ( typeof( value ) === "object" ) {
				this.options.shared = !this.options.shared;
				value = this.options.shared;
			}
			
			if(value){
				this.uiStickyShare.val('Unshare');
			} else {
				this.uiStickyShare.val('Share');
			}
			this.options.action = 'update';
		    this._sendData();
		},
		_setOption: function( key, value ) {
			
			this._super( key, value );
			
			if ( key === "pinned" ) {
				this._pin( value );
			}

		},
		_sendData: function() {
			//console.log(this.options);
			var thiselement = this;
			if(this.options.ajaxurl && this.options.sendData){
				$.ajax({
					cache: false,
					url: this.options.ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: this.options.action,
						locked: this.options.locked,
						shared: this.options.shared,
						content: this.options.content,
						id: this.options.stickyid,
						height: this.options.height,
						width: this.options.width,
						top: this.options.top,
						left: this.options.left,
						data: this.options.data,
						hash: this.options.hash,
						subid: this.options.subid,
						userid: this.options.userid
				    },
					error: function(jqXHR,status,content){
					    thiselement.uiStickyFooter.html('Request Error');
					    thiselement.uiStickyFooter.addClass('ui-state-error');
					    for(i=0;i<3;i++) {
							   thiselement.uiStickyFooter.show().effect('highlight', {color:'#ffaaaa'}, 500);
						     }
					},
				    success: function(json){
				    	if(json.result == 1){
				    		if(json.mData){
				    			thiselement.options.stickyid = json.mData;
				    			thiselement.uiSticky.attr('id','popnote_'+thiselement.options.stickyid);
				    		}
				        }
				    	else {
				    		thiselement.uiStickyFooter.html('Request Error');
						    thiselement.uiStickyFooter.addClass('ui-state-error');
						    for(i=0;i<3;i++) {
								   thiselement.uiStickyFooter.show().effect('highlight', {color:'#ffaaaa'}, 500);
							     }
				    	}	    		
				    }
				});
			}
		},
		_destroy: function() {
			this.uiSticky.remove();
		}
	});
	
}));
