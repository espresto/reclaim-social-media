<?php
class youtube_reclaim_module extends reclaim_module {
    private static $shortname = 'youtube';
    private static $timeout = 20;
    private static $apiurl = "https://gdata.youtube.com/feeds/api/users/%s/uploads?alt=json&prettyprint=true&orderby=published&racy=include&v=2&client=ytapi-youtube-profile";
    private static $post_format = 'video'; // or 'status', 'aside'

    public static function register_settings() {
        parent::register_settings(self::$shortname);

        register_setting('reclaim-social-settings', 'youtube_username');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Youtube', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('youtube username', 'reclaim'); ?></th>
            <td><input type="text" name="youtube_username" value="<?php echo get_option('youtube_username'); ?>" /></td>
        </tr>
<?php
    }

    public static function import($forceResync) {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (get_option('youtube_username')) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, get_option('youtube_username')), self::$timeout);
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

    private static function map_data($rawData) {
        $data = array();
        foreach($rawData['feed']['entry'] as $entry) {
            $content = self::get_content($entry);

            $data[] = array(
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry['published']['$t']))),
//                'post_excerpt' => $content,
                'post_content' => $content,
                'post_title' => $entry['title']['$t'],
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $entry["link"][0]['href'],
                'ext_image' => $entry['media$group']['media$thumbnail'][2]['url'],
                'ext_guid' =>  $entry['id']['$t']
            );

        }
        return $data;
    }

    private static function get_content($entry){
	$video_id = 0;
        $post_content = '';
        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $entry["link"][0]['href'], $match)) {
            $video_id = $match[1];
        }
        $post_content = '<div class="ytembed yt"><iframe width="625" height="352" src="http://www.youtube.com/embed/'.$video_id.'" frameborder="0" allowfullscreen></iframe></div>';
        return $post_content;
    }
}
?>
