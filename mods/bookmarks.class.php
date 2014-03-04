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

class bookmarks_reclaim_module extends reclaim_module {
    private static $timeout = 15;
    private static $count = 30; // pinboard.in maximum: 400 ?count=400
    private static $post_format = 'link'; // no specific format

    public function __construct() {
        $this->shortname = 'bookmarks';
        $this->has_ajaxsync = false;
    }

    public function register_settings() {
        parent::register_settings($this->shortname);
        register_setting('reclaim-social-settings', 'bookmarks_api_url');
    }

    public function display_settings() {
?>
<?php
        $displayname = __('Bookmarks', 'reclaim');
        parent::display_settings($this->shortname, $displayname);
?>
        <tr valign="top">
            <th scope="row">
                Bookmarks settings
            </th>
            <td>
                <label for="bookmarks_api_url"><?php _e('Bookmarks URL', 'reclaim'); ?></label>
                <input type="text" name="bookmarks_api_url" class="widefat" value="<?php echo get_option('bookmarks_api_url'); ?>" />
                <p class="description"><?php _e('Enter a Feed URL for delicious.com or pinboard.in. (i.e. <code>http://feeds.pinboard.in/rss/u:{username}/t:{tag}/t:{tag}/</code> or <code>http://feeds.delicious.com/v2/rss/{username}/{tag[+tag+...+tag]}</code>)', 'reclaim'); ?></p>
            </td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('bookmarks_api_url') ) {
            if ( ! class_exists( 'SimplePie' ) )
                require_once( ABSPATH . WPINC . '/class-feed.php' );

            $rss_source = get_option('bookmarks_api_url');
            /* Create the SimplePie object */
            $feed = new SimplePie();
            /* Set the URL of the feed you're retrieving */
            $feed->set_feed_url( $rss_source );
            /* Tell SimplePie to cache the feed using WordPress' cache class */
            $feed->set_cache_class( 'WP_Feed_Cache' );
            /* Tell SimplePie to use the WordPress class for retrieving feed files */
            $feed->set_file_class( 'WP_SimplePie_File' );
            /* Tell SimplePie how long to cache the feed data in the WordPress database */
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

    private function map_data($feed, $type="posts") {
        $data = array();
        $count = self::$count;

        foreach( $feed->get_items( 0, $count ) as $item ) {
            $tags = array();
            $title 	= $item->get_title();
            $id 	= $item->get_permalink();
            $link 	= $item->get_permalink();
            $image_url = '';
            $published = $item->get_date();
            $description = self::process_content($item);
	        
            $bookmarks_api_url_parsed = parse_url(get_option('bookmarks_api_url'));
            $is_pinboard = ($bookmarks_api_url_parsed['host'] == "feeds.pinboard.in");
            $tags = array();

            if ($category= $item->get_category() && $is_pinboard) {
                $tags = explode( " ", $item->get_category()->get_label() );
            } else {
                foreach ($item->get_categories() as $category) {
                    $tags[] = $category->get_label();
                }
            }
            // filter tags, tnx to http://stackoverflow.com/questions/369602/delete-an-element-from-an-array
            $tags = array_diff($tags, array("w", "s"));
            

            /*
            *  set post meta galore start
            */
            $post_meta["_".$this->shortname."_link_id"] = $id;
            $post_meta["_post_generator"] = $this->shortname;
            // in case someone uses WordPress Post Formats Admin UI
            // http://alexking.org/blog/2011/10/25/wordpress-post-formats-admin-ui
            $post_meta["_format_link_url"]  = $link;
            $post_meta["_reclaim_post_type"] = $type;
            /*
            *  set post meta galore end
            */
            

            $data[] = array(
                'post_author' => get_option(self::shortName().'_author'),
                'post_category' => array(get_option(self::shortName().'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($published))),
//                'post_excerpt' => $description,
                'post_content' => $description['constructed'],
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
            .'<p class="viewpost-bookmarks syndicationlink">(<a href="'.$item->get_permalink().'">'.sprintf(__('View on %s', 'reclaim'), $bookmark_domain).'</a>)</p>'
            ;

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed
        );

        return $content;
    }
}

