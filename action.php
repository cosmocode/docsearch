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
		global $QUERY;

		// only work with search
		if ($ACT != 'search') return;

		// backup the config array
		$cp = $conf;

		// set the index directory to the docsearch index
		$conf['indexdir'] = $conf['savedir'] . '/docsearch/index';

		// change the datadir to the docsearch data dir
		$conf['datadir'] = $conf['savedir'] . '/docsearch/pages';

		// search the documents
		//search($res,$conf['datadir'],'search_fulltext',array('query'=>$ID));
		$data = ft_pageSearch($QUERY,$regex);

		// restore the config
		$conf = $cp;

		// if there no results in the documents we have nothing else to do
		if (empty($data)) {
			return;
		}

		echo '<h2>'.hsc($this->getLang('title')).'</h2>';
		echo '<div class="search_result">';

		// printout the results
		$num = 0;
		foreach ($data as $id => $hits) {
			echo '<a href="'.ml($id).'" title="" class="wikilink1">'.hsc($id).'</a>:';
			echo '<span class="search_cnt">'.hsc($hits).' '.hsc($this->getLang('hits')).'</span>';
			if ($num < 15) {
				echo '<div class="search_snippet">';
				echo ft_snippet($id,$regex);
				echo '</div>';
			}
			echo '<br />';
			$num ++;
		}

		echo '</div>';
	}

	function _resultSearch($a,$b) {
		if ($a['count'] == $b['count']) return 0;
		return ($a['count'] > $b['count']) ? -1 : 1;
	}
}

?>
