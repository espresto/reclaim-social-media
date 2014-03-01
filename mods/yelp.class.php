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

class yelp_reclaim_module extends reclaim_module {
    private static $timeout = 15;
    private static $count = 10; // maximum: 10
    private static $post_format = 'article'; // no specific format

    public function __construct() {
        $this->shortname = 'yelp';
        $this->has_ajaxsync = false;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);
        register_setting('reclaim-social-settings', 'yelp_user_id');
    }

    public function display_settings() {
?>
<?php
        $displayname = __('Yelp', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row">
                <label for="yelp_user_id"><?php _e('Yelp User ID', 'reclaim'); ?></label>
            </th>
            <td>
                <input type="text" name="yelp_user_id" class="widefat" value="<?php echo get_option('yelp_user_id'); ?>" />
                <p class="description"><?php _e('Enter your Yelp User ID (not name). (i.e. a2Q607M2qn7Ik3XS3fzmvQ)', 'reclaim'); ?></p>
            </td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('yelp_user_id') ) {
            if ( ! class_exists( 'SimplePie' ) )
                require_once( ABSPATH . WPINC . '/class-feed.php' );

            $rss_source = 'http://www.yelp.de/syndicate/user/' . get_option('yelp_user_id') . '/rss.xml';
            /* Create the SimplePie object */
            $feed = new SimplePie();
            /* Set the URL of the feed you're retrieving */
            $feed->set_feed_url( $rss_source );
            /* Tell SimplePie to cache the feed using WordPress' cache class */
            $feed->set_cache_class( 'WP_Feed_Cache' );
            /* Tell SimplePie to use the WordPress class for retrieving feed files */
            $feed->set_file_class( 'WP_SimplePie_File' );
            /* Tell SimplePie how long to cache the feed data in the WordPress database */
            //$feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', get_option('reclaim_update_interval'), $rss_source ) );
            $feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', get_option('reclaim_update_interval'), $rss_source ) );
            /* Run any other functions or filters that WordPress normally runs on feeds */
            do_action_ref_array( 'wp_feed_options', array( &$feed, $rss_source ) );
            /* Initiate the SimplePie instance */
            $feed->init();
            /* Tell SimplePie to send the feed MIME headers */
            $feed->handle_content_type();

            if ( $feed->error() ) {
                parent::log(sprintf(__('no %s data', 'reclaim'), $this->shortname));
                parent::log($feed->error());
            }
            else {
                $data = self::map_data($feed);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function map_data($feed) {
        $data = array();
        $count = self::$count;

        foreach( $feed->get_items( 0, $count ) as $item ) {
            $id = $item->get_permalink();
            // check if item already exists. if so, 
            // no need to run through scraping et all
            if (!parent::post_exists($id)) {
                $title 	= str_replace("on Yelp", "", $item->get_title());
                $link 	= $item->get_permalink();
                $image_url = '';
                $published = $item->get_date();
                // http://www.yelp.com/biz/salon-graf-frisiersalon-berlin#hrid:C2JflaCF8jja2a5PHr7iHQ
                // lets get the yelp id from the url (hrid)
                //preg_match_all('/#hrid:(\w+)/', $link, $match);
                preg_match_all('/#hrid:(.*)/', $link, $match);
                $hrid = $match[1][0];
                // scrape the full description, not only the shortened RSS version
                // $description = self::process_content($item);

                $long_description = self::get_yelp_description($link, $hrid);
                $description = '<p class="anonce-yelp"><a rel="syndication" href="'.$link.'">'.__('I wrote a review on Yelp', 'reclaim').'</a>:</p>';
                $description .= ($long_description != '' ? $long_description : self::process_content($item));
                $description .= '<p class="viewpost-yelp">(<a rel="syndication" href="'.$link.'">'.__('View on Yelp', 'reclaim').'</a>)</p>';

                /*
                *  set post meta galore start
                */
                $post_meta["_".$this->shortname."_link_id"] = $id;
                $post_meta["_post_generator"] = $this->shortname;
                // in case someone uses WordPress Post Formats Admin UI
                // http://alexking.org/blog/2011/10/25/wordpress-post-formats-admin-ui
                $post_meta["_format_link_url"]  = $link;
                $post_meta["geo_latitude"] = $item->get_latitude();
                $post_meta["geo_longitude"] = $item->get_longitude();
                /*
                *  set post meta galore end
                */

                $data[] = array(
                    'post_author' => get_option(self::shortName().'_author'),
                    'post_category' => array(get_option(self::shortName().'_category')),
                    'post_format' => self::$post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($published))),
//                    'post_excerpt' => $description,
//                    'post_content' => $description['constructed'],
                    'post_content' => $description,
                    'post_title' => $title,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'ext_permalink' => $link,
                    'ext_image' => $image_url,
                    'tags_input' => $tags,
                    'ext_guid' => $id,
                    'post_meta' => $post_meta
                );
            }

        }
        return $data;
    }

    private function process_content($item) {
        $post_content_original = $item->get_description();
        $bookmark_domain = parse_url($item->get_permalink());
        $bookmark_domain = $bookmark_domain['host'];
        $post_content_constructed =
            '<div class="bomessage">'
            .$post_content_original
            .'</div>'
            .'<p class="viewpost-yelp syndicationlink">(<a href="'.$item->get_permalink().'">'.sprintf(__('View on %s', 'reclaim'), $bookmark_domain).'</a>)</p>'
            ;

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed
        );

        return $content;
    }

    private function get_yelp_description($url, $id) {
        $args = array(
            'timeout'     => $timeout,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo( 'url' ),
            'blocking'    => true,
            'headers'     => array('Accept-Language' => 'de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4'),
            'cookies'     => array(),
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => true,
            'stream'      => false,
            'filename'    => null
        );
        $response = wp_remote_get( $url, $args );
        if( is_wp_error( $response ) ) {
            parent::log('error while loading '.$url.': '.$response->get_error_message());
            return false;
        }
        $body = trim($response['body']);
        $html = new simple_html_dom();
        $html->load($body);

        // http://www.yelp.com/biz/salon-graf-frisiersalon-berlin#hrid:C2JflaCF8jja2a5PHr7iHQ
        // this won't show us the review with our hrid, so lets only get the biz id
        //
        // <meta name="yelp-biz-id" content="KP_8I0zN9s50fWWf-S6mgg">
        $bizID = $html->find('meta[name="yelp-biz-id"]',0)->content;
        // on this page will get our review for sure
        // so lets construct the url
        $sendToFriendPage = 'http://www.yelp.com/biz_share?bizid='.$bizID.'&return_url=foo&reviewid='.$id;
        // and get the page
        $response = wp_remote_get( $sendToFriendPage, $args );
        if( is_wp_error( $response ) ) {
            parent::log('error while loading '.$sendToFriendPage.': '.$response->get_error_message());
            return false;
        }
        $body = trim($response['body']);
        $html = new simple_html_dom();
        $html->load($body);
        // fix relative yelp urls 
        // (<a href="/redir?url=http%3A%2F%2Flandbrot.de&amp;s=3bfa424ca015c75831beb8ebe85fadd0eb9f19ff676338045a36d1fd9a8399e7" target="_blank" rel="nofollow">landbrot.de</a>)
        // http://stackoverflow.com/questions/4784243/how-do-i-extract-query-parameters-from-an-url-string-in-php
        foreach($html->find('a') as $link) {
            $query = parse_url('http://www.yelp.com' . $link->href, PHP_URL_QUERY);
            parse_str($query, $params);
            $link->href = urldecode($params['url']);
            //$link->href = 'http://www.yelp.com' . $link->href;
        }
        // now get our review
        $ret = $html->find('div[id=biz_review]',0)->innertext;

        return $ret;
    }

}

