<?php

require_once 'common.php';

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;


class action_plugin_ntid extends ActionPlugin
{

    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_loginredirect');
        $controller->register_hook('FORM_LOGIN_OUTPUT', 'BEFORE', $this, 'handle_loginformoutput');
    }

    function handle_loginredirect(Event $event, $param)
    {
        global $ID;

        $url = $this->getConf('ntid_url');
        $url = rtrim($url, '/');
        $url = $url . '/api/external';

        if ($event->data === 'login' && $this->getConf('redirect_to_ntid')) {
            $url = $url . '/login?client_id=' . $this->getConf('ntid_client_id');

            header('Location: ' . $url);
            exit();
        }

        if ($event->data === 'ntid_callback') {
            $session_id = $_GET['session_id'];

            $response = ntidRequest([
                'ntid_url' => $this->getConf('ntid_url'),
                'ntid_client_id' => $this->getConf('ntid_client_id'),
                'ntid_client_secret' => $this->getConf('ntid_client_secret')
            ], 'getSecret', [
                'session_id' => $session_id
            ]);

            if ($response === null) {
                msg('Failed to get session secret', -1);
                return false;
            }

            setcookie('ntid_session_secret', $response['session_secret'], time() + 2592000, '/');
            if (isset($_GET['redirect'])) {
                header('Location: ' . $_GET['redirect']);
            } else {
                header('Location: /');
            }
            exit();
        }
    }

    function handle_loginformoutput(Event $event, $param)
    {
        global $ID;

        $url = $this->getConf('ntid_url');
        $url = rtrim($url, '/');
        $url = $url . '/api/external';
        $url = $url . '/login?client_id=' . $this->getConf('ntid_client_id');

        $html = '<br><a href="' . $url . '">Login with NeTask ID</a><br>';

        $form = $event->data;

        $pos = $form->findPositionByAttribute('type', 'submit');
        if (!$pos)
            return;
        $form->addHTML($html);
    }
}