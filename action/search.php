<?php
/**
 * Script to search in uploaded pdf documents
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 * @author @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

if(!defined('DOKU_INC')) die();

class action_plugin_docsearch_search extends DokuWiki_Action_Plugin {

    private $backupConfig;

    function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'AFTER', $this, 'display', array());
    }

	function display(Doku_Event &$event, $param) {
		global $ACT;
		global $conf;
		global $QUERY;
		global $lang;

		// only work with search
		if ($ACT !== 'search') return;

		// backup the config array
		$this->backupConfig = $conf;

		// change index/pages folder for DocSearch
		$conf['indexdir'] = $conf['savedir'] . '/docsearch/index';
		$conf['datadir'] = $conf['savedir'] . '/docsearch/pages';

		$data = ft_pageSearch($QUERY, $regex);

		if (empty($data)) {
            $conf = $this->backupConfig;
			return;
		}

        $searchResults = array();
        $runs = 0;
        foreach ($data as $id => $hits) {
            $searchResults[$id] = array();
            $searchResults[$id]['hits'] = $hits;
            if ($runs < $this->getConf('showSnippets')) {
                $searchResults[$id]['snippet'] = ft_snippet($id, $regex);
            }
        }

        $conf = $this->backupConfig;

		echo '<h2>'.hsc($this->getLang('title')).'</h2>';
		echo '<div class="search_result">';

		$num = 0;
		foreach ($searchResults as $id => $data) {
            if ($this->getConf('showUsage') !== 0) {
                $usages = ft_mediause($id, $this->getConf('showUsage'));
            } else {
                $usages = array();
            }

			echo '<a href="'.ml($id).'" title="" class="wikilink1">'.hsc($id).'</a>:';
			echo '<span class="search_cnt">'.hsc($data['hits']).' '.hsc($lang['hits']).'</span>';
            if (!empty($usages)) {
                echo '<span class="usage">';
                echo ', Usage: ';
                foreach ($usages as $usage) {
                    echo html_wikilink($usage);
                }
                echo '</span>';
            }

			if (isset($data['snippet'])) {
				echo '<div class="search_snippet">';
				echo $data['snippet'];
				echo '</div>';
			}

			echo '<br />';
			$num ++;
		}

		echo '</div>';
	}
}
