<?php

// ensure that the request comes from the cli
if ($_SERVER['REMOTE_ADDR']) {
	die();
}

error_reporting(E_ALL);
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');

require_once(DOKU_INC . 'inc/init.php');
require_once(DOKU_INC . 'inc/common.php');
require_once(DOKU_INC . 'inc/indexer.php');
require_once(DOKU_INC . 'inc/io.php');
require_once(DOKU_INC . 'inc/confutils.php');
/**
 * Walks recrusive throu a directory and reports all files to the inspect function
 *
 * @param $dir 	the directory to walk throu
 */
function walk($dir) {

	// dir not readable
	if (!is_readable($dir)) return;

	// no dictionary
	if (!is_dir($dir)) return;

	$handle = opendir($dir);

	// cannot open
	if (!$handle) return;

	while (false !== ($file = readdir($handle))) {
		if ($file == '.' || $file == '..') continue; // skip
		$file = $dir . '/' . $file;
		if (is_file($file)) {
			inspect($file);
		} elseif (is_dir($file)) {
			walk($file);
		}
	}
}

/**
 * inspects a file path
 *
 * if it has a pdf file extension it trys to convert it to text in data/docsearch
 */
function inspect($file) {

	global $input;
	global $output;
	global $conf;
	global $ID;

	// dont handle non pdf files
	$ext = array();

	preg_match('/.([^\.]*)$/',$file,$ext);

	// no file extension -> woops maybe a TODO ?
	if (!isset($ext[1])) {
		return;
	}

	// unknowen extension -> return
	if (!in_array($ext[1],$conf['docsearchext'])) {
		return;
	}


	// prepare folder and pathes
	$abstract = preg_replace( '/'.str_replace('/','\\/',preg_quote($input)).'/', '', $file, 1);
	$out      = $output . $abstract . '.txt';
	$out      = $out;
	$id       = str_replace('/',':',$abstract);
	io_mkdir_p(dirname($out));

	// prepare command
	$cmd = $conf['docsearch'][$ext[1]];
	$cmd = str_replace('%in%',escapeshellarg($file),$cmd);
	$cmd = str_replace('%out%',escapeshellarg($out),$cmd);


	system( $cmd );

	// add the page to the index
	$ID = $id;
	idx_addPage($ID);

}

/**
 * Delete a file, or a folder and its contents (recursive algorithm)
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.0.3
 * @link        http://aidanlister.com/repos/v/function.rmdirr.php
 * @param       string   $dirname    Directory to delete
 * @return      bool     Returns TRUE on success, FALSE on failure
 */
function rmdirr($dirname)
{
	// Sanity check
	if (!file_exists($dirname)) {
		return false;
	}

	// Simple delete for a file
	if (is_file($dirname) || is_link($dirname)) {
		return unlink($dirname);
	}

	// Loop through the folder
	$dir = dir($dirname);
	while (false !== $entry = $dir->read()) {
		// Skip pointers
		if ($entry == '.' || $entry == '..') {
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

// load the dokuwiki config files
$conf = array();
foreach (array('conf/dokuwiki.php', 'conf/local.php', 'conf/local.protected.php') as $inc ) {
	if (is_file(DOKU_INC . $inc)) include DOKU_INC . $inc;
}

// load the plugin converter settings.

$conf['docsearch'] = confToHash(DOKU_INC.'lib/plugins/docsearch/conf/converter.php');

// no converters == no work ;-)
if (empty($conf['docsearch'])) {
	die();
}

$conf['docsearchext'] = array_keys($conf['docsearch']);

// build the data pathes

// the base "data" dir
$base = '';
if ($conf['savedir'][0] == '.') {
	$base = DOKU_INC;
}
$base .= $conf['savedir'] . '/';

// cleanup old data
rmdirr($base.'docsearch');

// build the important pathes
$input  = $base . ((isset($conf['mediadir'])) ? $conf['mediadir'] : 'media' );
$output = $base . 'docsearch/pages';
$index  = $base . 'docsearch/index';

// create output dir
io_mkdir_p($output);
io_mkdir_p($index);

// change the datadir and the indexdir
$conf['datadir']  = $output;
$conf['indexdir'] = $index;

// walk throu the media dir and search for pdf files
walk($input);

?>
