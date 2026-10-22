<?php
session_start();

if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    header("Location: login.php");
    exit();
}

require_once "dbconnection.php";
require_once "admin_functions.php";

$conn = get_db_connection();
$conn->set_charset("utf8mb4");

cleanupExpiredClaims();

function claimEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$message = "";
$error = "";
$search = trim($_GET["search"] ?? "");

/* ---------- HANDLE ADMIN ACTIONS ---------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $claimId = filter_input(
        INPUT_POST,
        "claim_id",
        FILTER_VALIDATE_INT
    );

    if (!$claimId || $claimId < 1) {

        $error = "Invalid claim ID.";

    } else {

        try {

            if ($action === "update_status") {

                $status = $_POST["status"] ?? "";

                $allowedStatuses = [
                    "Pending",
                    "Resolved",
                    "Rejected"
                ];

                if (!in_array($status, $allowedStatuses, true)) {
                    throw new Exception("Invalid claim status.");
                }

                $stmt = $conn->prepare(
                    "UPDATE `claim`
                     SET ClaimStatus = ?
                     WHERE Claim_ID = ?"
                );

                $stmt->bind_param("si", $status, $claimId);
                $stmt->execute();

                if ($stmt->affected_rows >= 0) {
                    $message = "Claim status updated successfully.";
                }

                $stmt->close();

            } elseif ($action === "delete") {

                $conn->begin_transaction();

                /* Find the claimant linked to this claim. */

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

                if (!$claim) {
                    throw new Exception("Claim record not found.");
                }

                $claimantId = (int)$claim["Claimant_ID"];

                /* Delete the selected claim only. */

                $stmt = $conn->prepare(
                    "DELETE FROM `claim`
                     WHERE Claim_ID = ?"
                );

                $stmt->bind_param("i", $claimId);
                $stmt->execute();
                $stmt->close();

                /*
                 * Delete claimant details only when no other
                 * claim is using the same claimant record.
                 */

                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total
                     FROM `claim`
                     WHERE Claimant_ID = ?"
                );

                $stmt->bind_param("i", $claimantId);
                $stmt->execute();

                $remaining = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ((int)$remaining["total"] === 0) {

                    $stmt = $conn->prepare(
                        "DELETE FROM claimant
                         WHERE Claimant_ID = ?"
                    );

                    $stmt->bind_param("i", $claimantId);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();

                $message = "Claim record deleted successfully.";

            } else {

                throw new Exception("Invalid action.");
            }

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // No active transaction to roll back.
            }

            error_log("Admin claim action failed: " . $e->getMessage());

            $error = "Unable to process this action. Please check the database relationships and try again.";
        }
    }
}

/* ---------- GET CLAIM RECORDS ---------- */

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

$params = [];
$types  = "";

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

    $like = "%" . $search . "%";

    $params[] = $like;   // ClaimantName
    $params[] = $like;   // ItemName
    $params[] = $like;   // ClaimantEmail
    $params[] = $like;   // ClaimDescription
    $params[] = $like;   // ClaimStatus
    $params[] = $like;   // ClaimDate (Y-m-d)
    $params[] = $like;   // ClaimDate (d Month Y)

    $types .= "sssssss";
}

$sql .= "
    ORDER BY
        CASE
            WHEN c.ClaimStatus = 'Pending' THEN 0
            ELSE 1
        END,
        c.ClaimDate DESC,
        c.Claim_ID DESC
";

$stmt = $conn->prepare($sql);

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
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
<div class="claims-container">

    <div class="claims-header">
        <h2>Manage Claims</h2>

        <div class="claims-toolbar">
            <a href="admin.php" class="back-link">
                &larr; Back to Admin Page
            </a>

            <form action="admin_claims.php" method="GET" class="claims-search-form">
                <input type="text"
                    name="search"
                    placeholder="Search claimant details / item details / request date / status..."
                    value="<?= claimEscape($search) ?>">

                <button type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>

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
                
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?= claimEscape($row["Claim_ID"]) ?>
                        </td>

                        <td>
                            <?= claimEscape($row["ItemName"]) ?>
                            <br>
                            <small>
                                Item ID:
                                <?= claimEscape($row["Item_ID"]) ?>
                            </small>
                        </td>

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


                        <td>
                            <?= claimEscape($row["ClaimDate"]) ?>
                        </td>

                        <td class="claim-description">
                            <?= nl2br(htmlspecialchars($row["ClaimDescription"] ?? '', ENT_QUOTES, 'UTF-8')) ?>
                        </td>

                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="claim_id" value="<?= (int)$row["Claim_ID"] ?>" >
                                <input type="hidden" name="action" value="update_status" >
                                
                                <select name="status">
                                    <?php
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
$conn->close();
?>