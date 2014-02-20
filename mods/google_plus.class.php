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

class google_plus_reclaim_module extends reclaim_module {
    private static $apiurl = "https://www.googleapis.com/plus/v1/people/%s/activities/public/?key=%s&maxResults=%s&pageToken=%s";
    private static $count = 10; // max = 100
    private static $timeout = 15;
    private static $post_format = 'aside'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'google_plus';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'google_plus_user_id');
        register_setting('reclaim-social-settings', 'google_api_key');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3><?php _e('Google+', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
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
                <p class="description">
                <?php
                echo sprintf(__('Read more info about the G+ API <a href="%s" target="_blank">here</a>','reclaim'),'https://github.com/espresto/reclaim-social-media/wiki/Get-API-keys-for-Google-');
                ?>
                </p>
            </td>
        </tr>
<?php
    }
    public function ajax_resync_items() {
		$offset = intval( $_POST['offset'] );
		$limit = intval( $_POST['limit'] );
		$count = intval( $_POST['count'] );
    	$next_url = isset($_POST['next_url']) ? $_POST['next_url'] : '';
    
    	self::log($this->shortName().' resync '.$offset.'-'.($offset + $limit).':'.$count);
    	 
    	$return = array(
    		'success' => false,
    		'error' => '',
			'result' => null
    	);
    	    	
        if (get_option('google_api_key') && get_option('google_plus_user_id')) {
    		// $next_url is actually the nextPageToken
    		if ($next_url != '') {
                $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('google_plus_user_id'), get_option('google_api_key'), self::$count, $next_url), self::$timeout);
			}
			else {
                $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('google_plus_user_id'), get_option('google_api_key'), self::$count, ""), self::$timeout);
    		}
            $rawData = json_decode($rawData, true);

            if (is_array($rawData) && !isset($rawdata['code'])) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
    			$return['result'] = array(
    				'offset' => $offset + sizeof($data),
					// use nextPageToken instead of url
					'next_url' => $rawData['nextPageToken'],
    			);
    			$return['success'] = true;
            }
    		else $return['error'] = sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname);
    		
    	}
    	else $return['error'] = sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname);

    	echo(json_encode($return));
    	 
    	die();
    }

    // this is only one loop, that is triggered through force refresh or the autoupdate
    // function. it gets the number of posts defined in $count
    public function import($forceResync) { 
        if (get_option('google_api_key') && get_option('google_plus_user_id')) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('google_plus_user_id'), get_option('google_api_key'), self::$count,""), self::$timeout);
            //parent::log(print_r($rawData,true));
            $rawData = json_decode($rawData, true);
            if (is_array($rawData) && !isset($rawdata['code'])) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    public function map_data($rawData) {
        $data = array();
        foreach($rawData['items'] as $entry) {
            $title = self::get_title($entry);
            $content = self::get_content($entry);
            $image = self::get_image_url($entry);
            $post_format = self::get_post_format($entry);
//            if ($post_format=="link") {$title = $entry['name'];}

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => $post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["published"]))),
                'post_content' => $content,
//                'post_excerpt' => $content,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry["url"],
                'ext_image' => $image,
                'ext_guid' => $entry["id"],
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    public function count_items() {
        // found no way to determine the overall post count on g+
        return 999999;
    }

    private function get_post_format($entry) {
        $verb = $entry['verb'];
        $objectType = isset($entry['object']['objectType']) ? $entry['object']['objectType'] : '';
        $attachmentObjectType = isset($entry['object']['attachments'][0]['objectType']) ? $entry['object']['attachments'][0]['objectType'] : '';
//        parent::log('objectType: '.$objectType);
//        parent::log('attachmentObjectType: '.$attachmentObjectType);

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

    private function get_title($entry) {

        if (preg_match( "/<b>(.*?)<\/b>/", $entry['object']['content'], $matches) && $matches[1]) {
            $title = $matches[1];
        }
        elseif ($entry['object']['attachments'][0]['objectType'] == "album" || $entry['object']['attachments'][0]['objectType'] == "photo") {
            $title = $entry['object']['attachments'][0]['displayName'];
        }
        else {
            $title = $entry['title'];
        }

        return $title;
    }

    private function get_content($entry) {
        $post_content = (preg_replace( "/<b>(.*?)<\/b>/", "", $entry['object']['content']));
        $post_content = (preg_replace( "/\A<br \/><br \/>/", "", $post_content));
        $post_content = (html_entity_decode(trim($post_content)));
        $post_content = preg_replace( "/\s((http|ftp)+(s)?:\/\/[^<>\s]+)/i", " <a href=\"\\0\" target=\"_blank\">\\0</a>", $post_content);

        $story = "";
        // it's a share
        if ($entry['verb']=="share") {
            $story = isset($entry['annotation']) ? ''.$entry['annotation'].'<br />' : '';
            $story .= sprintf(__('<hr /><p>Shared on <a href="%s">Google+</a> by <em><a href="%s">%s</a></em>:</p>', 'reclaim'), $entry['url'], $entry['object']['actor']['url'], $entry['object']['actor']['displayName']);
        }

        // it's a photo
        if (
            isset($entry['object'], $entry['object']['attachments']) && 
            ($entry['object']['attachments'][0]['objectType'] == "photo" || $entry['object']['attachments'][0]['objectType'] == "album")
            ) {
			//album? count attachments...
			$columns = 1;
			if (isset($entry['object']['attachments'][0]['thumbnails'])) {
                $count_thumbnails = count($entry['object']['attachments'][0]['thumbnails']);
			    if ($count_thumbnails >= 4) { $columns = 4; } else { $columns = $count_thumbnails; }
			}
            $post_content =
                '<div class="gimage gplus">'
                .'[gallery size="large" columns="'.$columns.'" link="file"]'
                .'<div class="gcontent gplus">'.$post_content.'</div>';

/*
            $post_content =
                '<div class="gimage gplus"><a href="'.$entry['object']['attachments'][0]['url'].'">'
                .'<img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['content'].'">'
                .'</a></div>'
                .'<div class="gcontent gplus">'.$post_content.'</div>';
*/
            if ($story!="") {
                $post_content = $story . '<div class="clearfix glink">'.$post_content.'</div>';
            }
        }
        else {
            if ($story!="") {
                $post_content = $story . '<div class="clearfix glink">'.$post_content.'</div>';
            }
        }

        //now other's content
        $attatchmentTypeIsArticle = isset($entry['object']['attachments'][0]['objectType']) &&
            $entry['object']['attachments'][0]['objectType'] == "article";
        $attachmentContentExists = isset($entry['object']['attachments'][0]['content']) &&
            $entry['object']['attachments'][0]['content'];
        if ($attatchmentTypeIsArticle && $attachmentContentExists) {
            $attatchmentImageExists = isset($entry['object']['attachments'][0]['image']['url']) &&
                filter_var($entry['object']['attachments'][0]['image']['url'],FILTER_VALIDATE_URL);
            if ($attatchmentImageExists) {
                $articleimage_html = '<div class="gimage"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="" class="gpreview-img attachment articleimage"></div>';
            } else {
                $articleimage_html = '';
            }

//            $description .= '<blockquote class="clearfix fbname fblink">'.$fblink_description.'</blockquote>'; // other's content
            $post_content .= '<blockquote class="clearfix glink">'
                .$articleimage_html
                .'<div class="glink-title garticle attachment"><a href="'.$entry['object']['attachments'][0]['url'].'">'.$entry['object']['attachments'][0]['displayName'].'</a></div>'
                .'<p class="glink-description">'.$entry['object']['attachments'][0]['content'].'</p></blockquote>';
        }
        if (isset($entry['object'], $entry['object']['attachments'], $entry['object']['attachments'][0], $entry['object']['attachments'][0]['objectType']) && $entry['object']['attachments'][0]['objectType'] == "video") {
            $post_content = '<div class="gimage gplus video"><a href="'.$entry['object']['attachments'][0]['url'].'"><img src="'.$entry['object']['attachments'][0]['image']['url'].'" alt="'.$entry['object']['attachments'][0]['displayName'].'"></a></div>'.'<div class="gcontent gplus">'.$post_content.'</div>';
        }
        $post_content .= '<p class="viewpost-google">(<a rel="syndication" href="'.$entry['url'].'">'.__('View on Google+', 'reclaim').'</a>)</p>';

        // add embedcode
        $post_content = '<div class="g-post" data-href="'.$entry['url'].'" data-width="100%">'
            .$post_content
            .'</div>';

        return $post_content;
    }

    private function get_image_url($entry) {
        $imageUrl = '';
        if (isset($entry['object']['attachments'][0]['image']['url'])) {
            if (isset($entry['object']['attachments'][0]['fullImage']['url']) &&
                filter_var($entry['object']['attachments'][0]['fullImage']['url'], FILTER_VALIDATE_URL)) {
                $imageUrl =  $entry['object']['attachments'][0]['fullImage']['url'];
            }
            else {
                $imageUrl = $entry['object']['attachments'][0]['image']['url'];
            }
        }
        elseif ($entry['object']['attachments'][0]['objectType'] == "album") {
            // get real images, 1000px width
            // https://lh6.googleusercontent.com/-Zinm3m1LeUc/UrVaVE40bzI/AAAAAAAAArE/iym-o-9Lh4Q/w126-h126-p/1-2.jpg
            // https://lh6.googleusercontent.com/-Zinm3m1LeUc/UrVaVE40bzI/AAAAAAAAArE/iym-o-9Lh4Q/s1000/1-2.jpg
            $i = 0;
            foreach($entry['object']['attachments'][0]['thumbnails'] as $attachment) {
                $images[$i]['link_url'] = $attachment['url'];
                $image_url = $attachment['image']['url'];
                $image_url = str_replace("w126-h126-p", "s1000", $image_url);
                $image_url = str_replace("w379-h379-p", "s1000", $image_url);
                $images[$i]['image_url'] = $image_url;
                $images[$i]['title'] = $attachment['description'];
                $i++;
            }
            $imageUrl = $images;
            //parent::log(print_r($images, true));
        }
        return $imageUrl;
    }
}
