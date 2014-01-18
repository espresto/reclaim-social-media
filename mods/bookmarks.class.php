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
//    private static $apiurl = "https://www.goodreads.com/review/list_rss/%s?shelf=read";
    private static $timeout = 15;
	private static $count = 30;
    private static $post_format = 'link'; // no specific format

    public function register_settings() {
        $this->shortname = 'bookmarks';
        parent::register_settings($this->shortname);
        register_setting('reclaim-social-settings', 'bookmarks_api_url');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Bookmarks', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
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

    private function map_data($feed) {
        $data = array();
        $count = self::$count;

		foreach( $feed->get_items( 0, $count ) as $item ) {
			$tags = array();
            $title 	= $item->get_title();
            $id 	= $item->get_permalink();
            $link 	= $item->get_permalink();
			$image_url = '';
            $published = $item->get_date();
            $description = $item->get_description();
			$tags = explode( " ", $item->get_category()->get_label() );
			// filter tags, tnx to http://stackoverflow.com/questions/369602/delete-an-element-from-an-array
			$tags = array_diff($tags, array("w", "s"));

//            $content = self::get_content($item,$id,$image_url,$description);

            $data[] = array(
                'post_author' => get_option(self::shortName().'_author'),
                'post_category' => array(get_option(self::shortName().'_category')),
                'post_format' => self::$post_format,
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($published))),
//                'post_excerpt' => $description,
                'post_content' => $description,
                'post_title' => $title,
                'post_type' => 'post',
                'post_status' => 'publish',
                'ext_permalink' => $link,
                'ext_image' => $image_url,
                'tags_input' => $tags,
                'ext_guid' => $id
            );

        }
        return $data;
    }

    private function process_content($item,$id,$image_url,$description) {
        $post_content_original = $description;
        $author_data = $item->get_item_tags('', 'author_name');
    	$author_name = $author_data[0]['data'];
        $user_review_data = $item->get_item_tags('', 'user_review');
    	$user_review = $user_review_data[0]['data'];
        $book_description_data = $item->get_item_tags('', 'book_description');
    	$book_description = $book_description_data[0]['data'];
    	if ($image_url!="") {
	    	$image_html = '<div class="grimage"><a href="'.$item->get_permalink.'"><img src="'.$image_url.'" alt="'.$item->get_title().'"></a></div>';
		}
		else {
			$image_html ="";
		}
		$post_content_constructed =
			'<div class="grmessage"><p>Ich habe <em><a href="'.$item->get_permalink.'">'.$item->get_title().'</a></em> von '.$author_name.' gelesen.</p>'
			.'<p>'.$user_review.'</p>'
//			.$image_html
			.'<div class="boimage">[gallery size="large" columns="1" link="file"]</div>'
//			.'<blockquote>'.$book_description.'</blockquote>'
//<a href="'.$item->get_permalink.'">
			.'</div>'
			;

        $content = array(
            'original' =>  $post_content_original,
            'constructed' =>  $post_content_constructed,
            'image' => $image_url
        );

        return $content;
    }
}

