<?php
// test_gpt.php

$apiKey = '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'; // Replace with your OpenAI API key

// The question to send
$question = "What is the size of Israel?";

// Prepare the request payload
$data = [
    "model" => "gpt-4o",
    "messages" => [
        ["role" => "system", "content" => "Answer under 10 words"],
        ["role" => "user", "content" => "What is the size of Israel?"]
    ],
    "temperature" => 0.7,
    "max_tokens" => 10
];

// Send request to OpenAI
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "cURL error: " . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Debug output
echo "<pre>Raw API Response:\n" . htmlspecialchars($response) . "\n</pre>";

$result = json_decode($response, true);
if (isset($result['choices'][0]['message']['content'])) {
    $answer = $result['choices'][0]['message']['content'];
} elseif (isset($result['error']['message'])) {
    $answer = "API Error: " . $result['error']['message'];
} else {
    $answer = "No valid response from API.";
}

echo "<h2>Question:</h2><p>$question</p>";
echo "<h2>Answer from ChatGPT:</h2><p>$answer</p>";
?>
