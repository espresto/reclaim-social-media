<?php
/*  Copyright 2013-2014 diplix
                   2014 Christian Muehlhaeuser <muesli@gmail.com>

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

class reclaim_module {
	private static $force_delete = true;
    protected $shortname;
    protected $has_ajaxsync;

    public function register_settings($modname) {
        register_setting('reclaim-social-settings', $modname.'_active');
        register_setting('reclaim-social-settings', $modname.'_category');
        register_setting('reclaim-social-settings', $modname.'_author');
    }

    public function display_settings($modname, $displayname = null) {
?>
        <tr valign="top">
            <th scope="row">
            <?php if (isset($displayname)) { echo '<h3>'.$displayname.'</h3>'; } ?>
            </th>
            <td>
            <fieldset>
            <legend class="screen-reader-text"><span><?php _e('Active', 'reclaim'); ?></span></legend>
            <label for="<?php echo $modname; ?>_active"><input type="checkbox" name="<?php echo $modname; ?>_active" value="1" <?php checked(get_option($modname.'_active')); ?> />
            <?php _e('Active', 'reclaim'); ?>
            <?php if (get_option($modname.'_active')) :?>
            <em>(<?php printf(__('last update %s', 'reclaim'), date_i18n(get_option('date_format').' '.get_option('time_format'), get_option('reclaim_'.$modname.'_last_update'))); ?>)</em>
            <?php endif;?>
            </label>
            </fieldset>
            </td>
        </tr>
        <?php if (get_option($modname.'_active')) :?>
        <tr valign="top">
            <th scope="row"></th>
            <td>
                    <?php if ($this->has_ajaxsync()) :?>
                        <input type="submit" class="button button-primary <?php echo $modname; ?>_resync_items" value="<?php _e('Resync with ajax', 'reclaim'); ?>" />
                        <input type="submit" class="<?php echo $modname; ?>_count_all_items button button-secondary" value="<?php _e('Count with ajax', 'reclaim'); ?>" />
                    <?php else :?>
                        <input type="submit" class="button button-primary" value="<?php _e('Re-Sync', 'reclaim'); ?>" name="<?php echo $modname; ?>_resync" />
                    <?php endif;?>
                    <input type="submit" class="button button-secondary" value="<?php _e('Reset', 'reclaim'); ?>" name="<?php echo $modname; ?>_reset" />
                    <?php $count = $this->count_posts(); ?>
                    <?php if ($count > 0) :?>
                    	<input type="submit" class="button button-secondary" value="<?php echo sprintf(__('Remove %s Posts', 'reclaim'), $count); ?>" name="<?php echo $modname; ?>_remove_posts" />
                    <?php endif; ?>
                    <input type="submit" class="button button-secondary" name="submit" value="<?php _e('Save', 'reclaim'); ?>" name="<?php echo $modname; ?>_save" />

                    <?php if ($this->has_ajaxsync()) :?>
                        <span id="<?php echo $modname; ?>_spinner" class="spinner"></span>
                        <div id="<?php echo $modname; ?>_notice" class="updated inline" style="display:none">
						    <p><strong class="message"></strong></p>
                        </div>
                    <?php endif;?>
                    <p><em></em></p>
            </td>
        </tr>
        <?php endif;?>
        <tr valign="top">
            <th scope="row"><?php _e('Category', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('hierarchical' => 1, 'name' => $modname.'_category', 'hide_empty' => 0, 'selected' => get_option($modname.'_category'))); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Author', 'reclaim'); ?></th>
            <td><?php wp_dropdown_users(array('name' => $modname.'_author', 'selected' => get_option($modname.'_author'))); ?></td>
        </tr>
<?php
    }

    /**
    * Interface
    */
    public function shortName() {
        return $this->shortname;
    }

    /**
    * Interface
    */
    public function has_ajaxsync() {
        return $this->has_ajaxsync;
    }

    /**
    * Interface
    */
    public function prepareImport($forceResync) {
        $this->log(sprintf(__('BEGIN %s import %s', 'reclaim'), $this->shortName(), $forceResync));
    }

    /**
    * Interface
    */
    public function import($forceResync) {
    }

    /**
    * Interface
    */
    public function finishImport($forceResync) {
        $this->log(sprintf(__('END %s import %s', 'reclaim'), $this->shortName(), $forceResync));
    }

    /**
    * Interface
    */
    private function map_data($rawData) {
        return $rawData;
    }

    /**
    * Interface
    */
    public function reset() {
    	update_option('reclaim_'.$this->shortName().'_last_update', 0);
    }
    
    public function remove_posts() {
    	$posts = new WP_Query(array(
    		'posts_per_page' => -1,
    		'post_type' => 'post',
    		'meta_query' => array(
    				array(
    						'key' => '_post_generator',
    						'value' => $this->shortName(),
    						'compare' => 'like'
    				)
    		)
    	));
    	
    	foreach ($posts->get_posts() as $post) {
    		$postid = $post->ID;
    		self::log($this->shortName().' remove post with id='.$postid);
    		
    		$attachments = get_children(array(
    			'post_type' => 'attachment',
    			'post_parent' => $postid
    		));
    		
    		foreach ($attachments as $attachment) {
    			self::log($this->shortName().' remove attachment with id='.$attachment->ID);
    			wp_delete_attachment($attachment->ID, self::$force_delete);
    		}
    		
    		wp_delete_post($postid, self::$force_delete);
    	}
    }

    /**
     * Interface
     */
    public function count_items() {
    	return false;
    }


    /**
     *
     */
    public function count_posts($type = null) {
        if (isset($type)) { 
            $type_query =  array(
                                 'key' => '_reclaim_post_type',
                                 'value' => $type,
                                 'compare' => 'like'
                            );
        } else {$type_query = array();}

    	$posts = new WP_Query(array(
    			'posts_per_page' => -1,
    			'post_type' => 'post',
    			'meta_query' => array(
    					array(
    							'key' => '_post_generator',
    							'value' => $this->shortName(),
    							'compare' => 'like'
    					), 
    					$type_query
    			)
    	));

    	return $posts ? $posts->found_posts : false;
    }

    /**
    *
    */
    public static function import_via_curl($apiurl, $timeout) {
        $args = array(
            'timeout'     => $timeout,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent'  => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo( 'url' ),
            'blocking'    => true,
            'headers'     => array(),
            'cookies'     => array(),
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => true,
            'stream'      => false,
            'filename'    => null
        );
        $response = wp_remote_get( $apiurl, $args );
        if( is_wp_error( $response ) ) {
            self::log('error while loading '.$apiurl.': '.$response->get_error_message());
            return false;
        }
        return trim($response['body']);
    }

    public static function post_exists($id) {
        return get_posts(array(
                'post_type' => 'post',
                // this is how we honor posts in the trash or marked as draft:
                // if the exist, these will not be resyndicated 
                // (without this, posts could not be deleted)
                'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash'),
                'meta_query' => array(
                    array(
                        'key' => 'original_guid',
                        'value' => $id,
                        'compare' => 'like'
                    )
                )
            ));
    }

    /**
    *
    */
    public static function insert_posts($data) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $post) {
            if (!self::post_exists($post['ext_guid'])) {
                $inserted_post_id = wp_insert_post($post);
                update_post_meta($inserted_post_id, 'original_permalink', $post['ext_permalink']);
                update_post_meta($inserted_post_id, 'original_guid', $post['ext_guid']);

                if (isset($post['post_meta'])) {
                    foreach ($post['post_meta'] as $key => $value) {
                        update_post_meta($inserted_post_id, $key, $value);
                    }
                }

                $ext_embed_code = isset($post['ext_embed_code']) ? trim($post['ext_embed_code']) : '';
                if ($ext_embed_code) {
                    update_post_meta($inserted_post_id, 'embed_code', $post['ext_embed_code']);
                }
                //$ext_image = isset($post['ext_image']) && !is_array($post['ext_image']) ? trim($post['ext_image']) : '';
                if (!empty($post['ext_image'])) {
                    if (!is_array($post['ext_image'])) {
                        update_post_meta($inserted_post_id, 'image_url', trim($post['ext_image']));
                        self::post_image_to_media_library($post['ext_image'], $inserted_post_id, $post['post_title'], true, $post['post_date']);
                    }
                    else {
                        //[$i]['link_url']
                        //[$i]['image_url']
                        //[$i]['title']
                        update_post_meta($inserted_post_id, 'image_url', trim($post['ext_image'][0]['image_url']));
                        foreach($post['ext_image'] as $post_image) {
                            self::post_image_to_media_library(trim($post_image['image_url']), $inserted_post_id, $post_image['title'], true, $post['post_date']);
                        }
                    }

                }
                else {
                    // possible performance hog
                    // to do:
                    // * activate or deactivate in settings
                    // * check if image-url was already saved (in another article) if so, use it instead
                    if ($post['ext_permalink']!="") {
                        $reader = new Opengraph\Reader();
                        $open_graph_content = self::my_get_remote_content($post['ext_permalink']);
                        if($open_graph_content) {
                            try {
                                $reader->parse($open_graph_content);
                                $open_graph_data = $reader->getArrayCopy();
                                $image_data = isset($open_graph_data[$reader::OG_IMAGE]) ? array_pop($open_graph_data[$reader::OG_IMAGE]) : array();
                                $image_url = isset($image_data['og:image:url']) ? $image_data['og:image:url'] : '';
                                if ($image_url != "") {
                                    update_post_meta($inserted_post_id, 'image_url', $image_url);
                                    self::post_image_to_media_library($image_url, $inserted_post_id, $post['post_title'], true, $post['post_date']);
                                }
                            } catch(RuntimeException $e) {
                                self::log('Remote opengraph-content not parsable:' . $open_graph_content);
                            }
                        } else {
                            self::log('No ext_permalink remote content fetched');
                        }
                    }
                }
                if ($post['post_format']!="") {
                    set_post_format($inserted_post_id, $post['post_format']);
                }
            }
        }
    }

    public static function post_image_to_media_library($source, $post_id, $title, $set_post_thumbnail = true, $post_date ) {
    // source http://digitalmemo.neobie.net/grab-save
        $imageurl = $source;
        $imageurl = stripslashes($imageurl);
        $uploads = wp_upload_dir();
        $ext = pathinfo(basename($imageurl), PATHINFO_EXTENSION);
        $newfilename = basename($imageurl);

        // sometimes facebook offers very long filename
        // if so, file_put_contents() throws an error
        if ( (strlen($newfilename) > 70) || (strlen($newfilename) < 10) ) {
            $newfilename = uniqid() . $ext;
        }

        $filename = wp_unique_filename( $uploads['path'], $newfilename, $unique_filename_callback = null );
        $wp_filetype = wp_check_filetype($filename, null );
        $fullpathfilename = $uploads['path'] . "/" . $filename;

        if ($title == "") {
            $title = preg_replace('/\.[^.]+$/', '', $filename);
        }

        try {
            $image_string = self::my_get_remote_content($imageurl, true);
            $headers = $image_string['headers'];
            //self::log( 'headers: '.print_r($headers, true) );
            if ( (!substr_count($headers["content-type"], "image") &&
                 !substr_count($wp_filetype['type'], "image")) || 
                 !isset($headers) ) {

                if (substr_count($headers["content-type"], "octet-stream")) {
                    self::log( basename($imageurl) . ' is not a valid image: ' . $wp_filetype['type'] . ' - ' . $headers["content-type"] . ' - trying to download it anyways...' );
                    /* workaround for twitpic: sometimes images return a header application/octet-stream
                     * in a browsers, this triggers a download. here it means the image won't be loaded.
                     * this makes it work. :( 
                     * ix@wirres.net, 2014-03-03
                     */ 
                    $headers["content-type"] = "image/jpeg";
                } else {
                    self::log( basename($imageurl) . ' is not a valid image: ' . $wp_filetype['type'] . ' - ' . $headers["content-type"] );
                    return;
                }

            }
            if (substr_count($headers["content-type"], "octet-stream")) {
                self::log( basename($imageurl) . ' is not a valid image: ' . $wp_filetype['type'] . ' - ' . $headers["content-type"] . ' - trying to download it anyways (2)...' );
                /* workaround for twitpic: sometimes images return a header application/octet-stream
                 * in a browsers, this triggers a download. here it means the image won't be loaded.
                 * this makes it work. :( 
                 * ix@wirres.net, 2014-03-03
                 */ 
                $headers["content-type"] = "image/jpeg";
            }
            $image_string = wp_remote_retrieve_body($image_string);
            $fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $image_string);
            if ( !$fileSaved ) {
                self::log("The file cannot be saved.");
                return;
            }

            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'inherit',
                'guid' => $uploads['url'] . "/" . $filename,
				'post_date' => $post_date,
				// assume that the given post_date is a gmt-date
				// we need to set this field, because otherwise the attachment
				// doesnt get the date
				'post_date_gmt' => $post_date
            );
            if (!is_array($headers["content-type"])) {
                $attachment['post_mime_type'] = $headers["content-type"];
            }

            $attach_id = wp_insert_attachment( $attachment, $fullpathfilename, $post_id );
            if ( !$attach_id ) {
                self::log("Failed to save record into database.");
            }
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            $attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename );
            wp_update_attachment_metadata( $attach_id,  $attach_data );
            if ($set_post_thumbnail) {
                set_post_thumbnail( $post_id, $attach_id);
            }

        } catch (Exception $e) {
            self::log($e->getMessage());
        }
    }

    public static function my_get_remote_content($url, $return_full_response = false) {
        $response = wp_remote_get($url,
            array(
                'headers' => array(
                    'user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2)'
                )
            )
        );
        if( is_wp_error( $response ) ) {
            self::log('Error fetching remote content from '.$url.', wp error: '.$response->get_error_message());
            return "";
        } 
        if ($return_full_response) {
            return $response;
        } else {
            $data = wp_remote_retrieve_body($response);
            return $data;
        }
    }

    public static function log($message) {
        file_put_contents(RECLAIM_PLUGIN_PATH.'/reclaim-log.txt', '['.date('c').']: '.$message."\n", FILE_APPEND);
    }
    
    public function add_admin_ajax_handlers() {
		add_action( 'wp_ajax_'.$this->shortName().'_count_all_items', array($this, 'ajax_count_all_items'));
		add_action( 'wp_ajax_'.$this->shortName().'_count_items', array($this, 'ajax_count_items'));
		add_action( 'wp_ajax_'.$this->shortName().'_resync_items', array($this, 'ajax_resync_items'));
		// todo: add actions for resync, remove posts
		// this way it may be possible to do page-based
		// imports which do not stretch memory and execution times
		
		add_action( 'admin_print_footer_scripts', array($this, 'print_scripts'));
	}
	
	public function ajax_count_all_items() {
		$items = $this->count_items( $_POST['type'] );
		if ($items == 999999) {$items = __('Unknown number of', 'reclaim');}
		$posts = $this->count_posts( $_POST['type'] );
		echo (json_encode(array(
			'success' => true,
			'result' => $items.' '
            	.__('items available', 'reclaim')
            	.', '.$posts.' '
            	.__('posts created', 'reclaim')
		)));
		
		die();
	}
	
	public function ajax_count_items() {
		$count = $this->count_items();
		
		echo(json_encode(array(
			'success' => true,
			'result' => $count
		)));
		die();
	}
	
	public function ajax_resync_items() {
		$offset = intval( $_POST['offset'] );
		$limit = intval( $_POST['limit'] );
		$count = intval( $_POST['count'] );
		$type = $_POST['type'];
		
		self::log($this->shortName().' resync '.$offset.'-'.($offset + $limit).':'.$count);
		
		echo(json_encode(array(
			'success' => false,
			'error' => 'ajax-resync is not implemented'
		)));
		
		die();
	}
	
	public function print_scripts() {
		?>
		<script type="text/javascript" >
		jQuery(document).ready(function($) {
			var modname = '<?php echo($this->shortName()); ?>';

			
			$('.'+modname+'_count_all_items').click(function(eventObject) {
				var r = reclaim.getInstance(modname, eventObject);
				var options = {};
				if ($(eventObject.target).data('resync')) {
					options = eval('('+$(eventObject.target).data('resync')+')');
				}

				r.count_all_items(options);
				
				return false;
			});

			$('.'+modname+'_resync_items').click(function(eventObject) {
				var r = reclaim.getInstance(modname, eventObject);
				// the options is generated from a json-field in the
				// dom of the clicked object:
				// eg: data-resync="{type:'favs'}"
				// the properties in this field are passed back to
				// wordpress via POST-variables
				// fieldvalues with the protected name 'offset' will be deleted.
				var options = {};
				
				if ($(eventObject.target).data('resync')) {
					options = eval('('+$(eventObject.target).data('resync')+')');
				}

				r.resync_items(options);
				
				return false;
			});
		});
		</script>
		<?php		
	}

    function short_title($title = '', $after = '&nbsp;&hellip;', $length) {
        $mytitle = explode(' ', $title, $length);
        if (count($mytitle)>=$length) {
            array_pop($mytitle);
            $mytitle = implode(" ",$mytitle). $after;
        } else {
            $mytitle = implode(" ",$mytitle);
        }
        return $mytitle;
    }

    function strpos_array($haystack, $needles) {
        if ( is_array($needles) ) {
            foreach ($needles as $str) {
                if ( is_array($str) ) {
                    $pos = strpos_array($haystack, $str);
                } else {
                    $pos = strpos($haystack, $str);
                }
                if ($pos !== FALSE) {
                    return $pos;
                }
            }
        } else {
            return strpos($haystack, $needles);
        }
    }

}
