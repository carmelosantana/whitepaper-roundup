<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

class Utils
{
    public static function crb_load()
    {
        \Carbon_Fields\Carbon_Fields::boot();
    }

    public static function delete_transients(): void
    {
        global $wpdb;

        $sql = 'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE "_transient_%"';
        $wpdb->query($sql);
    }

    public static function format($style, $string)
    {
        switch ($style) {
            case 'big_number':
                if ($string < 1000000) {
                    // Anything less than a million
                    $string = number_format($string);
                } else if ($string < 1000000000) {
                    // Anything less than a billion
                    $string = number_format($string / 1000000, 2) . 'M';
                } else {
                    // At least a billion
                    $string = number_format($string / 1000000000, 2) . 'B';
                }
                break;

            case 'currency':
                if ($string > .99) {
                    $string = number_format($string, 2);
                } elseif ($string > .02) {
                    $string = number_format($string, 4);
                } elseif ($string > .02) {
                    $string = number_format($string, 6);
                }
                $string = '$' .  $string;
                break;

            case 'date':
                if (!is_int($string))
                    $string = strtotime($string);

                if (is_int($string))
                    $string = date('F n, Y', $string);
                break;

            case 'market_change':
                $format = number_format($string, 2);
                if ($string > 0) {
                    $string = '<span class="market-up">▲ ' . $format . '</span>';
                } else {
                    $string = '<span class="market-down">▼ ' . $format . '</span>';
                }
                break;
        }
        return $string;
    }

    // https://www.wpastronaut.com/blog/upload-files-wordpress-programmatically/
    public static function handle_sideload($file, $post_id = 0, $desc = null)
    {
        self::init_media_handle_sideload();

        if (!is_file($file)) {
            if (self::is_wp_cli())
                \WP_CLI::error('Utils::handle_sideload() !$file', true);

            return new \WP_Error('error', 'File is empty');
        }

        $file_array = [
            'tmp_name' => $file,
            'name' => pathinfo($file)['basename'],
        ];

        // Store and validate
        $id = media_handle_sideload($file_array, $post_id, $desc);

        if (!$id or is_wp_error($id)) {
            return false;
            if (self::is_wp_cli()){
                \WP_CLI::warning('Upload $id error');

            } else {
                return new \WP_Error('error', "Upload ID is empty");

            }
        }

        return $id;
    }

    // https://www.php.net/manual/en/function.filesize.php#106569
    // https://stackoverflow.com/a/15188082
    public static function human_filesize($size, $unit = '')
    {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB")
            return number_format($size / (1 << 30), 2) . "GB";
        if ((!$unit && $size >= 1 << 20) || $unit == "MB")
            return number_format($size / (1 << 20), 2) . "MB";
        if ((!$unit && $size >= 1 << 10) || $unit == "KB")
            return number_format($size / (1 << 10), 2) . "KB";
        return number_format($size, 1) . " bytes";
    }

    /**
     * Check if we are in CLI
     *
     * @return boolean
     */
    public static function is_wp_cli()
    {
        if (defined('WP_CLI') and \WP_CLI)
            return true;

        return false;
    }

    public static function init_media_handle_sideload()
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    public static function update_post_meta($post = [], $meta = [])
    {
        $def = [
            'ID' => is_numeric($post) ? (int) $post : get_the_ID(),
        ];

        $post = wp_parse_args($post, $def);

        if (count($post) > 1) {
            wp_update_post($post);
            if (isset($post['tax_input']) and Utils::is_wp_cli()) {
                foreach ($post['tax_input'] as $tax_key => $tax)
                    wp_set_object_terms($post['ID'], $tax, $tax_key);
            }
        }

        if (!empty($meta)) {
            foreach ($meta as $key => $val) {
                delete_post_meta($post['ID'], $key);
                add_post_meta($post['ID'], $key, $val, true);
            }
        }
    }

    // https://stackoverflow.com/questions/5029409/how-to-check-if-an-integer-is-within-a-range
    public static function int_in_range($val, $min, $max)
    {
        return ($val >= $min and $val <= $max);
    }

    public static function word_check($string, $min = 3, $max = 20)
    {
        if (!is_string($string) or !Utils::int_in_range(strlen($string), $min, $max))
            return false;

        return true;
    }
}
