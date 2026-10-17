<?php

//api key put here
$apiKey = "AQ.Ab8RN6JXcOcdJMvgelVSUfQwLgXP_AQFkduHNGRqQ2iasFZPgA";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . $apiKey;

$imagePath = "black_wallet.jpg";
$imageData = base64_encode(file_get_contents($imagePath));

$data = [
    "contents" => [
        [
            "parts" => [
                [
                    // the instruction we give to ai
                    "text" => "Analyze this lost and found item. 

                                Return ONLY valid JSON with exactly these fields: 
                                object, color, category, description.

                                The category MUST be exactly one of:
                                electronics, book_stationery, clothing, keys_cards, accessories, personal_items, others.

                                Use book_stationery for books, textbooks, notebooks, pens, pencils, rulers, and other stationery items.

                                Do not add any extra text or markdown."
                
                    ],
                [
                    "inline_data" => [
                        "mime_type" => "image/jpeg",
                        "data" => $imageData
                    ]
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

//转换器 php 转 json, 给ai看
$jsonData = json_encode($data);

//http communication for gemini api (连接网站)
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);  //post method to transfer data
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);  //the data i want to send is jsonData
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json" //json type data sent
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //give the response to php and save into php variable

//ai feedback based on my instruction (execute)
$response = curl_exec($ch);

if ($response === false) {
    die("cURL Error: " . curl_error($ch));
}

curl_close($ch);

$responseData = json_decode($response, true);

// Check whether Gemini returned an error
if (isset($responseData["error"])) {
    echo "<pre>";
    print_r($responseData["error"]);
    echo "</pre>";
    exit;
}

// Check whether candidates exists
if (!isset($responseData["candidates"][0]["content"]["parts"][0]["text"])) {
    echo "<pre>";
    echo "Unexpected API response:";
    print_r($responseData);
    echo "</pre>";
    exit;
}

// Get AI's JSON text
$aiText = $responseData["candidates"][0]["content"]["parts"][0]["text"];

// Convert AI JSON text into PHP array
$aiResult = json_decode($aiText, true);

if ($aiResult === null) {
    echo "<pre>";
    echo "AI returned invalid JSON:";
    echo htmlspecialchars($aiText);
    echo "</pre>";
    exit;
}

// Display result
echo "<pre>";
print_r($aiResult);
echo "</pre>";

echo "Object: " . $aiResult["object"] . "<br>";
echo "Color: " . $aiResult["color"] . "<br>";
echo "Category: " . $aiResult["category"] . "<br>";
echo "Description: " . $aiResult["description"] . "<br>";
?>