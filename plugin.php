<?php
/**

 * Plugin Name: Featured Poster Image
 * Description: Set Post Thumbnail from Video Poster Image
 * Version: 1.0
 * Author: Chris McCoy
 * Author URI: http://github.com/chrismccoy
 *
 * @copyright 2023
 * @author Chris McCoy
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package Featured_Poster_Image
 */

if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * Initiate Featured Poster Image on plugins_loaded
 *
 */

if (!function_exists('featured_poster_image')) {
    function featured_poster_image()
    {
        $featured_poster_image = new Featured_Poster_Image();
    }

    add_action('plugins_loaded', 'featured_poster_image');
}

/**
 * Featured Poster Image
 *
 */

if (!class_exists('Featured_Poster_Image')) {
    class Featured_Poster_Image
    {
        private $video_shortcode_regex = '#<(?:video|video-js|presto-player)\s[^>]*poster="([^\"]*)"[^\]]*(?:\]|>)#';

        /**
         * Hook for saving post
         *
         */

        public function __construct()
        {
                add_action('save_post', [$this, 'save_post'], 20, 3);

        }

        /**
         * insert external url into media library
         *
         */
        public function insert_attachment_from_url($url, $post_ID = 0, $timeout = 10)
        {
            if (!function_exists('media_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }

            $tmp_file = download_url($url, $timeout);
            if (is_wp_error($tmp_file)) {
                return $tmp_file;
            }

            $file = [
                'name' => basename($url),
                'tmp_name' => $tmp_file,
            ];

            $attachment_id = media_handle_sideload($file, $post_ID, null, ['guid' => $url]);

            if (is_wp_error($attachment_id)) {
                unlink($tmp_file);
            }

            return $attachment_id;
        }

        /**
         * check the post content for video shortcode and poster and set as featured image
         *
         */
        public function set_featured_image($post_ID, $post)
        {
            if (preg_match($this->video_shortcode_regex, do_shortcode($post->post_content), $matches)) {
                $url = $matches[1];
                $attachment_id = $this->insert_attachment_from_url($url, $post_ID);
                if ($attachment_id) {
                    if (update_post_meta($post_ID, '_thumbnail_id', $attachment_id)) {
                        return $attachment_id;
                    }
                }
            }
        }

        /**
         * set featured image from poster image
         *
         */
        public function save_post($post_ID, $post, $update)
        {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (wp_is_post_revision($post_ID)) {
                return;
            }

            if ('auto-draft' === $post->post_status) {
                return;
            }

            $attachment_id = get_post_meta($post_ID, '_thumbnail_id', true);

            if (!$attachment_id) {
                $this->set_featured_image($post_ID, $post);
            }
        }
    }
}
