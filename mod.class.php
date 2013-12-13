<?php
class reclaim_module {

    public static function register_settings($modname) {
        register_setting('reclaim-social-settings', $modname.'_active');
        register_setting('reclaim-social-settings',  $modname.'_category');
        register_setting('reclaim-social-settings',  $modname.'_author');        
    }

    public static function display_settings($modname) {
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
    public static function import() {

    }

    /**
    * Interface
    */
    private static function map_data($rawData) {
        return $rawData;
    }

    /**
    *
    */
    public static function import_via_curl($apiurl, $timeout) {
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout);
        $output = curl_exec($ch);
        curl_close($ch);
        return trim($output);
    }

    /**
    *
    */
    public static function insert_posts($data) {
        if ($data) {
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
                    if ($post['ext_image']) {
                        self::post_thumbnail($post['ext_image'], $inserted_post_id);
                    }
                }
            }
        }
    }
    
    /**
    *
    */    
    public static function post_thumbnail($source, $post_id){
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $wp_upload_dir = wp_upload_dir();
        $filename = basename($source);
        if (strpos($filename, '?')) {
            $filename = substr($filename, 0, strpos($filename, '?'));
        }              
        if (strpos($filename, '.') === false) {
            $filename = $filename.'.jpg';
        }
        $filepath = $wp_upload_dir['path'].'/'.$filename;        
        if(wp_mkdir_p($wp_upload_dir['path'])) {    
            if (@copy($source, $filepath)) {                                                        
                $wp_filetype = wp_check_filetype($filepath, null);  
                $attachment = array(
                    'guid' => $wp_upload_dir['url'] . '/' . $filename, 
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);            
                $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
                wp_update_attachment_metadata($attach_id, $attach_data);        
                set_post_thumbnail( $post_id, $attach_id);
            }        
        }
    }
    
    public static function log($message) {
        file_put_contents(RECLAIM_PLUGIN_PATH.'/reclaim-log.txt', '['.date('c').']: '.$message."\n", FILE_APPEND);
    }
}
?>