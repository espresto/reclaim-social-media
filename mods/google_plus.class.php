<?php
class google_plus_reclaim_module extends reclaim_module {
    private static $shortname = 'google_plus';
    private static $apiurl = "https://www.googleapis.com/plus/v1/people/%s/activities/public/?key=%s&maxResults=%s&pageToken=";
    private static $count = 20;
    private static $timeout = 15;

    public static function register_settings() {
        parent::register_settings(self::$shortname);
        
        register_setting('reclaim-social-settings', 'google_plus_user_id');
        register_setting('reclaim-social-settings', 'google_api_key');        
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><strong><?php _e('google +', 'reclaim'); ?></strong></th>
        </tr>
<?php           
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('google + user id', 'reclaim'); ?></th>
            <td><input type="text" name="google_plus_user_id" value="<?php echo get_option('google_plus_user_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('google API key', 'reclaim'); ?></th>
            <td><input type="text" name="google_api_key" value="<?php echo get_option('google_api_key'); ?>" /></td>
        </tr>        
<?php
    }

    public static function import() {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (get_option('google_api_key') && get_option('google_plus_user_id')) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));            
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('google_plus_user_id'), get_option('google_api_key'), self::$count), self::$timeout);
            $rawData = json_decode($rawData, true);
            if (is_array($rawData)) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));                
            }
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));
    }

    public static function map_data($rawData) {
        $data = array();      
        foreach($rawData['items'] as $entry){
            $title = self::get_title($entry);
            $content = self::get_content($entry);
            $image = self::get_image_url($entry);
            
            $data[] = array(                
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_date' => date('Y-m-d H:i:s', strtotime($entry["published"])),                
                'post_excerpt' => $content,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry["url"],
                'ext_image' => $image,
                'ext_guid' => $entry["id"]
            );                 
            
        }
        return $data;
    }
    
    private static function get_title($entry) {        
        if (preg_match( "/<b>(.*?)<\/b>/", $entry['object']['content'], $matches) && $matches[1]) $title = $matches[1];
        else $title = $entry['title'];
        
        return $title;
    }    
    
    private static function get_content($entry){
        $post_content = (preg_replace( "/<b>(.*?)<\/b>/", "", $entry['object']['content']));
        $post_content = (preg_replace( "/\A<br \/><br \/>/", "", $post_content));
        $post_content = (html_entity_decode(trim($post_content)));
        $post_content = preg_replace( "/\s((http|ftp)+(s)?:\/\/[^<>\s]+)/i", " <a href=\"\\0\" target=\"_blank\">\\0</a>", $post_content);
        
        if (isset($entry['object'], $entry['object']['attachments']) && $entry['object']['attachments'][0]['objectType']=="photo") {
            $post_content = '
            <div class="gimage gplus"><a href="'.$entry['object']['attachments'][0]['url'].'">
            <img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['content'].'">
            </a></div>'.
            '<div class="gcontent gplus">'.$post_content.'</div>';
        }

        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['objectType']) && $entry['object']['attachments'][0]['objectType'] == "article" && isset($entry['object']['attachments'][0]['content']) && $entry['object']['attachments'][0]['content']) {
            $articleimage_html = '<div class="gplusimage"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="" class="gpreview-img attachment articleimage"></div>';
            $post_content .= '<blockquote>
            '.$articleimage_html.'
            <h3 class="garticle attachment"><a href="'.$entry['object']['attachments'][0]['url'].'">'.$entry['object']['attachments'][0]['displayName'].'</a></h3>
            '.$entry['object']['attachments'][0]['content'].'</blockquote>';
        }
        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['objectType']) && $entry['object']['attachments'][0]['objectType'] == "video") {
            $post_content = '<div class="gimage gplus video"><a href="'.$entry['object']['attachments'][0]['url'].'"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['displayName'].'"></a></div>'.'<div class="gcontent gplus">'.$post_content.'</div>';
        }    
        return $post_content;
    }
    
    private static function get_image_url($entry){
        $image = '';
        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['image']) && $entry['object']['attachments'][0]['image']['url']) {
            if ($entry['object']['attachments'][0]['fullImage']['url']) {
                $image =  $entry['object']['attachments'][0]['fullImage']['url'];
            }
            else {
                $image = $entry['object']['attachments'][0]['image']['url'];
            }
        }        
        return $image;
    }
}
?>