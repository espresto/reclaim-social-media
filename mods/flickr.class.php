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
/*
    public RSS, gets 20 last images of a user
*/  
    private static $feedurl = "http://www.flickr.com/services/feeds/photos_public.gne?id=%s&lang=%s&format=json";
    private static $apiurl  = "http://api.flickr.com/services/rest/?method=flickr.people.getPublicPhotos&user_id=%s&per_page=%s&page=%s&format=json&api_key=%s&extras=description,license,date_upload,date_taken,owner_name,icon_server,original_format,last_update,geo,tags,machine_tags,o_dims,views,media,url_l,url_o";

/*
    querying the API requires an API-key, but gets more than 20 images. in fact
    if you bother qurying all pages, you can get all images of a user
	docs: http://www.flickr.com/services/api/flickr.people.getPublicPhotos.html

	this should be an optional method if a api key is provided. it gets better and
	more data, but poses additional trouble to the user to obtain a key.

	so we go for the simple method first.

	for later, check out this: http://phpflickr.com/

*/

    private static $count = 10; // maximum für flickr RSS: 20
    private static $lang = 'de-de';
    private static $timeout = 15;
    private static $post_format = 'image'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'flickr';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'flickr_user_id');
        register_setting('reclaim-social-settings', 'flickr_api_key');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><a name="<?php echo $this->shortName(); ?>"></a><h3><?php _e('Flickr', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('flickr user id', 'reclaim'); ?></th>
            <td><input type="text" name="flickr_user_id" value="<?php echo get_option('flickr_user_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('(optional) flickr api key', 'reclaim'); ?></th>
            <td><input type="text" name="flickr_api_key" value="<?php echo get_option('flickr_api_key'); ?>" />
            <p class="description">Get your Flickr API key <a href="http://www.flickr.com/services/apps/by/me">here</a>.
            If you don't have a flickr app yet, <a href="http://www.flickr.com/services/apps/create/apply">apply for a noncommercial app key</a>.
            After successful registration, click on "view app key" and copy it here.
            This will pull your public photos only.</p>
            <p class="description">If you don't enter an API key, only the latest 20 pictures will be copied and the full ajax sync won't work.</p>

            </td>
        </tr>
<?php
    }

    public function import($forceResync) {
        $user_id = get_option('flickr_user_id');
        $app_key = get_option('flickr_api_key');
        if ( isset($user_id) && !isset($app_key) ) {
            $rawData = parent::import_via_curl(sprintf(self::$feedurl, get_option('flickr_user_id'), self::$lang), self::$timeout);
            // http://stackoverflow.com/questions/2752439/decode-json-string-returned-from-flickr-api-using-php-curl
            $rawData = str_replace( 'jsonFlickrFeed(', '', $rawData );
            $rawData = substr( $rawData, 0, strlen( $rawData ) - 1 ); //strip out last paren
            $rawData = json_decode($rawData, true);
            if (is_array($rawData)) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
            else {
	            parent::log(sprintf(__('no %s data', 'reclaim'), $this->shortname));
            }
        }
        elseif ( isset($user_id) && isset($app_key) ) {
            $i = 0;
            // todo: loop through pages to get all images...
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('flickr_user_id'), self::$count, $i+1, get_option('flickr_api_key')), self::$timeout);
            $rawData = str_replace( 'jsonFlickrApi(', '', $rawData );
            $rawData = substr( $rawData, 0, strlen( $rawData ) - 1 ); //strip out last paren
            $rawData = json_decode($rawData, true);
            if (is_array($rawData)) {
                $data = self::map_api_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));

    }

    public function ajax_resync_items() {
        // the type comes magically back from the
        // data-resync="{type:'favs'}" - attribute of the submit-button.
        // favs not implemented yet
        $type = isset($_POST['type']) ? $_POST['type'] : 'posts';
        $offset = intval( $_POST['offset'] );
        $limit = intval( $_POST['limit'] );
        $count = intval( $_POST['count'] );
        $next_url = isset($_POST['next_url']) ? $_POST['next_url'] : '';
        $user_id = get_option('flickr_user_id');
        $app_key = get_option('flickr_api_key');
    
        self::log($this->shortName().' ' . $type . ' resync '.$offset.'-'.($offset + $limit).':'.$count);
         
        $return = array(
            'success' => false,
            'error' => '',
            'result' => null
        );
                
        if ( isset($user_id) && isset($app_key) ) {
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            }
            else {
                //$apiurl_ = ($type == 'posts' ? self::$apiurl : self::$fav_apiurl);
                $rawData = parent::import_via_curl(sprintf(self::$apiurl, $user_id, self::$count, 1, $app_key), self::$timeout);
            }

            $rawData = str_replace( 'jsonFlickrApi(', '', $rawData );
            $rawData = substr( $rawData, 0, strlen( $rawData ) - 1 ); //strip out last paren
            $rawData = json_decode($rawData, true);

            if ($rawData['stat'] == "ok" && is_array($rawData)) { // xxx
                $data = self::map_api_data($rawData);
                //$data = self::map_data($rawData, $type);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
                //update_option('reclaim_'.$this->shortname.'_'.$type.'_last_update', current_time('timestamp'));
                $newoffset = $offset + sizeof($data);
                $page = floor($newoffset / self::$count)+1;
                $next_url = sprintf(self::$apiurl, $user_id, self::$count, $page, $app_key);
                $return['result'] = array(
                    'offset' => $newoffset,
                    // take the next pagination url instead of calculating
                    // a self one
                    'next_url' => $next_url,
                );
                $return['success'] = true;
            }
            elseif ($rawdata['stat'] != "ok") {
                $return['error'] = $rawData['message'] . " (Error code " . $rawData['code'] . ")";
            }
            else $return['error'] = sprintf(__('%s %s returned no data. No import was done', 'reclaim'), $this->shortname, $type);
        }
        else $return['error'] = sprintf(__('%s %s user data missing. No import was done', 'reclaim'), $this->shortname, $type);
        
        
        echo(json_encode($return));
         
        die();
    }


    private function map_data($rawData) {
        $data = array();
        foreach($rawData['items'] as $entry) {
            //date_taken
            //published
            //description
            //tags
            $title = $entry['title'];
            $id = self::get_id($entry["link"]);
            $link = $entry["link"];
            $image_url = self::get_image_url($entry["media"]["m"]);
            $description = self::get_flickr_description($entry["description"]);
            $tags = explode(" ",$entry['tags']);
            $content = self::construct_content($link, $image_url, $title, $description);

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["published"]))),
//                'post_excerpt' => $description,
                'post_content' => $content['constructed'],
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry['link'],
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    private function map_api_data($rawData) {
        $data = array();
        foreach($rawData['photos']['photo'] as $entry) {
            $title = $entry['title'];
            $link  = 'http://www.flickr.com/photos/'.$entry['owner'].'/'.$entry['id'].'/';
            $id = $entry['id'];
            // original
            //$image_url = $entry["url_o"];
            // large
            $image_url = $entry["url_l"];
            $description = $entry["description"]['_content'];
            $tags = explode(" ",$entry['tags']);
            $content = self::construct_content($link, $image_url, $title, $description);
            
            if ($entry['geo_is_public']) {
                $post_meta["geo_latitude"] = $entry['latitude'];
                $post_meta["geo_longitude"] = $entry['longitude'];
            }
            else {
                unset($post_meta["geo_latitude"]);
                unset($post_meta["geo_longitude"]);
            }
            if ($entry['ispublic']) {
                $post_status = 'publish';
            }
            else {
                $post_status = 'draft';
            }

            $post_meta["_".$this->shortname."_link_id"] = $id;
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $entry["dateupload"])),
                'post_content' => $content['constructed'],
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => $post_status,
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    public function count_items() {
        $user_id = get_option('flickr_user_id');
        $app_key = get_option('flickr_api_key');
        if ( isset($user_id) && isset($app_key) ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, $user_id, 1, 1, $app_key), self::$timeout);
            $rawData = str_replace( 'jsonFlickrApi(', '', $rawData );
            $rawData = substr( $rawData, 0, strlen( $rawData ) - 1 ); //strip out last paren
            $rawData = json_decode($rawData, true);
            //parent::log(print_r($rawData, true));
            return $rawData['photos']['total'];
        }
        else {
            return false;
        }
    }

    private function get_id($link) {
        // http://www.flickr.com/photos/92049783@N06/8763490364/
        // http://stackoverflow.com/questions/15118047/php-url-explode
        $link = substr($link, 0, -1);
        $r = parse_url($link);
        $id = strrchr($r['path'], '/');
        $id = substr($id, 1);

        return $id;
    }

    public function get_flickr_description($description) {
        $html = new simple_html_dom();
        $html->load($description);
        // get rid of img and a
        foreach($html->find('img') as $e)
            $e->outertext = '';
        foreach($html->find('a[href*="flickr.com"]') as $e)
            $e->outertext = '';
        //find last p, thats the plain description
        $description_plain= $html->find('p', -1)->innertext;
        return $description_plain;
    }

    private function get_image_url($url) {
        // get large image instead of medium size image
        // z: medium
        // b: large
//        $url = str_replace( '_m.jpg', '_z.jpg', $url );
        $url = str_replace( '_m.jpg', '_b.jpg', $url );
        return $url;
    }

    private function construct_content($link, $image_url, $title, $description) {
        $post_content_constructed_simple = '<a rel="syndication" href="'.$link.'"><img src="'.$image_url.'" alt="'.$title.'"></a><br />'.$description;
        $post_content_constructed = 
            '<div class="flimage">[gallery size="large" columns="1" link="file"]</div>'.'<p>'.$description.'</p>'
            .'<p class="viewpost-flickr">(<a rel="syndication" href="'.$link.'">'.__('View on Flickr', 'reclaim').'</a>)</p>'
            .'';

        $embed_code = '<frameset><iframe src="'.$link.'/player/'.'" width="500" height="375" frameborder="0" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe><noframes>'.$post_content_constructed_simple.'</noframes></frameset>';

        $content = array(
            'constructed' =>  $post_content_constructed,
            'embed_code' => $embed_code,
            'image' => $image_url
        );

        return $content;
    }
}
