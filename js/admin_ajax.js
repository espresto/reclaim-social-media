var reclaim = function () {};

jQuery(document).ready(function($) {
	reclaim.instances = {};
	
	reclaim.getInstance = function(modname, eventObject) {
		if (!reclaim.instances[eventObject.target.id]) {
			var r = new reclaim();
			r.init(modname, eventObject);
			reclaim.instances[eventObject.target.id] = r;
		}
		
		return reclaim.instances[eventObject.target.id];
	}
	
	reclaim.prototype = {
		init:function(modname, eventObject) {
			this.modname = modname;
			this.eventObject = eventObject;
			this.request = false;
			this.running = false;
			this.aborted = false;
			this.target_text = false;
		},
		
		is_running : function() {
			return this.running;
		},
		
		is_aborted : function() {
			return this.aborted;
		},
		
		ajax_start : function (message) {
			$('#'+this.modname+'_spinner').show();
			$('#'+this.modname+'_notice').show();
			$('#'+this.modname+'_notice .message').text(message);
			
			if (!this.target_text) {
				this.target_text = $(this.eventObject.target).val();
				$(this.eventObject.target).val('Cancel '+this.target_text);
			}
			
			this.running = true;
			this.aborted = false;
		},
	
		ajax_end : function (message) {
			$('#'+this.modname+'_notice .message').text(message);
			$('#'+this.modname+'_notice').show();
			$('#'+this.modname+'_spinner').hide();
			
			$(this.eventObject.target).val(this.target_text);
			this.target_text = false;
			
			this.running = false;
			
			// unregister instance
			reclaim.instances[this.eventObject.target] = null;
			delete reclaim.instances[this.eventObject.target];
		},
		
		ajax_abort : function() {
			this.aborted = true;
			this.ajax_end('Canceled.');
			
			if (this.request) {
				this.request.abort();
			}
		},
		
		ajax : function(fname, data, callback) {
			var that = this;
			data.action = this.modname+'_'+fname;
			this.request = $.ajax({
				url: ajaxurl,
				data : data,
				dataType : 'JSON',
				type : 'POST',
				success : function(data) {
					if (!data) {
						that.ajax_end('Whoops! Returned data must be not null.');
					}
					else if (data.success) {
						callback(data.result);
					} else {
						that.ajax_end('Error occured: ' + data.error);
					}
				}
			});
		},
		
		// functions that get called on click or something like that
		count_all_items: function() {
			if (this.is_running()) {
				this.ajax_abort();
			}
			else {
				this.ajax_start('Count items and posts...');
	
				this.ajax('count_all_items', {}, $.proxy(function(result) {
					this.ajax_end(result);
				}, this));
			}
		},
		
		resync_items: function() {
			if (this.is_running()) {
				this.ajax_abort();
				
				if (this.resync) {
					this.resync.abort();
				}
			}
			else {
				this.ajax_start('Count items...');
	
				this.ajax('count_items', {}, $.proxy(function(result) {
					if (this.is_aborted()) {
						// do nothing
					}
					else if (isNaN(result)) {
						this.ajax_end('item count is not a valid numbethis. value=' + result);
					}
					else if (result <= 0) {
						this.ajax_end('Not a valid item count: ' + result);
					}
					else {
						var resync = new reclaim.resync();
						this.resync = resync;
						resync.init(this, 0, 10, result);
						resync.run();
					}
				}, this));
			}
			
		}
	}
	
	reclaim.resync = function () {};
	reclaim.resync.prototype = {
		init : function (reclaim, offset, limit, count) {
			this.r = reclaim;
			this.limit = limit;
			this.count = count;
			this.start_date = new Date();
			// first offset
			this.data = {
				offset : offset
			}
			
			this.aborted = false;
		},
	
		run : function () {
			this.r.ajax_start('Resync ' + (this.count - this.data.offset) + ' items...');
			
			// take these values always from config
			this.data['limit'] = this.limit;
			this.data['count'] = this.count;
			
			this.r.ajax('resync_items', this.data, $.proxy(function(result) {
				var offset = parseInt(result.offset);
				// wrong implementation
				if (isNaN(offset)) {
					this.r.ajax_end('result.offset is not a number: value='+result.offset);
				}
				// end
				else if (this.aborted || offset <= this.data.offset || this.count <= offset) {
					this.r.ajax_end(Math.min(offset, this.count) + ' items resynced, duration: '+this.duration());
				}
				// next
				else {
					// copy the result into data and send
					// it to the next iteration
					this.data = $.extend(this.data, result);
					this.run();
				}
			}, this));
		},
		
		abort : function() {
			this.aborted = true;
		},
		
		duration : function() {
			var d2 = new Date().getTime();
			var d1 = this.start_date.getTime();
			
			var difference_ms = d2 - d1;
			
			difference_ms = difference_ms/1000;
			var seconds = Math.floor(difference_ms % 60);
			difference_ms = difference_ms/60; 
			var minutes = Math.floor(difference_ms % 60);
			difference_ms = difference_ms/60; 
			var hours = Math.floor(difference_ms % 24);  
			
			return ("00" + hours).slice(-2)+':'+("00" + minutes).slice(-2)+':'+("00" + seconds).slice(-2);
		}
	}
});