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

class instagram_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.instagram.com/v1/users/%s/media/recent/?access_token=%s&count=%s";
    private static $fav_apiurl = "https://api.instagram.com/v1/users/self/media/liked/?foo=%s&access_token=%s&count=%s";
    private static $apiurl_count = "https://api.instagram.com/v1/users/%s/?access_token=%s";
    private static $timeout = 15;
    private static $count = 40;
    private static $post_format = 'image'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'instagram';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'instagram_user_id');
        register_setting('reclaim-social-settings', 'instagram_user_name');
        register_setting('reclaim-social-settings', 'instagram_client_id');
        register_setting('reclaim-social-settings', 'instagram_client_secret');
        register_setting('reclaim-social-settings', 'instagram_access_token');
        register_setting('reclaim-social-settings', 'instagram_favs_category');
        register_setting('reclaim-social-settings', 'instagram_import_favs');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='instagram') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option('instagram_user_id', $user_profile->identifier);
                update_option('instagram_user_name', $user_profile->displayName);
                update_option('instagram_access_token', $user_access_tokens->access_token);
            }
//            print_r($_SESSION);
//            echo "<pre>" . print_r( $user_profile, true ) . "</pre>" ;
//            echo $user_access_tokens->accessToken;
//            echo $user_profile->displayName;
            if(session_id()) {
                session_destroy ();
            }
        }
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3><?php _e('instagram', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Get Favs?', 'reclaim'); ?></th>
            <td><input type="checkbox" name="instagram_import_favs" value="1" <?php checked(get_option('instagram_import_favs')); ?> />
            <input type="submit" class="button button-primary <?php echo $this->shortName(); ?>_resync_items" value="<?php _e('Resync favs with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category for Favs', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'instagram_favs_category', 'hide_empty' => 0, 'selected' => get_option('instagram_favs_category'))); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram user id', 'reclaim'); ?></th>
            <td><p><?php echo get_option('instagram_user_id'); ?></p>
            <input type="hidden" name="instagram_user_id" value="<?php echo get_option('instagram_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram user name', 'reclaim'); ?></th>
            <td><p><?php echo get_option('instagram_user_name'); ?></p>
            <input type="hidden" name="instagram_user_name" value="<?php echo get_option('instagram_user_name'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram client id', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="instagram_client_id" value="<?php echo get_option('instagram_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Instagram client secret', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="instagram_client_secret" value="<?php echo get_option('instagram_client_secret'); ?>" />
            <input type="hidden" name="instagram_access_token" value="<?php echo get_option('instagram_access_token'); ?>" />
            <p class="description">
            
            <?php
            echo sprintf(__('Get your Instagram client and credentials <a href="%s">here</a>. ','reclaim'),'http://instagram.com/developer/');
            echo sprintf(__('Use <code>%s</code> as "Redirect URI"','reclaim'),plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/')); 
            ?>
            </p>
           </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('instagram_client_id')!="")
            && (get_option('instagram_client_secret')!="")

            ) {
                $link_text = __('Authorize with Instagram', 'reclaim');
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('instagram_user_id')!="") && (get_option('instagram_access_token')!="") ) {
                    echo sprintf(__('<p>Instagram authorized as %s</p>', 'reclaim'), get_option('instagram_user_name'));
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

            }
            else {
                echo _e('enter instagram app id and secret', 'reclaim');
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
                "Instagram" => array(
                    "enabled" => true,
                    "keys"    => array ( "id" => get_option('instagram_client_id'), "secret" => get_option('instagram_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../vendor/hybridauth/hybridauth/additional-providers/hybridauth-instagram/Providers/Instagram.php',
                        "class" => "Hybrid_Providers_Instagram",
                    ),
                    "scope" => "basic comments",
                ),
            ),
        );
        return $config;
    }

    public function import($forceResync) {
        if (get_option('instagram_user_id') && get_option('instagram_access_token') ) {
            //get instagrams
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('instagram_user_id'), get_option('instagram_access_token'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
                $data = self::map_data($rawData, 'posts');
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_posts_last_update', current_time('timestamp'));
                parent::log(sprintf(__('END %s import', 'reclaim'), $this->shortname));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));

            if (get_option('instagram_import_favs')) {
            //get favs
            $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('instagram_access_token'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
                    $data = self::map_data($rawData, 'favs');
                    parent::insert_posts($data);
                    update_option('reclaim_'.$this->shortname.'_favs_last_update', current_time('timestamp'));
                    parent::log(sprintf(__('END %s favs import', 'reclaim'), $this->shortname));
                }
                else parent::log(sprintf(__('%s favs returned no data. No import was done', 'reclaim'), $this->shortname));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    public function ajax_resync_items() {
        // the type comes magically back from the
        // data-resync="{type:'favs'}" - attribute of the submit-button.
        $type = isset($_POST['type']) ? $_POST['type'] : 'posts';
        $offset = intval( $_POST['offset'] );
        $limit = intval( $_POST['limit'] );
        $count = intval( $_POST['count'] );
        $next_url = isset($_POST['next_url']) ? $_POST['next_url'] : '';
    
        self::log($this->shortName().' ' . $type . ' resync '.$offset.'-'.($offset + $limit).':'.$count);
         
        $return = array(
            'success' => false,
            'error' => '',
            'result' => null
        );
                
        if (get_option('instagram_user_id') && get_option('instagram_access_token') ) {
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            }
            else {
                $apiurl_ = ($type == 'posts' ? self::$apiurl : self::$fav_apiurl);
                $rawData = parent::import_via_curl(sprintf($apiurl_, get_option('instagram_user_id'), get_option('instagram_access_token'), self::$count, $min_id), self::$timeout);
            }

            $rawData = json_decode($rawData, true);
            if ($rawData['meta']['code'] == 200) {
                $data = self::map_data($rawData, $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));
                
                if (!isset($rawData['pagination']['next_url'])) { 
                    //$return['error'] = sprintf(__('%s %s import done.', 'reclaim'), $type, $this->shortname); 
                    $return['result'] = array(
                        // when we're done, tell ajax script the number of imported items
                        // and that we're done (offset == count)
                        'offset' => $offset,
                        'count' => $offset ,
                        'next_url' => $rawData['pagination']['next_url'],
                    );
                } else {
                    $return['result'] = array(
                        'offset' => $offset + sizeof($data),
                        // take the next pagination url instead of calculating
                        // a self one
                        'next_url' => $rawData['pagination']['next_url'],
                    );
                }
                $return['success'] = true;
            }
            elseif (isset($rawdata['meta']['code']) != 200) {
                $return['error'] = $rawData['meta']['error_message'] . " (Error code " . $rawData['meta']['code'] . ")";
            }
            else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
        }
        else $return['error'] = sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname);
        
        
        echo(json_encode($return));
         
        die();
    }

    private function map_data($rawData, $type = "posts") {
        $data = array();
        foreach($rawData['data'] as $entry){
            $id = $entry["link"];
            $link = $entry["link"];
            $tags = $entry['tags']; // not sure if that works
            $filter = $entry['filter'];
            $tags[] = 'filter:'.$filter;

            $content = self::construct_content($entry,$id,$image_url,$title); // !

            if ($type == "posts") {
                $description = $entry['caption']['text'];
                $venueName = $entry['location']['name'];
                if (isset($description) && isset($venueName)) {
                    $title = $description . ' @ ' . $venueName;
                }
                elseif ( isset($description) && !isset($venueName)) {
                    $title = $description;
                }
                else {
                    $title = ' @ ' . $venueName;
                }
                // save geo coordinates?
                // "location":{"latitude":52.546969779,"name":"Simit Evi - Caf\u00e9 \u0026 Simit House","longitude":13.357669574,"id":17207108},
                // http://codex.wordpress.org/Geodata
                $lat = $entry['location']['latitude'];
                $lon = $entry['location']['longitude'];
                $post_meta["geo_latitude"] = $lat;
                $post_meta["geo_longitude"] = $lon;
                $category = array(get_option($this->shortname.'_category'));
                $post_content = $content['constructed'];
                $image_url = $entry['images']['standard_resolution']['url'];
            } else {
                $title = sprintf(__('I faved an Instagram from %s', 'reclaim'), '@'.$entry['user']['username']);
                $category = array(get_option($this->shortname.'_favs_category'));
                $post_content = "[embed_code]";
                $image_url = '';
            }

            if ($entry['type']=='video') {
                // what to do with videos?
                // post format, show embed code instead of pure image
                // todo: get that video file and show it nativly in wp
                // $entry['videos']['standard_resolution']['url']
                self::$post_format = 'video';
            }
            else {
                self::$post_format = 'image';
            }

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => $category,
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $entry["created_time"])),
                'post_content' => $post_content,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }
    
    public function count_items($type = "posts") {
        if (get_option('instagram_user_id') && get_option('instagram_access_token') ) {
            // when it is called from a ajax-resync, post could be set...
            // this name 'type' should be better choosen not to break other things
            // in wordpress maybe mod_instagram_type
            $type = isset($_POST['type']) ? $_POST['type'] : $type;
            if ($type == "favs") { return 99999; }
            $rawData = parent::import_via_curl(sprintf(self::$apiurl_count, get_option('instagram_user_id'), get_option('instagram_access_token')), self::$timeout);
            $rawData = json_decode($rawData, true);
            return $rawData['data']['counts']['media'];
        }
        else {
            return false;
        }
    }
    
    private function construct_content($entry,$id,$image_url,$description) {
        $post_content_original = htmlentities($description);
        
        if ($entry['type']=='image') {
            $post_content_constructed = 
                 sprintf(__('I uploaded <a href="%s">an instagram</a>.', 'reclaim'), $entry['link'])
                .'<div class="inimage">[gallery size="large" columns="1" link="file"]</div>';
        } else {
            $post_content_constructed = 
                '[video src="'.$entry['videos']['standard_resolution']['url'].'" poster="'.$image_url.'"]';
        }
        $post_content_constructed .= '<p class="viewpost-instagram">(<a rel="syndication" href="'.$entry['link'].'">'.__('View on Instagram', 'reclaim').'</a>)</p>';

        // instagram embed code:
        // <iframe src="//instagram.com/p/jD91oVoLab/embed/" width="612" height="710" frameborder="0" scrolling="no" allowtransparency="true"></iframe>
        $embed_code = '<frameset><iframe class="instagram-embed" src="'.$entry['link'].'embed/" width="612" height="710" frameborder="0" scrolling="no" allowtransparency="true"></iframe>'
            .'<noframes>'
            .'<div class="inimage">[gallery size="large" columns="1" link="file"]</div>'
            .'<p class="viewpost-instagram">(<a rel="syndication" href="'.$entry['link'].'">'.__('View on Instagram', 'reclaim').'</a>)</p>'
            .'</noframes></frameset>';

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed,
            'embed_code' => $embed_code,
            'image' => $image_url
        );

        return $content;
    }
}
