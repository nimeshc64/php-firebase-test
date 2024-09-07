<?php

function getPrivateKey(){
    //get private kety content from JSON file
    return json_decode(file_get_contents('./key.json'), true);
}

function getBearerToken() {
    // Load the service account JSON file
    $serviceAccount = getPrivateKey();

    // Private key from the service account
    $privateKey = $serviceAccount['private_key'];

    // Get current time and expiration time
    $now = time();
    $expirationTime = $now + 3600; // 1 hour later

    // Create JWT header
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
    ];

    // Create JWT payload
    $payload = [
        'iss' => $serviceAccount['client_email'], // issuer (service account email)
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging', // scope
        'aud' => 'https://oauth2.googleapis.com/token', // audience
        'iat' => $now, // issued at (current time)
        'exp' => $expirationTime, // expiration (1 hour later)
    ];

    // Encode header and payload
    $encodedHeader = base64UrlEncode(json_encode($header));
    $encodedPayload = base64UrlEncode(json_encode($payload));

    // Create the unsigned token (header.payload)
    $unsignedToken = $encodedHeader . '.' . $encodedPayload;

    // Sign the token using the private key (RS256)
    openssl_sign($unsignedToken, $signature, $privateKey, 'sha256WithRSAEncryption');

    // Encode the signature
    $encodedSignature = base64UrlEncode($signature);

    // Create the signed JWT (header.payload.signature)
    $jwt = $unsignedToken . '.' . $encodedSignature;

    // Output the generated JWT
    echo "Generated JWT: " . $jwt . PHP_EOL;

    // Now use this JWT to get the Bearer token with a POST request

    $url = $serviceAccount['token_uri'];
    $data = [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'ignore_errors' => true, // Ignore SSL errors
        ],
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    // Parse the response
    $responseData = json_decode($response, true);

    // Output the Bearer Token
    if (isset($responseData['access_token'])) {
        echo "Bearer Token: " . $responseData['access_token'] . PHP_EOL;
    } else {
        echo "Error: " . $response . PHP_EOL;
    }

    return $responseData['access_token'];

}

function sendNotification() {

    $accessToken = getBearerToken();

    $url = "https://fcm.googleapis.com/v1/projects/<PROJECT>/messages:send";
    $IdentifireToken = "";
    
    $fields = [
        "message" => [
            "notification" => [
                "title" => 'title',
                "body" => 'body',
                "image" => "firebase-logo.png"
            ],
            "data" => [
                "title" => 'title',
                "body" => 'body',
                "sound" => 'mysound',
                "icon" => "logo_hor_black"
            ],
            "token" => $IdentifireToken,
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


sendNotification();