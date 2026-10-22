<?php

/* ---------- DATABASE CONNECTION ---------- */

// Load the database connection helper file.
require_once __DIR__ . '/dbconnection.php';

// Open a database connection.
$conn = db_connect();

// If the connection failed, stop the script with a simple error message.
if ($conn->connect_error) {
    die('Database connection failed.');
}

// Set the character set to UTF-8 so Chinese text and emoji work correctly.
$conn->set_charset('utf8mb4');


/* ---------- HELPER FUNCTION ---------- */

/**
 * Escape a value before printing it to HTML.
 * Prevents XSS attacks by converting special characters into safe HTML entities.
 */
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ---------- CATEGORY OPTIONS ---------- */

/**
 * Return the list of allowed item categories.
 * Used to validate the category submitted by the user.
 */
function categoryOptions() {
    return [
        'Electronics',
        'Book & Stationery',
        'Clothing',
        'Keys & Cards',
        'Accessories',
        'Personal Items',
        'Others'
    ];
}


/* ---------- LOCATION OPTIONS ---------- */

/**
 * Return the list of allowed lost locations.
 * Used to validate the lost location submitted by the user.
 */
function locationOptions() {

    return [
        'A1', 'A2', 'A3', 'A4',
        'A5', 'B1', 'D6', 'LY3'
    ];
}


/* ---------- SHOW ONE SPECIFIC ITEM ---------- */

/**
 * Fetch one item from the database by its ID.
 * Returns the result set so the caller can display item details.
 */
function single_item_data($item_id) {

    // Use the global connection (defined at the top of this file).
    global $conn;

    // Select only the fields needed for display.
    $sql = "SELECT Item_ID, ItemName, ItemCategory,
                   ItemDescription, ItemStatus
            FROM item
            WHERE Item_ID = ?";

    // Prepared statement prevents SQL injection.
    $stmt = mysqli_prepare($conn, $sql);

    // Bind the item ID as an integer.
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);

    // Get the result set and return it.
    $result = mysqli_stmt_get_result($stmt);

    return $result;
}


/* ---------- SUBMIT CLAIM ---------- */

/**
 * Validate and save a new claim.
 * Returns an array with 'ok' = true on success,
 * or 'ok' = false plus an 'errors' array on failure.
 */
function submitClaim($item_id, $post) {

    global $conn;

    // Collect all validation errors here.
    $errors = [];

    // Read and trim each form field. Empty string if missing.
    $name = trim($post['name'] ?? '');
    $contact = trim($post['contact'] ?? '');
    $email = trim($post['email'] ?? '');
    $category = trim($post['category'] ?? '');
    $lostLocation = trim($post['lostLocation'] ?? '');
    $lostDate = trim($post['lostDate'] ?? '');
    $description = trim($post['description'] ?? '');

    // The "confirm" checkbox is only present when checked.
    $confirm = isset($post['confirm']);


    /* ---------- VALIDATION ---------- */

    // Name is required.
    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    // Contact is required and must match a Malaysian phone number format:
    // - starts with 0 followed by 8-9 digits, OR
    // - starts with 60 followed by 9-10 digits.
    if ($contact === '') {
        $errors['contact'] = 'Contact number is required.';
    } elseif (!preg_match('/^(0\d{8,9}|60\d{9,10})$/', $contact)) {
        $errors['contact'] = 'Contact number format is invalid.';
    }

    // Email is required and must be a valid email address.
    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email format is invalid.';
    }

    // Category must be one of the allowed options (whitelist check).
    if (!in_array($category, categoryOptions(), true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    // Lost location must be one of the allowed options (whitelist check).
    if (!in_array($lostLocation, locationOptions(), true)) {
        $errors['lostLocation'] = 'Please select a valid lost location.';
    }

    // Validate the date using strict format checking.
    // The "!" prefix resets unused fields so the format check is exact.
    $date = DateTime::createFromFormat('!Y-m-d', $lostDate);

    // If parsing failed, or the normalized date differs from the input, it's invalid.
    if (!$date || $date->format('Y-m-d') !== $lostDate) {
        $errors['lostDate'] = 'Please select a valid lost date.';
    }

    // Description is required.
    if ($description === '') {
        $errors['description'] = 'Description is required.';
    }

    // The user must confirm the information is true.
    if (!$confirm) {
        $errors['confirm'] =
            'Please confirm that the information provided is true.';
    }

    // If any validation errors exist, return them without touching the database.
    if (!empty($errors)) {
        return [
            'ok' => false,
            'errors' => $errors
        ];
    }


    /* ---------- SAVE CLAIM ---------- */

    try {
        // Start a transaction: both the claimant and the claim must be saved together.
        mysqli_begin_transaction($conn);

        /* Check the item and lock its row */

        // FOR UPDATE locks the row so no other request can change the item
        // status while we are processing this claim.
        $sql = "SELECT Item_ID, ItemName, ItemStatus
                FROM item
                WHERE Item_ID = ?
                FOR UPDATE";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $selectedItem = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);


        // If the item does not exist, stop and roll back.
        if (!$selectedItem) {
            throw new Exception('The selected item does not exist.');
        }

        // Only items with status "Available" can be claimed.
        if ($selectedItem['ItemStatus'] !== 'Available') {
            mysqli_rollback($conn);

            return [
                'ok' => false,
                'errors' => [
                    'database' =>
                        'This item is not available for claiming.'
                ]
            ];
        }


        /* Insert claimant information */

        // Save the person who is making the claim.
        $sql = "INSERT INTO claimant
                    (ClaimantName, ClaimantContact, ClaimantEmail,
                     LostDate, LostLocation)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        // All five values are strings.
        mysqli_stmt_bind_param(
            $stmt,
            'sssss',
            $name,
            $contact,
            $email,
            $lostDate,
            $lostLocation
        );

        mysqli_stmt_execute($stmt);

        // Get the auto-generated claimant ID for the next insert.
        $claimant_id = mysqli_insert_id($conn);

        mysqli_stmt_close($stmt);


        /* Insert claim information */

        // The claim date is today, and the initial status is "Pending".
        $claimDate = date('Y-m-d');
        $claimStatus = 'Pending';

        // Combine the category and description into a single text block.
        $claimDescription = "Claim Category:\n" . $category . "\n\nDescription:\n" . $description;

        $sql = "INSERT INTO `claim`
                    (Item_ID, Claimant_ID, ClaimDate,
                     ClaimStatus, ClaimDescription)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        // i = integer (item ID, claimant ID)
        // s = string  (claim date, status, description)
        mysqli_stmt_bind_param(
            $stmt,
            'iisss',
            $item_id,
            $claimant_id,
            $claimDate,
            $claimStatus,
            $claimDescription
        );

        mysqli_stmt_execute($stmt);

        // Get the auto-generated claim ID for the success response.
        $claim_id = mysqli_insert_id($conn);

        mysqli_stmt_close($stmt);


        /* Save both records */

        // Commit the transaction: both inserts are now permanent.
        mysqli_commit($conn);

        return [
            'ok' => true,
            'claim_id' => $claim_id,
            'item_name' => $selectedItem['ItemName']
        ];


    } catch (Throwable $e) {

        // Undo any partial changes if something went wrong.
        mysqli_rollback($conn);

        // Log the technical error for debugging.
        error_log('submitClaim failed: ' . $e->getMessage());

        // Return a friendly error message to the user.
        return [
            'ok' => false,
            'errors' => [
                'database' =>
                    'Unable to submit the claim. Please try again.'
            ]
        ];
    }
}