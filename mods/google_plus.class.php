<?php
class google_plus_reclaim_module extends reclaim_module {
    private static $shortname = 'google_plus';
    private static $apiurl = "https://www.googleapis.com/plus/v1/people/%s/activities/public/?key=%s&maxResults=%s&pageToken=";
    private static $count = 20;
    private static $timeout = 15;
    private static $post_format = 'aside'; // or 'status', 'aside'

    public static function register_settings() {
        parent::register_settings(self::$shortname);

        register_setting('reclaim-social-settings', 'google_plus_user_id');
        register_setting('reclaim-social-settings', 'google_api_key');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Google+', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row">
            	Google+ App settings
            </th>
            <td>
            	<label for="google_plus_user_id"><?php _e('Google+ User ID', 'reclaim'); ?></label>
            	<input type="text" name="google_plus_user_id" class="widefat" value="<?php echo get_option('google_plus_user_id'); ?>" />
				<p class="description"><?php _e('Your Google+ profile ID is the long number at the end of your page or profile URL.', 'reclaim'); ?></p>
				<hr />
            	<label for="google_api_key"><?php _e('Google API Key', 'reclaim'); ?></label>
            	<input type="text" name="google_api_key" class="widefat" value="<?php echo get_option('google_api_key'); ?>" />
            </td>
        </tr>
<?php
    }

    public static function import($forceResync) {
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
            $post_format = self::get_post_format($entry);
//            if ($post_format=="link") {$title = $entry['name'];}

            $data[] = array(
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_format' => $post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["published"]))),
                'post_content' => $content,
//                'post_excerpt' => $content,
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

    private static function get_post_format($entry) {
	$verb = $entry['verb'];
	$objectType = $entry['object']['objectType'];
	$attachmentObjectType = $entry['object']['attachments'][0]['objectType'];
//	parent::log('objectType: '.$objectType);
//	parent::log('attachmentObjectType: '.$attachmentObjectType);
	if ($verb=="post") {

    }
    else {

    }

           	$post_format = "aside";
			if ($objectType=="activity") {
            	$post_format = "status";
            }
			if ($objectType=="note") {
            	$post_format = "aside";
            }

			if ( ($attachmentObjectType=="photo") || ($attachmentObjectType=="album") ) {
            	$post_format = "image";
            }
			if ($attachmentObjectType=="video") {
            	$post_format = "video";
            }
			if ($attachmentObjectType=="article") {
            	$post_format = "link";
            }

        return $post_format;
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

		$story = "";
		if ($entry['verb']=="share") {
		$story = ''.$entry['annotation'].'<br />';
		$story .= '<p>(Auf <a href="'.$entry['url'].'">Google+</a> ursprünglich von <a href="'.$entry['object']['actor']['url'].'">'.$entry['object']['actor']['displayName'].'</a> geshared.)</p>';
		}

		// it's a photo
        if (isset($entry['object'], $entry['object']['attachments']) && $entry['object']['attachments'][0]['objectType']=="photo") {
            $post_content =
            '<div class="gimage gplus"><a href="'.$entry['object']['attachments'][0]['url'].'">'
            .'<img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['content'].'">'
            .'</a></div>'
            .'<div class="gcontent gplus">'.$post_content.'</div>';
            if ($story!="") {
            $post_content = $story . '<blockquote class="clearfix glink">'.$post_content.'</blockquote>';
            }

        }
		else {
            if ($story!="") {
            $post_content = $story . '<blockquote class="clearfix glink">'.$post_content.'</blockquote>';
            }
		}

		//now other's content
        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['objectType']) && $entry['object']['attachments'][0]['objectType'] == "article" && isset($entry['object']['attachments'][0]['content']) && $entry['object']['attachments'][0]['content']) {
            $articleimage_html = '<div class="gimage"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="" class="gpreview-img attachment articleimage"></div>';
//			$description .= '<blockquote class="clearfix fbname fblink">'.$fblink_description.'</blockquote>'; // other's content
            $post_content .= '<blockquote class="clearfix glink">'
            .$articleimage_html
            .'<div class="glink-title garticle attachment"><a href="'.$entry['object']['attachments'][0]['url'].'">'.$entry['object']['attachments'][0]['displayName'].'</a></div>'
            .'<p class="glink-description">'.$entry['object']['attachments'][0]['content'].'</p></blockquote>';
        }
        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['objectType']) && $entry['object']['attachments'][0]['objectType'] == "video") {
            $post_content = '<div class="gimage gplus video"><a href="'.$entry['object']['attachments'][0]['url'].'"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['displayName'].'"></a></div>'.'<div class="gcontent gplus">'.$post_content.'</div>';
        }
		$post_content .= '<p class="gviewpost-google">(<a href="'.$entry['url'].'">'.__('View on Google+', 'reclaim').'</a>)</p>';

		// add embedcode
		$post_content = '<div class="g-post" data-href="'.$entry['url'].'" data-width="100%">'
		.$post_content
		.'</div>';

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
