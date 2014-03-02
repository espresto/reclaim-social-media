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

class tumblr_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.tumblr.com/v2/blog/%s/posts/?api_key=%s&limit=%s";
    private static $favs_apiurl = "https://api.tumblr.com/v2/user/likes?";
    private static $apiurl_count = "https://api.tumblr.com/v2/blog/%s/info/?api_key=%s&limit=%s";
    private static $timeout = 15;
    private static $count = 20;
    private static $post_format = 'image'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'tumblr';
        $this->has_ajaxsync = true;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'tumblr_user_id');
        register_setting('reclaim-social-settings', 'tumblr_user_name');
        register_setting('reclaim-social-settings', 'tumblr_client_id');
        register_setting('reclaim-social-settings', 'tumblr_client_secret');
        register_setting('reclaim-social-settings', 'tumblr_access_token');
        register_setting('reclaim-social-settings', 'tumblr_favs_category');
        register_setting('reclaim-social-settings', 'tumblr_import_favs');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='tumblr') && (isset($_SESSION['login'])) ) { //&& (isset($_SESSION['hybridauth_user_profile']))
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $full_user_profile  = json_decode($_SESSION['hybridauth_full_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $login              = $_SESSION['login'];
            $error              = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p>'.esc_html( $error ).'</p></div>';
            }
            else {
                update_option('tumblr_user_id', $user_profile->identifier);
                update_option('tumblr_user_name', $user_profile->displayName);
                update_option('tumblr_access_token', $user_access_tokens->access_token);
                update_option('tumblr_full_user_profile', $full_user_profile);
                update_option('tumblr_user_avatar', $user_profile->photoURL);
            }
            if ( $login == 0 ) {
                update_option('tumblr_user_id', '');
                update_option('tumblr_user_name', '');
                update_option('tumblr_access_token', '');
                update_option('tumblr_full_user_profile', '');
                update_option('tumblr_user_avatar', '');
            }
            //print_r($_SESSION);
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
        $displayname = __('tumblr', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Primary Tumblr blog', 'reclaim'); ?></th>
            <td><p><?php echo get_option('tumblr_user_id'); ?></p>
            <input type="hidden" name="tumblr_user_id" value="<?php echo get_option('tumblr_user_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Primary Tumblr user name', 'reclaim'); ?></th>
            <td><p><?php echo get_option('tumblr_user_name'); ?></p>
            <input type="hidden" name="tumblr_user_name" value="<?php echo get_option('tumblr_user_name'); ?>" />
            </td>
        </tr>
<?php
		// show secondary blogs
		$full_user_profile = get_option('tumblr_full_user_profile');
		if (isset($full_user_profile)) {
		    foreach ( $full_user_profile->response->user->blogs as $blog ){
		        if( !$blog->primary ){ 
?>

        <tr valign="top">
            <th scope="row"><?php echo sprintf(__('Synchronise secondary blog %s?', 'reclaim'), $blog->name); ?></th>
            <td><input type="checkbox" name="tumblr_import_blog_<?php echo $blog->name; ?>" value="1" <?php checked(get_option('tumblr_import_blog_<?php echo $blog->name; ?>')); ?> />
            <?php if (get_option('tumblr_import_blog_<?php echo $blog->name; ?>')) { ?><input type="submit" class="button button-primary <?php echo $this->shortName(); ?>_resync_items" value="<?php _e('Resync favs with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            <?php if (get_option('tumblr_import_blog_<?php echo $blog->name; ?>')) { ?><input type="submit" class="button button-secondary <?php echo $this->shortName(); ?>_count_all_items" value="<?php _e('Count with ajax', 'reclaim'); ?>" data-resync="{type:'favs'}" /><?php } ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php echo sprintf(__('Category for %s', 'reclaim'), $blog->name); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => 'tumblr_blog_<?php echo $blog->name; ?>_category', 'hide_empty' => 0, 'selected' => get_option('tumblr_blog_<?php echo $blog->name; ?>_category'))); ?></td>
        </tr>
		    
<?php
		        }
		    }
		}
?>
        <tr valign="top">
            <th scope="row"><?php _e('Tumblr OAuth Consumer Key', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="tumblr_client_id" value="<?php echo get_option('tumblr_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Tumblr Secret Key', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="tumblr_client_secret" value="<?php echo get_option('tumblr_client_secret'); ?>" />
            <input type="hidden" name="tumblr_access_token" value="<?php echo get_option('tumblr_access_token'); ?>" />
            <p class="description">
            
            <?php
            echo sprintf(__('Get your Tumblr client and credentials <a href="%s">here</a>. ','reclaim'),'http://www.tumblr.com/oauth/apps');
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
            (get_option('tumblr_client_id')!="")
            && (get_option('tumblr_client_secret')!="")

            ) {
                $link_text = __('Authorize with Tumblr', 'reclaim');
                // && (get_option('facebook_oauth_token')!="")
                if ( (get_option('tumblr_user_id')!="") && (get_option('tumblr_access_token')!="") ) {
                    echo sprintf(__('<p><img src="%s" class="reclaim_avatar" width="32" height="32" align="left">Tumblr authorized as <span class="name">%s</span></li></p>', 'reclaim'), get_option('tumblr_user_avatar'), get_option('tumblr_user_name'));
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
                echo _e('enter Tumblr app id and secret', 'reclaim');
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
                "Tumblr" => array(
                    "enabled" => true,
                    "keys"    => array ( "key" => get_option('tumblr_client_id'), "secret" => get_option('tumblr_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../vendor/hybridauth/hybridauth/additional-providers/hybridauth-tumblr/Providers/Tumblr.php',
                        "class" => "Hybrid_Providers_Tumblr",
                    ),
                ),
            ),
        );
        return $config;
    }
    
    public function import($forceResync) {
        // lets check for tumblr_access_token, although we only need the tumblr_client_id.
        // (makes sure user is authenticated with tumblr, and we have a list of blogs)
        if (get_option('tumblr_user_id') && get_option('tumblr_access_token') ) {
            // get $count number of posts from all blogs
            // first lets iterate through our bloglist
            // and check which ones are active
            $full_user_profile = get_option('tumblr_full_user_profile');
            if (isset($full_user_profile)) {
                foreach ( $full_user_profile->response->user->blogs as $blog ){
                    // get primary blog and all active secondary blogs
                    if( $blog->primary || get_option('tumblr_import_blog_'.$blog->name) ){ 
                        $rawData = parent::import_via_curl(sprintf(self::$apiurl, $blog->name . '.tumblr.com', get_option('tumblr_client_id'), self::$count), self::$timeout);
                        $rawData = json_decode($rawData, true);
                        //parent::log(print_r($rawData, true));
                        if ($rawData['meta']['status'] == 200) {
                            $data = self::map_data($rawData['response'], 'posts');
                            parent::insert_posts($data);
                            update_option('reclaim_'.$this->shortname.'_posts_last_update', current_time('timestamp'));
                            parent::log(sprintf(__('END %s posts import', 'reclaim'), $this->shortname));
                        }
                        else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
/*
                        // lets do that later:
                        // todo: get favs
                        if (get_option('tumblr_import_favs')) {
                            //get favs
                            parent:log(sprintf(self::$fav_apiurl, get_option('tumblr_access_token'), self::$count));
                            $rawData = parent::import_via_curl(sprintf(self::$fav_apiurl, get_option('tumblr_access_token'), self::$count), self::$timeout);
                            $rawData = json_decode($rawData, true);

                            if ($rawData) {
                                $data = self::map_data($rawData, 'favs');
                                //parent::insert_posts($data);
                                update_option('reclaim_'.$this->shortname.'_favs_last_update', current_time('timestamp'));
                                parent::log(sprintf(__('END %s favs import', 'reclaim'), $this->shortname));
                            }
                            else parent::log(sprintf(__('%s favs returned no data. No import was done', 'reclaim'), $this->shortname));
                        }
*/
                    }
                }
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
        
        // todo: synchronize secondary blogs
        $return = array(
            'success' => false,
            'error' => '',
            'result' => null
        );
                
        if (get_option('tumblr_user_id') && get_option('tumblr_access_token') ) {
            $apiurl_ = ($type == 'posts' ? self::$apiurl : self::$fav_apiurl);
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            }
            else {
                $rawData = parent::import_via_curl(sprintf($apiurl_, get_option('tumblr_user_name') . '.tumblr.com', get_option('tumblr_client_id'), self::$count), self::$timeout);
            }

            $rawData = json_decode($rawData, true);
            parent::log(print_r($rawData, true));
            if ($rawData['meta']['status'] == 200) {
                $data = self::map_data($rawData['response'], $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));
                
                $next_url = sprintf($apiurl_, get_option('tumblr_user_name') . '.tumblr.com', get_option('tumblr_client_id'), self::$count) . '&offset=' . ($offset + sizeof($data));
                $return['result'] = array(
                    'offset' => $offset + sizeof($data),
                    // take the next pagination url instead of calculating
                    // a self one
                    'next_url' => $next_url,
                );
                $return['success'] = true;
            }
            elseif (isset($rawdata['meta']['status']) != 200) {
                $return['error'] = $rawData['meta']['msg'] . " (Error code " . $rawData['meta']['status'] . ")";
            }
            else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
        }
        else $return['error'] = sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname);
        
        
        echo(json_encode($return));
         
        die();
    }

    private function map_data($rawData, $type = "posts") {
        $data = array();
        foreach($rawData['posts'] as $entry){
            $source = "";
            $id = $entry["id"];
            $link = $entry["post_url"];
            self::$post_format = $entry["type"];
            switch ($entry["type"]) {
                case 'text':
                    self::$post_format = 'article';
                    break;
                case 'answer':
                    self::$post_format = 'aside';
                    break;
                case 'photo':
                    self::$post_format = 'image';
                    break;
            }
            $tags = $entry['tags'];
            $tags[] = $entry['blog_name'];

            //$content = self::construct_content($entry,$id,$image_url,$title); // !

            if ($type == "posts") {
                // text posts and links
                //
                $title = $entry['title'];
                $category = array(get_option($this->shortname.'_category'));
                //$post_content = $content['constructed'];
                $post_content = $entry['body'];
                // no image for text posts
                $image_url = '';

                if ($entry['type']=='photo') {
                    $title = wp_strip_all_tags($entry['caption']);
                    $post_content = '[gallery size="large" columns="'.$columns.'" link="file"]'.$entry['caption'];
                    $images = array();
                    $i = 0;
                    foreach($entry['photos'] as $image) {
                        $images[$i]['link_url'] = '';
                        $images[$i]['image_url'] = $image['original_size']['url'];
                        $images[$i]['title'] = $image['caption'];
                        $i++;
                    }
                    $image_url = $images;
                    // source_url":"http:\/\/hebig.org\/","source_title":"hebig.org"
                    if ($entry['source_url']) {
                        $source = sprintf(__(', source: <a href="%s">%s</a>', 'reclaim'), $entry['source_url'], $entry['source_title']);
                    }
                }
                if ($entry['type']=='quote') {
                    $title = wp_strip_all_tags($entry['text']);
                    $post_content = '<blockquote>'.$entry['text'].'</blockquote>';
                    $post_content .= $entry['source'];
                    $post_meta["_format_quote_source_name"] = $entry['source_url'];
                    $post_meta["_format_quote_source_url"] = $entry['source_title'];
                }
                if ($entry['type']=='link') {
                    $post_content = $entry['description'];
                    // links have the original url
                    $post_meta["_format_link_url"] = $entry['url'];
                }
                if ($entry['type']=='chat') {
                    $title = '';
                    $post_content = $entry['body'];
                }
                if ($entry['type']=='audio') {
                    $title = wp_strip_all_tags($entry['id3_title']);
                    $post_content = $entry['caption'].$entry['player'];
                    $embed_code = $entry['player'];
                    $post_meta["_format_link_url"] = $entry['source_url'];
                }
                if ($entry['type']=='video') {
                    $title = $entry['source_title'];
                    $post_content = $entry['caption'].'[embed_code]';
                    $embed_code = $entry['player'][2]['embed_code'];
                    $post_meta["_format_link_url"] = $entry['permalink_url']; // source_url
                    $image_url = $entry['thumbnail_url'];
                }
                // todo: answer type
            $post_content .= '
                <p class="viewpost-tumblr">(<a rel="syndication" href="'.$link.'">'.sprintf(__('View on %s', 'reclaim'), $entry['blog_name'].'.tumblr.com').'</a>'.$source.')</p>';
            } 
            else {
                // todo: get tumblr favs
                $title = sprintf(__('I faved an Tumblr from %s', 'reclaim'), '@'.$entry['user']['username']);
                $category = array(get_option($this->shortname.'_favs_category'));
                $post_content = "[embed_code]";
                $image_url = '';
            }

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;
            $post_meta["_reclaim_post_type"] = $type;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => $category,
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $entry["timestamp"])),
                'post_content' => $post_content,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'ext_embed_code' => $embed_code,
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }
    
    public function count_items($type = "posts") {
        if (get_option('tumblr_user_id') && get_option('tumblr_access_token') ) {
            // todo: get count for secondary blogs
            // right now, only the count for the primary blog is fetched
            $type = isset($_POST['type']) ? $_POST['type'] : $type;
            if ($type == "favs") { return 999999; }
            $rawData = parent::import_via_curl(sprintf(self::$apiurl_count, get_option('tumblr_user_name') . '.tumblr.com', get_option('tumblr_client_id'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);
            return $rawData['response']['blog']['posts'];
        }
        else {
            return false;
        }
    }
    
}
