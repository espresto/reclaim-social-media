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
    protected $shortname;

    public function register_settings($modname) {
        register_setting('reclaim-social-settings', $modname.'_active');
        register_setting('reclaim-social-settings', $modname.'_category');
        register_setting('reclaim-social-settings', $modname.'_author');
    }

    public function display_settings($modname) {
?>
        <tr valign="top">
            <th scope="row"><?php _e('Active', 'reclaim'); ?></th>
            <td><input type="checkbox" name="<?php echo $modname; ?>_active" value="1" <?php checked(get_option($modname.'_active')); ?> />
                <?php if (get_option($modname.'_active')) :?>
                    <em><?php printf(__('last update %s', 'reclaim'), date(get_option('date_format').' '.get_option('time_format'), get_option('reclaim_'.$modname.'_last_update'))); ?></em>
                    <input type="submit" class="button button-primary" value="<?php _e('Re-Sync', 'reclaim'); ?>" name="<?php echo $modname; ?>_resync" />
                <?php endif;?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Category', 'reclaim'); ?></th>
            <td><?php wp_dropdown_categories(array('name' => $modname.'_category', 'hide_empty' => 0, 'selected' => get_option($modname.'_category'))); ?></td>
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
    public function prepareImport($forceResync) {
        $this->log(sprintf(__('BEGIN %s import %s', 'reclaim'), $this->shortName(), $forceResync));
        update_option('reclaim_'.$this->shortName().'_locked', 1);
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
        update_option('reclaim_'.$this->shortName().'_locked', 0);
        $this->log(sprintf(__('END %s import %s', 'reclaim'), $this->shortName(), $forceResync));
    }

    /**
    * Interface
    */
    private function map_data($rawData) {
        return $rawData;
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

    /**
    *
    */
    public static function insert_posts($data) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $post) {
            $exists = get_posts(array(
                'post_type' => 'post',
                'meta_query' => array(
                    array(
                        'key' => 'original_guid',
                        'value' => $post['ext_guid'],
                        'compare' => 'like'
                    )
                )
            ));
            if (!$exists) {
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
                $ext_image = isset($post['ext_image']) ? trim($post['ext_image']) : '';
                if ($ext_image) {
                    if (!is_array($post['ext_image'])) {
                        update_post_meta($inserted_post_id, 'image_url', trim($post['ext_image']));
                        self::post_image_to_media_library($post['ext_image'], $inserted_post_id, $post['post_title']);
                    }
                    else {
                        //[$i]['link_url']
                        //[$i]['image_url']
                        //[$i]['title']
                        update_post_meta($inserted_post_id, 'image_url', trim($post['ext_image'][0]['image_url']));
                        foreach($post['ext_image'] as $post_image) {
                            self::post_image_to_media_library(trim($post_image['image_url']), $inserted_post_id, $post_image['title']);
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
                                    self::post_image_to_media_library($image_url, $inserted_post_id, $post['post_title']);
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

    public static function post_image_to_media_library($source, $post_id, $title, $set_post_thumbnail = true ) {
    // source http://digitalmemo.neobie.net/grab-save
        $imageurl = $source;
        $imageurl = stripslashes($imageurl);
        $uploads = wp_upload_dir();
        $ext = pathinfo( basename($imageurl) , PATHINFO_EXTENSION);
        $newfilename = basename($imageurl);
		// sometimes facebook offers very long filename
		// if so, file_put_contents() throws an error
        if (strlen($newfilename) > 70) { $newfilename = uniqid() . $ext; }

        $filename = wp_unique_filename( $uploads['path'], $newfilename, $unique_filename_callback = null );
        $wp_filetype = wp_check_filetype($filename, null );
        $fullpathfilename = $uploads['path'] . "/" . $filename;

        if ($title == "") {
            $title = preg_replace('/\.[^.]+$/', '', $filename);
        }

        try {
            if ( !substr_count($wp_filetype['type'], "image") ) {
                self::log( basename($imageurl) . ' is not a valid image. ' . $wp_filetype['type']  . '' );
            }

            $image_string = self::my_get_remote_content($imageurl);
            $fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $image_string);
            if ( !$fileSaved ) {
                self::log("The file cannot be saved.");
            }

            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => $title,
                'post_content' => '',
                'post_status' => 'inherit',
                'guid' => $uploads['url'] . "/" . $filename
            );
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

    public static function my_get_remote_content($url) {
        $response = wp_remote_get($url,
            array(
                'headers' => array(
                    'user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2)'
                )
            )
        );
        if( is_wp_error( $response ) ) {
            self::log('Error fetching remote content from '.$url.', wp error: '.$response->get_error_message());
        } else {
            $data = wp_remote_retrieve_body($response);
            return $data;
        }
    }

    public static function log($message) {
        file_put_contents(RECLAIM_PLUGIN_PATH.'/reclaim-log.txt', '['.date('c').']: '.$message."\n", FILE_APPEND);
    }
}