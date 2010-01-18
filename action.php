<?php
/**
 * Script to search in uploaded pdf documents
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_DATA')) define('DOKU_DATA',DOKU_INC.'data/');

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC . 'inc/fulltext.php');


class action_plugin_docsearch extends DokuWiki_Action_Plugin {

	var $data = array();

    /**
	* return some info
	*/
    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
	}

    /**
	* Register to the content display event to place the results under it.
	*/
    function register(&$controller) {
        $controller->register_hook('TPL_CONTENT_DISPLAY',   'AFTER', $this, 'display', array());
    }

	/**
	 * do the search and displays the result
	 */
	function display(&$event, $param) {
		global $ACT;
		global $ID;
		global $conf;

		// only work with search
		if ($ACT != 'search') return;

		// backup the config array
		$cp = $conf;

		// set the index directory to the docsearch index
		$conf['index'] = $conf['savedir'] . '/docsearch/index';

		// change the datadir to the docsearch data dir
		$conf['datadir'] = $conf['savedir'] . '/docsearch/pages';

		// result array
		$res = array();

		// search the documents
		search($res,$conf['datadir'],'search_fulltext',array('query'=>$ID));

		// restore the config
		$conf = $cp;

		echo '<h2>'.hsc($this->getLang('title')).'</h2>';
		echo '<div class="search_result">';

		usort($res, array($this,'_resultSearch'));

		// printout the results
		foreach ($res as $r) {
			echo '<a href="'.ml($r['id']).'" title="" class="wikilink1">'.hsc($r['id']).'</a>:';
			echo '<span class="search_cnt">'.hsc($r['count']).' '.hsc($this->getLang('hits')).'</span>';
			echo '<div class="search_snippet">';
			echo $r['snippet'];
			echo '</div>';
			echo '<br />';
		}
		echo '</div>';
	}

	function _resultSearch($a,$b) {
		if ($a['count'] == $b['count']) return 0;
		return ($a['count'] > $b['count']) ? -1 : 1;
	}
}

?>
