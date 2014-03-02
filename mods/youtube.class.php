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

class youtube_reclaim_module extends reclaim_module {
    private static $timeout = 20;
    private static $count = 10; // maximum: 50
    private static $apiurl     = "https://gdata.youtube.com/feeds/api/users/%s/uploads?alt=json&max-results=%s";
	private static $fav_apiurl = "https://gdata.youtube.com/feeds/api/users/%s/favorites?alt=json&max-results=%s";
	private static $activity_apiurl = "https://gdata.youtube.com/feeds/api/events?author=%s&v=2&key=%s&alt=json&max-results=%s";

    private static $post_format = 'video'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'youtube';
        $this->has_ajaxsync = true;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'youtube_username');
        register_setting('reclaim-social-settings', 'youtube_favs_category');
        register_setting('reclaim-social-settings', 'youtube_import_favs');
        register_setting('reclaim-social-settings', 'youtube_activity_category');
        register_setting('reclaim-social-settings', 'youtube_import_activity');
    }

    public function display_settings() {
?>
<?php
        $displayname = __('YouTube', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Get Favs?', 'reclaim'); ?></th>
            <td><input type="checkbox" name="youtube_import_favs" value="1" <?php checked(get_option('youtube_import_favs')); ?> />
            <?php if (get_option('youtube_import_favs')) { ?><input type="submit" class="button button-primary <?php echo $this->shortName(); ?>_resync_items" value="<?php _e('Resync favs with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            <?php if (get_option('youtube_import_favs')) { ?><input type="submit" class="button button-secondary <?php echo $this->shortName(); ?>_count_all_items" value="<?php _e('Count with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            <p class="description"><?php _e('Count value returned by YouTube API might not be accurate.','reclaim'); ?></p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category for Favs', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'youtube_favs_category', 'hide_empty' => 0, 'selected' => get_option('youtube_favs_category'))); ?></td>
        </tr>
<?php if (get_option('google_api_key')) :?>
        <tr valign="top">
            <th scope="row"><?php _e('Get activity? (i.e. videos you gave a thumbs up)', 'reclaim'); ?></th>
            <td><input type="checkbox" name="youtube_import_activity" value="1" <?php checked(get_option('youtube_import_activity')); ?> />
            <?php if (get_option('youtube_import_activity')) { ?><input type="submit" class="button button-primary <?php echo $this->shortName(); ?>_resync_items" value="<?php _e('Resync activity with ajax', 'reclaim'); ?>" data-resync="{type:'activity'}" /><?php } ?>
            <?php if (get_option('youtube_import_activity')) { ?><input type="submit" class="button button-secondary <?php echo $this->shortName(); ?>_count_all_items" value="<?php _e('Count with ajax', 'reclaim'); ?>" data-resync="{type:'activity'}" /><?php } ?>
            <p class="description"><?php _e('Count value returned by YouTube API might not be accurate.','reclaim'); ?></p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category for activity', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'youtube_activity_category', 'hide_empty' => 0, 'selected' => get_option('youtube_activity_category'))); ?></td>
        </tr>
<?php endif;?>
        <tr valign="top">
            <th scope="row"><?php _e('YouTube username', 'reclaim'); ?></th>
            <td><input type="text" name="youtube_username" value="<?php echo get_option('youtube_username'); ?>" /></td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('youtube_username')) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('youtube_username'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);

            if (is_array($rawData)) {
                $data = self::map_data($rawData, 'posts');
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_videos_last_update', current_time('timestamp'));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
            if (get_option('youtube_import_favs')) {
                $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('youtube_username'), self::$count), self::$timeout);
                $rawData = json_decode($rawData, true);

                if (is_array($rawData)) {
                    $data = self::map_data($rawData, 'favs');
                    parent::insert_posts($data);
                    update_option('reclaim_'.$this->shortname.'_favs_last_update', current_time('timestamp'));
                }
                else parent::log(sprintf(__('%s favs returned no data. No import was done', 'reclaim'), $this->shortname));
            }
            if (get_option('youtube_import_activity') && get_option('google_api_key')) {
                $rawData = parent::import_via_curl(sprintf(self::$activity_apiurl, get_option('youtube_username'), get_option('google_api_key'), self::$count), self::$timeout);
                $rawData = json_decode($rawData, true);

                if (is_array($rawData)) {
                    $data = self::map_data($rawData, 'activity');
                    parent::insert_posts($data);
                    update_option('reclaim_'.$this->shortname.'_activity_last_update', current_time('timestamp'));
                }
                else parent::log(sprintf(__('%s activity returned no data. No import was done', 'reclaim'), $this->shortname));
            }

        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }
					
    // called from ajax sync, calls import_tweet()
    public function ajax_resync_items() {
        $type = isset($_POST['type']) ? $_POST['type'] : 'posts';
    	$offset = intval( $_POST['offset'] );
    	$limit = intval( $_POST['limit'] );
    	$count = intval( $_POST['count'] );
    	$next_url = isset($_POST['next_url']) ? $_POST['next_url'] : null;
    
        self::log($this->shortName().' ' . $type . ' resync '.$offset.'-'.($offset + $limit).':'.$count);
    	
    	$return = array(
    		'success' => false,
    		'error' => '',
			'result' => null
    	);
    	
        if (get_option('youtube_username')) {
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            } else {
                if ($type == 'posts') { $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('youtube_username'), self::$count), self::$timeout); }
                if ($type == 'favs') { $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('youtube_username'), self::$count), self::$timeout); }
                if ($type == 'activity') { $rawData = parent::import_via_curl(sprintf(self::$activity_apiurl, get_option('youtube_username'), get_option('google_api_key'), self::$count), self::$timeout); }
            }

            $rawData = json_decode($rawData, true);
            if (is_array($rawData)) {
                $data = self::map_data($rawData, $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));
                
                $new_next_url = null;
                foreach($rawData['feed']['link'] as $link) {
                   if ($link['rel'] == 'next') { $new_next_url = $link['href'];  }
                }

                
                if (!isset($new_next_url)) { 
                    //$return['error'] = sprintf(__('%s %s import done.', 'reclaim'), $type, $this->shortname); 
                    $return['result'] = array(
                        // when we're done, tell ajax script the number of imported items
                        // and that we're done (offset == count)
                        'offset' => $offset + sizeof($data),
                        'count' => $offset + sizeof($data),
                        'next_url' => null,
                    );
                } else {
                    // youtube returns next_url without the api key - which we need to get 
                    // activity. so lets add it, if we have it
                    $google_api_key = get_option('google_api_key');
                    $new_next_url .= isset($google_api_key) ? '&key='.$google_api_key : '';
                    $return['result'] = array(
                        'offset' => $offset + sizeof($data),
                        // take the next pagination url instead of calculating
                        // a self one
                        'next_url' => $new_next_url,
                    );
                }
                $return['success'] = true;
            }
            else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
        }
        else $return['error'] = sprintf(__('%s %s user data missing. No import was done', 'reclaim'), $this->shortname, $type);

    	echo(json_encode($return));
    	 
    	die();
    }

    public function count_items( $type = 'posts' ) {
        if (get_option('youtube_username')) {
            // when it is called from a ajax-resync, post could be set...
            // this name 'type' should be better choosen not to break other things
            // in wordpress maybe mod_instagram_type
            $type = (isset($_POST['type']) && $_POST['type'] != '') ? $_POST['type'] : $type;
            if (!isset($type) || $type == 'posts') { $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('youtube_username'), self::$count), self::$timeout); }
            if ($type == 'favs') { $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('youtube_username'), self::$count), self::$timeout); }
            if ($type == 'activity') { $rawData = parent::import_via_curl(sprintf(self::$activity_apiurl, get_option('youtube_username'), get_option('google_api_key'), 50).'&start-index=100', self::$timeout); } // max-results = 50 gets more accurate count, don't ask me why
            $rawData = json_decode($rawData, true);
            return $rawData['feed']['openSearch$totalResults']['$t'];
        }
        else {
            return false;
        }
    }


    private function map_data($rawData, $type = "posts") {
        $data = array();
        foreach($rawData['feed']['entry'] as $entry) {
            $content = self::get_content($entry, $type);
            $post_content = $content['post_content'];
            $link = $entry["link"][0]['href'];
            $id = $entry['id']['$t'];
            $image = $entry['media$group']['media$thumbnail'][2]['url'];
            $date = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry['published']['$t'])));
            $title = "";

            /*
            *  set post meta galore start
            */
            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;
            $post_meta["_reclaim_post_type"] = $type;
            // in case someone uses WordPress Post Formats Admin UI
            // http://alexking.org/blog/2011/10/25/wordpress-post-formats-admin-ui
            //$post_meta["_format_video_embed"]  = $entry["link"][0]['href'];
            /*
            *  set post meta galore end
            */
            if ($type == "posts") {
                $title = $entry['title']['$t'];
                $category = array(get_option($this->shortname.'_category'));
            } elseif ($type == "favs") {
                $title = sprintf(__('I added a video from %s to my YouTube favorites', 'reclaim'), $entry['author'][0]['name']['$t']);
                $category = array(get_option($this->shortname.'_favs_category'));
            } elseif ($type == "activity" && $entry['category'][1]['term'] == "video_rated") {
                $video_id = $entry['yt$videoid']['$t'];
                $id = 'http://gdata.youtube.com/feeds/api/videos/'.$video_id;
                $title = sprintf(__('I rated a video on YouTube', 'reclaim'));
                $category = array(get_option($this->shortname.'_activity_category'));
                $content['embed_code'] = '<div class="ytembed yt"><iframe width="625" height="352" src="http://www.youtube.com/embed/'.$video_id.'" frameborder="0" allowfullscreen></iframe></div>';
                $post_content = "[embed_code]";
                $link = 'https://www.youtube.com/watch?v='.$video_id;
                $image = "";
                $date = $entry['updated']['$t'];
            } 

            if ($title != "") {
                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => $category,
                    'post_format' => self::$post_format,
                    'post_date' => $date,
                    'post_content' => $post_content,
                    'post_title' => $title,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'ext_permalink' => $link,
                    'ext_embed_code' => $content['embed_code'],
                    'ext_image' => $image,
                    'ext_guid' =>  $id,
                    'post_meta' => $post_meta
                );
            }

        }
        return $data;
    }

    private function get_content($entry, $type="posts") {
        $video_id = 0;
        $post_content = '';
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $entry["link"][0]['href'], $match)) {
            $video_id = $match[1];
        }
        $embed_code = '<div class="ytembed yt"><iframe width="625" height="352" src="http://www.youtube.com/embed/'.$video_id.'" frameborder="0" allowfullscreen></iframe></div>';
        if ($type == "favs") {
            $post_content =  sprintf(__('<a href="%s">%s</a> from <a href="%s">%s</a>:'), $entry["link"][0]['href'], $entry['title']['$t'], $entry['author'][0]['uri']['$t'], $entry['author'][0]['name']['$t']);
            //$post_content .= $embed_code;
            $post_content .= "[embed_code] ";
            if ($entry['content']['$t'] != "") {
                $post_content .= '<blockquote>'.make_clickable($entry['content']['$t']).'</blockquote>';
            }
        } else {
            //$post_content = $embed_code;
            $post_content = "[embed_code] ";
            $post_content .= make_clickable($entry['content']['$t']);
        }
        $content = array(
            'post_content' => $post_content,
            'embed_code' => $embed_code
        );

        return $content;
    }
}
?>
