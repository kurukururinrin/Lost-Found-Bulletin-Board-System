<?php
session_start();
ob_start();

include "index_function.php";

$result = null;
$itemsPerPage = 5;

$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;

if ($page < 1) {
    $page = 1;
}

$isMatchResults = isset($_GET["matches"]);
$totalItems = 0;

if (isset($_GET["item_id"])) {

    $item_id = (int)$_GET["item_id"];

    $result = single_item_data($item_id);
    $totalItems = mysqli_num_rows($result);

} elseif ($isMatchResults) {

    // Determine which matching results the user wants to view
    $matchType = $_GET["matches"];

    if ($matchType === "image") {
        $matchIds = $_SESSION["image_matches"] ?? [];
    } elseif ($matchType === "description") {
        $matchIds = $_SESSION["description_matches"] ?? [];
    } else {
        $matchIds = [];
    }

    // Keep only valid, unique item IDs
    $matchIds = array_slice(
        array_values(
            array_unique(
                array_filter(
                    array_map("intval", $matchIds),
                    function ($id) {
                        return $id > 0;
                    }
                )
            )
        ),
        0,
        5
    );

    if (!empty($matchIds)) {

        $matchConn = new mysqli("localhost","root","","itmp");

        if ($matchConn->connect_error) {
            die("Database connection failed.");
        }

        $matchConn->set_charset("utf8mb4");

        $idList = implode(",", $matchIds);

        $sql = "
            SELECT item.*, finder.FoundLocation, finder.FoundDate
            FROM item
            LEFT JOIN finder
                ON item.Finder_ID = finder.Finder_ID
            WHERE item.Item_ID IN ($idList)
            ORDER BY FIELD(item.Item_ID, $idList)
        ";

        $result = $matchConn->query($sql);

        if ($result === false) {
            die("Unable to retrieve matching items.");
        }

        $totalItems = mysqli_num_rows($result);

    } else {
        $totalItems = 0;
    }

} elseif (
    isset($_GET["category"]) ||
    isset($_GET["location"]) ||
    isset($_GET["date"])
) {

    $category = $_GET["category"] ?? "";
    $location = $_GET["location"] ?? "";
    $date = $_GET["date"] ?? "";

    $result = filter_data(
        $category,
        $location,
        $date,
        $page,
        $itemsPerPage
    );

    $totalItems = total_filter_items(
        $category,
        $location,
        $date
    );

} elseif (isset($_GET["query"]) && $_GET["query"] !== "") {

    $query = $_GET["query"];

    $result = search_data(
        $query,
        $page,
        $itemsPerPage
    );

    $totalItems = total_search_items($query);

} else {

    $result = all_data($page, $itemsPerPage);
    $totalItems = total_items();
}

$totalPages = (int)ceil($totalItems / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>index</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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


<!-- HOME -->
<section class="info" id="home">
    <div class="content">
        <h3>Lost and Found Bulletin Board</h3>
        <span>Working Hour: Monday - Friday</span>
        <p>09:00 AM - 09:30PM</p>
    </div>
</section>

<main class="page-content">

<!-- SEARCH -->
<section class="search">
    <div class="search_box">

        <form action="index.php#board" method="GET">
            <input type="search" name="query" placeholder="Search">
            <a href="index.php#board" class="btn">View All</a>
        </form>

        <details class="filter_button">
            <summary><i class="fas fa-filter"></i></summary>

            <div class="filter_menu">

                <form action="index.php#board" method="GET">

                    <label for="category">Category:</label>

                    <select name="category" id="category">
                        <option value="">All</option>
                        <option value="electronics">Electronics</option>
                        <option value="book&stationery">Book & Stationery</option>
                        <option value="clothing">Clothing</option>
                        <option value="keys&cards">Keys & Cards</option>
                        <option value="accessories">Accessories</option>
                        <option value="personal_items">Personal Items</option>
                        <option value="others">Others</option>
                    </select>

                    <label for="location">Found Location:</label>

                    <select id="location" name="location">
                        <option value="">All Locations</option>
                        <option value="A1">A1</option>
                        <option value="A2">A2</option>
                        <option value="A3">A3</option>
                        <option value="A4">A4</option>
                        <option value="A5">A5</option>
                        <option value="B1">B1</option>
                        <option value="D6">D6</option>
                        <option value="LY3">LY3</option>
                    </select>

                    <label for="date">Found Date:</label>
                    <input type="date" name="date" id="date">

                    <button type="submit" class="btn">
                        Apply Filter
                    </button>

                </form>
            </div>
        </details>

    </div>
</section>


<!-- BOARD -->
<section class="table_header" id="board">

    <?php if ($isMatchResults): ?>

        <?php if ($_GET["matches"] === "image"): ?>
            <h3>AI Image Matching Results</h3>
        <?php else: ?>
            <h3>AI Description Matching Results</h3>
        <?php endif; ?>

        <p>Showing up to 5 potential matching items.</p>

        <p>
            <a href="match.php" class="btn">
                Back to AI Matching
            </a>
        </p>

    <?php else: ?>

        <h3>Lost Item Bulletin Board</h3>

    <?php endif; ?>

</section>


<table class="board">

    <tr>
        <th>Item Picture</th>
        <th>Item Name</th>
        <th>Category</th>
        <th>Found Location</th>
        <th>Found Date</th>
        <th>Description</th>
        <th>Claim</th>
    </tr>

    <?php
    if ($result && mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_array($result)) {

        $imageInfo = false;
        $src = "";

        if (!empty($row["ItemImage"])) {
            $imageInfo = getimagesizefromstring($row["ItemImage"]);

            if ($imageInfo !== false) {
                $imageData = base64_encode($row["ItemImage"]);

                $src = "data:" . $imageInfo["mime"]
                    . ";base64," . $imageData;
            }
        }

            $src = "";

            if ($imageInfo !== false) {
                $imageData = base64_encode($row["ItemImage"]);

                $src = "data:" . $imageInfo["mime"]
                    . ";base64," . $imageData;
            }

            $itemId = (int)$row["Item_ID"];

            $itemName = htmlspecialchars(
                $row["ItemName"],
                ENT_QUOTES,
                "UTF-8"
            );

            $category = htmlspecialchars(
                $row["ItemCategory"],
                ENT_QUOTES,
                "UTF-8"
            );

            $location = htmlspecialchars(
                $row["FoundLocation"],
                ENT_QUOTES,
                "UTF-8"
            );

            $date = htmlspecialchars(
                $row["FoundDate"],
                ENT_QUOTES,
                "UTF-8"
            );

            $description = htmlspecialchars(
                $row["ItemDescription"],
                ENT_QUOTES,
                "UTF-8"
            );

            echo "<tr>
                    <td>";

            if ($src !== "") {
                echo "
                    <input type='checkbox'
                           id='image_$itemId'
                           class='image-checkbox'>

                    <label for='image_$itemId'>
                        <img src='$src' alt='Item'>
                    </label>

                    <div class='image-popup'>
                        <label for='image_$itemId'
                               class='popup-background'></label>
                        <img src='$src' alt='Item'>
                    </div>";
            }

            $alreadyClaimed = has_claim($itemId);

            echo "</td>

                    <td>$itemName</td>
                    <td>$category</td>
                    <td>$location</td>
                    <td>$date</td>
                    <td>$description</td>

                    <td>";

            if ($alreadyClaimed) {
                // if already claimed, then use checkbox and label to show a popup
                echo "
                    <input type='checkbox' id='claimed_$itemId' class='claimed-checkbox'>

                    <label for='claimed_$itemId' class='claim_item claimed'>
                        CLAIM
                    </label>

                    <div class='claimed-popup'>
                        <label for='claimed_$itemId' class='claimed-popup-bg'></label>
                        <div class='claimed-popup-box'>
                            <h3>Item Already Claimed</h3>
                            <p>
                                \"$itemName\" already has a claim record.
                            </p>
                            <p>
                                If you still believe this item belongs to you,
                                please visit the Lost and Found Office at
                                <strong>Room 107</strong> for further assistance.
                            </p>
                            <label for='claimed_$itemId' class='claimed-popup-close'>
                                Got it
                            </label>
                        </div>
                    </div>
                ";
            } else {
                // if not claimed, then redirect user to claim page
                echo "<a href='claim.php?item_id=$itemId'
                        class='claim_item'>CLAIM</a>";
            }

            echo "</td>
                </tr>";
        }

    } elseif ($isMatchResults) {

        echo "<tr>
                <td colspan='7'>
                    No matching items found.
                    Please try another image or description.
                </td>
              </tr>";

    } elseif (isset($_GET["query"])) {

        echo "<tr>
                <td colspan='7'>Item not found</td>
              </tr>";
    }
    ?>

</table>


<!-- PAGINATION -->
<?php
// Matching results are limited to 5, so hide their pagination.
if (!$isMatchResults && $totalPages >= 1) {

    echo "<div class='pagination'>";

    if ($page > 1) {

        if (isset($_GET["query"]) && $_GET["query"] !== "") {

            echo "<a href='?query="
                . urlencode($query)
                . "&page=" . ($page - 1)
                . "#board'>&lt;</a>";

        } else {

            echo "<a href='?page="
                . ($page - 1)
                . "#board'>&lt;</a>";
        }

    } else {
        echo "<a href='#'>&lt;</a>";
    }

    for ($i = 1; $i <= $totalPages; $i++) {

        if (isset($_GET["query"]) && $_GET["query"] !== "") {

            $link = "?query=" . urlencode($query)
                . "&page=" . $i . "#board";

        } else {

            $link = "?page=" . $i . "#board";
        }

        if ($i == $page) {
            echo "<a href='$link' class='active'>$i</a>";
        } else {
            echo "<a href='$link'>$i</a>";
        }
    }

    if ($page < $totalPages) {

        if (isset($_GET["query"]) && $_GET["query"] !== "") {

            echo "<a href='?query="
                . urlencode($query)
                . "&page=" . ($page + 1)
                . "#board'>&gt;</a>";

        } else {

            echo "<a href='?page="
                . ($page + 1)
                . "#board'>&gt;</a>";
        }

    } else {
        echo "<a href='#'>&gt;</a>";
    }

    echo "</div>";
}
?>

</main>

</body>
</html>

<?php
$html_output = ob_get_clean();
echo $html_output;
?>