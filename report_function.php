<?php

// Set the default timezone for PHP date functions.
date_default_timezone_set('Asia/Kuala_Lumpur');

// Database connection settings.
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'itmp';
$DB_PORT = 3306;

// Throw exceptions instead of warnings when a database error occurs.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- DATABASE CONNECTION ---------- */

try {
    // Create a new MySQL connection object.
    $conn = new mysqli(
        $DB_HOST,
        $DB_USER,
        $DB_PASS,
        $DB_NAME,
        $DB_PORT
    );

    // Use UTF-8 character set to support multilingual text.
    $conn->set_charset('utf8mb4');
    // Set the connection timezone to UTC.
    $conn->query("SET time_zone = '+00:00'");

} catch (mysqli_sql_exception $e) {
    // Log the actual error to the server log.
    error_log('DB connection failed: ' . $e->getMessage());

    // Show a generic message to the user (do not expose DB details).
    exit('Sorry, the system is temporarily unavailable.');
}


/* ---------- HELPER FUNCTION ---------- */

/**
 * Escape a value before printing it to HTML.
 * Prevents XSS attacks by converting special characters into safe entities.
 */
function e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ---------- CATEGORY OPTIONS ---------- */

/**
 * Return the allowed categories as an array (key => display label).
 * The key is stored in the database, the label is shown to the user.
 */
function categoryOptions(): array {
    return [
        'electronics'     => 'Electronics',
        'book&stationery' => 'Book & Stationery',
        'clothing'        => 'Clothing',
        'keys&cards'      => 'Keys & Cards',
        'accessories'     => 'Accessories',
        'personal_items'  => 'Personal Items',
        'others'          => 'Others',
    ];
}

/**
 * Get the display label for a given category key.
 * Falls back to the key itself if not found.
 */
function categoryLabel(string $key): string {
    $map = categoryOptions();
    return $map[$key] ?? $key;
}


/* ---------- LOCATION OPTIONS ---------- */

/**
 * Return the allowed found locations.
 */
function locationOptions(): array {
    return ['A1', 'A2', 'A3', 'A4', 'A5', 'B1', 'D6', 'LY3'];
}


/* ---------- REPORT FOUND ITEM ---------- */

/**
 * Validate and save a new "found item" report.
 * Returns ['ok' => true, ...] on success, or ['ok' => false, 'errors' => [...]] on failure.
 */
function reportFoundItem(
    mysqli $conn,
    array $post,
    array $files
): array {

    // Collect validation errors here.
    $errors = [];

    // Read and trim each form field. Empty string if missing.
    $name        = trim((string)($post['name']          ?? ''));
    $contact     = trim((string)($post['contact']       ?? ''));
    $email       = trim((string)($post['gmail']         ?? ''));
    $itemName    = trim((string)($post['itemName']      ?? ''));
    $category    = trim((string)($post['category']      ?? ''));
    $location    = trim((string)($post['foundLocation'] ?? ''));
    $foundDate   = trim((string)($post['foundDate']     ?? ''));
    $description = trim((string)($post['description']   ?? ''));


    /* ---------- VALIDATION ---------- */

    // Name is required and must not exceed 150 characters.
    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Name must not exceed 150 characters.';
    }


    // Contact is optional, but if provided, check length and format.
    if ($contact !== '') {
        if (mb_strlen($contact) > 100) {
            $errors[] = 'Contact number must not exceed 100 characters.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) {
            $errors[] = 'Contact number format is invalid (e.g. 012-3456789).';
        }
    }


    // Email is optional, but if provided, check length and format.
    if ($email !== '') {
        if (mb_strlen($email) > 100) {
            $errors[] = 'Email must not exceed 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }
    }


    // Item name is required and must not exceed 150 characters.
    if ($itemName === '') {
        $errors[] = 'Item name is required.';
    } elseif (mb_strlen($itemName) > 150) {
        $errors[] = 'Item name must not exceed 150 characters.';
    }


    // Category must be one of the allowed keys.
    if (!array_key_exists($category, categoryOptions())) {
        $errors[] = 'Please select a valid category.';
    }


    // Found location must be in the whitelist.
    if (!in_array($location, locationOptions(), true)) {
        $errors[] = 'Please select a valid location.';
    }


    // Found date is required, must be valid, and cannot be in the future.
    if ($foundDate === '') {
        $errors[] = 'Found date is required.';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $foundDate);

        if (!$d || $d->format('Y-m-d') !== $foundDate) {
            $errors[] = 'Found date is invalid.';
        } elseif ($foundDate > date('Y-m-d')) {
            $errors[] = 'Found date cannot be in the future.';
        }
    }


    // Description is optional, but has a maximum length.
    if (mb_strlen($description) > 2000) {
        $errors[] = 'Description must not exceed 2000 characters.';
    }


    /* ---------- OPTIONAL IMAGE ---------- */

    $imageData = null;

    // Only process the file if one was actually uploaded.
    if (
        isset($files['itemPic'])
        && (int)$files['itemPic']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $f = $files['itemPic'];

        // Check for upload errors.
        if ((int)$f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The picture could not be uploaded (error code '
                . (int)$f['error'] . ').';
        
        // Check file size (max 2 MB).
        } elseif ((int)$f['size'] > 2 * 1024 * 1024) {
            $errors[] = 'The picture must be 2 MB or smaller.';
        } else {
            // Verify the file is actually a valid image.
            $info    = @getimagesize($f['tmp_name']);
            $allowed = [
                IMAGETYPE_JPEG,
                IMAGETYPE_PNG,
                IMAGETYPE_GIF,
                IMAGETYPE_WEBP
            ];

            // Reject anything that is not a real JPG, PNG, GIF, or WEBP.
            if (
                $info === false
                || !in_array($info[2], $allowed, true)
            ) {
                $errors[] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
            } else {
                // Read the raw image bytes to store in the database.
                $imageData = file_get_contents($f['tmp_name']);
            }
        }
    }


    // If there are any errors, stop and return them.
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }


    /* ---------- SAVE REPORT ---------- */

    // Begin transaction: both finder and item must be saved together.
    $conn->begin_transaction();

    try {

        /* ---------- INSERT FINDER ---------- */

        $sql = "
            INSERT INTO finder
            (
                FinderName,
                FinderContact,
                FinderEmail,
                FoundDate,
                FoundLocation
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = $conn->prepare($sql);

        // Convert empty strings to NULL for optional fields.
        $contactVal = ($contact === '') ? null : $contact;
        $emailVal   = ($email   === '') ? null : $email;

        $stmt->bind_param(
            'sssss',
            $name,
            $contactVal,
            $emailVal,
            $foundDate,
            $location
        );

        $stmt->execute();
        // Save the auto-generated finder ID for the item insert.
        $finderId = (int)$stmt->insert_id;
        $stmt->close();


        /* ---------- INSERT ITEM ---------- */
        $sql = "INSERT INTO item
            (
                ItemName,
                ItemImage,
                ItemCategory,
                ItemDescription,
                ItemStatus,
                Finder_ID
            )
            VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        // Convert the category key to its display label.
        $categoryVal = categoryLabel($category);
        // Newly reported items start as "Under Review".
        $status      = 'Under Review';

        /* Bind a NULL placeholder for the blob; send real bytes later. */
        $nullBlob = null;

        $stmt->bind_param(
            'sbsssi',
            $itemName,
            $nullBlob,
            $categoryVal,
            $description,
            $status,
            $finderId
        );

        /* Stream the actual image bytes into the placeholder. */
        // send_long_data is used because the image might be too large for bind_param.
        if ($imageData !== null) {
            $stmt->send_long_data(1, $imageData);
        }

        $stmt->execute();
        // Get the new item ID to show to the user.
        $itemId = (int)$stmt->insert_id;
        $stmt->close();


        /* ---------- COMMIT ---------- */

        // Save both inserts permanently.
        $conn->commit();

        return [
            'ok'        => true,
            'item_id'   => $itemId,
            'finder_id' => $finderId
        ];

    } catch (Throwable $e) {

        // Undo everything if something went wrong.
        $conn->rollback();

        // Log the technical error for debugging.
        error_log(
            'reportFoundItem failed: ' . $e->getMessage()
        );

        // Return a friendly message to the user.
        return [
            'ok' => false,
            'errors' => [
                'Database error: the record could not be saved.'
            ]
        ];
    }
}


/* ---------- HANDLE FORM SUBMISSION ---------- */

// Default values for the page.
$errors         = [];
$successMessage = '';

// Only process when the form is submitted via POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Call the validation + save function.
    $result = reportFoundItem($conn, $_POST, $_FILES);

    if ($result['ok']) {
        // Show success message with the new item ID.
        $successMessage =
            'Item reported successfully! '
            . 'Reference ID: '
            . $result['item_id'];

    } else {
        // Collect validation errors to display.
        $errors = $result['errors'];
    }
}

?>