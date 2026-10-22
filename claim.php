<?php
// Start the session (needed for e() to work with any session-based data).
session_start();

// Load the shared claim functions (e, categoryOptions, locationOptions, single_item_data, submitClaim).
require_once "claim_function.php";


/* ---------- GET SELECTED ITEM ---------- */

// Try to read the item ID from the URL (GET).
// FILTER_VALIDATE_INT rejects anything that is not a valid integer.
$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);

// If there is no GET item_id, try to read it from the POST form.
// This is needed because the form submits to claim.php?item_id=...,
// but we also keep a hidden input as a fallback.
if (!$itemId) {
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
}

// Default values before we query the database.
$item = null;           // The item row (array) once fetched.
$pageError = '';        // A page-level error message (e.g. item not found).
$errors = [];           // Field-level validation errors from submitClaim().
$successMessage = '';   // Set to "success" after a successful claim.

// If no valid item ID was provided, show an error.
if (!$itemId || $itemId < 1) {

    $pageError = 'No item was selected. Please return to the lost and found items page and choose an item first.';

} else {

    // Fetch the item from the database by ID.
    $result = single_item_data($itemId);
    $item = mysqli_fetch_assoc($result);

    // If the item does not exist, show an error.
    if (!$item) {

        $pageError = 'The selected item could not be found.';

    // If the item is already claimed, block the page.
    // strcasecmp compares case-insensitively, so "Claimed" and "claimed" both match.
    } elseif (strcasecmp((string)$item['ItemStatus'], 'Claimed') === 0) {

        $pageError = 'This item has already been claimed.';
    }
}


/* ---------- HANDLE FORM SUBMISSION ---------- */

// Only process the form when:
// 1. The request is POST,
// 2. An item was found,
// 3. There is no page-level error.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $item
    && $pageError === ''
) {

    // Validate and save the claim.
    $result = submitClaim($itemId, $_POST);

    // On success, set the success flag to show the success message.
    if ($result['ok']) {

        $successMessage = 'success';

    } else {

        // Collect field-level errors to display next to each input.
        $errors = $result['errors'];

        // Refresh the item status in case another user
        // claimed the item while this form was open.
        $itemResult = single_item_data($itemId);
        $item = mysqli_fetch_assoc($itemResult);

        // If the item disappeared, show an error.
        if (!$item) {

            $pageError = 'The selected item could not be found.';

        // If it was claimed in the meantime, show an error.
        } elseif (strcasecmp((string)$item['ItemStatus'], 'Claimed') === 0) {

            $pageError = 'This item has already been claimed.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item</title>
    <!-- Main stylesheet -->
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

<!-- Top navigation bar (same on every public page) -->
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

    <h2 class="title">Claim Item</h2>

    <?php
    /* ---------- DISPLAY PAGE ERROR ---------- */

    // If there is a page-level error, show it and a Return button.
    if ($pageError !== '') {

        echo '<div class="error-message">';
        echo '<p>' . e($pageError) . '</p>';
        echo '</div>';

        echo '<div class="submit-container">';
        echo '<a href="index.php" class="submit-btn">Return</a>';
        echo '</div>';

    // If the claim was submitted successfully, show the success message.
    } elseif ($successMessage !== '') {

        /* ---------- DISPLAY SUCCESS MESSAGE ---------- */

        echo '<div class="success-message">';
        echo '<p>Your claim has been submitted successfully.</p>';
        echo '<p>Please visit the Lost and Found Office at Room 107 to collect your item.</p>';
        echo '<p>Staff will conduct further verification to confirm your ownership of the item.</p>';
        echo '<a href="index.php" class="return-btn">Return to Home</a>';
        echo '</div>';
        
    } else {
    ?>

        <?php
        /* ---------- DISPLAY DATABASE ERROR ---------- */

        // If submitClaim() returned a database-level error, show it here.
        if (isset($errors['database'])) {
            echo '<div class="error-message">';
            echo '<p>' . e($errors['database']) . '</p>';
            echo '</div>';
        }
        ?>

        <!-- Selected Item -->

        <!-- Read-only field showing which item is being claimed. -->
        <div class="selected-item-box">
            <label>Item to Claim</label>
            <input
                type="text"
                value="<?= e($item['ItemName']) ?>"
                readonly
            >
        </div>

        <!-- The form submits back to this page with the item_id in the URL. -->
        <form action="claim.php?item_id=<?= e($itemId) ?>" method="POST">

            <!-- Hidden field as a fallback in case the URL loses item_id. -->
            <input
                type="hidden"
                name="item_id"
                value="<?= e($itemId) ?>"
            >

            <!-- Name -->

            <label for="name">Name</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Ex : Michael Jordan"
                value="<?= e($_POST['name'] ?? '') ?>"
                required
            >

            <?php
            // Show the name error if validation failed for this field.
            if (isset($errors['name'])) {
                echo '<p class="error-message">' . e($errors['name']) . '</p>';
            }
            ?>

            <!-- Contact Number -->

            <label for="contact">Contact Number</label>
            <input
                type="tel"
                id="contact"
                name="contact"
                placeholder="Ex : 60123456789"
                value="<?= e($_POST['contact'] ?? '') ?>"
                required
            >

            <?php
            // Show the contact error if validation failed for this field.
            if (isset($errors['contact'])) {
                echo '<p class="error-message">' . e($errors['contact']) . '</p>';
            }
            ?>

            <!-- Email -->

            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="Ex : Michael@gmail.com"
                value="<?= e($_POST['email'] ?? '') ?>"
                required
            >

            <?php
            // Show the email error if validation failed for this field.
            if (isset($errors['email'])) {
                echo '<p class="error-message">' . e($errors['email']) . '</p>';
            }
            ?>

            <!-- Category -->

            <label for="category">Category</label>

            <select id="category" name="category" required>
                <option value="">-Select-</option>

                <?php
                // Loop through the allowed categories and build the dropdown.
                foreach (categoryOptions() as $category) {

                    // Keep the user's previous choice selected after a validation error.
                    $selected = (
                        ($_POST['category'] ?? '') === $category
                    ) ? 'selected' : '';

                    echo '<option value="' . e($category) . '" ' . $selected . '>';
                    echo e($category);
                    echo '</option>';
                }
                ?>
            </select>

            <?php
            // Show the category error if validation failed for this field.
            if (isset($errors['category'])) {
                echo '<p class="error-message">' . e($errors['category']) . '</p>';
            }
            ?>

            <!-- Lost Location -->

            <label for="lostLocation">Lost Location</label>

            <select id="lostLocation" name="lostLocation" required>
                <option value="">-Select-</option>

                <?php
                // Loop through the allowed locations and build the dropdown.
                foreach (locationOptions() as $location) {

                    // Keep the user's previous choice selected after a validation error.
                    $selected = (
                        ($_POST['lostLocation'] ?? '') === $location
                    ) ? 'selected' : '';

                    echo '<option value="' . e($location) . '" ' . $selected . '>';
                    echo e($location);
                    echo '</option>';
                }
                ?>
            </select>

            <?php
            // Show the location error if validation failed for this field.
            if (isset($errors['lostLocation'])) {
                echo '<p class="error-message">' . e($errors['lostLocation']) . '</p>';
            }
            ?>

            <!-- Lost Date -->

            <label for="lostDate">Lost Date</label>

            <input
                type="date"
                id="lostDate"
                name="lostDate"
                value="<?= e($_POST['lostDate'] ?? '') ?>"
                required
            >

            <?php
            // Show the date error if validation failed for this field.
            if (isset($errors['lostDate'])) {
                echo '<p class="error-message">' . e($errors['lostDate']) . '</p>';
            }
            ?>

            <!-- Description -->

            <label for="description">Description of your lost item</label>

            <textarea
                id="description"
                name="description"
                placeholder="Ex : Color / Near to where / other characteristics"
                required
            ><?= e($_POST['description'] ?? '') ?></textarea>

            <?php
            // Show the description error if validation failed for this field.
            if (isset($errors['description'])) {
                echo '<p class="error-message">' . e($errors['description']) . '</p>';
            }
            ?>

            <!-- Confirmation -->

            <div class="checkbox-group">

                <input
                    type="checkbox"
                    id="confirm"
                    name="confirm"
                    value="1"
                    <?= isset($_POST['confirm']) ? 'checked' : '' ?>
                    required
                >

                <label for="confirm">
                    I confirm that I am the rightful owner and that the information provided is true.
                </label>

            </div>

            <?php
            // Show the confirmation error if the checkbox was not ticked.
            if (isset($errors['confirm'])) {
                echo '<p class="error-message">' . e($errors['confirm']) . '</p>';
            }
            ?>

            <!-- Submit -->

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