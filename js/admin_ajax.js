var reclaim = function () {};

jQuery(document).ready(function($) {
	reclaim.instances = {};
	
	reclaim.getInstance = function(modname, eventObject) {
		if (!reclaim.instances[eventObject.target]) {
			var r = new reclaim();
			r.init(modname, eventObject);
			reclaim.instances[eventObject.target] = r;
		}
		
		return reclaim.instances[eventObject.target];
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
				$(this.eventObject.target).val( admin_reclaim_script_translation.Cancel + this.target_text );
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
			this.ajax_end(admin_reclaim_script_translation.Canceled);
			
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
						that.ajax_end(admin_reclaim_script_translation.Whoops_Returned_data_must_be_not_null);
					}
					else if (data.success) {
						callback(data.result);
					} else {
						that.ajax_end(admin_reclaim_script_translation.Error_occured + data.error);
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
				this.ajax_start(admin_reclaim_script_translation.Count_items_and_posts);
	
				this.ajax('count_all_items', {}, $.proxy(function(result) {
					this.ajax_end(result);
				}, this));
			}
		},
		
		resync_items: function(options) {
			if (this.is_running()) {
				this.ajax_abort();
				
				if (this.resync) {
					this.resync.abort();
				}
			}
			else {
				this.ajax_start(admin_reclaim_script_translation.Count_items);
				var o = $.extend({}, options);
	
				this.ajax('count_items', o, $.proxy(function(result) {
					if (this.is_aborted()) {
						// do nothing
					}
					else if (isNaN(result)) {
						this.ajax_end(admin_reclaim_script_translation.item_count_is_not_a_valid_number + result);
					}
					else if (result <= 0) {
						this.ajax_end(admin_reclaim_script_translation.Not_a_valid_item_count + result);
					}
					else {
						var resync = new reclaim.resync();
						this.resync = resync;
						resync.init(this, 0, 10, result, o);
						resync.run();
					}
				}, this));
			}
			
		}
	}
	
	reclaim.resync = function () {};
	reclaim.resync.prototype = {
		init : function (reclaim, offset, limit, count, options) {
			this.r = reclaim;
			this.limit = limit;
			this.count = count;
			this.start_date = new Date();
			
			// clear options from field offset
			if (options) {
				delete options.offset;
			}
			
			this.options = options;
			
			// first offset
			this.data = $.extend({
				offset : offset
			}, this.options);
			
			this.aborted = false;
		},
	
		run : function () {
			this.r.ajax_start(admin_reclaim_script_translation.Resyncing_items + (this.count - this.data.offset));
			
			// take these values always from config
			this.data['limit'] = this.limit;
			this.data['count'] = this.count;
			
			this.r.ajax('resync_items', this.data, $.proxy(function(result) {
				var offset = parseInt(result.offset);
				// wrong implementation
				if (isNaN(offset)) {
					this.r.ajax_end(admin_reclaim_script_translation.result_offset_is_not_a_number+result.offset);
				}
				// end
				else if (this.aborted || offset <= this.data.offset || this.count <= offset) {
					this.r.ajax_end(Math.min(offset, this.count) + admin_reclaim_script_translation.items_resynced_duration+this.duration());
				}
				// next
				else {
					// copy the result into data and send
					// it to the next iteration
					this.data = $.extend(this.data, result, this.options);
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