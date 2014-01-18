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

class facebook_reclaim_module extends reclaim_module {
    private static $apiurl= "https://graph.facebook.com/%s/feed/?limit=%s&locale=%s&access_token=%s";
    private static $count = 200;
    private static $timeout = 20;

    public function __construct() {
        $this->shortname = 'facebook';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'facebook_username');
        register_setting('reclaim-social-settings', 'facebook_user_id');
        register_setting('reclaim-social-settings', 'facebook_app_id');
        register_setting('reclaim-social-settings', 'facebook_app_secret');
        register_setting('reclaim-social-settings', 'facebook_oauth_token');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='facebook') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error              = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
                //echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $e ),'</p></div>';
            }
            else {
                update_option('facebook_user_id', $user_profile->identifier);
                update_option('facebook_username', $user_profile->displayName);
                update_option('facebook_oauth_token', $user_access_tokens->access_token);
            }
//            print_r($_SESSION);
//            echo "<pre>" . print_r( $user_profile, true ) . "</pre>" ;
//            echo $user_access_token->accessToken;
//            $user_profile->displayName
            if (session_id()) {
                session_destroy ();
            }
        }

?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Facebook', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('facebook user ID', 'reclaim'); ?></th>
            <td><?php echo get_option('facebook_user_id'); ?>
            <input type="hidden" name="facebook_user_id" value="<?php echo get_option('facebook_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook user name', 'reclaim'); ?></th>
            <td><?php echo get_option('facebook_username'); ?>
            <input type="hidden" name="facebook_username" value="<?php echo get_option('facebook_username'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook app id', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_id" value="<?php echo get_option('facebook_app_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook app secret', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_secret" value="<?php echo get_option('facebook_app_secret'); ?>" />
            <input type="hidden" name="facebook_oauth_token" value="<?php echo get_option('facebook_oauth_token'); ?>" />
            </td>
        </tr>
        </tr>
        </tr>
        <tr valign="top">
            <th scope="row"></th>
            <td>
<?php
            if ((get_option('facebook_app_id')!="") && (get_option('facebook_app_secret')!="")) {
                $link_text = 'Authorize with Facebook';
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('facebook_user_id')!="") && (get_option('facebook_oauth_token')!="") ) {
                    echo '<p>Facebook authorized as '.get_option('facebook_username').'</p>';
                    $link_text = 'Authorize again';
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
                        "secret" => get_option('facebook_app_secret')
                    ),
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
            $lastupdate = current_time('timestamp');

            while (strlen($urlNext) > 0) {
                parent::log(sprintf(__('GETTING for %s from %s', 'reclaim'), $this->shortname, $urlNext));
                $rawData = parent::import_via_curl($urlNext, self::$timeout);
                $rawData = json_decode($rawData, true);

                if (isset($rawData["paging"]["next"])) {
                    $urlNext = $rawData["paging"]["next"];
                } else {
                    $urlNext = "";
                }

                $data = self::map_data($rawData);
                parent::insert_posts($data);
            }

            update_option('reclaim_'.$this->shortname.'_last_update', $lastupdate);
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function map_data($rawData) {
        $data = array();
        foreach($rawData['data'] as $entry){
            if (    (
                    /*
                     * filtering
                     * we don't want tweets, or ifft-stuff, cause that would
                     * duplicate the other feeds (or would it not?)
                     */
                    (
                    $entry['application']['name'] != "Twitter" // no tweets
                    && $entry['application']['namespace'] != "rssgraffiti" // no blog stuff
                    && $entry['application']['namespace'] != "ifthisthenthat" // no instagrams and ifttt
                    )
               )
               && ( $entry['status_type'] != "approved_friend" ) // no new friend anouncements
               && ( (!isset($entry['privacy']['value']) ) || ($entry['privacy']['value'] == "EVERYONE") ) // privacy OK? is it public?
               && $entry['from']['id'] == get_option('facebook_user_id') // only own stuff $user_namestuff
            ) {
                /*
                 * OK, everything is filtered now, lets proceed ...
                 */
                $link = self::get_link($entry);
                $image = self::get_image_url($entry);
                $title = self::get_title($entry);
                $excerpt = self::construct_content($entry, $link, $image);
                $post_format = self::get_post_format($entry);
                if (($post_format=="link") && isset($entry['name'])) {
                    $title = $entry['name'];
                }

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => $post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created_time"]))),
                    'post_content' => $excerpt,
//                    'post_excerpt' => $excerpt,
                    'post_title' => reclaim_text_add_more(reclaim_text_excerpt($title, 50, 0, 1, 0),' …', ''),
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'ext_permalink' => $link,
                    'ext_image' => $image,
                    'ext_guid' => $entry["id"]
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

    private function get_link($entry) {
        if (isset($entry["link"])) {
            $link = htmlentities($entry["link"]);
        } else {
            $id = substr(strstr($entry['id'], '_'),1);
            $link = "https://www.facebook.com/".get_option('facebook_user_id')."/posts/".$id;
        }
        return $link;
    }

    private function get_image_url($entry) {
        $image = '';
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
        if ($image == "http://www.facebook.com/images/devsite/attachment_blank.png") {
            $image ="";
        }

        if (isset($entry["story"]) && $entry["story"]) { // story
            $description .= $entry["story"];
            if (isset($entry["message"]) && $entry["message"]) {
                $description .= '<blockquote class="fb-story">'.$entry["message"].'</blockquote>';
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
                if (isset($entry["message"])) {
                    $description .= $entry["message"];
                }
            } else { // story?
                $description = $entry["story"];
                if (isset($entry["message"])) {
                    $description = '<blockquote>'.$entry["message"].'</blockquote>';
                }
            }
        }
        else {
            if (isset($entry["message"])) {
                $description = '<div class="fbmessage">'.$entry["message"].'</div>';
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

        $fb_link = "https://www.facebook.com/".$entry['from']['id']."/posts/".substr($entry['id'], 10);
        $description .= '<p class="fbviewpost-facebook">(<a href="'.$fb_link.'">'.__('View on Facebook', 'reclaim').'</a>)</p>';
        // add embedcode
        $description = '<div class="fb-post" data-href="'.$fb_link.'" data-width="100%">'
            .$description
            .'</div>';

        return $description;
    }
}
?>
