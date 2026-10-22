<?php
// Start the session.
session_start();

// Load the backend logic (validation, file upload handling, database insert).
require_once "report_function.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item</title>
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

    <h2 class="title">Report Lost Item</h2>

    <?php
    // If the report was submitted successfully, show the success message.
    if ($successMessage !== '') {
        echo '<div class="success-message">';
        echo '<p>' . e($successMessage) . '</p>';
        echo '<p>Your report has been recorded and is waiting for administrator review.</p>';
        echo '<a href="index.php" class="return-btn">Return to Home</a>';
        echo '</div>';

    } else {
    ?>

        <?php
        // If there are validation errors, display them in a red box at the top.
        if (!empty($errors)) {
            echo '<div class="error-message">';

            foreach ($errors as $err) {
                echo '<p>' . e($err) . '</p>';
            }

            echo '</div>';
        }
        ?>

        <!-- 
            The form uses enctype="multipart/form-data" 
            because it needs to upload an image file. 
        -->
        <form action="report.php" method="POST" enctype="multipart/form-data">

            <!-- Reporter's Name -->
            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Ex : John Smith"
                value="<?= e($_POST['name'] ?? '') ?>"
                required
            >

            <!-- Reporter's Contact Number -->
            <label for="contact">Contact Number</label>
            <input
                type="tel"
                id="contact"
                name="contact"
                placeholder="Ex : 60123456789"
                value="<?= e($_POST['contact'] ?? '') ?>"
                required
            >

            <!-- Reporter's Email -->
            <label for="gmail">Email</label>
            <input
                type="email"
                id="gmail"
                name="gmail"
                placeholder="Ex : john.smith@gmail.com"
                value="<?= e($_POST['gmail'] ?? '') ?>"
                required
            >

            <!-- Item Picture Upload -->
            <label for="itemPic">Item Picture</label>
            <input
                type="file"
                id="itemPic"
                name="itemPic"
                accept="image/*"
                required
            >

            <!-- Item Name -->
            <label for="itemName">Item Name</label>
            <input
                type="text"
                id="itemName"
                name="itemName"
                placeholder="Ex : Bottle"
                value="<?= e($_POST['itemName'] ?? '') ?>"
                required
            >

            <!-- Item Category (Dropdown) -->
            <label for="category">Category</label>

            <select id="category" name="category" required>
                <option value="">-Select-</option>

                <?php
                // Loop through the categories. Keep the user's previous selection if validation failed.
                foreach (categoryOptions() as $key => $label) {
                    $selected = (($_POST['category'] ?? '') === $key) ? 'selected' : '';

                    echo '<option value="' . e($key) . '" ' . $selected . '>';
                    echo e($label);
                    echo '</option>';
                }
                ?>
            </select>

            <!-- Found Location (Dropdown) -->
            <label for="foundLocation">Found Location</label>

            <select id="foundLocation" name="foundLocation" required>
                <option value="">-Select-</option>

                <?php
                // Loop through the locations. Keep the user's previous selection if validation failed.
                foreach (locationOptions() as $location) {
                    $selected = (($_POST['foundLocation'] ?? '') === $location) ? 'selected' : '';

                    echo '<option value="' . e($location) . '" ' . $selected . '>';
                    echo e($location);
                    echo '</option>';
                }
                ?>
            </select>

            <!-- Found Date -->
            <label for="foundDate">Found Date</label>

            <input
                type="date"
                id="foundDate"
                name="foundDate"
                value="<?= e($_POST['foundDate'] ?? '') ?>"
                required
            >

            <!-- Description -->
            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                placeholder="Ex : Color / Near to where / other characteristics"
                required
            ><?= e($_POST['description'] ?? '') ?></textarea>

            <!-- Submit Button -->
            <div class="submit-container">
                <button type="submit" class="submit-btn">Submit</button>
            </div>

        </form>

    <?php
    }
    ?>

</div>

</body>
</html>