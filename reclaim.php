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

if (file_exists( __DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

define('RECLAIM_UPDATE_INTERVAL', 10*60);
define('RECLAIM_PLUGIN_PATH', dirname( __FILE__));

class reclaim_core {
    private $mods_loaded = array();
    private static $instance = 0;

    public function __construct() {
        add_action('init', array($this, 'myStartSession'),1,1);

        require_once('helper-functions.php');
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
            if (is_admin() && isset($_REQUEST[$mod['name'].'_resync'])) {
                $this->updateMod($mod, true);
            }
        }

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'prefix_add_reclaim_stylesheet'));

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
        wp_register_style('leaflet', 'http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css');
        wp_enqueue_style('leaflet');
        wp_enqueue_script( 'leaflet', 'http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js' );
        wp_enqueue_script( 'stamen', 'http://maps.stamen.com/js/tile.stamen.js?v1.2.4' );
//        wp_enqueue_script( 'twitter-widget', 'https://platform.twitter.com/widgets.js' );
//        wp_enqueue_script( 'google-plus-widget', 'https://apis.google.com/js/plusone.js' );
//        wp_enqueue_script( 'facebook-jssdk', 'https://connect.facebook.net/de_DE/all.js#xfbml=1' );
//
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
        foreach($this->mods_loaded as $mod) {
            $mod['instance']->register_settings();
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
                <th colspan="2"><h3><?php _e('General Settings', 'reclaim'); ?></h3></th>
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
        if (get_post_meta($post->ID, 'geo_latitude', true) && get_post_meta($post->ID, 'geo_longitude', true)) {

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

