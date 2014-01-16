<?php
class twitter_reclaim_module extends reclaim_module {
    private static $shortname = 'twitter';
    private static $apiurl = "https://api.twitter.com/1.1/statuses/user_timeline.json";
    private static $count = 200;
    private static $lang = 'en';
    private static $post_format = 'status'; // or 'status', 'aside'

//	const TWITTER_TWEET_TPL = '<blockquote class="twitter-tweet imported"><p>%s</p>%s&mdash; %s (<a href="https://twitter.com/%s/">@%s</a>) <a href="http://twitter.com/%s/status/%s">%s</a></blockquote><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';

    public static function register_settings() {
        parent::register_settings(self::$shortname);

        register_setting('reclaim-social-settings', 'twitter_username');
        register_setting('reclaim-social-settings', 'twitter_consumer_key');
        register_setting('reclaim-social-settings', 'twitter_consumer_secret');
        register_setting('reclaim-social-settings', 'twitter_user_token');
        register_setting('reclaim-social-settings', 'twitter_user_secret');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Twitter', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings(self::$shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('twitter username', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_username" value="<?php echo get_option('twitter_username'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter consumer key', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_consumer_key" value="<?php echo get_option('twitter_consumer_key'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter consumer secret', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_consumer_secret" value="<?php echo get_option('twitter_consumer_secret'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter user token', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_user_token" value="<?php echo get_option('twitter_user_token'); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('twitter user secret', 'reclaim'); ?></th>
            <td><input type="text" name="twitter_user_secret" value="<?php echo get_option('twitter_user_secret'); ?>" /></td>
        </tr>
<?php
    }

    public static function import() {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (get_option('twitter_consumer_key') && get_option('twitter_consumer_secret') && get_option('twitter_user_token') && get_option('twitter_user_secret')) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));

            do {
                $tmhOAuth = new tmhOAuth(array(
                    'consumer_key' => get_option('twitter_consumer_key'),
                    'consumer_secret' => get_option('twitter_consumer_secret'),
                    'user_token' => get_option('twitter_user_token'),
                    'user_secret' => get_option('twitter_user_secret'),
                ));

                $reqOptions = array(
                    'lang' => self::$lang,
                    'count' => self::$count,
                    'screen_name' => get_option('twitter_username'),
                    'include_rts' => "false",
                    'exclude_replies' => "true",
                    'include_entities' => "true"
                );
                if (isset($lastid)) {
                    $reqOptions['max_id'] = $lastid;
                }
                $tmhOAuth->request('GET', self::$apiurl, $reqOptions, true);

                if ($tmhOAuth->response['code'] == 200) {
                    $data = self::map_data(json_decode($tmhOAuth->response['response'], true));
                    parent::insert_posts($data);

                    $reqOk = count($data) > 0 && $data[count($data)-1]["ext_guid"] != $lastid;
                    $lastid = $data[count($data)-1]["ext_guid"];
                    parent::log(sprintf(__('Retrieved set of twitter messages: %d, last id: %s, req-ok: %d', 'reclaim'), count($data), $lastid, $reqOk));

                    update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));
                }
                else {
                    $reqOk = false;
                    parent::log(sprintf(__('GET failed with: %s', 'reclaim'), $tmhOAuth->response['code']));
                }
            } while ($reqOk);
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));
    }

    private static function map_data($rawData) {
        $data = array();
        $tags = array();
        foreach($rawData as $entry) {
            $content = self::get_content($entry);
            $tags = self::get_hashtags($entry);
            // http://codex.wordpress.org/Function_Reference/wp_insert_post
            $data[] = array(
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_date' => date('Y-m-d H:i:s', strtotime($entry["created_at"])),
                'post_format' => self::$post_format,
// neu
				'post_content'   => $content['embedcode'],
// changed
//                'post_excerpt' => $content['embedcode'],
//                'post_excerpt' => $content['original'],
                'post_title' => strip_tags($content['original']),
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => 'http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"],
                'ext_image' => $content['image'],
                'ext_guid' => $entry["id_str"]
            );
        }
        return $data;
    }

    private static function get_hashtags($entry) {
		$tags = array();
        if (count($entry['entities']['hashtags'])) {
            foreach ($entry['entities']['hashtags'] as $hashtag) {
                $tags[] = $hashtag['text'];
            }
        }
        return $tags;
	}

    private static function get_content($entry) {
        $post_content = $entry['text'];
        $post_content = html_entity_decode($post_content); // ohne trim?
        //links einsetzen/auflösen
        if (count($entry['entities']['urls'])) {
            foreach ($entry['entities']['urls'] as $url) {
                $post_content = str_replace( $url['url'], '<a href="'.$url['expanded_url'].'">'.$url['display_url'].'</a>', $post_content);
            }
        }
        $image_url = "";
        $image_html = "";
        if (isset($entry['entities']['media']) && $entry['entities']['media']) {
            foreach ($entry['entities']['media'] as $media) {
                $post_content = str_replace( $media['url'], '<a href="'.$media['expanded_url'].'">'.$media['display_url'].'</a>', $post_content);
                if ($media['type']=="photo") {
                    $image_url = $media['media_url'];
                    $image_html = '<div class="twitter-image"><a href="'.$media['expanded_url'].'"><img src="'.$image_url.'" alt=""></a></div>';
                }
            }
        }
        $post_content = preg_replace( "/\s((http|ftp)+(s)?:\/\/[^<>\s]+)/i", " <a href=\"\\0\" target=\"_blank\">\\0</a>",$post_content);
        $post_content = preg_replace('/[@]+([A-Za-z0-9-_]+)/', '<a href="http://twitter.com/\\1" target="_blank">\\0</a>', $post_content );

	        // Autolink hashtags (wordpress funktion)
        $post_content = preg_replace('/(^|[^0-9A-Z&\/]+)(#|\xef\xbc\x83)([0-9A-Z_]*[A-Z_]+[a-z0-9_\xc0-\xd6\xd8-\xf6\xf8\xff]*)/iu', '${1}<a href="http://twitter.com/search?q=%23${3}" title="#${3}">${2}${3}</a>', $post_content);

	// original twitter embed code (more or less)
        $embedcode = '<blockquote class="twitter-tweet imported"><p>'.$post_content.'</p>'.$image_html.'&mdash; '.$entry['user']['name'].' (<a href="https://twitter.com/'.$entry['user']['screen_name'].'/">@'.$entry['user']['screen_name'].'</a>) <a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.date('d.m.Y H:i', strtotime($entry["created_at"])).'</a></blockquote><script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';
	// if these are one's own tweets, there is no point to mark the username. also the date and time is supeficial.
		$embedcode = '<blockquote class="twitter-tweet imported"><p>'.$post_content.'</p><div class="twimage">'.$image_html.'</div><span style="display: none;">&mdash; '.$entry['user']['name'].' (<a href="https://twitter.com/'.$entry['user']['screen_name'].'/">@'.$entry['user']['screen_name'].'</a>) <a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.date('d.m.Y H:i', strtotime($entry["created_at"])).'</a></span><p class="twviewpost-twitter">(<a href="http://twitter.com/'.get_option('twitter_username').'/status/'.$entry["id_str"].'">'.__('View on Twitter', 'reclaim').'</a>)</p></blockquote>';

/*
		setlocale (LC_ALL, get_bloginfo ( 'language' ) );
		$embedcode = sprintf(
				self::TWITTER_TWEET_TPL,
				$post_content,
				$image_html,
				$entry['user']['name'],
				$entry['user']['screen_name'],
				$entry['user']['screen_name'],
				get_option('twitter_username'),
				$entry["id_str"],
				date('d.m.Y H:i', strtotime($entry["created_at"]))
//				date(get_option('date_format'), strtotime($entry["created_at"]))
			);
*/
        $content = array(
            'original' =>  $post_content,
            'embedcode' => $embedcode,
            'image' => $image_url
        );

        return $content;
    }
}
