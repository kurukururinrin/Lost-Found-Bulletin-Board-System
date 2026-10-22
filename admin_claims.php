<?php
// Start the session so we can check if the admin is logged in.
session_start();

// Security check: if the user is not logged in, redirect to the login page.
// This prevents people from opening the admin page by typing the URL directly.
if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    header("Location: login.php");
    exit(); // Stop running the script after redirecting.
}

// Load the database connection helper and the shared admin functions.
require_once "dbconnection.php";
require_once "admin_functions.php";

// Open a database connection and set the character set to UTF-8 (supports Chinese and emoji).
$conn = get_db_connection();
$conn->set_charset("utf8mb4");

// Automatically clean up resolved claims older than 30 days every time this page loads.
cleanupExpiredClaims();

/**
 * Escape a value before printing it to the HTML page.
 * This prevents XSS attacks by converting special characters like < > " ' into safe HTML entities.
 */
function claimEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// Variables to hold feedback messages for the admin.
$message = "";
$error = "";

// Read the search keyword from the URL (GET), trim spaces. Empty string if not provided.
$search = trim($_GET["search"] ?? "");

/* ---------- HANDLE ADMIN ACTIONS ---------- */

// Only process actions when the form was submitted via POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Which action does the admin want? (update_status or delete)
    $action = $_POST["action"] ?? "";

    // Validate claim_id: it must be a positive integer.
    // filter_input rejects anything that is not a valid integer.
    $claimId = filter_input(
        INPUT_POST,
        "claim_id",
        FILTER_VALIDATE_INT
    );

    // If the claim ID is missing or invalid, show an error and skip processing.
    if (!$claimId || $claimId < 1) {

        $error = "Invalid claim ID.";

    } else {

        try {

            // ---------- ACTION: UPDATE STATUS ----------
            if ($action === "update_status") {

                // Get the new status from the form.
                $status = $_POST["status"] ?? "";

                // Whitelist of allowed statuses.
                // Anything else is rejected to keep the database clean.
                $allowedStatuses = [
                    "Pending",
                    "Resolved",
                    "Rejected"
                ];

                // If the submitted status is not in the whitelist, throw an exception.
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new Exception("Invalid claim status.");
                }

                // Update the claim status using a prepared statement (prevents SQL injection).
                $stmt = $conn->prepare(
                    "UPDATE `claim`
                     SET ClaimStatus = ?
                     WHERE Claim_ID = ?"
                );

                // "si" = string (status), integer (claim ID).
                $stmt->bind_param("si", $status, $claimId);
                $stmt->execute();

                // affected_rows >= 0 means the query ran successfully (even if 0 rows changed).
                if ($stmt->affected_rows >= 0) {
                    $message = "Claim status updated successfully.";
                }

                $stmt->close();

            // ---------- ACTION: DELETE CLAIM ----------
            } elseif ($action === "delete") {

                // Start a transaction so all delete steps succeed or fail together.
                $conn->begin_transaction();

                /* Step 1: Find the claimant linked to this claim. */

                // "FOR UPDATE" locks the row so no other request can modify it at the same time.
                $stmt = $conn->prepare(
                    "SELECT Claimant_ID
                     FROM `claim`
                     WHERE Claim_ID = ?
                     FOR UPDATE"
                );

                $stmt->bind_param("i", $claimId);
                $stmt->execute();

                $result = $stmt->get_result();
                $claim = $result->fetch_assoc();
                $stmt->close();

                // If the claim does not exist, stop and throw an error.
                if (!$claim) {
                    throw new Exception("Claim record not found.");
                }

                // Save the claimant ID now, because after deleting the claim we cannot look it up.
                $claimantId = (int)$claim["Claimant_ID"];

                /* Step 2: Delete the selected claim only. */

                $stmt = $conn->prepare(
                    "DELETE FROM `claim`
                     WHERE Claim_ID = ?"
                );

                $stmt->bind_param("i", $claimId);
                $stmt->execute();
                $stmt->close();

                /* Step 3: Check if this claimant still has other claims. */

                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM `claim`
                     WHERE Claimant_ID = ?"
                );

                $stmt->bind_param("i", $claimantId);
                $stmt->execute();

                $remaining = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Step 4: Only delete the claimant if no other claim uses this claimant record.
                if ((int)$remaining["total"] === 0) {

                    $stmt = $conn->prepare(
                        "DELETE FROM claimant
                         WHERE Claimant_ID = ?"
                    );

                    $stmt->bind_param("i", $claimantId);
                    $stmt->execute();
                    $stmt->close();
                }

                // All steps succeeded, so save the changes permanently.
                $conn->commit();

                $message = "Claim record deleted successfully.";

            // ---------- UNKNOWN ACTION ----------
            } else {

                throw new Exception("Invalid action.");
            }

        } catch (Throwable $e) {

            // Roll back any partial changes if something went wrong.
            // Wrapped in try-catch because there may be no active transaction to roll back.
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // No active transaction to roll back.
            }

            // Log the technical error for debugging (admin does not see this).
            error_log("Admin claim action failed: " . $e->getMessage());

            // Show a friendly error message to the admin.
            $error = "Unable to process this action. Please check the database relationships and try again.";
        }
    }
}

/* ---------- GET CLAIM RECORDS ---------- */

// Base query: fetch all claims along with item and claimant details.
// INNER JOIN is used because every claim must have a matching item and claimant.
$sql = "
    SELECT
        c.Claim_ID,
        c.Item_ID,
        c.ClaimDate,
        c.ClaimStatus,
        c.ClaimDescription,
        i.ItemName,
        i.ItemCategory,
        cl.Claimant_ID,
        cl.ClaimantName,
        cl.ClaimantContact,
        cl.ClaimantEmail,
        cl.LostDate,
        cl.LostLocation
    FROM `claim` AS c
    INNER JOIN item AS i
        ON c.Item_ID = i.Item_ID
    INNER JOIN claimant AS cl
        ON c.Claimant_ID = cl.Claimant_ID
    WHERE 1=1
";

// Arrays to hold prepared statement parameters and their types.
$params = [];
$types  = "";

// If the admin entered a search keyword, add a search condition.
// The keyword can match claimant name, item name, email, description,
// status, or the claim date in two different formats.
if ($search !== "") {
    $sql .= " AND (
                cl.ClaimantName LIKE ?
                OR i.ItemName LIKE ?
                OR cl.ClaimantEmail LIKE ?
                OR c.ClaimDescription LIKE ?
                OR c.ClaimStatus LIKE ?
                OR DATE_FORMAT(c.ClaimDate, '%Y-%m-%d') LIKE ?
                OR DATE_FORMAT(c.ClaimDate, '%d %M %Y') LIKE ?
              )";

    // % is a wildcard, so "%abc%" means "anything containing abc".
    $like = "%" . $search . "%";

    $params[] = $like;   // ClaimantName
    $params[] = $like;   // ItemName
    $params[] = $like;   // ClaimantEmail
    $params[] = $like;   // ClaimDescription
    $params[] = $like;   // ClaimStatus
    $params[] = $like;   // ClaimDate (Y-m-d)
    $params[] = $like;   // ClaimDate (d Month Y)

    // 7 string parameters.
    $types .= "sssssss";
}

// Sort results: Pending claims first, then newest claim date, then largest ID.
$sql .= "
    ORDER BY
        CASE
            WHEN c.ClaimStatus = 'Pending' THEN 0
            ELSE 1
        END,
        c.ClaimDate DESC,
        c.Claim_ID DESC
";

// Prepare and execute the query.
$stmt = $conn->prepare($sql);

// Bind the search parameters if there are any.
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims</title>
    <!-- Main admin stylesheet -->
    <link rel="stylesheet" href="admin_style.css">
    <!-- Font Awesome icons (search icon, etc.) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
<div class="claims-container">

    <div class="claims-header">
        <h2>Manage Claims</h2>

        <div class="claims-toolbar">
            <!-- Link back to the main admin dashboard -->
            <a href="admin.php" class="back-link">
                &larr; Back to Admin Page
            </a>

            <!-- Search form: submits via GET so the keyword appears in the URL -->
            <form action="admin_claims.php" method="GET" class="claims-search-form">
                <input type="text"
                    name="search"
                    placeholder="Search claimant details / item details / request date / status..."
                    value="<?= claimEscape($search) ?>">

                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>

                <!-- Show a "Clear" link only when a search keyword is active -->
                <?php if ($search !== ""): ?>
                    <a href="admin_claims.php" class="claims-search-clear">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>


    <div class="claims-table-wrapper">
        <table class="claims-table">

            <!-- Table header row -->
            <thead>
                <tr>
                    <th>Claim ID</th>
                    <th>Item</th>
                    <th>Claimant Details</th>
                    <th>Lost Item Details</th>
                    <th>Claim Request Date</th>
                    <th>Details</th>
                    <th>Status / Actions</th>
                </tr>
            </thead>


            <tbody>
            <!-- If no claims were found, show an empty-state message -->
            <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="7">
                        <?php if ($search !== ""): ?>
                            No claims match "<strong><?= claimEscape($search) ?></strong>".
                            <a href="admin_claims.php">Show all</a>
                        <?php else: ?>
                            No claim records found.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                
                <!-- Loop through each claim record and print one table row -->
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <!-- Claim ID -->
                        <td>
                            <?= claimEscape($row["Claim_ID"]) ?>
                        </td>

                        <!-- Item name and item ID -->
                        <td>
                            <?= claimEscape($row["ItemName"]) ?>
                            <br>
                            <small>
                                Item ID:
                                <?= claimEscape($row["Item_ID"]) ?>
                            </small>
                        </td>

                        <!-- Claimant name, phone, and email -->
                        <td>
                            <strong>
                                <?= claimEscape($row["ClaimantName"]) ?>
                            </strong>
                            <br>
                            Phone:
                            <?= claimEscape($row["ClaimantContact"]) ?>
                            <br>
                            Email:
                            <?= claimEscape($row["ClaimantEmail"]) ?>
                        </td>

                        <!-- Item category, lost date, and lost location -->
                        <td>
                            <strong>Category:</strong>
                            <?= claimEscape($row["ItemCategory"]) ?>
                            <br>

                            <strong>Lost Date:<br/></strong>
                            <?= claimEscape($row["LostDate"]) ?>
                            <br>

                            <strong>Lost Location:</strong>
                            <?= claimEscape($row["LostLocation"]) ?>
                        </td>

                        <!-- Claim request date -->
                        <td>
                            <?= claimEscape($row["ClaimDate"]) ?>
                        </td>

                        <!-- Claim description. nl2br keeps line breaks, htmlspecialchars prevents XSS. -->
                        <td class="claim-description">
                            <?= nl2br(htmlspecialchars($row["ClaimDescription"] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                        </td>

                        <!-- Status update form and delete form -->
                        <td>
                            <!-- Form 1: update the claim status -->
                            <form method="POST" class="status-form">
                                <!-- Hidden fields tell the server which claim and which action -->
                                <input type="hidden" name="claim_id" value="<?= (int)$row["Claim_ID"] ?>" >
                                <input type="hidden" name="action" value="update_status" >
                                
                                <select name="status">
                                    <?php
                                    // Only these two statuses can be selected from this page.
                                    $statuses = [ "Pending", "Resolved"];

                                    foreach ($statuses as $status):
                                    ?>
                                        <option
                                            value="<?= claimEscape($status) ?>"
                                            <?= $row["ClaimStatus"] === $status
                                                ? "selected"
                                                : "" ?>
                                        >
                                            <?= claimEscape($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit"> Update Status </button>
                            </form>

                            <!-- Form 2: delete this claim (asks for confirmation first) -->
                            <form
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this claim record?');"
                            >
                                <input type="hidden" name="claim_id" value="<?= (int)$row["Claim_ID"] ?>" >
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="delete-button"> Delete Claim </button>
                            </form>

                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

<?php
// Close the database connection when the page is done.
$conn->close();
?>