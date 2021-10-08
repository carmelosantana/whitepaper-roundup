<?php

declare(strict_types=1);

namespace carmelosantana\WhitepaperRoundup;

use Codenixsv\CoinGeckoApi\CoinGeckoClient;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// define( 'WP_IMPORTING', true );
class CLI extends \WP_CLI_Command
{
    public function bulk_import($args = [], $assoc_args = [])
    {

        define('WP_IMPORT', true);

        $args = wp_parse_args($args, [
            'coingecko',
            'cryptorating'
        ]);

        $assoc_args = wp_parse_args($assoc_args, [
            // generic
            'cache' => true,
            'copy' => false,
            'dry-mode' => false,
            'purge' => false,
            'offset' => 0,
            'limit' => 100,

            // git
            'download' => true,
            'repo' => null,

            // posts
            'post_type' => 'document',

            // cryptorating
            'cryptorating' => true,

            // coingecko
            'coingecko' => true,
            'coingecko_ids' => null,
        ]);

        $data = [
            'cryptorating' => [],
            'coingecko_market_cap' => [],
        ];

        define('WP_CLI_DRY', ($assoc_args['dry-mode'] ? true :  false));

        // parse folders
        if (in_array('cryptorating', $args) and in_array('git', $args) and $assoc_args['repo']) {
            $git_path = $this->git(['clone'], $assoc_args);
            $data['cryptorating'] = $this->parse_folder($git_path, $assoc_args);
            $assoc_args['coingecko_ids'] = self::coingecko_ids($data['cryptorating'], $assoc_args['offset'], $assoc_args['limit']);
            \WP_CLI::debug($git_path);
        }

        // get market cap for folders
        if (in_array('coingecko', $args) and $assoc_args['coingecko_ids']) {
            $data['coingecko_market_cap'] = $this->coingecko(['markets'], $assoc_args);
        }

        // delete posts
        if ($assoc_args['purge'])
            $this->purge_posts($args, $assoc_args);

        // add initial posts based on matching Cryptorating + Coin Gecko marketcap
        $this->add_cryptorating_coingecko($data['cryptorating'], $data['coingecko_market_cap'], $assoc_args);

        // clear cache
        Utils::delete_transients();
    }

    public function is_dry()
    {
        if (\WP_CLI_DRY)
            return true;

        return false;
    }

    private function add_cryptorating_coingecko($cryptorating, $coingecko, $assoc_args)
    {
        $progress = \WP_CLI\Utils\make_progress_bar('add_cryptorating_coingecko()', count($cryptorating));

        // start adding coingecko coins they have confirmed name + symbol
        foreach ($cryptorating as $id => $whitepaper) {
            $coin = $coingecko[$id] ?? null;
            $meta_input = [];

            $post_name = $id;

            if (isset($coin['symbol'])) {
                $post_name .= '-' . $coin['symbol'];
                $meta_input['coingecko'] = json_encode($coin);
            }

            // build existing meta_input
            foreach (Document::data_mapping() as $source) {
                foreach ($source as $key => $data) {
                    if ($data[0] != 'meta')
                        continue;

                    // add coingecko data
                    if (isset($coin[$key]))
                        $meta_input[$data[1]] = $coin[$key];
                }
            }

            // start post args
            $post = [
                'post_name' => $post_name,
                'post_status' => $assoc_args['post_status'] ?? 'publish',
                'post_title' => $coin['name'] ?? $whitepaper['name'],
                'post_type' => $assoc_args['post_type'] ?? 'document',
                'meta_input' => $meta_input,
            ];

            $post_id = wp_insert_post($post);

            // sideload media
            $cc = 0;

            if (isset($whitepaper['files']) and !empty($whitepaper['files'])) {
                foreach ($whitepaper['files'] as $file) {
                    if (!is_file($file))
                        continue;

                    $original = $file;

                    // make copy, good while debugging
                    if ($assoc_args['copy']) {
                        $copy = sys_get_temp_dir() . '/' . md5($file) . '.' . pathinfo($file, PATHINFO_EXTENSION);
                        copy($file, $copy);
                        $file = $copy;
                    }

                    $attach_id = Utils::handle_sideload($file, $post_id, pathinfo($original, PATHINFO_BASENAME));

                    // remove copy
                    if ($assoc_args['copy'] and is_file($copy))
                        unlink($copy);

                    if ($attach_id) {
                        carbon_set_post_meta($post_id, 'files[' . $cc . ']/file', $attach_id);
                        carbon_set_post_meta($post_id, 'files[' . $cc . ']/title', pathinfo($original)['basename']);
                    }
                    $cc++;
                }
                Document::post_save($post_id);
            }
            $progress->tick();
        }
        $progress->finish();
    }

    public function git($args = [], $assoc_args = [])
    {
        $process = null;

        switch ($args[0]) {
            default:
                if (!isset($assoc_args['repo'])) {
                    \WP_CLI::error('Missing git repository', true);
                } elseif (!strstr($assoc_args['repo'], '/')) {
                    \WP_CLI::error($assoc_args['repo'] . ' : Not a complete repository', true);
                }

                $github = 'https://github.com/' . $assoc_args['repo'] . '.git';
                $path = dirname(getcwd()) . DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR . explode('/', $assoc_args['repo'])[1];



                $cmd_args = ['git', 'clone', '--depth=1', $github, $path];
                $process = new Process($cmd_args);
                break;
        }

        // executes after the command finishes
        if ($process and $assoc_args['download']) {
            $process->run(function ($type, $buffer) {
                if (is_dir($path))
                    self::rmdir_tree($path);

                if (Process::ERR === $type) {
                    \WP_CLI::log($buffer);
                } else {
                    \WP_CLI::log($buffer);
                }
            });
        }

        if (is_dir($path))
            return $path;

        \WP_CLI::error('Problem cloning repository or creating directory.', true);
    }

    public function purge_posts()
    {
        $posts = get_posts([
            'numberposts' => -1,
            'post_type' => ['document', 'attachment'],
            'post_status' => ['any']
        ]);

        \WP_CLI::log('To Delete: ' . count($posts));

        if (!$this->is_dry()) {
            $progress = \WP_CLI\Utils\make_progress_bar('purge_posts()', count($posts));

            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
                $progress->tick();
            }

            $progress->finish();
        }
    }

    private function parse_folder($path, $assoc_args)
    {
        $data = [];

        $ignore = [
            '.git',
            'scam',
            'symbol',
            'index.md',
            'No-whitepaper.txt'
        ];

        foreach (new \DirectoryIterator($path) as $fi) {
            $tmp = [];

            if ($fi->isDot() or $fi->isLink() or in_array($fi->getBasename(), $ignore)) continue;

            if ($fi->isDir()) {
                $tmp['name'] = $fi->getFilename();

                $tmp['files'] = [];

                $tmp['files_string'] = null;

                foreach (new \DirectoryIterator($fi->getPathname()) as $fi_coin) {
                    if ($fi_coin->isDot() or $fi_coin->isLink() or in_array($fi_coin->getBasename(), $ignore)) continue;

                    $tmp['files'][] = $fi_coin->getPathname();

                    $tmp['files_string'] .= $fi_coin->getFilename() . ', ';
                }

                $tmp['file_count'] = count($tmp['files']);

                if (!empty($tmp['files']))
                    $tmp['files_string'] = rtrim($tmp['files_string'], ', ');
            }

            if (!empty($tmp))
                $data[strtolower($tmp['name'])] = $tmp;
        }

        $formatter = new \WP_CLI\Formatter($assoc_args, array(
            'name',
            'file_count',
            'files_string',
        ));

        // ksort($data);
        $formatter->display_items($data);

        // counts
        \WP_CLI::log('Total whitepapers: ' . count($data));

        return $data;
    }

    public function coingecko($args = [], $assoc_args = [])
    {
        $client = new CoinGeckoClient();
        $key = 'coingecko_' . $args[0] . '_' . md5(json_encode($assoc_args));
        $expire = 300;

        $data = filter_var($assoc_args['cache'], FILTER_VALIDATE_BOOLEAN) ? get_transient($key) : false;

        if ($data)
            \WP_CLI::notice('Cache found.' . count($data));

        switch ($args[0]) {
            case 'ping':
                if (!$data) {
                    $data = [];
                    $data[] = $client->ping();
                }

                $format = [
                    'gecko_says'
                ];

                $expire = 10;
                break;

            case 'markets':
                $coingecko_args = [
                    'ids' => $assoc_args['coingecko_ids'],
                    'per_page' => $assoc_args['limit'],
                ];

                if (!$data) {
                    $tmp = $client->coins()->getMarkets('usd', $coingecko_args);
                    $data = [];
                    foreach ($tmp as $coin) {
                        $data[$coin['id']] = $coin;
                    }
                }

                $format = [
                    'id',
                    'symbol',
                    'name',
                    'current_price',
                    'market_cap_rank',
                ];
                break;
        }

        if (!$data)
            set_transient($key, $data, $expire);

        $formatter = new \WP_CLI\Formatter($assoc_args, $format);
        $formatter->display_items($data);

        \WP_CLI::log('Total: ' . count($data));

        return $data;
    }

    public static function coingecko_ids($data, $offset = 0, $limit = 100)
    {
        if (!is_array($data) and is_string($data))
            return $data;

        $ids = array_keys($data);

        if ($limit != -1)
            $ids = array_slice($ids, $offset, $limit);

        $ids = strtolower(implode(',', $ids));

        \WP_CLI::debug($ids);

        return $ids;
    }

    public static function get_import_path($dir = null)
    {
        $path = dirname(getcwd()) . DIRECTORY_SEPARATOR . 'import' . DIRECTORY_SEPARATOR;
        $path .= $dir;

        return $path;
    }

    public static function rmdir_tree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::rmdir_tree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
