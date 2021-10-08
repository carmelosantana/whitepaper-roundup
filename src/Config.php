<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Config
{
    public static function meta_options()
    {
        Container::make('post_meta', __('Whitepaper'))
            ->where('post_type', '=', 'document')
            ->add_tab('Files', [
                Field::make('complex', 'files', __('Files'))
                    ->add_fields([
                        Field::make('text', 'title', __('Title')),
                        Field::make('file', 'file', __('Attachment ID'))
                    ])
            ])
            ->add_tab('Details', [
                Field::make('text', 'symbol', __('Symbol'))
                    ->set_width(25),
                Field::make('date', 'publish_date', __('Publish Date'))
                    ->set_width(25),
                Field::make('text', 'source_url', __('Source URL'))
                    ->set_width(100),
            ]);

        Container::make('post_meta', __('Page Query'))
            ->where('post_type', '=', 'page')
            ->add_fields([
                Field::make('select', 'query', __('Query'))
                    ->set_width(100)
                    ->set_options(
                        [
                            '' => __('Default'),
                            'top_100' => __('Top 100'),
                            'by_market_cap' => __('By Market Cap'),
                            'spotlight' => __('Spotlight'),
                            'alphabetical' => __('Alphabetical'),
                            'random' => __('Random'),
                        ]
                    ),
                Field::make('complex', 'meta_query', __('Meta Query'))
                    ->add_fields([
                        Field::make('text', 'meta_key', __('Meta Key')),
                    ])
            ])
            ->set_context('side');
    }

    public static function mime_types($mime_types)
    {
        $mime_types['html'] = 'text/html';
        $mime_types['md'] = 'text/markdown';
        $mime_types['pdf'] = 'application/pdf';
        return $mime_types;
    }

    public static function post_types()
    {
        $labels = [
            'name' => _x('Documents', 'Post Type General Name', 'whitepaper-roundup'),
            'singular_name' => _x('Document', 'Post Type Singular Name', 'whitepaper-roundup'),
            'menu_name' => _x('Documents', 'Admin Menu text', 'whitepaper-roundup'),
            'name_admin_bar' => _x('Document', 'Add New on Toolbar', 'whitepaper-roundup'),
            'archives' => __('Document Archives', 'whitepaper-roundup'),
            'attributes' => __('Document Attributes', 'whitepaper-roundup'),
            'parent_item_colon' => __('Parent Document:', 'whitepaper-roundup'),
            'all_items' => __('All Documents', 'whitepaper-roundup'),
            'add_new_item' => __('Add New Document', 'whitepaper-roundup'),
            'add_new' => __('Add New', 'whitepaper-roundup'),
            'new_item' => __('New Document', 'whitepaper-roundup'),
            'edit_item' => __('Edit Document', 'whitepaper-roundup'),
            'update_item' => __('Update Document', 'whitepaper-roundup'),
            'view_item' => __('View Document', 'whitepaper-roundup'),
            'view_items' => __('View Documents', 'whitepaper-roundup'),
            'search_items' => __('Search Document', 'whitepaper-roundup'),
            'not_found' => __('Not found', 'whitepaper-roundup'),
            'not_found_in_trash' => __('Not found in Trash', 'whitepaper-roundup'),
            'featured_image' => __('Featured Image', 'whitepaper-roundup'),
            'set_featured_image' => __('Set featured image', 'whitepaper-roundup'),
            'remove_featured_image' => __('Remove featured image', 'whitepaper-roundup'),
            'use_featured_image' => __('Use as featured image', 'whitepaper-roundup'),
            'insert_into_item' => __('Insert into Document', 'whitepaper-roundup'),
            'uploaded_to_this_item' => __('Uploaded to this Document', 'whitepaper-roundup'),
            'items_list' => __('Documents list', 'whitepaper-roundup'),
            'items_list_navigation' => __('Documents list navigation', 'whitepaper-roundup'),
            'filter_items_list' => __('Filter Documents list', 'whitepaper-roundup'),
        ];

        $taxonomies = [];
        foreach (Document::data_mapping() as $source) {
            foreach ($source as $key => $data) {
                if ($data[0] != 'tax')
                    continue;

                $taxonomies[] = $data[1];
            }
        }
        $args = [
            'label' => __('Document', 'whitepaper-roundup'),
            'description' => __('Whitepapers', 'whitepaper-roundup'),
            'labels' => $labels,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'],
            'taxonomies' => $taxonomies,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'exclude_from_search' => false,
            'show_in_rest' => true,
            'publicly_queryable' => true,
            'capability_type' => 'post',
        ];
        register_post_type('document', $args);
    }

    /**
     * Changes search to exact query + document post type
     *
     * @param [type] $query
     * @return void
     */
    public static function search_filter($query)
    {
        if ($query->is_search) {
            $query->set('exact', true);
            $query->set('post_type', ['document']);
        }

        return $query;
    }

    public static function theme_options()
    {
        Container::make('theme_options', __('Theme Options'))
            ->add_tab('General', [
                Field::make('separator', 'sep_theme', __('Theme')),
                Field::make('checkbox', 'front_page_title', __('Title on Homepage'))
                    ->set_width(25),
                Field::make('text', 'keyword_limit', __('Keyword Limit'))
                    ->set_default_value(10)
                    ->set_width(25),
                Field::make('separator', 'sep_embed', __('Embed')),
                Field::make('select', 'pdf_embed', __('PDF Embed'))
                    ->set_help_text(__('Embed, object or iframe?'))
                    ->set_options([
                        'iframe' => 'iframe',
                        'pdfobject' => 'PDFObject.js',
                    ])
                    ->set_width(25),
                Field::make('select', 'document_embed', __('Document Embed'))
                    ->set_help_text(__('Embed, object or iframe?'))
                    ->set_options([
                        'iframe' => 'iframe',
                        'pdfobject' => 'PDFObject.js',
                    ])
                    ->set_width(25),
                Field::make('separator', 'sep_scripts', __('Scripts')),
                Field::make('header_scripts', 'header_scripts', __('Header Scripts')),
                Field::make('footer_scripts', 'footer_scripts', __('Footer Scripts')),
                Field::make('separator', 'sep_cache', __('Cache Expire')),
                Field::make('text', 'cache_expire_sml', __('Short'))
                    ->set_default_value(300)
                    ->set_width(25),
                Field::make('text', 'cache_expire_med', __('Medium'))
                    ->set_default_value(3600)
                    ->set_width(25),
                Field::make('text', 'cache_expire_lrg', __('Long'))
                    ->set_default_value(86400)
                    ->set_width(25),
            ])
            ->add_tab('Queries', [
                Field::make('separator', 'sep_trending', __('Trending')),
                Field::make('association', 'document_spotlight', __('Document Spotlight'))
                    ->set_types([
                        [
                            'type' => 'post',
                            'post_type' => 'document',
                        ]
                    ])
            ])
            ->add_tab('Language', [
                Field::make('text', 'projects', __('Projects'))
                    ->set_default_value('Projects')
                    ->set_width(25),
                Field::make('text', 'documents', __('Documents'))
                    ->set_default_value('Documents')
                    ->set_width(25),
                Field::make('text', 'coins', __('Coins'))
                    ->set_default_value('Coins')
                    ->set_width(25),
                Field::make('text', 'ai_assists', __('AI Assists'))
                    ->set_default_value('AI Assists')
                    ->set_width(25),
                Field::make('text', 'copyright_holder', __('Copyright Holder')),
            ])
            ->add_tab('Ads', [
                Field::make('separator', 'sep_ad_top_728_90', __('Top 728x90')),
                Field::make('checkbox', 'ad_top_728_90_front_page', __('Display on Homepage'))
                    ->set_width(25),
                Field::make('textarea', 'ad_top_728_90', __('Script'))
                    ->set_help_text('728x90')
                    ->set_width(100),
            ])
            ->add_tab('Locale', [
                Field::make('text', 'currency', __('Currency'))
                    ->set_default_value('usd')
                    ->set_width(25),
            ]);
    }

    public static function taxonomies()
    {
        foreach (Document::data_mapping() as $source) {
            foreach ($source as $data) {
                if ($data[0] != 'tax')
                    continue;

                self::taxonomy($data[1], $data[2]);
            }
        }
    }

    public static function taxonomy($slug, $single, $plural = null)
    {
        if (!$plural)
            $plural = $single . 's';

        $labels = [
            'name' => _x($plural, 'Taxonomy General Name', 'whitepaper-roundup'),
            'singular_name' => _x($single, 'Taxonomy Singular Name', 'whitepaper-roundup'),
            'menu_name' => __($plural, 'whitepaper-roundup'),
            'all_items' => __('All Items', 'whitepaper-roundup'),
            'parent_item' => __('Parent Item', 'whitepaper-roundup'),
            'parent_item_colon' => __('Parent Item:', 'whitepaper-roundup'),
            'new_item_name' => __('New Item Name', 'whitepaper-roundup'),
            'add_new_item' => __('Add New Item', 'whitepaper-roundup'),
            'edit_item' => __('Edit Item', 'whitepaper-roundup'),
            'update_item' => __('Update Item', 'whitepaper-roundup'),
            'view_item' => __('View Item', 'whitepaper-roundup'),
            'separate_items_with_commas' => __('Separate items with commas', 'whitepaper-roundup'),
            'add_or_remove_items' => __('Add or remove items', 'whitepaper-roundup'),
            'choose_from_most_used' => __('Choose from the most used', 'whitepaper-roundup'),
            'popular_items' => __('Popular Items', 'whitepaper-roundup'),
            'search_items' => __('Search Items', 'whitepaper-roundup'),
            'not_found' => __('Not Found', 'whitepaper-roundup'),
            'no_terms' => __('No items', 'whitepaper-roundup'),
            'items_list' => __('Items list', 'whitepaper-roundup'),
            'items_list_navigation' => __('Items list navigation', 'whitepaper-roundup'),
        ];
        $args = [
            'labels' => $labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,

            // 'update_count_callback' => '_update_post_term_count',
            // 'query_var' => true,
            // 'rewrite' => array( 'slug' => $slug ),		
        ];
        register_taxonomy($slug, 'document',  $args);
    }

    public static function pre_get_posts($query)
    {
        if (!is_admin() and $query->is_main_query()) {
            $query->set('post_type', ['post', 'document']);
        }
    }
}
