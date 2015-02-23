<?php
/*  Copyright 2013-2014 diplix
                   2014 Christian Muehlhaeuser <muesli@gmail.com>

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

class facebook_reclaim_module extends reclaim_module {
    private static $apiurl= "https://graph.facebook.com/%s/feed/?limit=%s&locale=%s&access_token=%s";
    private static $photo_url = 'https://graph.facebook.com/%s/picture?access_token=%s';
    private static $count = 40;
    private static $max_import_loops = 1;
    private static $timeout = 60;

    public function __construct() {
        $this->shortname = 'facebook';
        $this->has_ajaxsync = true;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'facebook_username');
        register_setting('reclaim-social-settings', 'facebook_user_id');
        register_setting('reclaim-social-settings', 'facebook_app_id');
        register_setting('reclaim-social-settings', 'facebook_app_secret');
        register_setting('reclaim-social-settings', 'facebook_oauth_token');
        register_setting('reclaim-social-settings', 'facebook_import_non_public_items');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='facebook') && (isset($_SESSION['login'])) ) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $login              = $_SESSION['login'];
            $error              = $_SESSION['e'];

            if ($error!="") {
                echo '<div class="error"><p>'.esc_html( $error ).'</p></div>';
            }
            else {
                update_option('facebook_user_id', $user_profile->identifier);
                update_option('facebook_username', $user_profile->displayName);
                update_option('facebook_oauth_token', $user_access_tokens->access_token);
            }

            if ( $login == 0 ) {
                update_option('facebook_user_id', '');
                update_option('facebook_username', '');
                update_option('facebook_oauth_token', '');
            }

//            print_r($_SESSION);
//            echo "<pre>" . print_r( $user_profile, true ) . "</pre>" ;
//            echo $user_access_token->accessToken;
//            $user_profile->displayName
            if(session_id()) {
                session_destroy ();
            }
        }

?>
<?php
        $displayname = __('Facebook', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Facebook user ID', 'reclaim'); ?></th>
            <td><?php //echo get_option('facebook_user_id'); ?>
            <input type="text" name="facebook_user_id" value="<?php echo get_option('facebook_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Facebook user name', 'reclaim'); ?></th>
            <td><?php //echo get_option('facebook_username'); ?>
            <input type="text" name="facebook_username" value="<?php echo get_option('facebook_username'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="facebook_import_non_public_items"><?php _e('Include nonpublic items', 'reclaim'); ?></label></th>
            <td><input type="checkbox" name="facebook_import_non_public_items" value="1" <?php checked(get_option('facebook_import_non_public_items')); ?> /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="facebook_app_id"><?php _e('Facebook app id', 'reclaim'); ?></label></th>
            <td><input type="text" name="facebook_app_id" value="<?php echo get_option('facebook_app_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="facebook_app_secret"><?php _e('Facebook app secret', 'reclaim'); ?></label></th>
            <td><input type="text" name="facebook_app_secret" value="<?php echo get_option('facebook_app_secret'); ?>" />
            <input type="hidden" name="facebook_oauth_token" value="<?php echo get_option('facebook_oauth_token'); ?>" />
            <p class="description"><?php echo sprintf(__('Some help on how to get the keys and secrets <a href="%s">here</a>.','reclaim'), 'https://github.com/espresto/reclaim-social-media/wiki/Get-App-Credentials-for-Facebook'); ?></p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"></th>
            <td>
<?php
            if ((get_option('facebook_app_id')!="") && (get_option('facebook_app_secret')!="")) {
                $link_text = __('Authorize with Facebook', 'reclaim');
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('facebook_user_id')!="") && (get_option('facebook_oauth_token')!="") ) {
                    echo sprintf(__('<p>Facebook is authorized as %s</p>', 'reclaim'), get_option('facebook_username'));
                    $link_text = __('Authorize again', 'reclaim');
                }

                // send to helper script
                // put all configuration into session
                // todo
                $config = self::construct_hybridauth_config();
                $callback =  urlencode(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=reclaim/reclaim.php&link=1&mod='.$this->shortname);

                $_SESSION[$this->shortname]['config'] = $config;
//                $_SESSION[$this->shortname]['mod'] = $this->shortname;

                echo '<a class="button button-secondary" href="'
                    .plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
                    .'?'
                    .'&mod='.$this->shortname
                    .'&callbackUrl='.$callback
                    .'">'.$link_text.'</a>';
                echo '<a class="button button-secondary" href="'
                    .plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
                    .'?'
                    .'&mod='.$this->shortname
                    .'&callbackUrl='.$callback
                    .'&login=0'
                    .'">logout</a>';
            }
            else {
                echo 'enter facebook app id and facebook app secret';
            }
?>
            </td>
        </tr>

<?php
    }

    public function construct_hybridauth_config() {
        $config = array(
            // "base_url" the url that point to HybridAuth Endpoint (where the index.php and config.php are found)
            "base_url" => plugins_url('/vendor/hybridauth/hybridauth/hybridauth/', dirname(__FILE__) ),
            "providers" => array(
                "Facebook" => array(
                    "enabled" => true,
                    "keys"    => array(
                        "id" => get_option('facebook_app_id'),
                        "secret" => get_option('facebook_app_secret'),
                    ),
                    "scope" => "read_stream, user_photos"
                ),
            ),
        );
        return $config;
    }

    public function import($forceResync) {
        if (!get_option('facebook_oauth_token') && get_option('facebook_app_id') && get_option('facebook_app_secret')) {
            parent::log(sprintf(__('getting FB token', 'reclaim'), $this->shortname));
        }

        if (get_option('facebook_username') && get_option('facebook_user_id') &&  get_option('facebook_oauth_token')) {
            $lastupdate = get_option('reclaim_'.$this->shortname.'_last_update');
            $urlNext = sprintf(self::$apiurl, get_option('facebook_user_id'), self::$count, substr(get_bloginfo('language'), 0, 2), get_option('facebook_oauth_token'));
            if (strlen($lastupdate) > 0 && !$forceResync) {
                $urlNext .= "&since=" . $lastupdate;
            }
            // get GMT time. thats what we need, not local time
            $newlastupdate = current_time('timestamp', 1);

            $errors = 0;
            $i = 0;
            while (strlen($urlNext) > 0) {
                $rawData = parent::import_via_curl($urlNext, self::$timeout);
                if ($rawData) {
                    $errors = 0;
                    $rawData = json_decode($rawData, true);

                    if (isset($rawData["paging"]["next"])) {
                        $urlNext = $rawData["paging"]["next"];
                    } else {
                        $urlNext = "";
                    }

                    $data = self::map_data($rawData);
                    parent::insert_posts($data);

                    if (
                        !$forceResync && count($data) > 0
                        && intval($rawData['data'][count($rawData['data'])-1]["created_time"]) < intval($lastupdate)
                        || $i > self::$max_import_loops
                        ) {
                        // abort requests if we've already seen these events
                        $urlNext = "";
                    }
                }
                else {
                    // throw exception or end?
                    if (++$errors == 3) {
                        parent::log(sprintf(__('%s ended with an error. Aborted getting %s', 'reclaim'), $this->shortname, $urlNext));
                        return;
                    }
                    parent::log(sprintf(__('%s ended with an error. Continue anyway with %s', 'reclaim'), $this->shortname, $urlNext));
                }
            $i++;
            }

            update_option('reclaim_'.$this->shortname.'_last_update', $newlastupdate);
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
    	
        if (get_option('facebook_username') && get_option('facebook_user_id') &&  get_option('facebook_oauth_token')) {
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            } else {
                $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('facebook_user_id'), self::$count, substr(get_bloginfo('language'), 0, 2), get_option('facebook_oauth_token')), self::$timeout);
            }

            $rawData = json_decode($rawData, true);
            if (!($rawData['error']['code'])) {
                $data = self::map_data($rawData, $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));

                if (!isset($rawData['paging']['next'])) { 
                    //$return['error'] = sprintf(__('%s %s import done.', 'reclaim'), $type, $this->shortname); 
                    $return['result'] = array(
                        // when we're done, tell ajax script the number of imported items
                        // and that we're done (offset == count)
                        'offset' => $offset + sizeof($data),
                        'count' => $offset + sizeof($data),
                        'next_url' => null,
                    );
                } else {
                    $return['result'] = array(
                        'offset' => $offset + sizeof($data),
                        // take the next pagination url instead of calculating
                        // a self one
                        'next_url' => $rawData['paging']['next'],
                    );
                }
                $return['success'] = true;
            }
            elseif (isset($rawdata['error']['code']) != 200) {
                $return['error'] = $rawData['error']['message'] . " (Error code " . $rawData['error']['code'] . ")";
            }
            else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
        }
        else $return['error'] = sprintf(__('%s %s user data missing. No import was done', 'reclaim'), $this->shortname, $type);

    	echo(json_encode($return));
    	 
    	die();
    }


    public function count_items() {
    	return 999999;
    }
    private function filter_item($entry) {
        /*
        * filtering
        * we don't want tweets, or ifft-stuff, cause that would
        * duplicate the other feeds (or would it not?)
        */
        if ( 
                    (
                    $entry['application']['name'] != "Twitter" // no tweets
                    && $entry['application']['namespace'] != "rssgraffiti" // no blog stuff
                    && $entry['application']['namespace'] != "NetworkedBlogs" // no  NetworkedBlogs syndication
                    && $entry['application']['namespace'] != "ifthisthenthat" // no instagrams and ifttt
                    && $entry['application']['namespace'] != "friendfeed" // no friendfeed
                    )
               && ( $entry['status_type'] != "approved_friend" ) // no new friend anouncements
               // difficult: if privacy value is empty, is it public? it seems to me, but i'm not sure
               && ( get_option('facebook_import_non_public_items') || ($entry['privacy']['value'] == "") || ($entry['privacy']['value'] == "EVERYONE") ) // privacy OK? is it public?
               && $entry['from']['id'] == get_option('facebook_user_id') // only own stuff $user_name stuff
            )
        { return false; }
        else 
        { return true; }
    }

    private function map_data($rawData, $type="posts") {
        $data = array();
        if (!is_array($rawData['data'])) {
            // sometimes it's not an array
            return false;
        }
        foreach($rawData['data'] as $entry) {
            if (!self::filter_item($entry)) {
                /*
                * OK, everything is filtered now, lets proceed ...
                */
                $link = self::get_link($entry, 0);
                $image = self::get_image_url($entry);
                $title = self::get_title($entry);
                $content = self::construct_content($entry, $link, $image);
                $post_format = self::get_post_format($entry);
                if (($post_format=="link") && isset($entry['name'])) {
                    $title = $entry['name'];
                    // in case someone uses WordPress Post Formats Admin UI
                    // http://alexking.org/blog/2011/10/25/wordpress-post-formats-admin-ui
                    $post_meta["_format_link_url"]  = $link;
                }
                else {
                    unset($post_meta["_format_link_url"]);
                }

                /*
                *  set post meta galore start
                */
                if (isset($entry['place'])) {
                    $post_meta["geo_latitude"] = $entry['place']['location']['latitude'];
                    $post_meta["geo_longitude"] = $entry['place']['location']['longitude'];
                }
                else {
                    unset($post_meta["geo_latitude"]);
                    unset($post_meta["geo_longitude"]);
                }
                // hidden fields for adding syndication links later
                $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
                $post_meta["_post_generator"] = $this->shortname;
                $post_meta["_reclaim_post_type"] = $type;

                // setting for social plugin (https://github.com/crowdfavorite/wp-social/)
                // to be able to retrieve facebook comments and likes (if social is 
                // installed)
                $from = $entry['from']['id'];
                $id = $entry['id'];
                $broadcasted_ids = array();
                $broadcasted_ids[$this->shortname][$from][$id] = array('message' => '','urls' => '');
                $post_meta["_social_broadcasted_ids"] = $broadcasted_ids;
                $tags = [];
                if ($entry['application']['namespace']){
                	$post_meta['applicationName'] = $entry['application']['name'];
                	$post_meta['applicationNamespace'] = $entry['application']['namespace'];
                	$tags[] = 'fb-app:'.$entry['application']['namespace'];
                }

                /*
                *  set post meta galore end
                */

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => $post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created_time"]))),
                    'post_content' => $content,
                    'post_title' => reclaim_text_add_more(reclaim_text_excerpt($title, 50, 0, 1, 0),' …', ''),
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'ext_permalink' => $link,
                    'ext_image' => $image,
                    'ext_guid' => $entry["id"],
                    'post_meta' => $post_meta,
                	'tags_input' => $tags,
                );
            }
        }
        return $data;
    }

    private function get_post_format($entry) {
        if ($entry['type']=="link") {
            $post_format = "link";
        }
        elseif ($entry['type']=="photo") {
            $post_format = "image";
        }
        elseif ($entry['type']=="status") {
            $post_format = "status";
            if (!isset($entry["story"]) || $entry["story"] == "") {
                $post_format = "aside";
            }
        }
        elseif ($entry['type']=="video") {
            $post_format = "video";
        }
        else {
            $post_format = "aside";
        }
        return $post_format;
    }

    private function get_link($entry, $fb_only = 0) {
        if (isset($entry["link"]) && !$fb_only) {
            $link = htmlentities($entry["link"]);
        } else {
            $ids = explode('_', $entry['id']);
            $id = $ids[1];
            $link = "https://www.facebook.com/".$entry['from']['id']."/posts/".$id;
        }
        return $link;
    }

    private function get_image_url($entry) {
        $image = '';
        if ($entry['type'] === 'photo') {
        	$url = sprintf(self::$photo_url, $entry['object_id'], get_option('facebook_oauth_token'));
        	return $url;
        }
        if (isset($entry['picture'])) {
            $image = $entry['picture'];
            if ($image) {
                $parse_image_url = parse_url($image);
                if (isset($parse_image_url['query']) && $parse_image_url['query'])  {
                    $parts = explode('&', $parse_image_url['query']);
                    foreach ($parts as $p) {
                        $item = explode('=', $p);
                        if ($item[0] == 'url') $image = urldecode($item[1]);
                    }
                }
            }
        }
        //get larger image instead of _s (small)
        $image = str_replace( '_s.', '_n.', $image );
        $image = str_replace( '_q.', '_n.', $image );
        return $image;
    }

    private function get_title($entry) {
        if (isset($entry["story"]) && $entry["story"]) {
            $title = $entry['story'];
        }
        elseif (isset($entry["message"]) && $entry["message"]) {
            $title = $entry['message'];
        }
        else {
            $title = __('Facebook activity', 'reclaim');
        }
        return $title;
    }

    private function construct_content($entry, $link = '', $image  = ''){
        $description = "";
        $post_format = "";
        $message = htmlentities($entry["message"], ENT_NOQUOTES, "UTF-8");
        if ($image == "http://www.facebook.com/images/devsite/attachment_blank.png") {
            $image ="";
        }

        if (isset($entry["story"]) && $entry["story"]) { // story
            $description .= $entry["story"];
            if (isset($message) && $message) {
                $description .= '<blockquote class="fb-story">'.$message.'</blockquote>';
            }
        }
        elseif (isset($entry['application']) && $entry['application']['name']=='Likes') { // likes?
            if (isset($entry['name']) && $entry['name']) {
                $entry_name = $entry['name'];
            }
            else {
                $entry_name = __('multiple items', 'reclaim');  // manchmal liefert fb nix (nochmal id checken?)
            }

            $description = "like. ";
            $description .= sprintf(__('%s liked <a href="%s">%s</a>', 'reclaim'), get_option('facebook_username'), $link, $entry_name);
        }
        elseif (isset($entry["type"]) && $entry["type"] == 'status') { // status?
            if (!isset($entry["story"]) || $entry["story"] == "") {  // no story?
                if (isset($message)) {
                    $description .= $message;
                }
            } else { // story?
                $description = $entry["story"];
                if (isset($message)) {
                    $description = '<blockquote>'.$message.'</blockquote>';
                }
            }
        }
        else {
            if (isset($message)) {
                $description = '<div class="fbmessage"><p>'.$message.'</p></div>';
            }
        }

        $description = make_clickable($description);
        //now other's content
        $fblink_description = "";
        if ($image!="") {
            $fblink_description .= '<div class="fbimage">'
            //.'<img src="'.$image.'">'
            // alternativly use attaches image
            .'[gallery columns=1 size="large" link="file"]'
            .'</div>';

    // if it's an image, render it right away, not in the teaser
            if ( ($entry['type']=='photo') && (!isset($entry['name'])) ) {
                $description .=
                    '<div class="fbimage">'
//                    .'<img src="'.$image.'">'
                    .'[gallery columns=1 size="large" link="file"]'
                    .'</div>';
            }
        }
        if (isset($entry['name']) && $entry['name']) {
            $fblink_description .= '<div class="fblink-title"><a href="'.$link.'">'.$entry["name"].'</a></div>';
        }
        if (isset($entry['properties']) && $entry['properties']) {
            $fblink_description .= '<div class="fblink-title props"><a href="'.$entry['properties'][0]['href'].'">'.$entry['properties'][0]['name'].' '.$entry['properties'][0]['text'].'</a></div>';
        }
        if (isset($entry["caption"]) && $entry["caption"]) {
            if (isset($entry["description"]) && $entry["description"]) {
                $fblink_description .=	'<p class="clearfix fblink-caption">'.$entry["caption"].'</p>';
            }
            else {
                $fblink_description .=	'<p class="fblink-description caption">'.$entry["caption"].'</p>';
            }
        }
        if (isset($entry["description"]) && $entry["description"]) {
            $fblink_description .= '<p class="fblink-description">'.$entry["description"].'</p>';
        }
        // only if there is a name, it's a real teaser
        if ( (isset($entry['name']) && $entry['name']) ) {
            $fblink_description = make_clickable($fblink_description);
            $description .= '<blockquote class="clearfix fbname fblink">'.$fblink_description.'</blockquote>'; // other's content
        }

        $fb_link = self::get_link($entry, 1);
        $description .= '<p class="viewpost-facebook">(<a rel="syndication" href="'.$fb_link.'">'.__('View on Facebook', 'reclaim').'</a>)</p>';
        // add embedcode
        $description = '<div class="fb-post" data-href="'.$fb_link.'" data-width="100%">'
            .$description
            .'</div>';

        return $description;
    }
}
?>
