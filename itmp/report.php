<?php
session_start();
require_once "report_function.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item</title>
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
        <a href="login.php"><i class="fa-solid fa-user"></i>ADMIN</a>
    </nav>
</header>

<div class="form-container">

    <h2 class="title">Report Lost Item</h2>

    <?php
    if ($successMessage !== '') {
        echo '<div class="success-message">';
        echo '<p>' . e($successMessage) . '</p>';
        echo '<p>Your report has been recorded and is waiting for administrator review.</p>';
        echo '<a href="index.php" class="return-btn">Return to Home</a>';
        echo '</div>';

    } else {
    ?>

        <?php
        if (!empty($errors)) {
            echo '<div class="error-message">';

            foreach ($errors as $err) {
                echo '<p>' . e($err) . '</p>';
            }

            echo '</div>';
        }
        ?>

        <form action="report.php" method="POST" enctype="multipart/form-data">

            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Ex : John Smith"
                value="<?= e($_POST['name'] ?? '') ?>"
                required
            >

            <label for="contact">Contact Number</label>
            <input
                type="tel"
                id="contact"
                name="contact"
                placeholder="Ex : 60123456789"
                value="<?= e($_POST['contact'] ?? '') ?>"
            >

            <label for="gmail">Email</label>
            <input
                type="email"
                id="gmail"
                name="gmail"
                placeholder="Ex : john.smith@gmail.com"
                value="<?= e($_POST['gmail'] ?? '') ?>"
            >

            <label for="itemPic">Item Picture</label>
            <input
                type="file"
                id="itemPic"
                name="itemPic"
                accept="image/*"
            >

            <label for="itemName">Item Name</label>
            <input
                type="text"
                id="itemName"
                name="itemName"
                placeholder="Ex : Bottle"
                value="<?= e($_POST['itemName'] ?? '') ?>"
                required
            >

            <label for="category">Category</label>

            <select id="category" name="category" required>
                <option value="">-Select-</option>

                <?php
                foreach (categoryOptions() as $key => $label) {
                    $selected = (($_POST['category'] ?? '') === $key) ? 'selected' : '';

                    echo '<option value="' . e($key) . '" ' . $selected . '>';
                    echo e($label);
                    echo '</option>';
                }
                ?>
            </select>

            <label for="foundLocation">Found Location</label>

            <select id="foundLocation" name="foundLocation" required>
                <option value="">-Select-</option>

                <?php
                foreach (locationOptions() as $location) {
                    $selected = (($_POST['foundLocation'] ?? '') === $location) ? 'selected' : '';

                    echo '<option value="' . e($location) . '" ' . $selected . '>';
                    echo e($location);
                    echo '</option>';
                }
                ?>
            </select>

            <label for="foundDate">Found Date</label>

            <input
                type="date"
                id="foundDate"
                name="foundDate"
                value="<?= e($_POST['foundDate'] ?? '') ?>"
                required
            >

            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                placeholder="Ex : Color / Near to where / other characteristics"
            ><?= e($_POST['description'] ?? '') ?></textarea>

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