<?php

date_default_timezone_set('Asia/Kuala_Lumpur');

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'itmp';
$DB_PORT = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- DATABASE CONNECTION ---------- */

try {
    $conn = new mysqli(
        $DB_HOST,
        $DB_USER,
        $DB_PASS,
        $DB_NAME,
        $DB_PORT
    );

    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+00:00'");

} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());

    exit('Sorry, the system is temporarily unavailable.');
}


/* ---------- HELPER FUNCTION ---------- */

function e($value): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ---------- CATEGORY OPTIONS ---------- */

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

function categoryLabel(string $key): string {
    $map = categoryOptions();
    return $map[$key] ?? $key;
}


/* ---------- LOCATION OPTIONS ---------- */

function locationOptions(): array {
    return ['A1', 'A2', 'A3', 'A4', 'A5', 'B1', 'D6', 'LY3'];
}


/* ---------- REPORT FOUND ITEM ---------- */

function reportFoundItem(
    mysqli $conn,
    array $post,
    array $files
): array {

    $errors = [];

    $name        = trim((string)($post['name']          ?? ''));
    $contact     = trim((string)($post['contact']       ?? ''));
    $email       = trim((string)($post['gmail']         ?? ''));
    $itemName    = trim((string)($post['itemName']      ?? ''));
    $category    = trim((string)($post['category']      ?? ''));
    $location    = trim((string)($post['foundLocation'] ?? ''));
    $foundDate   = trim((string)($post['foundDate']     ?? ''));
    $description = trim((string)($post['description']   ?? ''));


    /* ---------- VALIDATION ---------- */

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Name must not exceed 150 characters.';
    }


    if ($contact !== '') {
        if (mb_strlen($contact) > 100) {
            $errors[] = 'Contact number must not exceed 100 characters.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) {
            $errors[] = 'Contact number format is invalid (e.g. 012-3456789).';
        }
    }


    if ($email !== '') {
        if (mb_strlen($email) > 100) {
            $errors[] = 'Email must not exceed 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }
    }


    if ($itemName === '') {
        $errors[] = 'Item name is required.';
    } elseif (mb_strlen($itemName) > 150) {
        $errors[] = 'Item name must not exceed 150 characters.';
    }


    if (!array_key_exists($category, categoryOptions())) {
        $errors[] = 'Please select a valid category.';
    }


    if (!in_array($location, locationOptions(), true)) {
        $errors[] = 'Please select a valid location.';
    }


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


    if (mb_strlen($description) > 2000) {
        $errors[] = 'Description must not exceed 2000 characters.';
    }


    /* ---------- OPTIONAL IMAGE ---------- */

    $imageData = null;

    if (
        isset($files['itemPic'])
        && (int)$files['itemPic']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $f = $files['itemPic'];

        if ((int)$f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The picture could not be uploaded (error code '
                . (int)$f['error'] . ').';
        } elseif ((int)$f['size'] > 2 * 1024 * 1024) {
            $errors[] = 'The picture must be 2 MB or smaller.';
        } else {
            $info    = @getimagesize($f['tmp_name']);
            $allowed = [
                IMAGETYPE_JPEG,
                IMAGETYPE_PNG,
                IMAGETYPE_GIF,
                IMAGETYPE_WEBP
            ];

            if (
                $info === false
                || !in_array($info[2], $allowed, true)
            ) {
                $errors[] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
            } else {
                $imageData = file_get_contents($f['tmp_name']);
            }
        }
    }


    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }


    /* ---------- SAVE REPORT ---------- */

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

        $categoryVal = categoryLabel($category);
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
        if ($imageData !== null) {
            $stmt->send_long_data(1, $imageData);
        }

        $stmt->execute();
        $itemId = (int)$stmt->insert_id;
        $stmt->close();


        /* ---------- COMMIT ---------- */

        $conn->commit();

        return [
            'ok'        => true,
            'item_id'   => $itemId,
            'finder_id' => $finderId
        ];

    } catch (Throwable $e) {

        $conn->rollback();

        error_log(
            'reportFoundItem failed: ' . $e->getMessage()
        );

        return [
            'ok' => false,
            'errors' => [
                'Database error: the record could not be saved.'
            ]
        ];
    }
}


/* ---------- HANDLE FORM SUBMISSION ---------- */

$errors         = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $result = reportFoundItem($conn, $_POST, $_FILES);

    if ($result['ok']) {

        $successMessage =
            'Item reported successfully! '
            . 'Reference ID: '
            . $result['item_id'];

    } else {

        $errors = $result['errors'];
    }
}

?>