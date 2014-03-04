<?php
/*  Copyright 2013-2014 diplix

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class moves_reclaim_module extends reclaim_module {
    private static $apiurl = "https://api.moves-app.com/api/1.1/user/summary/daily?pastDays=%s&access_token=%s";
    private static $apiurl_month = "https://api.moves-app.com/api/1.1/user/summary/daily/%s?&access_token=%s";
    private static $apiurl_profile = "https://api.moves-app.com/api/1.1/user/profile?access_token=%s";

    // change for one-time import
    // private static $apiurl= "https://api.moves-app.com/api/v1/user/summary/daily?from=yyyymmdd&to=yyyymmdd&&foo=%s&access_token=%s";
    // private static $apiurl= "https://api.moves-app.com/api/v1/user/summary/daily?from=20130304&to=20130331&&foo=%s&access_token=%s";

    private static $timeout = 15;
    private static $count = 31; // maximum 31 days
    private static $post_format = 'status'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'moves';
        $this->has_ajaxsync = true;

        add_filter('the_content', array($this, 'moves_content'), 100);
        add_action('wp_enqueue_scripts', array($this, 'moves_add_reclaim_stylesheet'));
        add_action('wp_head', array($this, 'add_moves_styles'));
        
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'moves_user_id');
        register_setting('reclaim-social-settings', 'moves_client_id');
        register_setting('reclaim-social-settings', 'moves_client_secret');
        register_setting('reclaim-social-settings', 'moves_access_token');
        register_setting('reclaim-social-settings', 'reclaim_show_moves_dataset');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='moves') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option('moves_user_id', $user_profile->identifier);
                update_option('moves_access_token', $user_access_tokens->access_token);
            }
            if(session_id()) {
                session_destroy ();
            }
        }
?>
<?php
        $displayname = __('moves', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
         <tr valign="top">
            <th scope="row"><?php _e('Show moves diagram', 'reclaim'); ?></th>
            <td><input type="checkbox" name="reclaim_show_moves_dataset" value="1" <?php checked(get_option('reclaim_show_moves_dataset')); ?> /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Moves client id', 'reclaim'); ?></th>
            <td><input type="text" name="moves_client_id" value="<?php echo get_option('moves_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Moves client secret', 'reclaim'); ?></th>
            <td><input type="text" name="moves_client_secret" value="<?php echo get_option('moves_client_secret'); ?>" />
            <input type="hidden" name="moves_user_id" value="<?php echo get_option('moves_user_id'); ?>" />
            <input type="hidden" name="moves_access_token" value="<?php echo get_option('moves_access_token'); ?>" />
            <p class="description">
            <?php 
            echo sprintf(__('Get your Moves client and credentials <a href="%s">here</a>. ','reclaim'),'https://dev.moves-app.com/apps');  
            echo sprintf(__('Use <code>%s</code> as "Redirect URI"','reclaim'),plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/')); ?>
            </p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('moves_client_id')!="")
            && (get_option('moves_client_secret')!="")

            ) {
                $link_text = __('Authorize with Moves', 'reclaim');
                if ( (get_option('moves_user_id')!="") && (get_option('moves_access_token')!="") ) {
                    echo sprintf(__('<p>Moves is authorized</p>', 'reclaim'), get_option('moves_user_id'));
                    $link_text = __('Authorize again', 'reclaim');
                }

                // send to helper script
                // put all configuration into session
                // todo
                $config = $this->construct_hybridauth_config();
                $callback =  urlencode(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=reclaim/reclaim.php&link=1&mod='.$this->shortname);

                $_SESSION[$this->shortname]['config'] = $config;

                echo '<a class="button button-secondary" href="'
                    .plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
                    .'?'
                    .'&mod='.$this->shortname
                    .'&callbackUrl='.$callback
                    .'">'.$link_text.'</a>';
            }
            else {
                _e('enter moves app id and secret', 'reclaim');
            }

            ?>
            </td>
        </tr>

<?php
    }

    public function construct_hybridauth_config() {
        $config = array(
            // "base_url" the url that point to HybridAuth Endpoint (where the index.php and config.php are found)
            "base_url" => plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/'),
            "providers" => array (
                "Moves" => array(
                    "enabled" => true,
                    "keys"    => array ( "id" => get_option('moves_client_id'), "secret" => get_option('moves_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/Moves.php',
                        "class" => "Hybrid_Providers_Moves",
                    ),
                ),
            ),
        );
        return $config;
    }

    public function import($forceResync) {
        if (get_option('moves_user_id') && get_option('moves_access_token') ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, self::$count, get_option('moves_access_token')), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
                $data = $this->map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    public function ajax_resync_items() {
		$offset = intval( $_POST['offset'] );
		$limit = intval( $_POST['limit'] );
		$count = intval( $_POST['count'] );
    	$next_url = isset($_POST['next_url']) ? $_POST['next_url'] : '';
    
    	self::log($this->shortName().' resync '.$offset.'-'.($offset + $limit).':'.$count);
    	 
    	$return = array(
    		'success' => false,
    		'error' => '',
			'result' => null
    	);
    	    	
    	if (get_option('moves_user_id') && get_option('moves_access_token') ) {
            $month = date( "Ym", round(time() - ($offset * (60*60*24))));
    		if ($next_url != '') {
				$rawData = parent::import_via_curl($next_url, self::$timeout);
			}
			else {
                // offset = days synced, substracting that point to month to be synced
                // at first run: 0
                parent::log("month: ".$month);
    			$rawData = parent::import_via_curl(sprintf(self::$apiurl_month, $month, get_option('moves_access_token')), self::$timeout);
    		}

    		$rawData = json_decode($rawData, true);
    		
    		if ($rawData) {
    			$data = self::map_data($rawData);
    			parent::insert_posts($data);
    			update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));

                // calculate next url
                $new_offset = $offset + sizeof($data);
                $month = date( "Ym", round(strtotime($month) - (($new_offset) * (60*60*24))));
                parent::log("strtotime(month): ". strtotime($month). " month: ".$month . " new_offset: ".$new_offset);
    			$next_url = sprintf(self::$apiurl_month, $month, get_option('moves_access_token'));
    			
    			$return['result'] = array(
    				'offset' => $new_offset,
					// take the next pagination url instead of calculating
					// a self one
					'next_url' => $next_url,
    			);
    			$return['success'] = true;
    		}
    		else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
    	}
    	else $return['error'] = sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname);
    	
    	
    	echo(json_encode($return));
    	 
    	die();
    }

    /**
     * Maps moves summery data to wp-content data. Check https://dev.moves-app.com/docs/api_summaries for more info.
     * @param array $rawData
     * @return array
     */
    private function map_data(array $rawData, $type="posts") {
        $data = array();
        foreach($rawData as $day){
            if ($this->check_for_import($day) && intval(date("H")) > 2) {
                $id = 'moves-'.$day["date"];
                $image_url = '';
                $tags = '';
                $link = '';
                $title = sprintf(__('Activity on %s', 'reclaim'), date_i18n(get_option('date_format'), strtotime($day["date"])));

                $post_meta['moves_api_data'] = json_encode($day);
                $activity_grouped = $this->construct_activity_group_array($day);
                $post_meta['moves_group_data'] = $activity_grouped['data2'];
                $post_meta['moves_group_data1'] = $activity_grouped['data1'];
                $content = $this->construct_content($activity_grouped['data1']);

                $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
                $post_meta["_post_generator"] = $this->shortname;
                $post_meta["_reclaim_post_type"] = $type;

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => self::$post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($day["date"])+72000)),
                    'post_content' => $content,
                    'post_title' => $title,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'tags_input' => $tags,
                    'ext_permalink' => $link,
                    'ext_image' => $image_url,
                    'ext_guid' => $id,
                    'post_meta' => $post_meta
                );
            }
        }
        return $data;
    }

    public function count_items() {
		if (get_option('moves_user_id') && get_option('moves_access_token') ) {
			$rawData = parent::import_via_curl(sprintf(self::$apiurl_profile, get_option('moves_access_token')), self::$timeout);
    		$rawData = json_decode($rawData, true);
    		$firstdate = strtotime($rawData['profile']['firstDate']);
            $second = 1; 
            $minute = $second*60; 
            $hour = $minute*60; 
            $day = $hour*24; 
            $week = $day*7; 

            $delta = (time()-$firstdate); //29584979
            $days = round($delta/$day); 
    		return $days;
    	}
    	else {
    		return false;
    	}
    }

    public function moves_add_reclaim_stylesheet() {
        if (get_option('reclaim_show_moves_dataset') == '1') {
	        wp_enqueue_script( 'd3', RECLAIM_PLUGIN_URL.'/js/d3.v3.min.js' );
        }

    }
    public function add_moves_styles() {
        if ( get_option('reclaim_show_moves_dataset') == '1' ) {
    ?>
    <style type="text/css" id="moves-custom-styles">
    /* SV for moves */

	svg.moves {
		width: 100%;
		height: 200px;
	}

	svg {
		/* background-color: #000; */
		background-color: transparent;
	}
	
	svg body {
		background-color: transparent !important;
		background-image: none;
	}

	svg circle {
		fill: rgba(225, 152, 53, 1.000);
		-webkit-svg-shadow: -2px 1px 4px rgba(0, 0, 0, 0.6);
	}
	
	svg circle.walking, svg circle.walking_on_treadmill,
	svg text.walking {
		fill: #00d55a;
		text-color: #00d55a;
	}

	svg circle.cycling, svg circle.indoor_cycling,
	svg text.cycling {
		fill: #00cdec;
		text-color: #00cdec;
	}
	
	svg circle.transport, svg circle.underground,
	svg text.transport {
		fill: #848484;
		text-color: #848484;
	}

	svg circle.running, svg circle.running_on_treadmill,
	svg text.running {
		fill: #f660f4;
		text-color: #f660f4;
	}
	

	svg text {
		fill: rgba(255, 255, 255, 0.9);
		font-weight: normal;
		font-family: sans-serif;
		font-size: 10pt;
		text-anchor: middle;
		alignment-baseline: central;
	}

	svg .title {
		font-family: sans-serif;
		font-weight: bold;
		font-size: 32pt;
		letter-spacing: -0.05em;
		text-anchor: start;
		fill: rgba(255, 255, 255, 0.9);
	}

	svg div.labelDiv {
		padding: 0px;
		margin: 0px;
		display: table-cell;
		vertical-align: middle;
		text-align: center;
	}

	svg foreignObject {
		padding: 0px;
	}

	svg .label,
	svg.moves p.label,
	#core-content svg p.label {
		font-weight: normal;
		font-family: sans-serif;
		font-size: 9pt;
		line-height:10pt;
		color: rgba(255, 255, 255, 0.9);
		margin: 0;
		padding: 0;
		display: block;
		border-radius: 0;
		text-align: center;
		
	}
	span.label-small {
		font-size:8px;
	}
    </style>
    <?php
        }
    }
    
    private function check_for_import(&$day) {
        $check = true;
        
        // no entry, if it's from today
        if ( strtotime($day['date']) >= strtotime(date('d.m.Y')) ) {
            $check = false;
        }
        
        return $check;
    }
    
    private function construct_content($day) {
        $distance = 0;
        $transport_distance = 0;
        $description = __("Today I ", 'reclaim');
        $description_transport ="";
        if (isset($day['walking']['steps']) && $day['walking']['steps'] >= 500) {
            $distance = intval($day['walking']['distance']);
            $description .= sprintf(__("walked %s steps", 'reclaim'), number_format(intval($day['walking']['steps']),0,',','.'));
        }
        else {
            $description .= __("didn’t walk much", 'reclaim');
        }
        if (isset($day['running']['distance']) && $day['running']['distance'] >= 500) {
            $distance = $distance + intval($day['running']['distance']);
            $description .= sprintf(__(" and ran for %s kilometers", 'reclaim'), number_format( (intval($day['running']['distance'])/1000), 1, ',', '.'));
        }
        else {
            $description .= "";
        }
        if (isset($day['cycling']['distance']) && $day['cycling']['distance'] >= 1000) {
            $distance = $distance + intval($day['cycling']['distance']);
            $description .= sprintf(__(" and rode bicycle for %s kilometers", 'reclaim'), number_format( (intval($day['cycling']['distance'])/1000), 1, ',', '.'));
        }
        else {
            $description .= "";
        }
        if (isset($day['transport']['distance']) && $day['transport']['distance'] >= 1000) {
            $transport_distance = intval($summary['distance']);
            // 
            $description .= sprintf(__(" and used transport for %s kilometers", 'reclaim'), number_format( (intval($day['transport']['distance'])/1000), 1, ',', '.'));
        }
        else {
            $description .= "";
            $description_transport = "";
        }
        $description .= '.';

        if ($distance <= 500 && $description_transport != "") {
            $description = sprintf(__("I hardly moved today, but i %s.", 'reclaim'), $description_transport); 
        } elseif ($distance <= 500 && $description_transport == "") {
            $description = __("I hardly moved today", 'reclaim');
        }
        
        if ($distance == 0 && $transport_distance == 0) {
            $description = __("I hardly moved today", 'reclaim');
        }

        return $description;
    }

    /**
     * Returns meta data for every activity in a moves summary data day.
     * @param array $day Data return from moves api. Known possible keys so far:
     *  activity, distance, duration, steps (not if activity == cyc), calories
     * @return array
     */
    private function construct_post_meta(array $day) {
        if (isset($day['summary'])) {
        $post_meta = array();
        $post_meta_dataset_distance = array();
        
            foreach ($day['summary'] as $activityData) {
                $activity = isset($activityData['activity']) ? $activityData['activity'] : 'unknown';
                unset($activityData['activity']);
                foreach ($activityData as $activityDataKey => $activityDataValue) {
                    $postMetaKey = $activity . '_' . $activityDataKey;
                    $post_meta[$postMetaKey] = $activityDataValue;
                    if ($activityDataKey == "distance") {
                        $post_meta_dataset_distance[] = array("label" => $activity, "circle_label" => number_format( (intval($activityDataValue)/1000), 1, ',', '.') . ' km', "value" => $activityDataValue);
                    }
                }
            }
            $post_meta['moves_dataset'] = json_encode($post_meta_dataset_distance);
            return $post_meta;
        }
        else {
            return array();
        }
    }

    private function construct_activity_group_array(array $day) {
        $groups = array(
            "cycling" => array("label" => __("cycling","reclaim")), // 
            "running" => array("label" => __("running","reclaim")), // 
            "walking" => array("label" => __("walking","reclaim")), // 
            "transport" => array("label" => __("transport","reclaim")) // 
            );
        if (isset($day['summary'])) {
        $data = array();
        $graph_data = array();
        foreach ($groups as $group => $label) {
            // filter all activity with group
            // sum it up
            foreach ($day['summary'] as $activityData) { 
            	if ($activityData['group'] == $group) {
                    unset($activityData['activity']);
                    unset($activityData['group']);
                    $data[$group]['group'] = $group;
                    $data[$group]['label'] = $label['label'];
                    foreach ($activityData as $activityDataKey => $activityDataValue) {
                        $data[$group][$activityDataKey] = $data[$group][$activityDataKey] + $activityDataValue; // summieren pro key?
                    }
            	}
            }
        }
        //parent::log(json_encode($data));
            foreach ($data as $group) { 
                $graph_data[] = $group;
            }
        	$data['data1'] = $data;
        	$data['data2'] = $graph_data;

            return $data;
        }
        else {
            return array();
        }
    }

    public function moves_content($content = '') {
        global $post;

        // Do not process feed / excerpt
        if (is_feed() || reclaim_core::in_excerpt())
            return $content;

            //!is_home() &&
            //!is_single() &&
            //!is_page() &&
            //!is_archive() &&
            //!is_category()
        
        if ( get_option('reclaim_show_moves_dataset') == '1' && get_post_meta($post->ID, 'moves_group_data', true) ) {
            $moves_diagram = '
    <div id="moves-'.$post->ID.'"></div>
    <script>

    var h = 200;
    var w = 500;
    var minimumBubbleSize = 10;
    var labelsWithinBubbles = true;
    var title = "";
    var dataset = '.json_encode(get_post_meta($post->ID, 'moves_group_data', true)).';
    var gapBetweenBubbles = 15;
    var xPadding = 20;
    var yPadding = 100;
    var scaling = 45;
    var steps = false;
    var distance = true;
    
    /* Sort the dataset to ensure the bubble are always ascending */
    dataset = dataset.sort(function (a, b) { return (b.distance - a.distance);});

    /* Scale the dataset */
    var factor = minimumBubbleSize / dataset[0].distance;
    //var l = dataset.length-1;
    //var factor = minimumBubbleSize / dataset[l].distance;
    
    dataset.forEach(function(d) { d.value = d.distance * factor; });

    /* Scaling */

    function getRadius(area) {
        return Math.sqrt(area / Math.PI);
    }

    function getLabelDivSideFromArea(area) {
        return Math.sqrt(Math.pow(2 * rScale(area), 2) / 2);
    }

    var rScale = function(input) {
        /* Magic number here is just to get a reasonable sized smallest bubble */
        return getRadius(input) * scaling;
    }

    /* For bubbles that are too big to centre their text, compute a better position */

    function getNewXPosition(leftBubble, rightBubble) {

    }

    function getNewYPosition(leftBubble, rightBubble) {

    }

    /* Create the chart */

    var svg = d3.select("div#moves-'.$post->ID.'")
    .append("svg")
    .attr("width", w)
    .attr("height", h)
    .attr("class", "moves")
    .attr("viewBox", "0 0 "+ w + " " + h)

    /* Adjust left hand side to add on the radius of the first bubble */
    xPaddingPlusRadius = xPadding + rScale(dataset[0].value);
    dataset[0].xPos = xPaddingPlusRadius;
	
	var node = svg.selectAll(".node")
    .data(dataset)
    .enter()
    .append("g")
    .attr("class", "node")
    //.attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; })
    .on("mouseover", function(d) {
    	d3.select(this).transition().ease("elastic")
	        .duration(1000)
	        .select("circle").attr("r", rScale(d.value)-1)
	        ;
    })
    .on("mousemove", function(d,i)
    {
        //tooltipDivID.css({top:d.y+d3.mouse(this)[1],left:d.x+d3.mouse(this)[0]+50});
        //showToolTip("<ul><li>"+data[0][i]+"<li>"+data[1][i]+"</ul>",d.x+d3.mouse(this)[0]+10,d.y+d3.mouse(this)[1]-10,true);
        //console.log(d3.mouse(this));
    })    
    .on("mouseout", function(d) {
    	d3.select(this).transition().ease("elastic")
	        .duration(1000)
	        .attr("transform", "scale(1)")
	        .select("circle").attr("r", rScale(d.value))
	        ;
    })    
    .on("mousedown", function(d) {
        if (rScale(d.value) > 30 && (d.group == "walking" || d.group == "running")) { 
			if (steps == false) {
	    		d3.select(this)
		        .select(".label")
			    .html(function(d, i) { 
		    	    return "<p class=\'label\'>" + (d.steps) + " <br /><span class=\'label-small\'>'.__("Steps", 'reclaim').'</span></p>"; 
	    		});
				d3.select(this).select("circle")
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-3)
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-1)
	    		;
		    	steps = true;
		    } else {
    			d3.select(this)
	    	    .select(".label")
		    	.html(function(d, i) { 
			        return "<p class=\'label\'>" + (d.distance/1000).toFixed(1) + " <br /><span class=\'label-small\'>km</span></p>"; 
    			})
				d3.select(this).select("circle")
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-3)
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-1)
	    		;
	    		steps = false;
		    }
	    }
        if (rScale(d.value) > 30 && (d.group == "transport" || d.group == "cycling")) { 
			if (distance == false) {
	    		d3.select(this)
		        .select(".label")
			    .html(function(d, i) { 
		    	    return "<p class=\'label\'>" + (d.distance/1000).toFixed(1) + " <br /><span class=\'label-small\'>km</span></p>"; 
	    		});
				d3.select(this).select("circle")
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-3)
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-1)
	    		;
		    	distance = true;
		    } else {
    			d3.select(this)
	    	    .select(".label")
		    	.html(function(d, i) { 
			        return "<p class=\'label\'>" + ("0" + Math.floor(d.duration/(60*60))).slice(-2) + ":" +  ("0" + (Math.floor(d.duration/60)%60)).slice(-2) + " '.__("h", "reclaim").'</p>"; 
    			})
				d3.select(this).select("circle")
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-3)
				.transition().ease("elastic")
		        .duration(100)
		        .attr("r", rScale(d.value)-1)
	    		;
	    		distance = false;
		    }

		}
    });

    var accumulator = xPaddingPlusRadius;
    node.append("circle")
    .attr("cx", function(d, i) {

        if (i > 0) {

            var previousRadius = rScale(dataset[i-1].value);
            var currentRadius = rScale(d.value);
            var increment = previousRadius + currentRadius + gapBetweenBubbles;
            accumulator += increment;
            d.xPos = accumulator;
            return accumulator;

        } else {
            return xPaddingPlusRadius;
        }

    })
    .attr("cy", function(d) {
        //return h - rScale(d.value) - yPadding;
        return h / 2;
    })
    .attr("r", function(d) {
        return rScale(d.value);
    })
    .attr("class", function(d) {
        return d.group;
    })
    ;

    /* Place text in the circles. Could try replacing this with foreignObject */

    node.append("foreignObject")
    .attr("x", function(d, i) {
        if (d.xPos > w) {
            /* Do the different thing */
            return d.xPos - ((getLabelDivSideFromArea(d.value)*1.2)/2);
        } else {
            return d.xPos - ((getLabelDivSideFromArea(d.value)*1.2)/2);
        }
    })
    .attr("y", function(d, i) {
        if (labelsWithinBubbles) {
                return h /2  - ((getLabelDivSideFromArea(d.value)*1.2)/2);
        } else {
            return h - yPadding + 20;
        }
    })
    .attr("width", function(d) { return getLabelDivSideFromArea(d.value)*1.2; })
    .attr("height", function(d) { return getLabelDivSideFromArea(d.value)*1.2; })
    .append("xhtml:body")
    .append("div")
    .attr("style", function(d) { return "width: " + getLabelDivSideFromArea(d.value)*1.2 + "px; height: " + getLabelDivSideFromArea(d.value)*1.2 + "px;"; })
    .attr("class", "labelDiv")
    .attr("title", function(d) {
        return d.label + " " + (d.distance/1000).toFixed(1) + " km";
    })
    .html(function(d, i) { 
        if (rScale(d.value) > 20) { 
            return "<p class=\'label\'>" + (d.distance/1000).toFixed(1) + " <br /><span class=\'label-small\'>km</span></p>"; 
        } else { return ""; }
    })
    ;


	node.append("text")
    .text(function(d){
    	return d.label;
    })
    .attr("y", function(d) {
        return h/2 + rScale(d.value)+10;
    })
    .attr("x", function(d,i){
    	return d.xPos;
    })
    .attr("font-size",10)
    .attr("class", function(d) {
        return d.group;
    })
    .attr("text-anchor","middle")
    ;



    </script>
';
            $content .= $moves_diagram;
        }

        return $content;
    }

}
