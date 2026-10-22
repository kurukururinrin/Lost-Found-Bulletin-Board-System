<?php
// Start the session to save and restore AI match results across page loads.
session_start();

// Default variables for displaying messages.
$results = "";
$errorMessage = "";

// Restore previous results when returning to this page
// (e.g., if the user navigates away and comes back, they don't lose their results).
$imageMatches = $_SESSION["image_matches_data"] ?? [];
$descriptionMatches = $_SESSION["description_matches_data"] ?? [];

// Only process when the form is submitted via POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Check if an image was uploaded successfully.
    $hasImage = isset($_FILES["lost_image"]) &&
                $_FILES["lost_image"]["error"] === UPLOAD_ERR_OK;

    // Read and trim the description input.
    $description = trim($_POST["description"] ?? "");

    // Reject the request if both fields are empty.
    if (!$hasImage && $description === "") {
        $errorMessage = "Please upload an image or enter a description.";
    } else {

        $filePath = "";

        // Upload the image if provided
        if ($hasImage) {

            // Define the upload directory inside the current folder.
            $uploadDir = __DIR__ . "/uploads/";

            // Create the directory if it doesn't exist yet.
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Get the file extension (lowercase).
            $extension = strtolower(
                pathinfo($_FILES["lost_image"]["name"], PATHINFO_EXTENSION)
            );

            // Only allow JPG, JPEG, and PNG files.
            if (!in_array($extension, ["jpg", "jpeg", "png"], true)) {
                $errorMessage = "Please upload a JPG or PNG image.";
            } else {

                // Generate a unique filename to prevent overwriting.
                $fileName = uniqid("lost_", true) . "." . $extension;
                $filePath = $uploadDir . $fileName;

                // Move the uploaded file from the temporary folder to our uploads folder.
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

            // Initialize cURL to talk to the Flask AI server.
            $ch = curl_init("http://127.0.0.1:5000/match");

            // Prepare the POST data (description is always sent).
            $postData = [
                "description" => $description
            ];

            // If an image was uploaded, attach it to the request.
            if ($hasImage) {
                $postData["image"] = new CURLFile($filePath);
            }

            // Set cURL options.
            curl_setopt($ch, CURLOPT_POST, true);            // Use POST method.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // Attach the data.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Return the response as a string.
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    // Max 10 seconds to connect.
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);          // Max 180 seconds for the whole request.

            // Execute the request and capture the response + status.
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            // Decode the JSON response from the AI server.
            $data = is_string($response)
                ? json_decode($response, true)
                : null;

            // Handle connection failures.
            if ($response === false) {

                $errorMessage = "Unable to connect to the AI server. "
                              . $curlError;

            // Handle HTTP error codes (e.g., 400, 500).
            } elseif ($httpCode < 200 || $httpCode >= 300) {

                $errorMessage = "The AI server returned an error. "
                              . "HTTP status: " . $httpCode;

            // Handle invalid JSON responses.
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
                // (so they can be displayed again if the user refreshes).
                $_SESSION["image_matches_data"] = $imageMatches;
                $_SESSION["description_matches_data"] = $descriptionMatches;

                // Save item IDs for index.php
                // (index.php will use these to highlight matched items on the board).
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
    <!-- Main stylesheet -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- Top navigation bar -->
<header>
    <nav class="navbar">
        <a href="index.php"><i class="fa-solid fa-house-chimney"></i>HOME</a>
        <a href="report.php"><i class="fa-solid fa-file-lines"></i>REPORT LOST ITEM</a>
        <a href="match.php"><i class="fa-solid fa-code-compare"></i>MATCH ITEMS</a>
        <a href="policy.php"><i class="fa-solid fa-key"></i>TERMS AND CONDITIONS</a>
        <a href="login.php"><i class="fa-solid fa-user"></i>ADMIN</a>
    </nav>
</header>

<div class="form-container">

    <h2 class="match-title">AI Lost Item Matching</h2>

    <p class="match-subtitle">Upload an image or describe your lost item to find potential matches.</p>

    <!-- 
        The form uses multipart/form-data because it uploads an image.
        It sends both the image (optional) and the description (optional) to the AI server.
    -->
    <form action="match.php"
          method="POST"
          enctype="multipart/form-data">

        <label>Upload Image</label>

        <!-- File input restricted to JPG and PNG. -->
        <input type="file"
               name="lost_image"
               accept="image/jpeg,image/png">

        <label for="description">Describe your lost item:</label>

        <!-- Textarea keeps the user's previous input after form submission. -->
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

        <!-- Display an error message if something went wrong. -->
        <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?></p>

    <?php endif; ?>

    <?php if (!empty($imageMatches) || !empty($descriptionMatches)): ?>

        <br/>
        <hr>

        <h3 class="ai-result-title">AI Analysis Result</h3>

        <div class="ai-result">

            <?php if (!empty($imageMatches)): ?>

                <!-- Show the top 5 image-based matches from the AI. -->
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

                        // Print each match as "1. Item Name - Similarity: 95%".
                        echo $rank . ". " . $itemName
                            . " - Similarity: " . $similarity . "%\n";

                        $rank++;
                    }
                ?></pre>

                <br/><br/>

                <!-- Link to index.php with a filter so it only shows image-matched items. -->
                <a href="index.php?matches=image#board" class="submit-btn">
                    Bring Me There
                </a>

                <br/><br/><br/>

            <?php endif; ?>

            <?php if (!empty($descriptionMatches)): ?>

                <!-- Show the top 5 description-based matches from the AI. -->
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

                        // Print each match as "1. Item Name - Similarity: 95%".
                        echo $rank . ". " . $itemName
                            . " - Similarity: " . $similarity . "%\n";

                        $rank++;
                    }
                ?></pre>

                <br/><br/>

                <!-- Link to index.php with a filter so it only shows description-matched items. -->
                <a href="index.php?matches=description#board" class="submit-btn">
                    Bring Me There
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>