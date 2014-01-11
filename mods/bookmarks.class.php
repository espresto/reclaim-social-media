<?php
class bookmarks_reclaim_module extends reclaim_module {
    private static $shortname = 'bookmarks';
//    private static $apiurl = "https://www.goodreads.com/review/list_rss/%s?shelf=read";
    private static $timeout = 15;
	private static $count = 30;
    private static $post_format = 'link'; // no specific format 

    public static function register_settings() {
        parent::register_settings(self::$shortname);
        register_setting('reclaim-social-settings', 'bookmarks_api_url');
    }

    public static function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('Bookmarks', 'reclaim'); ?></h3></th>
        </tr>
<?php           
        parent::display_settings(self::$shortname);
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

    public static function import() {
        parent::log(sprintf(__('%s is stale', 'reclaim'), self::$shortname));
        if (get_option('bookmarks_api_url') ) {
            parent::log(sprintf(__('BEGIN %s import', 'reclaim'), self::$shortname));            
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
	            parent::log(sprintf(__('no %s data', 'reclaim'), self::$shortname));
		        parent::log($feed->error());
			} 
			else {
                $data = self::map_data($feed);
                parent::insert_posts($data);
                update_option('reclaim_'.self::$shortname.'_last_update', current_time('timestamp'));                
            }
            parent::log(sprintf(__('END %s import', 'reclaim'), self::$shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), self::$shortname));

    }

    private static function map_data($feed) {
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
                'post_author' => get_option(self::$shortname.'_author'),
                'post_category' => array(get_option(self::$shortname.'_category')),
                'post_format' => self::$post_format,
                'post_date' => date('Y-m-d H:i:s', strtotime($published)),                
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


    private static function process_content($item,$id,$image_url,$description){

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
			.'<div class="grimage"><a href="'.$item->get_permalink.'">[gallery size="large" columns="1"]</a></div>'
//			.'<blockquote>'.$book_description.'</blockquote>'
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

