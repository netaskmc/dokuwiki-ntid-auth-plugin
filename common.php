<?php

function ntidSendWebPostRequest($url, $data)
{
    // no curl, no problem
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ]);

    $body = file_get_contents($url, false, $context);

    if ($body === false) {
        return null;
    }
    // check status code
    $headers = $http_response_header;
    $status = explode(' ', $headers[0]);
    if ($status[1] !== '200') {
        return null;
    }

    return $body;
}

function ntidRequest($conf, $type, $body)
{
    $ntid_url = $conf['ntid_url'];
    $ntid_url = rtrim($ntid_url, '/');
    $ntid_url = $ntid_url . '/api/external/auth';

    try {
        $response = ntidSendWebPostRequest($ntid_url, json_encode([
            'action' => $type,
            'client_id' => $conf['ntid_client_id'],
            'client_secret' => $conf['ntid_client_secret'],
            ...$body
        ]));

        if ($response === null) {
            return null;
        }

        return json_decode($response, true);
    } catch (Exception $e) {
        return null;
    }
}