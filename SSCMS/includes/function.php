<?php
function sendSMS($number, $message) {
    $apiKey = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC';

    // Convert 09xxxxxxxxx to 639xxxxxxxxx
    if (preg_match('/^09[0-9]{9}$/', $number)) {
        $number = '63' . substr($number, 1);
    }

    // Validate final number format
    if (!preg_match('/^63[0-9]{9}$/', $number)) {
        return 'Error: Invalid phone number format';
    }

    $url = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey' => $apiKey,
        'number' => $number,
        'message' => $message,
        'sendername' => $senderName
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = 'Error: ' . curl_error($ch);
        error_log("[SSCMS SMS] cURL Error: " . curl_error($ch));
        curl_close($ch);
        return $error;
    }

    curl_close($ch);
    return $response;
}
?>