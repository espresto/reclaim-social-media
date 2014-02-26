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

class soundcloud_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.soundcloud.com/users/%s/tracks.json?client_id=%s&limit=%s";
    private static $fav_apiurl = "https://api.soundcloud.com/users/%s/favorites.json?client_id=%s&limit=%s";
    //private static $apiurl= "https://api.soundcloud.com/me/favorites.json?oauth_token=%s&limit=%s";
    //private static $fav_apiurl = "https://api.soundcloud.com/me/favorites.json?oauth_token=%s&limit=%s";
    private static $apiurl_count = "https://api.soundcloud.com/users/%s.json?client_id=%s";
    private static $timeout = 15;
    private static $count = 10; // max is 50
    private static $post_format = 'audio'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'soundcloud';
        $this->has_ajaxsync = true;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'soundcloud_user_id');
        register_setting('reclaim-social-settings', 'soundcloud_user_name');
        register_setting('reclaim-social-settings', 'soundcloud_client_id');
        register_setting('reclaim-social-settings', 'soundcloud_client_secret');
        register_setting('reclaim-social-settings', 'soundcloud_access_token');
        register_setting('reclaim-social-settings', 'soundcloud_favs_category');
        register_setting('reclaim-social-settings', 'soundcloud_import_favs');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='soundcloud') && (isset($_SESSION['login'])) ) { //&& (isset($_SESSION['hybridauth_user_profile']))
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $login              = $_SESSION['login'];
            $error              = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p>'.esc_html( $error ).'</p></div>';
            }
            else {
                update_option('soundcloud_user_id', $user_profile->identifier);
                update_option('soundcloud_user_name', $user_profile->displayName);
                update_option('soundcloud_access_token', $user_access_tokens->access_token);
            }
            if ( $login == 0 ) {
                update_option('soundcloud_user_id', '');
                update_option('soundcloud_user_name', '');
                update_option('soundcloud_access_token', '');
            }
//            print_r($_SESSION);
//            echo "<pre>" . print_r( $user_profile, true ) . "</pre>" ;
//            echo "<pre>" . print_r( $_SESSION, true ) . "</pre>" ;
//            echo $user_access_tokens->accessToken;
//            echo $user_profile->displayName;
            if(session_id()) {
                session_destroy ();
            }
        }
?>
<?php
        $displayname = __('Soundcloud', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Get Favs?', 'reclaim'); ?></th>
            <td><input type="checkbox" name="soundcloud_import_favs" value="1" <?php checked(get_option('soundcloud_import_favs')); ?> />
            <?php if (get_option('soundcloud_import_favs')) { ?><input type="submit" class="button button-primary <?php echo $this->shortName(); ?>_resync_items" value="<?php _e('Resync favs with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            <?php if (get_option('soundcloud_import_favs')) { ?><input type="submit" class="button button-secondary <?php echo $this->shortName(); ?>_count_all_items" value="<?php _e('Count with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category for Favs', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'soundcloud_favs_category', 'hide_empty' => 0, 'selected' => get_option('soundcloud_favs_category'))); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('SoundCloud user id', 'reclaim'); ?></th>
            <td><p><?php echo get_option('soundcloud_user_id'); ?></p>
            <input type="hidden" name="soundcloud_user_id" value="<?php echo get_option('soundcloud_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('SoundCloud user name', 'reclaim'); ?></th>
            <td><p><?php echo get_option('soundcloud_user_name'); ?></p>
            <input type="hidden" name="soundcloud_user_name" value="<?php echo get_option('soundcloud_user_name'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('SoundCloud client id', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="soundcloud_client_id" value="<?php echo get_option('soundcloud_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('SoundCloud client secret', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="soundcloud_client_secret" value="<?php echo get_option('soundcloud_client_secret'); ?>" />
            <input type="text" name="soundcloud_access_token" value="<?php echo get_option('soundcloud_access_token'); ?>" />
            <p class="description">
            
            <?php
            echo sprintf(__('Get your SoundCloud client and credentials <a href="%s">here</a>. ','reclaim'),'http://soundcloud.com/you/apps');
            echo sprintf(__('Use <code>%s</code> as "Redirect URI"','reclaim'),plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/?hauth.done=Soundcloud')); 
            ?>
            </p>
           </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('soundcloud_client_id')!="")
            && (get_option('soundcloud_client_secret')!="")

            ) {
                $link_text = __('Authorize with SoundCloud', 'reclaim');
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('soundcloud_user_id')!="") && (get_option('soundcloud_access_token')!="") ) {
                    echo sprintf(__('<p>SoundCloud authorized as %s</p>', 'reclaim'), get_option('soundcloud_user_name'));
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
                echo _e('Enter SoundCloud app id and secret', 'reclaim');
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
                "Soundcloud" => array(
                    "enabled" => true,
                    "keys"    => array ( "key" => get_option('soundcloud_client_id'), "secret" => get_option('soundcloud_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/Soundcloud.php',
                        "base_url"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/',
                        "class" => "Hybrid_Providers_Soundcloud",
                    ),
                ),
            ),
        );
        return $config;
    }

    public function import($forceResync) {
        if (get_option('soundcloud_user_id') && get_option('soundcloud_client_id') ) {
            //get soundclouds
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('soundcloud_user_id'), get_option('soundcloud_client_id'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
                $data = self::map_data($rawData, 'posts');
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_posts_last_update', current_time('timestamp'));
                parent::log(sprintf(__('END %s import', 'reclaim'), $this->shortname));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));

            if (get_option('soundcloud_import_favs')) {
            //get favs
            $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('soundcloud_user_id'), get_option('soundcloud_client_id'), self::$count), self::$timeout);
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
                
        if (get_option('soundcloud_user_id') && get_option('soundcloud_access_token') ) {
            $apiurl_ = ($type == 'posts' ? self::$apiurl : self::$fav_apiurl);
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            }
            else {
                $rawData = parent::import_via_curl(sprintf($apiurl_, get_option('soundcloud_user_id'), get_option('soundcloud_client_id'), self::$count), self::$timeout);
            }

            $rawData = json_decode($rawData, true);
            if (!isset($rawData['errors'])) {
                $data = self::map_data($rawData, $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));
                
                $next_url = sprintf($apiurl_ . '&offset=' . strval($offset + self::$count), get_option('soundcloud_user_id'), get_option('soundcloud_client_id'), self::$count);
                $return['result'] = array(
                    'offset' => $offset + sizeof($data),
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

    private function map_data($rawData, $type = "posts") {
        $data = array();
        foreach($rawData as $entry){
            $id = $entry["id"];
            $link = $entry["permalink_url"];
            $image_url = $entry["artwork_url"];
            $title = $entry["title"];
            $description = $entry["description"];
            // $tags = ...
            $tags = array();
            preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $entry['tag_list'], $tags);
            foreach($tags as $tag) {
                $tags[] = str_replace('"', "", $tag);
            }
            $tags = $tags[0];

            $content = self::construct_content($entry,$id,$image_url,$title); // !
            //some post meta
            $post_meta["_".$this->shortname."_download_url"] = $entry["download_url"];
            $post_meta["_".$this->shortname."_stream_url"] = $entry["stream_url"];
            $post_meta["_".$this->shortname."_video_url"] = $entry["video_url"];
            $post_meta["_".$this->shortname."_bpm"] = $entry["bpm"];
            $post_meta["_".$this->shortname."_license"] = $entry["license"];
            $post_meta["_".$this->shortname."_downloadable"] = $entry["downloadable"];
            $post_meta["_".$this->shortname."_streamable"] = $entry["streamable"];

            if ($type == "posts") {
                $category = array(get_option($this->shortname.'_category'));
                $post_content = $content['constructed'];
            } else {
                $title = sprintf(__('I faved an track on SoundCloud from %s', 'reclaim'), '@'.$entry['user']['username']);
                $category = array(get_option($this->shortname.'_favs_category'));
                $post_content = "[embed_code]";
            }

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;
            $post_meta["_reclaim_post_type"] = $type;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => $category,
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created_at"]))),
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
        if (get_option('soundcloud_user_id') && get_option('soundcloud_access_token') ) {
            // when it is called from a ajax-resync, post could be set...
            // this name 'type' should be better choosen not to break other things
            // in wordpress maybe mod_soundcloud_type
            $type = isset($_POST['type']) ? $_POST['type'] : $type;
            $rawData = parent::import_via_curl(sprintf(self::$apiurl_count, get_option('soundcloud_user_id'), get_option('soundcloud_client_id')), self::$timeout);
            $rawData = json_decode($rawData, true);
            if ($type == "favs") { 
                return $rawData['public_favorites_count'];
            }
            else {
                return $rawData['track_count'];
            }
        }
        else {
            return false;
        }
    }
    
    private function construct_content($entry,$id,$image_url,$description) {
        $post_content_original = htmlentities($description);
        
        $post_content_constructed = 
                 sprintf(__('I uploaded <a href="%s">a track</a> to SoundCloud.', 'reclaim'), $entry['permalink_url'])
                .'<div class="soundcloud_embed">[embed_code]</div>'
                .'<p class="viewpost-soundcloud">(<a rel="syndication" href="'.$entry['permalink_url'].'">'.__('View on SoundCloud', 'reclaim').'</a>)</p>';

        // soundcloud embed code:
        // 
        $embed_code = '<frameset><iframe width="100%" height="450" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url='.urlencode($entry['uri']).'&amp;auto_play=false&amp;hide_related=false&amp;visual=true"></iframe>'
            .'<noframes>'
            .'<div class="scimage">[gallery size="large" columns="1" link="file"]</div>'
            .'<p class="viewpost-soundcloud">(<a rel="syndication" href="'.$entry['permalink_url'].'">'.__('View on SoundCloud', 'reclaim').'</a>)</p>'
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
