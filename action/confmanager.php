<?php

class action_plugin_docsearch_confmanager extends DokuWiki_Action_Plugin {

    public function register(&$controller) {
        $controller->register_hook('CONFMANAGER_CONFIGFILES_REGISTER', 'BEFORE',  $this, 'addConfigFile', array());
    }

    public function addConfigFile(Doku_Event $event, $params) {
        if (class_exists('ConfigManagerTwoLine')) {
            $config = new ConfigManagerTwoLine($this->getLang('confmanager title'), $this->getDescription(), DOKU_PLUGIN . 'docsearch/conf/converter.php');
            $event->data[] = $config;
        }
    }

    private function getDescription() {
        $fn = $this->localFN('confmanager_description');
        if (!@file_exists($fn)) {
            return '';
        }
        $content = file_get_contents($fn);
        return $content;
    }

}
