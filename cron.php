#!/usr/bin/php
<?php

// ensure that the request comes from the cli
if('cli' != php_sapi_name()) die();

error_reporting(E_ALL & ~E_NOTICE);

$incremental = true;
$verbose = false;
if(isset($argv[1])) {
	$index = 1;
	
	// allow setting incremental or complete rebuild mode
    if($argv[1] == 'incremental' || $argv[1] == 'rebuild') {
	    $incremental = $argv[1] == 'incremental';
	    $index++;
    }
    
    // allow setting an animal as first commandline parameter for use in farming
    if(isset($argv[$index])) {
        $_SERVER['animal'] = $argv[$index];
    }
}

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
require_once(DOKU_INC . 'inc/init.php');
require_once DOKU_INC . 'inc/cliopts.php';


/**
 * Remove pseudo wiki page if media file is removed or outdated
 *
 */
function cb_cleanup(&$data, $base, $file, $type, $lvl, $opts) {
    if($type == 'd') {
        return true;
    }

    $fullpath_page = $base . $file;
    $fullpath_media = $opts['mediadir'] . preg_replace('#\.txt$#','',$file);

    //remove if media file is removed or updated
    if(!file_exists($fullpath_media) || filemtime($fullpath_media) > filemtime($fullpath_page)) {
        if ($verbose) {
            echo 'cleaning up: '.$file."\n";
        }
        $data['cleaned']++;
        //remove old pseudo page file
        unlink($fullpath_page);

        //update search index for deleted page -> remove from index
        $id = pathID($file);
        idx_addPage($id);
    }

    return true;
}

/**
 * Try to convert a given file to text and add it to the DocSearch index
 *
 */
function cb_convert(&$data, $base, $file, $type, $lvl, $opts) {
    global $conf;

    if($type == 'd') {
        return true;
    }
    $input = $base;
    $output = $opts['output'];
    $file = $base . $file;

    $extension = array();

    preg_match('/.([^\.]*)$/', $file, $extension);

    // no file extension -> woops maybe a TODO ?
    if(!isset($extension[1])) {
        return true;
    }
    $extension = $extension[1];

    // unknown extension -> return
    if(!in_array($extension, $conf['docsearchext'])) {
        return true;
    }

    // prepare folder and paths
    $inputPath = preg_quote($input, '/');
    $abstract = preg_replace('/^' . $inputPath . '/', '', $file, 1);
    $out = $output . $abstract . '.txt';
    $id = str_replace('/', ':', $abstract);
    io_mkdir_p(dirname($out));

    if(file_exists($out) && filemtime($out) > filemtime($file)) {
        if ($verbose) {
            echo 'skipping: '.$file."\n";
        }
        $data['skipped']++;
        return true;
    }
    if ($verbose) {
        echo 'indexing: '.$file."\n";
    }
    $data['indexed']++;

    // prepare command
    $cmd = $conf['docsearch'][$extension];
    $cmd = str_replace('%in%', escapeshellarg($file), $cmd);
    $cmd = str_replace('%out%', escapeshellarg($out), $cmd);

    // Run command
    $exitCode = 0;
    system($cmd, $exitCode);
    if($exitCode != 0) {
        fwrite(STDERR, 'Command failed: '.$cmd."\n");
    }

    // check file encoding for bad utf8 characters - if a bad thing is found convert assuming latin1 as source encoding
    $text = file_get_contents($out);
    if(!utf8_check($text)) {
        $text = utf8_encode($text);
        file_put_contents($out, $text);
    }

    // add the page to the index
    $id = cleanID($id);
    idx_addPage($id);
    return true;
}

/**
 * Delete a file, or a folder and its contents (recursive algorithm)
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.3
 * @link        http://aidanlister.com/repos/v/function.rmdirr.php
 * @param       string $dirname    Directory to delete
 * @return      bool     Returns TRUE on success, FALSE on failure
 */
function rmdirr($dirname) {
    // Sanity check
    if(!file_exists($dirname)) {
        return false;
    }

    // Simple delete for a file
    if(is_file($dirname) || is_link($dirname)) {
        return unlink($dirname);
    }

    // Loop through the folder
    $dir = dir($dirname);
    while(false !== $entry = $dir->read()) {
        // Skip pointers
        if($entry == '.' || $entry == '..') {
            continue;
        }

        // Recurse
        rmdirr($dirname . DIRECTORY_SEPARATOR . $entry);
    }

    // Clean up
    $dir->close();
    return rmdir($dirname);
}



function main() {
    global $conf;
    global $incremental;
    $starttime = time();

    // load the plugin converter settings.
    $converter_conf = DOKU_INC . 'lib/plugins/docsearch/conf/converter.php';
    $conf['docsearch'] = confToHash($converter_conf);

    // no converters == no work ;-)
    if(empty($conf['docsearch'])) {
        fwrite(STDERR, 'No converters found in '.$converter_conf."\n");
        exit(1);
    }

    $conf['docsearchext'] = array_keys($conf['docsearch']);

    // the base "data" dir
    $base = '';
    if($conf['savedir'][0] === '.') {
        $base = DOKU_INC;
    }
    $base .= $conf['savedir'] . '/';

    // build the important pathes    
    $input = $conf['mediadir'];
    $docsearch__datapath = $base . 'docsearch';    
    $output = $docsearch__datapath . '/pages';
    $index = $docsearch__datapath . '/index';
    $cache = $docsearch__datapath . '/cache';
    $meta = $docsearch__datapath . '/meta';
    $locks = $docsearch__datapath . '/locks';

    // cleanup old data
    if(!$incremental) {
        rmdirr($docsearch__datapath);
    }
    
    // ensure output dirs exist
    io_mkdir_p($output);
    io_mkdir_p($index);
    io_mkdir_p($cache);
    io_mkdir_p($meta);
    io_mkdir_p($locks);

    // change the data folders
    $conf['datadir'] = $output;
    $conf['indexdir'] = $index;
    $conf['cachedir'] = $cache;
    $conf['metadir'] = $meta;
    $conf['lockdir'] = $locks;

    @set_time_limit(0);
    $data = array('cleaned' => 0, 'indexed' => 0, 'skipped' => 0);

    // cleanup old data (incremental)
    if($incremental) {
        search($data, $output, 'cb_cleanup', array('mediadir' => $input));
    }

    // walk through the media dir and search for files to convert
    search($data, $input, 'cb_convert', array('output' => $output));

    echo 'cleaned: '.$data['cleaned']."\n";
    echo 'skipped: '.$data['skipped']."\n";
    echo 'indexed: '.$data['indexed']."\n";
    echo 'duration: '.(time() - $starttime)."secs\n";
}

main();
