<?php

/* ---------- DATABASE CONNECTION ---------- */

require_once __DIR__ . '/dbconnection.php';
$conn = db_connect();

if ($conn->connect_error) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');


/* ---------- HELPER FUNCTION ---------- */

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/* ---------- CATEGORY OPTIONS ---------- */

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

function locationOptions() {

    return [
        'A1', 'A2', 'A3', 'A4',
        'A5', 'B1', 'D6', 'LY3'
    ];
}


/* ---------- SHOW ONE SPECIFIC ITEM ---------- */

function single_item_data($item_id) {

    global $conn;

    $sql = "SELECT Item_ID, ItemName, ItemCategory,
                   ItemDescription, ItemStatus
            FROM item
            WHERE Item_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    return $result;
}


/* ---------- SUBMIT CLAIM ---------- */

function submitClaim($item_id, $post) {

    global $conn;

    $errors = [];

    $name = trim($post['name'] ?? '');
    $contact = trim($post['contact'] ?? '');
    $email = trim($post['email'] ?? '');
    $category = trim($post['category'] ?? '');
    $lostLocation = trim($post['lostLocation'] ?? '');
    $lostDate = trim($post['lostDate'] ?? '');
    $description = trim($post['description'] ?? '');
    $confirm = isset($post['confirm']);


    /* ---------- VALIDATION ---------- */

    if ($name === '') {
        $errors['name'] = 'Name is required.';
    }

    if ($contact === '') {
        $errors['contact'] = 'Contact number is required.';
    } elseif (!preg_match('/^(0\d{8,9}|60\d{9,10})$/', $contact)) {
        $errors['contact'] = 'Contact number format is invalid.';
    }

    if ($email === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email format is invalid.';
    }

    if (!in_array($category, categoryOptions(), true)) {
        $errors['category'] = 'Please select a valid category.';
    }

    if (!in_array($lostLocation, locationOptions(), true)) {
        $errors['lostLocation'] = 'Please select a valid lost location.';
    }

    $date = DateTime::createFromFormat('!Y-m-d', $lostDate);

    if (!$date || $date->format('Y-m-d') !== $lostDate) {
        $errors['lostDate'] = 'Please select a valid lost date.';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required.';
    }

    if (!$confirm) {
        $errors['confirm'] =
            'Please confirm that the information provided is true.';
    }

    if (!empty($errors)) {
        return [
            'ok' => false,
            'errors' => $errors
        ];
    }


    /* ---------- SAVE CLAIM ---------- */

    try {
        mysqli_begin_transaction($conn);

        /* Check the item and lock its row */

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


        if (!$selectedItem) {
            throw new Exception('The selected item does not exist.');
        }

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

        $sql = "INSERT INTO claimant
                    (ClaimantName, ClaimantContact, ClaimantEmail,
                     LostDate, LostLocation)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

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
        $claimant_id = mysqli_insert_id($conn);

        mysqli_stmt_close($stmt);


        /* Insert claim information */

        $claimDate = date('Y-m-d');
        $claimStatus = 'Pending';
        $claimDescription = "Claim Category:\n" . $category . "\n\nDescription:\n" . $description;

        $sql = "INSERT INTO `claim`
                    (Item_ID, Claimant_ID, ClaimDate,
                     ClaimStatus, ClaimDescription)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

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
        $claim_id = mysqli_insert_id($conn);

        mysqli_stmt_close($stmt);


        /* Save both records */

        mysqli_commit($conn);

        return [
            'ok' => true,
            'claim_id' => $claim_id,
            'item_name' => $selectedItem['ItemName']
        ];


    } catch (Throwable $e) {

        mysqli_rollback($conn);

        error_log('submitClaim failed: ' . $e->getMessage());

        return [
            'ok' => false,
            'errors' => [
                'database' =>
                    'Unable to submit the claim. Please try again.'
            ]
        ];
    }
}
