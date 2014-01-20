<?php
/*  Copyright 2014 Christian Muehlhaeuser <muesli@gmail.com>

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

class github_reclaim_module extends reclaim_module {
    private static $apiurl = "https://api.github.com/users/%s/events/public?page=%d";
    private static $timeout = 15;

    public function __construct() {
        $this->shortname = 'github';
    }

    public function register_settings() {
        parent::register_settings($this->shortname);

        register_setting('reclaim-social-settings', 'github_username');
    }

    public function display_settings() {
?>
        <tr valign="top">
            <th colspan="2"><h3><?php _e('GitHub', 'reclaim'); ?></h3></th>
        </tr>
<?php
        parent::display_settings($this->shortname);
?>
        <tr valign="top">
            <th scope="row"><?php _e('GitHub username', 'reclaim'); ?></th>
            <td><input type="text" name="github_username" value="<?php echo get_option('github_username'); ?>" /></td>
        </tr>
<?php
    }

    public function import($forceResync) {
        if (get_option('github_username')) {
            $lastseenid = get_option('reclaim_'.$this->shortname.'_last_seen_id');

            $page = 0;
            do {
                $req = sprintf(self::$apiurl, get_option('github_username'), $page);
                $feed = parent::import_via_curl($req, self::$timeout);

                $data = self::map_data(json_decode($feed, true));
                parent::insert_posts($data);

                $reqOk = count($data) > 0;
                if (!$forceResync && $reqOk && intval($data[count($data)-1]["ext_guid"]) < intval($lastseenid)) {
                    // abort requests if we've already seen these events
                    $reqOk = false;
                }

                if (intval($newlastseenid) < intval($data[0]["ext_guid"]) && $reqOk) {
                    // store the last-seen-id, which is the first message of the first request
                    $newlastseenid = $data[0]["ext_guid"];
                }

                parent::log(sprintf(__('Retrieved set of GitHub events: %d, last seen id: %s, req-ok: %d', 'reclaim'), count($data), $lastseenid, $reqOk));
                $page++;
            } while ($reqOk);

            update_option('reclaim_'.$this->shortname.'_last_update', current_time('timestamp'));
            update_option('reclaim_'.$this->shortname.'_last_seen_id', $newlastseenid);
        }
        else parent::log(sprintf(__('%s user data missing. No import was done', 'reclaim'), $this->shortname));
    }

    private function map_data($rawData) {
        $data = array();
        $tags = array();
        foreach($rawData as $entry) {
            if (!$entry["public"])
                continue;
            if ($entry["type"] != "PushEvent")
                continue;

            $post_format = 'status';
            $content = self::construct_content($entry);
            $tags = self::get_hashtags($entry);

            // http://codex.wordpress.org/Function_Reference/wp_insert_post
            $data[] = array(
                'post_author' => get_option($this->shortname.'_author'),
                'post_category' => array(get_option($this->shortname.'_category')),
                'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', strtotime($entry["created_at"]))),
                'post_format' => $post_format,
                'post_content'   => $content['content'],
                'post_title' => strip_tags($content['title']),
                'post_type' => 'post',
                'post_status' => 'publish',
                'tags_input' => $tags,
                'ext_permalink' => $content['url'],
                'ext_guid' => $entry["id"]
            );
            parent::log(sprintf(__('%s posted new status: %s on %s', 'reclaim'), $this->shortname, $content["title"], $data[count($data)-1]["post_date"]));
        }

        return $data;
    }

    private function get_hashtags($entry) {
        $tags = array();
        return $tags;
    }

    private function construct_content($entry) {
        $before = $entry["payload"]["before"];
        $after = $entry["payload"]["head"];
        $url = $entry["repo"]["url"] . "/compare/" . substr($before, 0, 8) . "..." . substr($after, 0, 8);
        $url = str_replace("https://api.github.com/repos", "https://github.com", $url);
        $repoName = $entry["repo"]["name"];

        $commitCount = 0;
        foreach($entry["payload"]["commits"] as $commit) {
            if ($commit["sha"] == $before)
                continue;

            $commitCount++;
            $commitDesc .= $commit["message"] . "<br />";
        }

        if ($commitCount > 1) {
            $content = sprintf("Pushed %d commits to %s: %s", $commitCount, $repoName, $commitDesc);
            $title = sprintf("Pushed %d commits to %s.", $commitCount, $repoName);
        } else {
            $content = sprintf("Pushed a commit to %s: %s", $repoName, $commitDesc);
            $title = sprintf("Pushed a commit to %s.", $repoName);
        }

        return array(
            'content' =>  $content,
            'title' => $title,
            'url' => $url
        );
    }
}
