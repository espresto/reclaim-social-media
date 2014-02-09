var reclaim = function () {};

reclaim.prototype = {
	init:function($, modname) {
		this.$ = $;
		this.modname = modname;
	},
	
	ajax_start : function (message) {
		this.$('#'+this.modname+'_spinner').show();
		this.$('#'+this.modname+'_notice').show();
		this.$('#'+this.modname+'_notice .message').text(message);
	},

	ajax_end : function (message) {
		this.$('#'+this.modname+'_notice .message').text(message);
		this.$('#'+this.modname+'_notice').show();
		this.$('#'+this.modname+'_spinner').hide();
	},
	
	ajax : function(fname, data, callback) {
		var that = this;
		data.action = this.modname+'_'+fname;
		this.$.ajax({
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
	},

	run : function () {
		this.r.ajax_start('Resync Items '+this.data.offset+'-' + Math.min(this.data.offset + this.limit, this.count) + ' of ' + this.count + '...');
		
		var that = this;
		// take these values always from config
		this.data['limit'] = this.limit;
		this.data['count'] = this.count;
		
		this.r.ajax('resync_items', this.data, function(result) {
			var offset = parseInt(result.offset);
			// wrong implementation
			if (isNaN(offset)) {
				that.r.ajax_end('result.offset is not a number: value='+result.offset);
			}
			// end
			else if (offset <= that.data.offset || that.count <= offset) {
				
				that.r.ajax_end(Math.min(offset, that.count) + ' items resynced, duration: '+that.duration());
			}
			// next
			else {
				// copy the result into data and send
				// it to the next iteration
				that.data = that.r.$.extend(that.data, result);
				that.run();
			}
		});
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
};