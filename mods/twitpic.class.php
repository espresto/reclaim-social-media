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

class twitpic_reclaim_module extends reclaim_module {
/*
    public RSS, gets 20 last images of a user
*/  
    private static $apiurl = "http://api.twitpic.com/2/users/show.json?username=%s&page=%s";
    private static $count = 20; // standard value
    private static $lang = 'de-de';
    private static $timeout = 15;
    private static $post_format = 'image'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'twitpic';
        $this->has_ajaxsync = true;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'twitpic_user_name');
    }

    public function display_settings() {

        $displayname = __('TwitPic', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('TwitPic user name (same as on Twitter)', 'reclaim'); ?></th>
            <td><input type="text" name="twitpic_user_name" value="<?php echo get_option('twitpic_user_name'); ?>" /></td>
        </tr>
<?php
    }

    public function import($forceResync) {
        $user_name = get_option('twitpic_user_name');
        if ( isset($user_name) ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, $user_name, 1), self::$timeout);
            $rawData = json_decode($rawData, true);
            if ($rawData) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
                parent::log(sprintf(__('END %s posts import', 'reclaim'), $this->shortname));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    public function ajax_resync_items() {
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
        
        $user_name = get_option('twitpic_user_name');
        if ( isset($user_name) ) {
            if ($next_url != '') {
                $rawData = parent::import_via_curl($next_url, self::$timeout);
            }
            else {
                $rawData = parent::import_via_curl(sprintf(self::$apiurl, $user_name, 1), self::$timeout);
            }

            $rawData = json_decode($rawData, true);

            if ($rawData) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
                parent::log(sprintf(__('END %s posts import', 'reclaim'), $this->shortname));

                $newoffset = $offset + sizeof($data);
                $page = floor($newoffset / self::$count)+1;
                $next_url = sprintf(self::$apiurl, $user_name, $page);
                $return['result'] = array(
                    'offset' => $newoffset,
                    // take the next pagination url instead of calculating
                    // a self one
                    'next_url' => $next_url,
                );
                $return['success'] = true;
            }
            else $return['error'] = sprintf(__('%s %s returned no data. No import was done', 'reclaim'), $this->shortname, $type);
        }
        else $return['error'] = sprintf(__('%s %s user data missing. No import was done', 'reclaim'), $this->shortname, $type);
        
        
        echo(json_encode($return));
         
        die();
    }

    private function map_data($rawData, $type="posts") {
        $data = array();
        foreach($rawData['images'] as $entry) {
            $title = $entry['title'];
            $link  = 'http://twitpic.com/'.$entry['short_id'].'/';
            $id = $entry['id'];
            $short_id = $entry['short_id'];
            $status_id = $entry['status_id'];
            $in_reply_to_status_id = $entry['in_reply_to_status_id'];
            $location = $entry['location'];
            $post_meta["short_id"] = $short_id;
            $post_meta["location"] = $location;
            $post_meta["status_id"] = $status_id;
            $post_meta["in_reply_to_status_id"] = $in_reply_to_status_id;
            
            $title = $entry['message'];
            $description = '[gallery size="large" columns="'.$columns.'" link="file"]'.$entry['message'];
            $twitterlink = ($status_id ? ', <a rel="syndication" href="http://twitter.com/'.get_option('twitpic_user_name').'/status/'.$status_id.'">'.sprintf(__('view on %s', 'reclaim'), 'Twitter').'</a>' : '');
            $description .= '
                <p class="viewpost-twitpic">(<a rel="syndication" href="'.$link.'">'.sprintf(__('View on %s', 'reclaim'), 'TwitPic').'</a>'.$twitterlink.')</p>';
            // todo $entry['video']
            // http://twitpic.com/show/thumb/xm9k.jpg
            // http://d3j5vwomefv46c.cloudfront.net/photos/thumb/1568504.jpg?1230567269
            // http://d3j5vwomefv46c.cloudfront.net/photos/large/1535734.jpg?1230354023
            // http://twitpic.com/show/large/xm9k.jpg
            // http://twitpic.com/show/large/3puuqi.png
            $image_url = 'http://twitpic.com/show/large/'. $short_id . '.' . $entry['type'];
            //$image_url = 'http://d3j5vwomefv46c.cloudfront.net/photos/large/'. $id . '.' . $entry['type'];
            $tags = '';
            //$tags = explode(" ",$entry['tags']);
            //$content = self::construct_content($link, $image_url, $title, $description);
            /*
            if ($entry['geo_is_public']) {
                $post_meta["geo_latitude"] = $entry['latitude'];
                $post_meta["geo_longitude"] = $entry['longitude'];
            }
            else {
                unset($post_meta["geo_latitude"]);
                unset($post_meta["geo_longitude"]);
            }
            */

            $post_meta["_".$this->shortname."_link_id"] = $id;
            $post_meta["_post_generator"] = $this->shortname;
            $post_meta["_reclaim_post_type"] = $type;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["timestamp"]))),
                'post_content' => $description,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_embed_code' => '',
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    public function count_items() {
        $user_name = get_option('twitpic_user_name');
        if ( isset($user_name) ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, $user_name, 1), self::$timeout);
            $rawData = json_decode($rawData, true);
            //parent::log(print_r($rawdata, true));
            return $rawData['photo_count'];
        }
        else {
            return false;
        }
    }

    private function construct_content($link, $image_url, $title, $description) {
        $post_content_constructed_simple = '<a rel="syndication" href="'.$link.'"><img src="'.$image_url.'" alt="'.$title.'"></a><br />'.$description;
        $post_content_constructed = 
            '<div class="flimage">[gallery size="large" columns="1" link="file"]</div>'.'<p>'.$description.'</p>'
            .'<p class="viewpost-twitpic">(<a rel="syndication" href="'.$link.'">'.__('View on Flickr', 'reclaim').'</a>)</p>'
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
