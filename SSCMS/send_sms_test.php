<?php
function sendSMS($number, $message) {
    $apiKey = '2840c5de6cdfbe118d100ad33fdc179b';
    $senderName = 'ICCBICLINIC'; // Must be registered and approved in your Semaphore account

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
        echo 'Error: ' . curl_error($ch);
    }

    curl_close($ch);
    return $response;
}

// Test
$testNumber = '09383072884'; // Must be in international format without plus sign
$testMessage = 'Good day! This is ICCBI CLINIC. We would like to inform you that your child, [STUDENT_NAME], visited the school clinic today at [TIME] for a health concern. Rest assured they were properly attended to.';

$response = sendSMS($testNumber, $testMessage);
echo $response;
?>
