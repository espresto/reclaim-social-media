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
        $this->log(sprintf(__('BEGIN %s import %s', 'reclaim'), $this->shortname, $forceResync));
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
        $this->log(sprintf(__('END %s import', 'reclaim'), $this->shortName()));
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
        if (!$data || !is_array($data)) {
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

                if ($post['ext_embed_code']!="") {
                    update_post_meta($inserted_post_id, 'embed_code', $post['ext_embed_code']);
                }
                if ($post['ext_image']!="") {
                    update_post_meta($inserted_post_id, 'image_url', $post['ext_image']);
                    self::post_thumbnail($post['ext_image'], $inserted_post_id, $post['post_title']);
                }
                else {
                    // possible performance hog
                    // to do:
                    // * activate or deactivate in settings
                    // * check if image-url was already saved (in another article) if so, use it instead
                    if ($post['ext_permalink']!="") {
                        $graph = OpenGraph::fetch($post['ext_permalink']);
                        $image_url = $graph->image;
                        if ($image_url!="") {
                            update_post_meta($inserted_post_id, 'image_url', $image_url);
                            self::post_thumbnail($image_url, $inserted_post_id, $post['post_title']);
                        }
                    }
                }
                if ($post['post_format']!="") {
                    set_post_format($inserted_post_id, $post['post_format']);
                }
            }
        }
    }

    public static function post_thumbnail($source, $post_id, $title) {
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
            set_post_thumbnail( $post_id, $attach_id);

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
            throw new Exception('Error fetching remote content from '.$url.', wp error: '.$response->get_error_message());
        } else {
            $data = wp_remote_retrieve_body($response);
            return $data;
        }
    }

    public static function log($message) {
        file_put_contents(RECLAIM_PLUGIN_PATH.'/reclaim-log.txt', '['.date('c').']: '.$message."\n", FILE_APPEND);
    }
}

/*
  Copyright 2010 Scott MacVicar

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

	Original can be found at https://github.com/scottmac/opengraph/blob/master/OpenGraph.php

*/

class OpenGraph implements Iterator
{
  /**
   * There are base schema's based on type, this is just
   * a map so that the schema can be obtained
   *
   */
    public static $TYPES = array(
        'activity' => array('activity', 'sport'),
        'business' => array('bar', 'company', 'cafe', 'hotel', 'restaurant'),
        'group' => array('cause', 'sports_league', 'sports_team'),
        'organization' => array('band', 'government', 'non_profit', 'school', 'university'),
        'person' => array('actor', 'athlete', 'author', 'director', 'musician', 'politician', 'public_figure'),
        'place' => array('city', 'country', 'landmark', 'state_province'),
        'product' => array('album', 'book', 'drink', 'food', 'game', 'movie', 'product', 'song', 'tv_show'),
        'website' => array('blog', 'website'),
    );

  /**
   * Holds all the Open Graph values we've parsed from a page
   *
   */
    private $_values = array();

  /**
   * Fetches a URI and parses it for Open Graph data, returns
   * false on error.
   *
   * @param $URI    URI to page to parse for Open Graph data
   * @return OpenGraph
   */
    static public function fetch($URI) {
        $response = wp_remote_get( $URI );
        if( !is_wp_error( $response ) ) {
            return self::_parse($response['body']);
        } else {
            $message = 'og:parser: no response from '.$URI;
            file_put_contents(RECLAIM_PLUGIN_PATH.'/reclaim-log.txt', '['.date('c').']: '.$message."\n", FILE_APPEND);
            return false;
        }
	}

  /**
   * Parses HTML and extracts Open Graph data, this assumes
   * the document is at least well formed.
   *
   * @param $HTML    HTML to parse
   * @return OpenGraph
   */
    static private function _parse($HTML) {
        $old_libxml_error = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($HTML);

        libxml_use_internal_errors($old_libxml_error);

        $tags = $doc->getElementsByTagName('meta');
        if (!$tags || $tags->length === 0) {
            return false;
        }

        $page = new self();

        $nonOgDescription = null;

        foreach ($tags AS $tag) {
            if ($tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'og:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
                $page->_values[$key] = $tag->getAttribute('content');
            }
            // pinterestapp:
            if ($tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'pinterestapp:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 0), '-', '_');
                $page->_values[$key] = $tag->getAttribute('content');
            }
            // flickr_photos:
            if ($tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'flickr_photos:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 0), '-', '_');
                $page->_values[$key] = $tag->getAttribute('content');
            }
            //twitter:card
            if ($tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'twitter:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 0), '-', '_');
                $page->_values[$key] = $tag->getAttribute('content');
            }

            //Added this if loop to retrieve description values from sites like the New York Times who have malformed it.
            if ($tag ->hasAttribute('value') && $tag->hasAttribute('property') &&
                strpos($tag->getAttribute('property'), 'og:') === 0) {
                $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
                $page->_values[$key] = $tag->getAttribute('value');
            }
            //Based on modifications at https://github.com/bashofmann/opengraph/blob/master/src/OpenGraph/OpenGraph.php
            if ($tag->hasAttribute('name') && $tag->getAttribute('name') === 'description') {
                $nonOgDescription = $tag->getAttribute('content');
            }

        }
        //Based on modifications at https://github.com/bashofmann/opengraph/blob/master/src/OpenGraph/OpenGraph.php
        if (!isset($page->_values['title'])) {
            $titles = $doc->getElementsByTagName('title');
            if ($titles->length > 0) {
                $page->_values['title'] = $titles->item(0)->textContent;
            }
        }
        if (!isset($page->_values['description']) && $nonOgDescription) {
            $page->_values['description'] = $nonOgDescription;
        }

        //Fallback to use image_src if ogp::image isn't set.
        if (!isset($page->values['image'])) {
            $domxpath = new DOMXPath($doc);
            $elements = $domxpath->query("//link[@rel='image_src']");

            if ($elements->length > 0) {
                $domattr = $elements->item(0)->attributes->getNamedItem('href');
                if ($domattr) {
                    $page->_values['image'] = $domattr->value;
                    $page->_values['image_src'] = $domattr->value;
                }
            }
        }

        if (empty($page->_values)) { return false; }

        return $page;
    }

  /**
   * Helper method to access attributes directly
   * Example:
   * $graph->title
   *
   * @param $key    Key to fetch from the lookup
   */
    public function __get($key) {
        if (array_key_exists($key, $this->_values)) {
            return $this->_values[$key];
        }

        if ($key === 'schema') {
            foreach (self::$TYPES AS $schema => $types) {
                if (array_search($this->_values['type'], $types)) {
                    return $schema;
                }
            }
        }
    }

  /**
   * Return all the keys found on the page
   *
   * @return array
   */
    public function keys() {
        return array_keys($this->_values);
    }

  /**
   * Helper method to check an attribute exists
   *
   * @param $key
   */
    public function __isset($key) {
        return array_key_exists($key, $this->_values);
    }

  /**
   * Will return true if the page has location data embedded
   *
   * @return boolean Check if the page has location data
   */
    public function hasLocation() {
        if (array_key_exists('latitude', $this->_values) && array_key_exists('longitude', $this->_values)) {
            return true;
        }

        $address_keys = array('street_address', 'locality', 'region', 'postal_code', 'country_name');
        $valid_address = true;
        foreach ($address_keys AS $key) {
            $valid_address = ($valid_address && array_key_exists($key, $this->_values));
        }
        return $valid_address;
    }

  /**
   * Iterator code
   */
    private $_position = 0;
    public function rewind() { reset($this->_values); $this->_position = 0; }
    public function current() { return current($this->_values); }
    public function key() { return key($this->_values); }
    public function next() { next($this->_values); ++$this->_position; }
    public function valid() { return $this->_position < sizeof($this->_values); }
}
