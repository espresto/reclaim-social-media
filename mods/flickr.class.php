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

class flickr_reclaim_module extends reclaim_module {
    private static $shortname = 'flickr';
/*
    public RSS, gets 20 last images of a user
*/
    private static $apiurl = "http://www.flickr.com/services/feeds/photos_public.gne?id=%s&lang=%s&format=json";
//    http://www.flickr.com/services/feeds/photos_public.gne?id=35591378@N03&lang=de-de&format=json

/*
    querying the API requires an API-key, but gets more than 20 images. in fact
    if you bother qurying all pages, you can get all images of a user
	docs: http://www.flickr.com/services/api/flickr.people.getPublicPhotos.html

	this should be an optional method if a api key is provided. it gets better and
	more data, but poses additional trouble to the user to obtain a key.

	so we go for the simple method first.

	for later, check out this: http://phpflickr.com/

*/

//    private static $apiurl = "http://api.flickr.com/services/rest/?method=flickr.people.getPublicPhotos";
/*
	$apiurl = "http://api.flickr.com/services/rest/?method=flickr.people.getPublicPhotos"
	."&user_id=".$userid
	."&per_page=".$count."&page=1&format=feed-atom_10"
	."&api_key=".$flickr_api_key;
*/

    private static $count = 20; //maximum für flickr RSS
    private static $lang = 'de-de';
    private static $timeout = 15;
    private static $post_format = 'image'; // or 'status', 'aside'

    public static function register_settings() {
        parent::register_settings(self::$shortname);

        register_setting('reclaim-social-settings', 'flickr_user_id');
        register_setting('reclaim-social-settings', 'flickr_api_key');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Flickr', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('flickr user id', 'reclaim'); ?></th>
            <td><input type="text" name="flickr_user_id" value="<?php echo get_option('flickr_user_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('(optional) flickr api key', 'reclaim'); ?></th>
            <td><input type="text" name="flickr_api_key" value="<?php echo get_option('flickr_api_key'); ?>" /></td>
        </tr>
<?php
    }

    public static function import($forceResync) {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (get_option('flickr_user_id') ) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));
            update_option('reclaim_'.self::$shortname.'_locked', 1);
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('flickr_user_id'), self::$lang), self::$timeout);
			// http://stackoverflow.com/questions/2752439/decode-json-string-returned-from-flickr-api-using-php-curl
			$rawData = str_replace( 'jsonFlickrFeed(', '', $rawData );
			$rawData = substr( $rawData, 0, strlen( $rawData ) - 1 ); //strip out last paren
            $rawData = json_decode($rawData, true);
            if (is_array($rawData)) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));
            }
            else {
	            parent::log(sprintf(__('no %s data', 'reclaim'), self::$shortname));
            }
            update_option('reclaim_'.self::$shortname.'_locked', 0);
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));

    }

    private static function map_data($rawData) {
        $data = array();
        foreach($rawData['items'] as $entry){
            //date_taken
            //published
            //description
            //tags
            $title = $entry['title'];
            $id = self::get_id($entry["link"]);
            $image_url = self::get_image_url($entry["media"]["m"]);
            $description = self::get_flickr_description($entry["description"]);
            $tags = explode(" ",$entry['tags']);
            $content = self::construct_content($entry,$id,$image_url,$description);
            $data[] = array(
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["date_taken"]))),
//                'post_excerpt' => $description,
                'post_content' => $content['constructed'],
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry['link'],
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id
            );

        }
        return $data;
    }

    private static function get_id($link){
    	// http://www.flickr.com/photos/92049783@N06/8763490364/
		// http://stackoverflow.com/questions/15118047/php-url-explode
		$link = substr($link, 0, -1);
    	$r = parse_url($link);
		$id = strrchr($r['path'], '/');
		$id = substr($id, 1);

		return $id;
	}

	public static function get_flickr_description($description) {
		$html = new simple_html_dom();
		$html->load($description);
		// get rid of img and a
		foreach($html->find('img') as $e)
		    $e->outertext = '';
		foreach($html->find('a') as $e)
		    $e->outertext = '';
		//find last p, thats the plain description
		$description_plain= $html->find('p', -1)->innertext;
		return $description_plain;
	}

    private static function get_image_url($url){
		// get large image instead of medium size image
		// z: medium
		// b: large
//		$url = str_replace( '_m.jpg', '_z.jpg', $url );
		$url = str_replace( '_m.jpg', '_b.jpg', $url );
		return $url;
	}


    private static function construct_content($entry,$id,$image_url,$description){
		// flickr embed code:
		// <iframe src="http://www.flickr.com/photos/92049783@N06/8497830300/player/" width="500" height="375" frameborder="0" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe>
        $post_content_original = $entry['description'];
        $post_content_original = html_entity_decode($post_content); // ohne trim?

		$post_content_constructed_simple = '<a href="'.$entry['link'].'"><img src="'.$image_url.'" alt="'.$entry['title'].'"></a><br />'.$description;
		$post_content_constructed = '<div class="flimage">[gallery size="large" columns="1" link="file"]</div>'.'<p>'.$description.'</p>';

		$embed_code = '<frameset><iframe src="'.$entry['link'].'/player/'.'" width="500" height="375" frameborder="0" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe><noframes>'.$post_content_constructed_simple.'</noframes></frameset>';

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed,
            'embed_code' => $embed_code,
            'image' => $image_url
        );

        return $content;
    }


}
