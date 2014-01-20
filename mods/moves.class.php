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

class moves_reclaim_module extends reclaim_module {
    private static $apiurl= "https://api.moves-app.com/api/v1/user/summary/daily?pastDays=%s&access_token=%s";
    // change for one-time import
    // private static $apiurl= "https://api.moves-app.com/api/v1/user/summary/daily?from=yyyymmdd&to=yyyymmdd&&foo=%s&access_token=%s";
    // private static $apiurl= "https://api.moves-app.com/api/v1/user/summary/daily?from=20130304&to=20130331&&foo=%s&access_token=%s";

    private static $timeout = 15;
    private static $count = 31; // maximum 31 days
    private static $post_format = 'status'; // or 'status', 'aside'

// callback-url: http://root.wirres.net/reclaim/wp-content/plugins/reclaim/vendor/hybridauth/hybridauth/src/
// new app: http://instagram.com/developer/clients/manage/

    public function __construct() {
        $this->shortname = 'moves';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'moves_user_id');
        register_setting('reclaim-social-settings', 'moves_client_id');
        register_setting('reclaim-social-settings', 'moves_client_secret');
        register_setting('reclaim-social-settings', 'moves_access_token');
    }

    public function display_settings() {
        if ( isset( $_GET['link']) && (strtolower($_GET['mod'])=='moves') && (isset($_SESSION['hybridauth_user_profile']))) {
            $user_profile       = json_decode($_SESSION['hybridauth_user_profile']);
            $user_access_tokens = json_decode($_SESSION['hybridauth_user_access_tokens']);
            $error = $_SESSION['e'];

            if ($error) {
                echo '<div class="error"><p><strong>Error:</strong> ',esc_html( $error ),'</p></div>';
            }
            else {
                update_option('moves_user_id', $user_profile->identifier);
                update_option('moves_access_token', $user_access_tokens->access_token);
            }
            if(session_id()) {
                session_destroy ();
            }
        }
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('moves', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('Moves client id', 'reclaim'); ?></th>
            <td><input type="text" name="moves_client_id" value="<?php echo get_option('moves_client_id'); ?>" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e('Moves client secret', 'reclaim'); ?></th>
            <td><input type="text" name="moves_client_secret" value="<?php echo get_option('moves_client_secret'); ?>" />
            <input type="hidden" name="moves_user_id" value="<?php echo get_option('moves_user_id'); ?>" />
            <input type="hidden" name="moves_access_token" value="<?php echo get_option('moves_access_token'); ?>" />
            <p class="description">Get your Moves client and credentials <a href="https://dev.moves-app.com/apps">here</a>. Use <code><?php echo plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/') ?></code> as "Redirect URI"</p>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"></th>
            <td>
            <?php
            if (
            (get_option('moves_client_id')!="")
            && (get_option('moves_client_secret')!="")

            ) {
                $link_text = __('Authorize with Moves', 'reclaim');
                if ( (get_option('moves_user_id')!="") && (get_option('moves_access_token')!="") ) {
                    echo sprintf(__('<p>Moves is authorized</p>', 'reclaim'), get_option('moves_user_id'));
                    $link_text = __('Authorize again', 'reclaim');
                }

                // send to helper script
                // put all configuration into session
                // todo
                $config = $this->construct_hybridauth_config();
                $callback =  urlencode(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=reclaim/reclaim.php&link=1&mod='.$this->shortname);

                $_SESSION[$this->shortname]['config'] = $config;

                echo '<a class="button button-secondary" href="'
                    .plugins_url( '/helper/hybridauth/hybridauth_helper.php' , dirname(__FILE__) )
                    .'?'
                    .'&mod='.$this->shortname
                    .'&callbackUrl='.$callback
                    .'">'.$link_text.'</a>';
            }
            else {
                _e('enter moves app id and secret', 'reclaim');
            }

            ?>
            </td>
        </tr>

<?php
    }

    public function construct_hybridauth_config() {
        $config = array(
            // "base_url" the url that point to HybridAuth Endpoint (where the index.php and config.php are found)
            "base_url" => plugins_url('reclaim/vendor/hybridauth/hybridauth/hybridauth/'),
            "providers" => array (
                "Moves" => array(
                    "enabled" => true,
                    "keys"    => array ( "id" => get_option('moves_client_id'), "secret" => get_option('moves_client_secret') ),
                    "wrapper" => array(
                        "path"  => dirname( __FILE__ ) . '/../helper/hybridauth/provider/Moves.php',
                        "class" => "Hybrid_Providers_Moves",
                    ),
                ),
            ),
        );
        return $config;
    }

    public function import() {
        if (get_option('moves_user_id') && get_option('moves_access_token') ) {
            $rawData = parent::import_via_curl(sprintf(self::$apiurl, self::$count, get_option('moves_access_token')), self::$timeout);
            $rawData = json_decode($rawData, true);

            if ($rawData) {
                $data = $this->map_data($rawData);
                parent::insert_posts($data);
                update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            }
            else parent::log(sprintf(__('%s returned no data. No import was done', 'reclaim'), $this->shortname));
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    /**
     * Maps moves summery data to wp-content data. Check https://dev.moves-app.com/docs/api_summaries for more info.
     * @param array $rawData
     * @return array
     */
    private function map_data(array $rawData) {
        $data = array();
        foreach($rawData as $day){

            // today?
            if ( strtotime($day['date']) >= strtotime(date('d.m.Y')) ) {
                // no entry, if it's from today
            } else {
            // post activity after 02:00
            if (intval(date("H"))>2) {
                $id = $day["date"];
                $image_url = '';
                $tags = '';
                $link = '';
                $title = sprintf(__('Bewegung am %s', 'reclaim'), date('d.m.Y', strtotime($day["date"])));

                $content = $this->construct_content($day);
                $post_meta = $this->construct_post_meta($day);

                $data[] = array(
                    'post_author' => get_option($this->shortname.'_author'),
                    'post_category' => array(get_option($this->shortname.'_category')),
                    'post_format' => self::$post_format,
                    'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($day["date"])+79200)),
                    'post_content' => $content,
                    'post_title' => $title,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'tags_input' => $tags,
                    'ext_permalink' => $link,
                    'ext_image' => $image_url,
                    'ext_guid' => $id,
                    'post_meta' => $post_meta
                );
                }
            }
        }
        return $data;
    }

    private function construct_content($day) {
        if (isset($day['summary'])) {
            $distance = 0;
            $description = 'ich bin heute ';
            foreach($day['summary'] as $summary) {
                if (isset($summary['activity'])) {
                    if ($summary['activity']=="wlk") {
                        $distance = intval($summary['distance']);
                        if ($summary['steps'] >= 500) {
                            $description .= number_format(intval($summary['steps']),0,',','.'). ' schritte gelaufen';
                        }
                        else {
                            $description .= 'sehr wenig gelaufen';
                        }
                    } elseif ($summary['activity']=="run") {
                        $distance = $distance + intval($summary['distance']);
                        if ($summary['distance'] >= 500) {
                            $description .= ' und ' . number_format( (intval($summary['distance'])/1000), 1, ',', '.') . ' kilometer gerannt';
                        }
                    } elseif ($summary['activity']=="cyc") {
                        $distance = $distance + intval($summary['distance']);
                        if ($summary['distance'] >= 1000) {
                            $description .= ' und ' . number_format( (intval($summary['distance'])/1000), 1, ',', '.') . ' kilometer fahrrad gefahren';
                        }
                    }
                }
            }
            $description .= '.';
            if ($distance <= 500) {
                $description = 'ich habe mich heute kaum bewegt.';
            }
        }
        else {
            $description = 'ich habe mich heute kaum bewegt.';
        }

        return $description;
    }

    /**
     * Returns meta data for every activity in a moves summary data day.
     * @param array $day Data return from moves api. Known possible keys so far:
     *  activity, distance, duration, steps (not if activity == cyc), calories
     * @return array
     */
    private function construct_post_meta(array $day)
    {
        if (isset($day['summary'])) {
        $post_meta = array();
            foreach ($day['summary'] as $activityData) {
                $activity = isset($activityData['activity']) ? $activityData['activity'] : 'unknown';
                unset($activityData['activity']);
                foreach ($activityData as $activityDataKey => $activityDataValue) {
                    $postMetaKey = $activity . '_' . $activityDataKey;
                    $post_meta[$postMetaKey] = $activityDataValue;
                }
            }
            return $post_meta;
        }
        else {
            return array();
        }
    }
}