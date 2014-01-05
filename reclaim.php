<?php
/*
Plugin Name: Reclaim
Plugin URI: http://reclaim.fm
Description: Reclaim your digital life. Take back control of everything you create, curate and share on the internet. Creates posts on your blog from your posts in different social applications (twitter, facebook, g+ ...)
Version: 0.1
Author: to be disclosed
License: GPL
*/

if (file_exists( __DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

define('RECLAIM_UPDATE_INTERVAL', 10*60);
define('RECLAIM_PLUGIN_PATH', dirname( __FILE__));

class reclaim_core {
    private $mods_loaded = array();

    public function __construct() {
        require_once('helper-functions.php');
        /* Load modules */        
        require_once('mod.class.php');
        foreach (glob(dirname( __FILE__).'/mods/*.class.php') as $file) {
            require_once($file);
            $name = basename($file, '.class.php');
            $this->mods_loaded[] = array('name' => $name, 'active' => get_option($name.'_active'));
        }
        
        foreach ($this->mods_loaded as $mod){
            $isStaleMod = $this->is_stale_mod($mod['name']);
            $adminResync = is_admin() && isset($_REQUEST[$mod['name'].'_resync']);

            if ($mod['active']) {
                if (($isStaleMod && get_option('reclaim_auto_update')) || $adminResync) {
                    call_user_func(array($mod['name'].'_reclaim_module', 'import'));
                }
            }
        }
        
        add_action('admin_menu', array($this, 'admin_menu'));
		add_action('wp_enqueue_scripts', array($this, 'prefix_add_reclaim_stylesheet'));
        
	add_filter('post_link', array($this, 'original_permalink'), 1, 3);
	add_filter('post_type_link', array($this, 'original_permalink'), 1, 4);   
    }

	public function prefix_add_reclaim_stylesheet() {
    	wp_register_style('prefix-style', plugins_url('css/style.css', __FILE__));
    	wp_enqueue_style('prefix-style');
//		wp_enqueue_script( 'twitter-widget', 'https://platform.twitter.com/widgets.js' );
//		wp_enqueue_script( 'google-plus-widget', 'https://apis.google.com/js/plusone.js' );
//		wp_enqueue_script( 'facebook-jssdk', 'https://connect.facebook.net/de_DE/all.js#xfbml=1' );
	}

    public function get_interval(){
        $interval = get_option('reclaim_update_interval');
        if (false === $interval) {
            $interval = RECLAIM_UPDATE_INTERVAL;
        }
        return $interval;
    } 
    
    public function is_stale_mod($mod){
        $last = get_option('reclaim_'.$mod.'_last_update');
        if (false === $last) {
            $ret = true;
        }
        elseif (is_numeric($last)) {
            $interval = $this->get_interval();
            $ret = ( (current_time( 'timestamp' ) - $last) > $interval );
        }
        return $ret;  
    }

    public function admin_menu(){
        add_options_page( __('Reclaim Social Accounts Settings', 'reclaim'), __('Reclaim', 'reclaim'), 'manage_options', __FILE__, array($this, 'display_settings'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings(){
        register_setting('reclaim-social-settings', 'reclaim_update_interval');
        register_setting('reclaim-social-settings', 'reclaim_auto_update');
        foreach($this->mods_loaded as $mod) {
            call_user_func(array($mod['name'].'_reclaim_module', 'register_settings'));
        }
    }

    public function display_settings() {
?>
    <div class="wrap">
        <div id="icon-options-general" class="icon32"></div>
        <h2><?php _e('Reclaim Social Accounts Settings', 'reclaim'); ?></h2>
        <form action="options.php" method="post">
            <?php settings_fields('reclaim-social-settings'); ?>
            <table class="form-table">
            <tr valign="top">
                <th colspan="2"><strong><?php _e('General Settings', 'reclaim'); ?></strong></th>
            </tr>   
            <tr valign="top">
                <th scope="row"><?php _e('Auto-update', 'reclaim'); ?></th>
                <td><input type="checkbox" name="reclaim_auto_update" value="1" <?php checked(get_option('reclaim_auto_update')); ?> /></td>
            </tr>            
            <tr valign="top">
                <th scope="row"><?php _e('Update Interval (in seconds)', 'reclaim'); ?></th>
                <td><input type="text" name="reclaim_update_interval" value="<?php echo self::get_interval(); ?>" /></td>
            </tr>                
<?php
        foreach($this->mods_loaded as $mod) {
            call_user_func(array($mod['name'].'_reclaim_module', 'display_settings'));
        }
?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
    }
    
    public function original_permalink ($permalink = '', $post = null, $leavename = false, $sample = false) {
	global $id;

	if (is_object($post) and isset($post->ID) and !empty($post->ID)) {
            $postId = $post->ID;
        }
        elseif (is_string($permalink) and strlen($permalink) > 0) {
            $postId = url_to_postid($permalink);
        } 
        else {
            $postId = $id;
        }
        
        $link = get_post_meta($postId, 'original_permalink', true);
	if ($link){
            $permalink = $link;
        }
	return $permalink;
    } 
}

add_action('init', 'reclaim_init');
function reclaim_init() {
    $reclaim = new reclaim_core();
}