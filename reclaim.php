<?php
/*
Plugin Name: Reclaim
Plugin URI: http://reclaim.fm
Description: Reclaim your digital life. Take back control of everything you create, curate and share on the internet. Creates posts on your blog from your posts in different social applications (twitter, facebook, g+ ...)
Version: 0.1
Author: to be disclosed
License: GPL
*/

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

if ( version_compare(PHP_VERSION, '5.3.2', '<') ) {
    wp_die(__('At least PHP version 5.3.2 is required for Reclaim Social Media.', 'reclaim'));
};

if (file_exists( __DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

define('RECLAIM_UPDATE_INTERVAL', 10*60);
define('RECLAIM_PLUGIN_PATH', dirname( __FILE__));
define('RECLAIM_PLUGIN_URL', plugins_url('', __FILE__));

class reclaim_core {
    private $mods_loaded = array();
    private static $instance = 0;
    private static $options_page_url = 'options-general.php?page=reclaim/reclaim.php';

    public function __construct() {
        add_action('init', array($this, 'myStartSession'),1,1);

        require_once('helper-functions.php');

        $plugin_slug = dirname( plugin_basename( __FILE__ ) );
        load_plugin_textdomain( 'reclaim', false, $plugin_slug . '/languages/' );

        /* Load modules */
        foreach (glob(dirname( __FILE__).'/mods/*.class.php') as $file) {
            require_once($file);
            $name = basename($file, '.class.php');
            $cName = $name.'_reclaim_module';
            $this->mods_loaded[] = array('name' => $name,
                                         'active' => get_option($name.'_active'),
                                         'instance' => new $cName);
        }

        foreach ($this->mods_loaded as $mod) {
            if (is_admin()) {
            	if (isset($_REQUEST[$mod['name'].'_resync'])) {
	                $this->updateMod($mod, true);
	                if (wp_redirect(self::$options_page_url.'#'.$mod['name'])) {
	                	exit;
	                }
            	} else if (isset($_REQUEST[$mod['name'].'_reset'])) {
            		$this->resetMod($mod);
            		if (wp_redirect(self::$options_page_url.'#'.$mod['name'])) {
	                	exit;
	                }
            	} else if (isset($_REQUEST[$mod['name'].'_remove_posts'])) {
            		$this->removePostsMod($mod);
            		if (wp_redirect(self::$options_page_url.'#'.$mod['name'])) {
	                	exit;
	                }
            	}
            }
        }
        
        $this->add_admin_ajax_handlers();

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_stylesheets'));
        add_action('wp_enqueue_scripts', array($this, 'prefix_add_reclaim_stylesheet'));
        
        //dashboard widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets') );
        
        // get those sessions strated, before it's too late
        // don't know if this works properly
        add_action('wp_logout', array($this, 'myEndSession'), 1, 2);
        add_action('wp_login', array($this, 'myEndSession'), 1, 3);

        add_filter('post_link', array($this, 'original_permalink'), 1, 4);
        add_filter('post_type_link', array($this, 'original_permalink'), 1, 5);
        add_filter('the_content', array($this, 'reclaim_content'), 100);

        add_action('reclaim_update_hook', array($this, 'updateMods'));
    }

    public static function instance() {
        if ( self::$instance == 0 ) {
            self::$instance = new reclaim_core();
        }
        return self::$instance;
    }

    public function updateMods() {
        foreach ($this->mods_loaded as $mod) {
            if (get_option('reclaim_auto_update')) {
                $this->updateMod($mod, false);
            }
        }
    }

    public function updateMod(&$mod, $adminResync) {
        if ($mod['active']) {
            $mod['instance']->prepareImport($adminResync);
            $mod['instance']->import($adminResync);
            $mod['instance']->finishImport($adminResync);
        }
    }
    
    public function resetMod(&$mod) {
    	if ($mod['active']) {
    		$mod['instance']->reset();
    	}
    }
    
    public function removePostsMod(&$mod) {
    	if ($mod['active']) {
    		$mod['instance']->remove_posts();
    	}
    }

    public function myStartSession() {
        if (!session_id()) {
            session_start();
        }
    }

    public function myEndSession() {
        if (session_id()) {
            session_destroy();
        }
    }

    public function prefix_add_reclaim_stylesheet() {
        wp_register_style('prefix-style', plugins_url('css/style.css', __FILE__));
        wp_enqueue_style('prefix-style');
        if (get_option('reclaim_show_map') == '1') {
	        wp_register_style('leaflet', 'http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css');
	        wp_enqueue_style('leaflet');
	        wp_enqueue_script( 'leaflet', 'http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js' );
	        wp_enqueue_script( 'stamen', 'http://maps.stamen.com/js/tile.stamen.js?v1.2.4' );
        }
//        wp_enqueue_script( 'twitter-widget', 'https://platform.twitter.com/widgets.js' );
//        wp_enqueue_script( 'google-plus-widget', 'https://apis.google.com/js/plusone.js' );
//        wp_enqueue_script( 'facebook-jssdk', 'https://connect.facebook.net/de_DE/all.js#xfbml=1' );
//
    }
    
    public function admin_stylesheets() {
    	wp_register_script('admin-reclaim-script', plugins_url('js/admin_ajax.js', __FILE__), array('jquery'));
    	wp_enqueue_script('admin-reclaim-script');
    	wp_localize_script('admin-reclaim-script', 'admin_reclaim_script_translation', self::localize_admin_reclaim_script());
    	wp_register_style('admin-reclaim-style', plugins_url('css/style_admin.css', __FILE__));
    	wp_enqueue_style('admin-reclaim-style');
    }
    
    public function localize_admin_reclaim_script() {
        return array(
            'Cancel' => __('Cancel ', 'reclaim'),
            'Canceled' => __('Canceled.', 'reclaim'),
            'Whoops_Returned_data_must_be_not_null' => __('Whoops! Returned data must be not null.', 'reclaim'),
            'Error_occured' => __('Error occured: ', 'reclaim'),
            'Count_items_and_posts' => __('Count items and posts...', 'reclaim'),
            'Count_items' => __('Count items...', 'reclaim'),
            'item_count_is_not_a_valid_number' => __('item count is not a valid number. value=', 'reclaim'),
            'Not_a_valid_item_count' => __('Not a valid item count: ', 'reclaim'),
            'Resyncing_items' => __('Resyncing items: ', 'reclaim'),
            'result_offset_is_not_a_number' => __('result.offset is not a number: value=', 'reclaim'),
            'items_resynced_duration' => __(' items resynced, duration: ', 'reclaim'),
        );
    }
    
    public function add_dashboard_widgets() {
    	if (is_admin()) {
    		wp_add_dashboard_widget('reclaim-dashboardwidget', 'Reclaim Status', array($this, 'status_widget') );
    	}
    }
    
    public function status_widget() {
    	?>
    	<h4><?php _e('Auto Update', 'reclaim')?>: <?php get_option('reclaim_auto_update') ? _e('On', 'reclaim') : _e('Off', 'reclaim'); ?></h4>
		<div class="table">
			<table class="reclaim-status-table">
				<thead>
					<tr>
						<th>Mod</th>
						<th>Items</th>
						<th>Posts</th>
					</tr>
				</thead>
				<tbody>
					<?php 
					foreach ($this->mods_loaded as $mod) {
					?>
					<tr>
						<td><input type="checkbox" disabled="disabled"
						<?php checked($mod['active']); ?> /> <a href="<?php echo self::$options_page_url; ?>#<?php echo $mod['instance']->shortName(); ?>"><?php _e($mod['instance']->shortName(), 'reclaim'); ?></a>
						</td>
		
						<td class="count"><?php echo $mod['instance']->count_items() ?>
						</td>
						<td class="count"><?php echo $mod['instance']->count_posts() ?>
						</td>
					</tr>
					<?php 
		    		}
		    		?>
				</tbody>
			</table>
		</div>
		<?php 
    }

    public function get_interval() {
        $interval = get_option('reclaim_update_interval');
        if (false === $interval) {
            $interval = RECLAIM_UPDATE_INTERVAL;
        }
        return $interval;
    }

    public function admin_menu() {
        if(!session_id()) {
            session_start();
        }
        add_options_page( __('Reclaim Social Accounts Settings', 'reclaim'), __('Reclaim', 'reclaim'), 'manage_options', __FILE__, array($this, 'display_settings'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting('reclaim-social-settings', 'reclaim_update_interval');
        register_setting('reclaim-social-settings', 'reclaim_auto_update');
        register_setting('reclaim-social-settings', 'reclaim_show_map');
        foreach($this->mods_loaded as $mod) {
            $mod['instance']->register_settings();
        }
    }

    public function display_settings() {
?>
    <div class="wrap">
<?php
        foreach($this->mods_loaded as $mod) {
           echo '<a href="#'.$mod['name'].'">'.$mod['name'].'</a> | ';
        }
?>
        <div id="icon-options-general" class="icon32"></div>
        <h2><?php _e('Reclaim Social Accounts Settings', 'reclaim'); ?></h2>
        <form action="options.php" method="post">
            <?php settings_fields('reclaim-social-settings'); ?>
            <table class="form-table">
            <tr valign="top">
                <th colspan="2"><h3><?php _e('General Settings', 'reclaim'); ?></h3></th>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="reclaim_auto_update"><?php _e('Auto-update', 'reclaim'); ?></label></th>
                <td><input type="checkbox" name="reclaim_auto_update" value="1" <?php checked(get_option('reclaim_auto_update')); ?> /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="reclaim_update_interval"><?php _e('Update Interval (in seconds)', 'reclaim'); ?></label></th>
                <td><input type="text" name="reclaim_update_interval" value="<?php echo self::get_interval(); ?>" /></td>
            </tr>
             <tr valign="top">
                <th scope="row"><label for="reclaim_show_map"><?php _e('Show integrated map', 'reclaim'); ?></label></th>
                <td><input type="checkbox" name="reclaim_show_map" value="1" <?php checked(get_option('reclaim_show_map')); ?> /></td>
            </tr>
<?php
        foreach($this->mods_loaded as $mod) {
            echo '<tr id="'.$mod['name'].'"><th colspan="2"><hr /></th></tr>';
            $mod['instance']->display_settings();
        }
?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
    }

    public function original_permalink($permalink = '', $post = null, $leavename = false, $sample = false) {
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
        if ( $link != "" ) {
            // added this, because otherwise link slug would be added in some occasions
            //  $link .= '#'; // adds hash to the original_permalink
            //  $link .= '?'; // strange: resets original
        }
        if ($link) {
            $permalink = $link;
        }
        return $permalink;
    }

    public function reclaim_content($content = '') {
        global $post;

        // Do not process feed / excerpt
        if (is_feed() || self::in_excerpt())
            return $content;

            //!is_home() &&
            //!is_single() &&
            //!is_page() &&
            //!is_archive() &&
            //!is_category()

        // Show map, if geo data present
        if (get_option('reclaim_show_map') == '1' && get_post_meta($post->ID, 'geo_latitude', true) && get_post_meta($post->ID, 'geo_longitude', true)) {

            $map = '<div class="clearfix leaflet-map" id="map-'.$post->ID.'" style=""></div>'
                .'<script type="text/javascript">var layer = new L.StamenTileLayer("toner-lite");'
                .'var map = new L.Map("map-'.$post->ID.'", '
                // options
                .'{center: new L.LatLng('.get_post_meta($post->ID, 'geo_latitude', true).', '.get_post_meta($post->ID, 'geo_longitude', true).'), '
                .'zoom: 14,'
                .'scrollWheelZoom: false'
                .'});'
                .'map.addLayer(layer);'
                .'var marker = L.marker(['.get_post_meta($post->ID, 'geo_latitude', true).', '.get_post_meta($post->ID, 'geo_longitude', true).']).addTo(map);'
                .'</script>';
            //scrollWheelZoom

            $content .= $map;
        }

        return $content;
    }

    public static function in_excerpt() {
        return
            in_array('the_excerpt', $GLOBALS['wp_current_filter']) ||
            in_array('get_the_excerpt', $GLOBALS['wp_current_filter']);
    }
    
    public function add_admin_ajax_handlers() {
		if (is_admin()) {
			foreach($this->mods_loaded as $mod) {
				if ($mod['active']) {
					$mod['instance']->add_admin_ajax_handlers();
				}
			}
		}
	}
}

add_action('init', 'reclaim_init');
function reclaim_init() {
    $reclaim = reclaim_core::instance();
}

function reclaim_update_schedule($schedules) {
    $reclaim = reclaim_core::instance();
    $schedules['reclaim_interval'] = array( 'interval' => $reclaim->get_interval(),
                                            'display' => 'Reclaim custom update interval' );
    return $schedules;
}
add_filter('cron_schedules', 'reclaim_update_schedule');

function reclaim_createSchedule() {
    wp_schedule_event( time(), 'reclaim_interval', 'reclaim_update_hook' );
}

function reclaim_deleteSchedule() {
    $time = wp_next_scheduled( 'reclaim_update_hook' );
    wp_unschedule_event( $time, 'reclaim_update_hook' );
}

register_activation_hook( __FILE__, 'reclaim_createSchedule' );
register_deactivation_hook( __FILE__, 'reclaim_deleteSchedule' );


// workaround: using wp_cron won't save post data containing an <iframe>.
// this lets us save the instagram embed code, that uses an iframe, with wp_cron.
// http://wordpress.stackexchange.com/questions/100588/wp-cron-doesnt-save-iframe-or-object-in-post-body
add_shortcode('embed_code', array('embed_code_shortcode', 'shortcode'));
class embed_code_shortcode {
    function shortcode($atts, $content=null) {
          $post_id = get_the_ID();
          $content = do_shortcode(get_post_meta($post_id, 'embed_code', true));
     return $content;
    }
}
