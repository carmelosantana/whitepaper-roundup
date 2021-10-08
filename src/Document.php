<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

use Smalot\PdfParser\Parser;
use PhpScience\TextRank\TextRankFacade;
use PhpScience\TextRank\Tool\StopWords\English;
use mikehaertl\wkhtmlto\Pdf;

class Document
{
    public function get($post_id = 0): array
    {
        // get ID if 1 isn't supplied
        if (!$post_id)
            $post_id = get_the_ID();

        // get the post
        $post = get_post($post_id);

        // simple metas        
        $symbol = carbon_get_post_meta($post_id, 'symbol');

        // get the first file
        $files = carbon_get_post_meta($post_id, 'files');
        $file = $files[0] ?? null;
        if (!empty($file)) {
            $image = wp_get_attachment_thumb_url($files[0]['file']);
            $file = wp_get_attachment_url($files[0]['file']);
        }

        // build keywords list
        $keywords = get_the_term_list($post_id, 'keyword', '', ' ');

        // add additional meta data
        $meta_data = get_post_meta($post_id, 'document', true);
        if ( $meta_data and !empty($meta_data) ){
            $meta_data = json_decode($meta_data, true);
            $meta_data['Size'] = Utils::human_filesize(get_post_meta($post_id, 'size', true));
            $meta_data = self::output_map_array($post_id, 'document', [
                'PTEX.Fullbanner' => null,
                'ADBE_ProducerDetails' => null,
                'GTS_PDFXConformance' => null,
                'GTS_PDFXVersion' => null,
            ], $meta_data);
        }

        $data = [
            // general
            'name' => $post->post_title,
            'symbol' => $symbol ? strtoupper($symbol) : null,

            // URLS
            'file' => $file ?? '',
            'download' => $file ? $file . '?download' : null,
            'href' => get_permalink($post_id),

            // images
            'image' => $image ?? null,
            'thumbnail' => has_post_thumbnail() ? get_the_post_thumbnail(null, 'thumbnail') : '',

            // data in arrays
            'keywords' => $keywords ? $keywords :  '',
            'market_data' => self::output_map_array($post_id, 'coingecko', ['id',  'symbol']),
            'meta_data' => $meta_data,

            // dates
            'creation_date' => get_post_meta($post_id, 'creation_date', 1),

            'original_filename' => $files[0]['title'] ?? null,
        ];
        return $data;
    }

    public static function post_save($post_id = 0)
    {
        $attachment_id = carbon_get_post_meta($post_id, 'files')[0]['file'] ?? null;

        if (!$attachment_id)
            return false;

        if (!$post_id)
            $post_id = get_the_ID();

        // get path from attachment ID
        $path = get_attached_file($attachment_id);

        // send back if we don't have a valid path
        if (!$path or !is_file($path))
            return false;

        switch (pathinfo($path, PATHINFO_EXTENSION)) {
            case 'md':
                self::parse_md($post_id, $path);
                break;

            case 'pdf':
                self::parse_pdf($post_id, $path);
                break;
        }

        self::parse_file($post_id, $path);
        self::parse_text_rank($post_id);
    }

    public static function parse_file($post_id, $path)
    {
        $post = [
            'ID' => $post_id,
        ];

        $meta = [
            'size' => filesize($path)
        ];

        Utils::update_post_meta($post, $meta);
    }

    public static function parse_md($post_id = 0, $path)
    {
        $text = file_get_contents($path);

        // make HTML
        $html = self::parse_md_html($text);

        $post_args = [
            'ID' => $post_id,
            'post_content' => $html,
        ];

        // make pdf
        $pdf = new Pdf($text);

        // tmp pdf location
        $file = sys_get_temp_dir() . '/' . pathinfo($path, PATHINFO_BASENAME) . '.pdf';

        if (!$pdf->saveAs($file)) {
            \WP_CLI::error($pdf->getError());
            \WP_CLI::warning($path);
        }

        // sideload new file
        $pdf_id = Utils::handle_sideload($file, $post_id, pathinfo($file, PATHINFO_FILENAME));

        // if we have an id, add to #0 position of files array
        if ($pdf_id) {
            $files = carbon_get_post_meta($post_id, 'files');
            $new_files = [
                'file' => (string) $pdf_id,
                'title' => pathinfo($path, PATHINFO_BASENAME)
            ];
            array_unshift($files, $new_files);
            carbon_set_post_meta($post_id, 'files', $files);
            self::parse_pdf($post_id, get_attached_file($pdf_id));
        }

        // add post_content
        Utils::update_post_meta($post_args);
    }

    public static function parse_md_html($content, $document = false)
    {
        $Parsedown = new \Parsedown();
        $Parsedown->setSafeMode(true);

        $html = $Parsedown->text($content);

        // add class line to <hr>
        // $body = str_replace('<hr />', '<hr class="line"/>', $body);
        // $body = str_replace('&lt;', '<', $body);
        // $body = str_replace('&gt;', '>', $body);

        if (!$html)
            return false;

        // build HTML
        if ($document) {
            $doc = '<!DOCTYPE HTML>';
            // $doc .= '<html lang="en">';
            $doc .= '<head>';
            $doc .= '<title></title>';
            $doc .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
            $doc .= '<link rel="stylesheet" type="text/css" href="github-markdown.css">';
            $doc .= '</head>';
            $doc .= '<body class="markdown-body">';
            $doc .= $html;
            $doc .= '</body>';
            $doc .= '</html>';

            return $doc;
        }

        return $html;
    }

    public static function parse_pdf($post_id = 0, $path, $allowed = [])
    {
        // setup pdf parser
        $parser = new Parser();
        try {
            $pdf = $parser->parseFile($path);
        } catch (\Exception $e) {
            \WP_CLI::warning($e->getMessage());
            \WP_CLI::warning($path);
            $post = [
                'ID' => $post_id,
                'post_status' => 'draft',
            ];
            Utils::update_post_meta($post);
            return false;
        }

        try {
            $content = $pdf->getText();
            $post_status = 'publish';
        } catch (\Exception $e) {
            \WP_CLI::warning($e->getMessage());
            \WP_CLI::warning($path);
            $content = '';
            $post_status = 'draft';
        }

        // build post arguments
        $post = [
            'ID' => $post_id,
            'post_content' => $content,
            'post_status' => $post_status,
        ];

        $meta_input = [];

        try {
            $tax_input = [];
            foreach (self::data_mapping()['document'] as $key => $data) {
                if (!isset($pdf->getDetails()[$key]))
                    continue;

                switch ($data[0]) {
                    case 'tax':
                        if (is_string($pdf->getDetails()[$key]))
                            $tax_input[$data[1]] = [$pdf->getDetails()[$key]];
                        break;

                    case 'meta':
                        $meta_input[$data[1]] = $pdf->getDetails()[$key];
                        break;
                }
                if (!empty($tax_input))
                    $post['tax_input'] = $tax_input;
            }

            $meta_input['document'] = json_encode($pdf->getDetails());
        } catch (\Exception $e) {
            \WP_CLI::warning($e->getMessage());
        }

        // update post
        Utils::update_post_meta($post, $meta_input);
    }

    public static function parse_text_rank($post_id, $content = null)
    {
        // setup text summary
        $text = new TextRankFacade();
        $stop_words = new English();
        $text->setStopWords($stop_words);
        $meta_input = [];

        if (!$content) {
            $post = get_post($post_id);
            $content = $post->post_content ?? false;
        }

        if (!$content)
            return false;

        $clean = preg_replace("#\r#", ' ', $content);

        // set taxonomy
        $keywords = $text->getOnlyKeyWords($content);

        // build post arguments
        $post_args = [
            'ID' => $post_id,
            'post_excerpt' => implode(' ', $text->summarizeTextCompound($clean)),
        ];

        if ($keywords) {
            $meta_input['keywords'] = json_encode($keywords);

            $tax_limit = carbon_get_theme_option('keyword_limit');
            $tax_input = [];
            $pspell_link = pspell_new("en");
            foreach (array_keys(array_slice($keywords, 1, ($tax_limit * 4))) as $keyword) {
                if (!Utils::word_check($keyword))
                    continue;

                // check words with hyphens
                if (strstr($keyword, '-')) {
                    foreach (explode('-', $keyword) as $part) {
                        if (!pspell_check($pspell_link, $part) or !Utils::word_check($part))
                            continue 2;
                    }
                    $tax_input[] = $keyword;
                    continue;
                }

                // check string
                if (pspell_check($pspell_link, $keyword) and count($tax_input) != $tax_limit)
                    $tax_input[] = $keyword;
            }
            $post_args['tax_input'] = ['keyword' => $tax_input];
        }

        // update post
        Utils::update_post_meta($post_args, $meta_input);
    }

    public static function data_mapping()
    {
        return [
            'document' => [
                'CreationDate' => ['meta', 'creation_date', __('Creation Date'), 'date'],
                'ModDate' => [null, 'ModDate', __('Modified Date'), 'date'],
                'Author' => ['tax', 'writer', __('Writer')],
                'Creator' => [null, 'creator', __('Creator')],
                'Composer' => [null, 'composer', __('Composer')],
                '_keyword' => ['tax', 'keyword', __('Keyword')],
                // 'Keywords' => ['tax', 'tags', __('Tags')],
                'Subject' => [null, 'subject', __('Subject')],
                'Title' => [null, 'title', __('Title')],
                'Size' => [null, 'size', __('Size')],
            ],
            'coingecko' => [
                'id' => ['meta', 'id', __('ID')],
                'symbol' => ['meta', '_symbol', __('Symbol')],
                'current_price' => ['meta', 'current_price', __('Current price'), 'currency'],
                'market_cap' => ['meta', 'market_cap', __('Market cap'), 'big_number'],
                'market_cap_rank' => ['meta', 'market_cap_rank', __('Rank by cap')],
                'total_volume' => ['meta', 'total_volume', __('Total volume'), 'big_number'],
                'ath' => ['meta', 'ath', __('All time high'), 'currency'],
                'atl' => ['meta', 'atl', __('All time low'), 'currency'],
                'price_change_percentage_24h' => [null, 'price_change_percentage_24h', __('24h% Change'), 'market_change'],
            ],
        ];
    }

    public static function output_meta_tax($post_id, $mapping, $skip = [])
    {
        $output = [];

        foreach (self::data_mapping()[$mapping] as $key => $map) {
            if (in_array($map[1], $skip))
                continue;

            switch ($map[0]) {
                case 'meta':
                    $tmp = get_post_meta($post_id, $map[1], true);

                    if (is_array($tmp))
                        $tmp = $tmp[0];

                    if ($tmp or !empty($tmp))
                        $output[$map[2]] = $tmp;
                    break;
            }
        }

        return $output;
    }

    public static function output_map_array($post_id, $key, $skip = [], $metas=[])
    {
        $out = [];

        $map = self::data_mapping()[$key];

        if (empty($metas)){
            $metas = get_post_meta($post_id, $key, true);
            $metas = json_decode($metas, true);
        }

        if (!$metas or empty($metas))
            return false;

        foreach ($metas as $key => $val) {
            if (in_array($key, $skip))
                continue;

            $out[$map[2] ?? $key] = $val;
        }

        return $out;
    }

    public static function redirect($post_id)
    {
    }

    public static function download()
    {
    }
}
