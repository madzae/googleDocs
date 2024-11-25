<?php
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $client_id = 'yourclientid';
    $client_secret = 'yoursecretid';
    $redirect_uri = 'realToken.php';

    $url = 'https://oauth2.googleapis.com/token';
    $data = [
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code',
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $response_data = json_decode($response, true);

    if (isset($response_data['error'])) {
        echo 'Error: ' . $response_data['error'];
    } else {
        $access_token = $response_data['access_token'];
        $refresh_token = isset($response_data['refresh_token']) ? $response_data['refresh_token'] : 'No refresh token';

        echo 'Access Token: ' . $access_token . '<br>';
        echo "<br />";
        echo 'Refresh Token: ' . $refresh_token . '<br>';
    }
} else {
    echo 'No authorization code found.';
}


echo "<pre>";
var_dump($response_data);
echo "</pre>";


?>
