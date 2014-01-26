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

/*
* used some code from https://github.com/FaridW/Vine
* curl work is done here, because vine requires an iphone user agent
* todo: get video file and save it to the media library
*/

class vine_reclaim_module extends reclaim_module {
    // api calls hav their own function
    private static $apiurl = "";
    private static $timeout = 15;
    private static $count = 20;
    private static $post_format = 'video'; // or 'status', 'aside'

    public function __construct() {
        $this->shortname = 'vine';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'vine_user_id');
        register_setting('reclaim-social-settings', 'vine_password');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Vine', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('vine email', 'reclaim'); ?></th>
            <td><input type="text" name="vine_user_id" value="<?php echo get_option('vine_user_id'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('vine password', 'reclaim'); ?></th>
            <td><input type="text" type="password" name="vine_password" value="<?php echo get_option('vine_password'); ?>" /></td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('vine_user_id') ) {
            $key = self::vineAuth(get_option('vine_user_id'),get_option('vine_password'));
            $userId = strtok($key,'-');
            $rawData = self::vineTimeline($userId,$key);
//            parent::log(print_r($rawData,1));

            if (is_array($rawData)) {
                $data = self::map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
            else {
                parent::log(sprintf(__('no %s data', 'reclaim'), $this->shortname));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));

    }

    private function map_data($rawData) {
        $data = array();
        //echo '<li><a href="'.$record->permalinkUrl.'">'.$record->description.' @ '.$record->venueName.'</a></li>';
        foreach($rawData['records'] as $entry){
            $description = htmlentities($entry['description']);
            $venueName = $entry['venueName'];
            if (isset($description) && isset($venueName)) {
            	$title = $description . ' @ ' . $venueName;
            }
            elseif ( isset($description) && !isset($venueName)) {
            	$title = $description;
            }
            else {
            	$title = ' @ ' . $venueName;
            }
            $id = $entry["permalinkUrl"];
            $image_url_explode = explode('?', $entry["thumbnailUrl"]);
            $image_url = $image_url_explode[0];
            $tags = $entry['tags'];
            $content = self::construct_content($entry,$id,$image_url,$title);
            $post_meta["geo_address"] = $entry['venueAddress'];
            if ($entry["venueCity"]!="")
                $post_meta["geo_address"] .= ', '.$entry['venueCity'];
            if ($entry["venueState"]!="")
                $post_meta["geo_address"] .= ', '.$entry['venueState'];
            $post_meta["venueName"] = $entry['venueName'];
            $post_meta["foursquareVenueId"] = $entry['foursquareVenueId'];
            $post_meta["venueCategoryId"] = $entry['venueCategoryId'];

            $post_meta["_".$this->shortname."_link_id"] = $entry["id"];
            $post_meta["_post_generator"] = $this->shortname;

            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created"]))),
//                'post_excerpt' => $description,
//                'post_content' => $content['constructed'],
                'post_content' => $content['embed_code'],
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry['permalinkUrl'],
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_embed_code' => $content['embed_code'],
                'ext_guid' => $id,
                'post_meta' => $post_meta
            );

        }
        return $data;
    }

    private function construct_content($entry, $id, $image_url, $description) {
        $post_content_original = htmlentities($description);

/*
        $post_content_constructed = 'ich habe ein vine-video hochgeladen.'
            .'<a href="'.$entry['permalinkUrl'].'"><img src="'.$image_url.'" alt="'.$description.'"></a>';
*/
        $post_content_constructed = 'ich habe <a href="'.$entry['permalinkUrl'].'">ein vine-video</a> hochgeladen.'
            .'<a href="'.$entry['permalinkUrl'].'">'
            .'<div class="viimage">[gallery size="large" columns="1" link="file"]</div>'
            .'</a>';

        // vine embed code:
        $embed_code = '<frameset><iframe class="vine-embed" src="'.$entry['permalinkUrl'].'/embed/simple" width="600" height="600" frameborder="0"></iframe>'
            .'<noframes>'
            .'ich habe <a href="'.$entry['permalinkUrl'].'">ein vine-video</a> hochgeladen.'
            .'<a href="'.$entry['permalinkUrl'].'">'
            .'<div class="viimage">[gallery size="large" columns="1" link="file"]</div>'
            .'</a>'
            .'</noframes></frameset>'
            .'<script async src="//platform.vine.co/static/scripts/embed.js" charset="utf-8"></script>';

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed,
            'embed_code' => $embed_code,
            'image' => $image_url
        );

        return $content;
    }

    private function vineAuth($username, $password) {
        $loginUrl =	"https://api.vineapp.com/users/authenticate";
        $username = urlencode($username);
        $password = urlencode($password);
        $token = sha1($username); // I believe this field is currently optional, but always sent via the app

        $postFields = "deviceToken=$token&password=$password&username=$username";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_USERAGENT, "iphone/110 (iPhone; iOS 7.0.4; Scale/2.00)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = json_decode(curl_exec($ch));

        if (!$result)
        {
            curl_error($ch);
        }
        else
        {
            // Key also contains numeric userId as the portion of the string preceding the first dash
            return $result->data->key;
        }

        curl_close($ch);
    }

    private function vineTimeline($userId, $key) {
        // Additional endpoints available from https://github.com/starlock/vino/wiki/API-Reference
        //$url = 'https://vine.co/api/timelines/users/906592469217587200';
        //$url = 'https://api.vineapp.com/timelines/users/'.$userId;
        $url = 'https://api.vineapp.com/timelines/users/'.$userId.'?size='.self::$count;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "iphone/110 (iPhone; iOS 7.0.4; Scale/2.00)");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('vine-session-id: '.$key));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $result = json_decode(curl_exec($ch), true);

        if (!$result) {
            echo curl_error($ch);
            parent::log('curl error:'.curl_error($ch));
        }
        else
        {
            return $result['data'];
        }

        curl_close($ch);
    }
}
