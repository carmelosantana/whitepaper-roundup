<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

class WhitepaperRoundup
{
    public function __construct()
    {
        $config = new Config();

        // Actions
        add_action('after_setup_theme', [__NAMESPACE__ . '\Utils', 'crb_load']);
        add_action('carbon_fields_post_meta_container_saved', [__NAMESPACE__ . '\Utils', 'post_save']);
        add_action('carbon_fields_register_fields', [$config, 'meta_options']);
        add_action('carbon_fields_register_fields', [$config, 'theme_options']);
        add_action('init', [$config, 'post_types'], 0);
        add_action('init', [$config, 'taxonomies'], 0);

        // Filters
        add_filter('pre_get_posts', [$config, 'search_filter']);
        add_filter('upload_mimes', [$config, 'mime_types'], 1, 1);

        // CLI
        if (Utils::is_wp_cli())
            \WP_CLI::add_command('wiki', (__NAMESPACE__ . '\CLI'));
    }
}
