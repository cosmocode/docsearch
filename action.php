<?php
/**
 * Script to search in uploaded pdf documents
 *
 * @author Dominik Eckelmann <eckelmann@cosmocode.de>
 * @author @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_DATA')) define('DOKU_DATA',DOKU_INC.'data/');

require_once(DOKU_PLUGIN.'action.php');
require_once(DOKU_INC . 'inc/fulltext.php');


class action_plugin_docsearch extends DokuWiki_Action_Plugin {

	var $data = array();

    /**
	* Register to the content display event to place the results under it.
	*/
    function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('TPL_CONTENT_DISPLAY',   'AFTER', $this, 'display', array());
    }

	/**
	 * do the search and displays the result
	 */
	function display(Doku_Event &$event, $param) {
		global $ACT;
		global $conf;
		global $QUERY;
		global $lang;

		// only work with search
		if ($ACT !== 'search') return;

		// backup the config array
		$configBackup = $conf;

		// change index/pages folder for DocSearch
		$conf['indexdir'] = $conf['savedir'] . '/docsearch/index';
		$conf['datadir'] = $conf['savedir'] . '/docsearch/pages';

		$data = ft_pageSearch($QUERY, $regex);

		if (empty($data)) {
            $conf = $configBackup;
			return;
		}
		echo '<h2>'.hsc($this->getLang('title')).'</h2>';
		echo '<div class="search_result">';

		$num = 0;
		foreach ($data as $id => $hits) {
			echo '<a href="'.ml($id).'" title="" class="wikilink1">'.hsc($id).'</a>:';
			echo '<span class="search_cnt">'.hsc($hits).' '.hsc($lang['hits']).'</span>';
			if ($num < 15) {
				echo '<div class="search_snippet">';
				echo ft_snippet($id, $regex);
				echo '</div>';
			}
			echo '<br />';
			$num ++;
		}

		echo '</div>';

        $conf = $configBackup;
	}
}
