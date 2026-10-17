<?php
session_start();

$results = "";
$errorMessage = "";

// Restore previous results when returning to this page
$imageMatches = $_SESSION["image_matches_data"] ?? [];
$descriptionMatches = $_SESSION["description_matches_data"] ?? [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $hasImage = isset($_FILES["lost_image"]) &&
                $_FILES["lost_image"]["error"] === UPLOAD_ERR_OK;

    $description = trim($_POST["description"] ?? "");

    if (!$hasImage && $description === "") {
        $errorMessage = "Please upload an image or enter a description.";
    } else {

        $filePath = "";

        // Upload the image if provided
        if ($hasImage) {

            $uploadDir = __DIR__ . "/uploads/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = strtolower(
                pathinfo($_FILES["lost_image"]["name"], PATHINFO_EXTENSION)
            );

            if (!in_array($extension, ["jpg", "jpeg", "png"], true)) {
                $errorMessage = "Please upload a JPG or PNG image.";
            } else {

                $fileName = uniqid("lost_", true) . "." . $extension;
                $filePath = $uploadDir . $fileName;

                if (!move_uploaded_file(
                    $_FILES["lost_image"]["tmp_name"],
                    $filePath
                )) {
                    $errorMessage = "Failed to upload image.";
                }
            }
        }

        // Send the request to the AI server
        if ($errorMessage === "") {

            $ch = curl_init("http://127.0.0.1:5000/match");

            $postData = [
                "description" => $description
            ];

            if ($hasImage) {
                $postData["image"] = new CURLFile($filePath);
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            $data = is_string($response)
                ? json_decode($response, true)
                : null;

            if ($response === false) {

                $errorMessage = "Unable to connect to the AI server. "
                              . $curlError;

            } elseif ($httpCode < 200 || $httpCode >= 300) {

                $errorMessage = "The AI server returned an error. "
                              . "HTTP status: " . $httpCode;

            } elseif (!is_array($data)) {

                $errorMessage = "The AI server returned an invalid response.";

            } else {

                // Keep the top 5 AI results
                $imageMatches = array_slice(
                    $data["image_matches"] ?? [],
                    0,
                    5
                );

                $descriptionMatches = array_slice(
                    $data["description_matches"] ?? [],
                    0,
                    5
                );

                // Save the full AI results for this page
                $_SESSION["image_matches_data"] = $imageMatches;
                $_SESSION["description_matches_data"] = $descriptionMatches;

                // Save item IDs for index.php
                $_SESSION["image_matches"] = array_column(
                    $imageMatches,
                    "item_id"
                );

                $_SESSION["description_matches"] = array_column(
                    $descriptionMatches,
                    "item_id"
                );
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Item Match</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<header>
    <nav class="navbar">
        <a href="index.php"><i class="fa-solid fa-house-chimney"></i>HOME</a>
        <a href="report.php"><i class="fa-solid fa-file-lines"></i>REPORT LOST ITEM</a>
        <a href="match.php"><i class="fa-solid fa-code-compare"></i>MATCH ITEMS</a>
        <a href="policy.php"><i class="fa-solid fa-key"></i>TERMS AND CONDITIONS</a>
        <a href="#"><i class="fa-solid fa-user"></i>ADMIN</a>
    </nav>
</header>

<div class="form-container">

    <h2 class="match-title">AI Lost Item Matching</h2>

    <p class="match-subtitle">Upload an image or describe your lost item to find potential matches.</p>

    <form action="match.php"
          method="POST"
          enctype="multipart/form-data">

        <label>Upload Image</label>

        <input type="file"
               name="lost_image"
               accept="image/jpeg,image/png">

        <label for="description">Describe your lost item:</label>

        <textarea id="description"
                  name="description"
                  placeholder="Ex: Specific Place / Specific Time / Other Characteristic"><?php
                      echo htmlspecialchars(
                          $_POST["description"] ?? "",
                          ENT_QUOTES,
                          "UTF-8"
                      );
                  ?></textarea>

        <div class="submit-container">
            <button type="submit" class="submit-btn">
                Find Matches
            </button>
        </div>

    </form>

    <?php if ($errorMessage !== ""): ?>

        <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>

    <?php endif; ?>

    <?php if (!empty($imageMatches) || !empty($descriptionMatches)): ?>

        <br/>
        <hr>

        <h3 class="ai-result-title">AI Analysis Result</h3>

        <div class="ai-result">

            <?php if (!empty($imageMatches)): ?>

                <h4>AI Image Matching Results:</h4>

                <pre class="ai-result-text"><?php
                    $rank = 1;

                    foreach ($imageMatches as $match) {
                        $itemName = htmlspecialchars(
                            $match["item_name"] ?? "Unknown Item",
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        $similarity = htmlspecialchars(
                            (string)($match["similarity"] ?? "N/A"),
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        echo $rank . ". " . $itemName
                            . " - Similarity: " . $similarity . "%\n";

                        $rank++;
                    }
                ?></pre>

                <br/><br/>

                <a href="index.php?matches=image#board" class="submit-btn">
                    Bring Me There
                </a>

                <br/><br/><br/>

            <?php endif; ?>

            <?php if (!empty($descriptionMatches)): ?>

                <h4>AI Description Matching Results:</h4>

                <pre class="ai-result-text"><?php
                    $rank = 1;

                    foreach ($descriptionMatches as $match) {
                        $itemName = htmlspecialchars(
                            $match["item_name"] ?? "Unknown Item",
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        $similarity = htmlspecialchars(
                            (string)($match["similarity"] ?? "N/A"),
                            ENT_QUOTES,
                            "UTF-8"
                        );

                        echo $rank . ". " . $itemName
                            . " - Similarity: " . $similarity . "%\n";

                        $rank++;
                    }
                ?></pre>

                <br/><br/>

                <a href="index.php?matches=description#board" class="submit-btn">
                    Bring Me There
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>