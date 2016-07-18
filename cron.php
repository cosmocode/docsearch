#!/usr/bin/php
<?php

// ensure that the request comes from the cli
if('cli' != php_sapi_name()) die();

error_reporting(E_ALL & ~E_NOTICE);

// allow setting an animal as first commandline parameter for use in farming
if(isset($argv[1])) {
    $_SERVER['animal'] = $argv[1];
}

if(!defined('DOKU_INC')) define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
require_once(DOKU_INC . 'inc/init.php');

/**
 * Walks recursive through a directory and reports all files to the inspect function
 *
 * @param string $dir the folder to walk through
 */
function walk($dir) {

    if(!is_readable($dir)) return;
    if(!is_dir($dir)) return;

    $handle = opendir($dir);
    if(!$handle) return;

    while(false !== ($file = readdir($handle))) {
        if($file == '.' || $file == '..') continue;

        $file = "$dir/$file";
        if(is_file($file)) {
            inspect($file);
            continue;
        }

        if(is_dir($file)) {
            walk($file);
            continue;
        }
    }
}

/**
 * Try to convert a given file to text and add it to the DocSearch index
 *
 * @var string $file File to inspect
 */
function inspect($file) {
    global $input;
    global $output;
    global $conf;
    global $ID;

    // dont handle non pdf files
    $extension = array();

    preg_match('/.([^\.]*)$/', $file, $extension);

    // no file extension -> woops maybe a TODO ?
    if(!isset($extension[1])) {
        return;
    }
    $extension = $extension[1];

    // unknown extension -> return
    if(!in_array($extension, $conf['docsearchext'])) {
        return;
    }

    // prepare folder and paths
    $inputPath = preg_quote($input, '/');
    $abstract = preg_replace('/^' . $inputPath . '/', '', $file, 1);
    $out = $output . $abstract . '.txt';
    $id = str_replace('/', ':', $abstract);
    io_mkdir_p(dirname($out));

    #echo "indexing: $id\n";

    // prepare command
    $cmd = $conf['docsearch'][$extension];
    $cmd = str_replace('%in%', escapeshellarg($file), $cmd);
    $cmd = str_replace('%out%', escapeshellarg($out), $cmd);

    // Run command
    $exitCode = 0;
    system($cmd, $exitCode);
    if($exitCode != 0) fwrite(STDERR, "Command failed: $cmd\n");

    // check file encoding for bad utf8 characters - if a bad thing is found convert assuming latin1 as source encoding
    $text = file_get_contents($out);
    if(!utf8_check($text)) {
        $text = utf8_encode($text);
        file_put_contents($out, $text);
    }

    // add the page to the index
    $ID = cleanID($id);
    idx_addPage($ID);
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

/******************************************************************************
 ********************************** Script ************************************
 ******************************************************************************/

$ID = '';

// load the plugin converter settings.

$converter_conf = DOKU_INC . 'lib/plugins/docsearch/conf/converter.php';
$conf['docsearch'] = confToHash($converter_conf);

// no converters == no work ;-)
if(empty($conf['docsearch'])) {
    fwrite(STDERR, "No converters found in $converter_conf\n");
    exit(1);
}

$conf['docsearchext'] = array_keys($conf['docsearch']);

// build the data pathes

// the base "data" dir
$base = '';

if($conf['savedir'][0] === '.') {
    $base = DOKU_INC;
}
$base .= $conf['savedir'] . '/';

// cleanup old data
rmdirr($base . 'docsearch');

// build the important pathes
$input = $conf['mediadir'];
$output = $base . 'docsearch/pages';
$index = $base . 'docsearch/index';
$cache = $base . 'docsearch/cache';
$meta = $base . 'docsearch/meta';
$locks = $base . 'docsearch/locks';

// create output dir
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

// walk through the media dir and search for pdf files
walk($input);
