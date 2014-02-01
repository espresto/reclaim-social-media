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

class foursquare_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.foursquare.com/v2/users/self/checkins?limit=%s&oauth_token=%s&v=20140120";
	private static $apiurl_count= "https://api.foursquare.com/v2/users/self/checkins?limit=1&oauth_token=%s&v=20140120";

    private static $timeout = 15;
    private static $count = 31; // maximum 31 days
    private static $post_format = 'status'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'foursquare';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'foursquare_user_id');
        register_setting('reclaim-social-settings', 'foursquare_client_id');
        register_setting('reclaim-social-settings', 'foursquare_client_secret');
        register_setting('reclaim-social-settings', 'foursquare_access_token');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='foursquare') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option('foursquare_user_id', $user_profile->identifier);
                update_option('foursquare_user_name', $user_profile->displayName);
                update_option('foursquare_access_token', $user_access_tokens->access_token);
            }
            if(session_id()) {
                session_destroy ();
            }
        }
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3 id=""><?php _e('foursquare', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('foursquare client id', 'reclaim'); ?></th>
            <td><input type="text" name="foursquare_client_id" value="<?php echo get_option('foursquare_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('foursquare client secret', 'reclaim'); ?></th>
            <td><input type="text" name="foursquare_client_secret" value="<?php echo get_option('foursquare_client_secret'); ?>" />
            <input type="hidden" name="foursquare_user_id" value="<?php echo get_option('foursquare_user_id'); ?>" />
            <input type="hidden" name="foursquare_access_token" value="<?php echo get_option('foursquare_access_token'); ?>" />
            <p class="description">Get your Foursqaure client and credentials <a href="https://de.foursquare.com/developers/apps">here</a>. Use <code><?php echo plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/') ?></code> as "Redirect URI"</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('foursquare_client_id')!="")
            && (get_option('foursquare_client_secret')!="")

            ) {
                $link_text = __('Authorize with Foursquare', 'reclaim');
                if ( (get_option('foursquare_user_id')!="") && (get_option('foursquare_access_token')!="") ) {
                    echo sprintf(__('<p>Foursquare is authorized as %s</p>', 'reclaim'), get_option('foursquare_user_name'));
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
                _e('enter Foursquare app id and secret', 'reclaim');
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
                "Foursquare" => array(
                    "enabled" => true,
                    "keys"    => array ( "id" => get_option('foursquare_client_id'), "secret" => get_option('foursquare_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/Foursquare.php',
                        "class" => "Hybrid_Providers_Foursquare",
                    ),
                ),
            ),
        );
        return $config;
    }

    public function import() {
        if (get_option('foursquare_user_id') && get_option('foursquare_access_token') ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, self::$count, get_option('foursquare_access_token')), self::$timeout);
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

    /**
     * Maps foursquare checkins data to wp-content data. Check https://developer.foursquare.com/docs/users/checkins for more info.
     * @param array $rawData
     * @return array
     */
    private function map_data(array $rawData) {
        $data = array();
        foreach($rawData['response']['checkins']['items'] as $checkin){

                $id = $checkin['id'];
                // there might be more than one image (or) none. 
                // lets take only the first one
                if (($checkin['photos']['count'] > 0) && ($checkin['photos']['items'][0]['visibility'] == "public") ) {
                    $image_url = $checkin['photos']['items'][0]['prefix']
                                .$checkin['photos']['items'][0]['width']
                                . 'x' 
                                .$checkin['photos']['items'][0]['height']
                                .$checkin['photos']['items'][0]['suffix'];
                } else {
                    $image_url = '';
                }
                $tags = '';
                $link = 'https://foursquare.com/user/'.get_option('foursquare_user_id').'/checkin/'.$id;
                $content = '<p>'.sprintf(__('Checked in to <a href="%s">%s</a>', 'reclaim'), $link, $checkin['venue']['name']).'</p>';
                // added htmlentities() just to be sure
                if (isset($checkin['shout'])) { $content .= '<blockquote>'.htmlentities($checkin['shout'],ENT_NOQUOTES, "UTF-8").'</blockquote>'; }
                //if (isset($checkin['shout'])) { $content .= '<blockquote>'.$checkin['shout'].'</blockquote>'; }
                
                $title = sprintf(__('Checked in to %s', 'reclaim'), $checkin['venue']['name']);

                //$post_meta = $this->construct_post_meta($day);
                $lat = $checkin['venue']['location']['lat'];
                $lon = $checkin['venue']['location']['lng'];
                $post_meta["geo_latitude"] = $lat;
                $post_meta["geo_longitude"] = $lon;
                $post_meta["venueCountry"] = $checkin['venue']['location']['country'];
                $post_meta["venueName"] = $checkin['venue']['name'];
                $post_meta["foursquareVenueId"] = $checkin['venue']['id'];

                $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
                $post_meta["_post_generator"] = $this->shortname;

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => self::$post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $checkin["createdAt"])),
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
        return $data;
    }
    
    public function count_items() {
    	if (get_option('foursquare_user_id') && get_option('foursquare_access_token') ) {
    		$rawData = parent::import_via_curl(sprintf(self::$apiurl_count, get_option('foursquare_access_token')), self::$timeout);
    		$rawData = json_decode($rawData, true);
    		return $rawData['response']['checkins']['count'];
    	}
    	else {
    		return false;
    	}
    }
    

    private function construct_content(array $checkin) {}

    /**
     * Returns meta data for every activity in a foursquare summary data day.
     * @param array $day Data return from moves api. Known possible keys so far:
     *  activity, distance, duration, steps (not if activity == cyc), calories
     * @return array
     */
    private function construct_post_meta(array $checkin) {}
}