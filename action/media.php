<?php
/**
 * Add or remove documents from DokuWiki index
 *
 * @author Giuseppe Di Terlizzi <giuseppe.diterlizzi@gmail.com>
 * @author @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

if(!defined('DOKU_INC')) die();

class action_plugin_docsearch_media extends DokuWiki_Action_Plugin {

    private $backupConfig;

    public function register(Doku_Event_Handler $controller) {

        if ($this->getConf('autoIndex')) {
            $controller->register_hook('MEDIA_UPLOAD_FINISH', 'AFTER', $this, 'handle_media_event', 'upload');
            $controller->register_hook('MEDIA_DELETE_FILE',   'AFTER', $this, 'handle_media_event', 'delete');
        }

    }


    public function handle_media_event(Doku_Event &$event, $param) {

        global $ACT;
        global $conf;

        // load the plugin converter settings.
        $converter_conf = DOKU_INC . 'lib/plugins/docsearch/conf/converter.php';
        $conf['docsearch'] = confToHash($converter_conf);

        // no converters == no work ;-)
        if(empty($conf['docsearch'])) {
            return;
        }

        $conf['docsearchext'] = array_keys($conf['docsearch']);

        // build the data pathes

        // the base "data" dir
        $base = '';

        if($conf['savedir'][0] === '.') {
            $base = DOKU_INC;
        }

        $base .= $conf['savedir'] . '/';

        // build the important pathes
        $input  = $conf['mediadir'];
        $output = $base . 'docsearch/pages';
        $index  = $base . 'docsearch/index';
        $cache  = $base . 'docsearch/cache';
        $meta   = $base . 'docsearch/meta';
        $locks  = $base . 'docsearch/locks';

        // create output dir
        io_mkdir_p($output);
        io_mkdir_p($index);
        io_mkdir_p($cache);
        io_mkdir_p($meta);
        io_mkdir_p($locks);

        // backup original DokuWiki config
        $this->backupConfig = $conf;

        // change the data folders
        $conf['datadir']  = $output;
        $conf['indexdir'] = $index;
        $conf['cachedir'] = $cache;
        $conf['metadir']  = $meta;
        $conf['lockdir']  = $locks;

        // dbglog("Data: " . print_r($event->data, true));
        // dbglog("Param: $param");

        if ($param == 'delete') {

            $page = $event->data['id'];

            $Indexer = idx_get_indexer();
            $result = $Indexer->deletePage($page);

            // remove meta file and converted file
            @unlink(metaFN($page,'.indexed'));
            @unlink(metaFN($page,'.meta'));
            @unlink(wikiFN($page));

        }

        if ($param == 'upload') {

            $path_parts = pathinfo($event->data[1]);
            $extension  = $path_parts['extension'];

            if (! $extension) {
                return;
            }

            // unknown extension -> return
            if (!in_array($extension, $conf['docsearchext'])) {
                return;
            }

            // prepare folder and paths
            $file = $event->data[1];
            $id   = $event->data[2];
            $out  = $output . '/' . str_replace(':', '/', $id) . '.txt';

            io_mkdir_p(dirname($out));

            // dbglog("indexing: $id");
            // dbglog("output: $out");

            // prepare command
            $cmd = $conf['docsearch'][$extension];
            $cmd = str_replace('%in%', escapeshellarg($file), $cmd);
            $cmd = str_replace('%out%', escapeshellarg($out), $cmd);

            // dbglog("CMD: $cmd");

            // Run command
            $exitCode = 0;
            system($cmd, $exitCode);

            if ($exitCode != 0) {
                dbglog("Command failed: $cmd");
            }

            // check file encoding for bad utf8 characters - if a bad thing is found convert assuming latin1 as source encoding
            $text = io_readFile($out);

            if (!utf8_check($text)) {
                $text = utf8_encode($text);
                io_saveFile($out, $text);
            }

            // add the page to the index
            $ID = cleanID($id);
            idx_addPage($ID);

        }

        // restore original config
        $conf = $this->backupConfig;

    }

}

