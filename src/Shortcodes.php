<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

class Shortcodes
{
    public function __construct()
    {
        add_shortcode('query', [$this, 'query']);
    }

    public static function query($atts)
    {
        $atts = shortcode_atts(
            [
                
            ],
            $atts,
            'query'
        );
    }
}
