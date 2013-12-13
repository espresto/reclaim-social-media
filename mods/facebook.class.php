<?php
class facebook_reclaim_module extends reclaim_module {
    private static $shortname = 'facebook';
    private static $apiurl= "https://graph.facebook.com/%s/feed/?limit=%s&locale=de&access_token=%s";
    private static $count = 40;
    private static $timeout = 15;
    
    private static function get_access_token(){
        $rawData = parent::import_via_curl(sprintf('https://graph.facebook.com/oauth/access_token?client_id=%s&client_secret=%s&grant_type=client_credentials', get_option('facebook_app_id'), get_option('facebook_app_secret')), self::$timeout);
        $pos = strpos($rawData, '=');
        if($pos !== false) {
            $token = substr($rawData, $pos + 1);
            update_option('facebook_oauth_token', $token);
        }
    }     

    public static function register_settings() {
        parent::register_settings(self::$shortname);
        
        register_setting('reclaim-social-settings', 'facebook_username');
        register_setting('reclaim-social-settings', 'facebook_username_slug');
        register_setting('reclaim-social-settings', 'facebook_app_id');
        register_setting('reclaim-social-settings', 'facebook_app_secret');
        register_setting('reclaim-social-settings', 'facebook_oauth_token');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><strong><?php _e('facebook', 'reclaim'); ?></strong></th>
        </tr>
<?php        
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('facebook username slug', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_username_slug" value="<?php echo get_option('facebook_username_slug'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook username', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_username" value="<?php echo get_option('facebook_username'); ?>" /></td>
        </tr>        
        <tr valign="top">
            <th scope="row"><?php _e('facebook app id', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_id" value="<?php echo get_option('facebook_app_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('facebook app secret', 'reclaim'); ?></th>
            <td><input type="text" name="facebook_app_secret" value="<?php echo get_option('facebook_app_secret'); ?>" /></td>
        </tr>     
<?php
    }

    public static function import() {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (!get_option('facebook_oauth_token') && get_option('facebook_app_id') && get_option('facebook_app_secret')) {
            self::get_access_token();
        }

        if (get_option('facebook_username') && get_option('facebook_username_slug') &&  get_option('facebook_oauth_token')) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('facebook_username_slug'), self::$count, get_option('facebook_oauth_token')), self::$timeout);
            $rawData = json_decode($rawData, true);
            
            $data = self::map_data($rawData);
            parent::insert_posts($data);
            update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));
    }       

    private static function map_data($rawData) {
        $data = array();
        foreach($rawData['data'] as $entry){
            if ((
                    !isset($entry['application']) || (
                        $entry['application']['name'] != "Twitter" // no tweets
                        && $entry['application']['namespace'] != "rssgraffiti" // no blog stuff
                        && $entry['application']['namespace'] != "ifthisthenthat" // no instagrams and ifttt                            
                    )
                )
                && (
                    !isset($entry['status_type']) || $entry['status_type'] != "approved_friend" // no new friend anouncements
                )
                && ($entry['privacy']['value'] == "" || $entry['privacy']['value'] == "EVERYONE") // privacy OK? is it public?
                && $entry['from']['name'] == get_option('facebook_username') // only own stuff $user_namestuff
            ) {
         
                $link = self::get_link($entry);
                $image = self::get_image_url($entry);
                $title = self::get_title($entry);
                $excerpt = self::get_excerpt($entry, $link, $image);
                
                $data[] = array(                
                    'post_author' => get_option(self::$shortname.'_author'),
                    'post_category' => array(get_option(self::$shortname.'_category')),
                    'post_date' => date('Y-m-d H:i:s', strtotime($entry["created_time"])),
                    'post_excerpt' => $excerpt,
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
    
    private static function get_link($entry) {
        if (isset($entry["link"])) {
            $link = htmlentities($entry["link"]);
        } else {
            $id = substr(strstr($entry['id'], '_'),1);
            $link = "https://www.facebook.com/".get_option('facebook_username_slug')."/posts/".$id;                
        } 
        return $link;
    } 
    
    private static function get_image_url($entry) {
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
        return $image;
    }
    
    private static function get_title($entry) {
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
    
    private static function get_excerpt($entry, $link = '', $image  = ''){
        $description = "";
        
        if (isset($entry["story"]) && $entry["story"]) {
            $description = '<a href="'.$link.'">'.$entry["story"].'</a>';
            if (isset($entry["message"]) && $entry["message"]) {
                $description .= '<blockquote>'.$entry["message"].'</blockquote>';
            }
            if ($image) {
                $description .= '<div class="fbimage"><img src="'.$image.'"></div>';
            }
            if (isset($entry['name']) && $entry['name']) {
                $description .= '<div class="clearfix fbname"><a href="'.$link.'">'.$entry["name"].'</a></div>';
            }
            if (isset($entry['properties']) && $entry['properties']) {
                $description .= '<a href="'.$entry['properties'][0]['href'].'">'.$entry['properties'][0]['text'].'</a><br />';
            }
            if (isset($entry["caption"]) && $entry["caption"]) {
                $description .=	'<div class="clearfix fbcaption">'.$entry["caption"].'</div>';
            }
            if (isset($entry["description"]) && $entry["description"]) {
                $description .= '<blockquote class="fbdescription">'.$entry["description"].'</blockquote>';
            }
        }        
        elseif (isset($entry['application']) && $entry['application']['name']=='Likes') { // likes?
            if (isset($entry['name']) && $entry['name']) { 
                $entry_name = $entry['name']; 
            } 
            else {
                $entry_name = __('multiple items', 'reclaim');  // manchmal liefert fb nix (nochmal id checken?)
            }
            $description = sprintf(__('%s liked <a href="%s">%s</a>', 'reclaim'), get_option('facebook_username'), $link, $entry_name);            
            if ($image) {
                $description .= '<div class="fbimage"><img src="'.$image.'"></div>';
            }
            if (isset($entry["caption"]) && $entry["caption"]) {
                '<div class="clearfix fbcaption">'.$entry["caption"].'</div>';
            }
            if (isset($entry["description"]) && $entry["description"]) {
                $description .= '<blockquote>'.$entry["description"].'</blockquote>';
            }			
        }
        elseif (isset($entry["type"]) && $entry["type"] == 'status') {
            if (!isset($entry["story"]) || $entry["story"] == "") {  // no story?
                $description = "";
                if (isset($entry["message"])) {
                    $description = '<a href="'.$link.'">'.$entry["message"].'</a>';
                }
                if ($image) {
                    $description .= '<div class="fbimage"><img src="'.$image.'"></div>';
                }
                if (isset($entry["name"]) && $entry["name"]) {
                    $description .= '<div class="clearfix fbname"><a href="'.$link.'">'.$entry["name"].'</a></div>' ;
                }               
                if (isset($entry["caption"]) && $entry["caption"]) {
                    $description .= '<div class="clearfix fbcaption">'.$entry["caption"].'</div>';
                }
                if (isset($entry["description"])) {
                    $description .= '<blockquote>'.$entry["description"].'</blockquote>';
                }
            } else { // story?
                $description = '<a href="'.$link.'">'.$entry["story"].'</a>';
                if (isset($entry["message"])) {
                    $description = '<blockquote>'.$entry["message"].'</blockquote>';
                }
                if ($image) {
                    $description .= '<div class="fbimage"><img src="'.$image.'"></div>';
                }
                if (isset($entry["name"]) && $entry["name"]) {
                    $description .= '<div class="clearfix fbname"><a href="'.$link.'">'.$entry["name"].'</a></div>' ;
                }                   
                if (isset($entry["caption"])) {
                    $description .= '<blockquote class="clearfix fbcaption">'.$entry["caption"].'</blockquote>';
                }
                if (isset($entry["description"])) {
                    $description .= '<blockquote>'.$entry["description"].'</blockquote>';
                }
            }
        }
        else {        
            if (isset($entry["message"])) {
                $description = '<div class="fbmessage"><a href="'.$link.'">'.$entry["message"].'</a></div>';
            }
            if ($image) {
                $description .= '<div class="fbimage"><img src="'.$image.'"></div>';
            }
            if (isset($entry["name"]) && $entry["name"]) {
                $description .= '<div class="clearfix fbname"><a href="'.$link.'">'.$entry["name"].'</a></div>' ;
            }   
            if (isset($entry["caption"])) {
                $description .=	'<blockquote class="clearfix fbcaption">'.$entry["caption"].'</blockquote>';
            }
        
            if (isset($entry["description"])) {
                $description .= '<blockquote>'.$entry["description"].'</blockquote>';
            }
        }
        return $description;
    }
}
?>