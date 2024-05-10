<?php

require_once 'common.php';

use dokuwiki\Extension\AuthPlugin;

class auth_plugin_ntid extends AuthPlugin
{

    public function __construct()
    {
        parent::__construct(); // always call parent constructor
        $this->cando['external'] = true;
        $this->cando['logoff'] = true;
        $this->cando['getGroups'] = true;


        $this->success = true;
    }

    private function ntidRequest($type, $body)
    {
        return ntidRequest([
            'ntid_url' => $this->getConf('ntid_url'),
            'ntid_client_id' => $this->getConf('ntid_client_id'),
            'ntid_client_secret' => $this->getConf('ntid_client_secret')
        ], $type, $body);
    }

    public function trustExternal($user, $pass, $sticky = false)
    {
        global $USERINFO;
        // external auth only, we skip the user and pass
        // after successful authentication, we have set a session cookie
        // just validate it against the server
        // no jwt, no oauth, we goin' raw

        // but seriously, this has benefits over jwt and oauth, such as
        // synchronizing user data with the main server, like
        // user roles, banned status, etc.

        // besides, this code runs on the same server as the main server
        // so we don't have to worry about delays or anything

        // FIRST, check if the session secret is set
        if (!isset($_COOKIE['ntid_session_secret'])) {
            msg("Invalid session", -1);
            auth_logoff();
            return false;
        }

        $session_secret = $_COOKIE['ntid_session_secret'];

        // send a request to the ntid instance { "session_secret": "..." }
        $response = $this->ntidRequest('validate', [
            'session_secret' => $session_secret
        ]);

        if ($response === null) {
            msg("Invalid session", -1);
            auth_logoff();
            return null;
        }

        $response = $response['user'];

        // it should contain the user data
        /*
        {
            "id": string,
            "name": string | null, // username (handle) from discord initially, should be always set and never change (gets updated on discord side, but not here)
            "mcUsername": string | null,
            "mcUuid": string | null,
            "email": string | null,
            "image": string | null,
            "verified": boolean,

            "isAdmin": boolean,
            "isVip": boolean,
            "supporterUntil": number (millis) | null,
            "bans": [...]
        }
        */
        // if the user is not verified, return null
        if (!$response['verified']) {
            msg("Not verified on NeTask!", -1);
            return null;
        }
        // we return null because we want dokkuwiki to fall back to the default auth flow

        // if the user is banned, return null
        if (isset($response['bans']) && $response['bans'] !== null && count($response['bans']) > 0) {
            msg("You are banned on NeTask!", -1);
            return null;
        }
        // if the user does not have a username somehow, return null
        if ($response['name'] === null) {
            msg("You have no username on NeTask!", -1);
            return null;
        }

        // if the user is verified, not banned, and has a username
        // we set the user data
        $USERINFO['name'] = $response['mcUsername'];
        $USERINFO['mail'] = $response['email'];
        $USERINFO['grps'] = ["user"];
        if ($response['isAdmin']) {
            $USERINFO['grps'][] = 'admin';
        }
        if ($response['isVip']) {
            $USERINFO['grps'][] = 'vip';
        }
        // supporterUntil is a unix millis timestamp
        if ($response['supporterUntil'] !== null && $response['supporterUntil'] > time() * 1000) {
            $USERINFO['grps'][] = 'supporter';
        }

        // we set the user data
        $_SERVER['REMOTE_USER'] = $response['name'];
        $_SERVER[DOKU_COOKIE]['auth']['user'] = $response['name'];
        $_SERVER[DOKU_COOKIE]['auth']['info'] = $USERINFO;

        return true;
    }

    public function getUserData($user, $requireGroups = true)
    {
        $response = $this->ntidRequest('getUserDataByName', [
            'name' => $user
        ]);

        if ($response === null) {
            return null;
        }

        $response = $response['user'];

        $data = [
            'name' => $response['mcUsername'],
            'mail' => $response['email'],
            'grps' => ["user"],
        ];

        if ($response['isAdmin']) {
            $data['grps'][] = 'admin';
        }
        if ($response['isVip']) {
            $data['grps'][] = 'vip';
        }
        if ($response['supporterUntil'] !== null && $response['supporterUntil'] > time() * 1000) {
            $data['grps'][] = 'supporter';
        }
        if (isset($response['bans']) && $response['bans'] !== null && count($response['bans']) > 0) {
            return null;
        }

        return $data;
    }

    public function logOff()
    {
        if (!isset($_COOKIE['ntid_session_secret'])) {
            return true;
        }

        $response = $this->ntidRequest('invalidate', [
            'session_secret' => $_COOKIE['ntid_session_secret']
        ]);
        if ($response === null) {
            return null;
        }

        // remove the session secret cookie
        setcookie('ntid_session_secret', '', time() - 3600, '/');

        return true;
    }

    public function retrieveGroups($start = 0, $limit = 0)
    {
        $groups = [
            'user',
            'admin',
            'vip',
            'supporter'
        ];
        // let's respect the start and limit
        if ($start > 0 || $limit > 0) {
            if ($limit === 0) {
                $limit = count($groups);
            }
            $groups = array_slice($groups, $start, $limit);
        }
        return $groups;
    }
}