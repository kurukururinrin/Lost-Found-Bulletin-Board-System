<?php
// Start the session to access saved AI match results.
session_start();

// Start output buffering so we can modify the HTML before sending it to the browser.
ob_start();

// Load all the helper functions (database queries, search, filtering, etc.).
include "index_function.php";

// Default values.
$result = null;
$itemsPerPage = 5;

// Read the current page number from the URL. Default to page 1.
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;

// Make sure the page number is at least 1.
if ($page < 1) {
    $page = 1;
}

// Check if the user is viewing AI match results.
$isMatchResults = isset($_GET["matches"]);
$totalItems = 0;

// ---------- CASE 1: SHOW A SINGLE ITEM ----------
if (isset($_GET["item_id"])) {

    // Convert the item ID to an integer.
    $item_id = (int)$_GET["item_id"];

    // Fetch that specific item.
    $result = single_item_data($item_id);
    $totalItems = mysqli_num_rows($result);

// ---------- CASE 2: SHOW AI MATCH RESULTS ----------
} elseif ($isMatchResults) {

    // Determine which matching results the user wants to view
    $matchType = $_GET["matches"];

    // Get the matching item IDs from the session (saved by match.php).
    if ($matchType === "image") {
        $matchIds = $_SESSION["image_matches"] ?? [];
    } elseif ($matchType === "description") {
        $matchIds = $_SESSION["description_matches"] ?? [];
    } else {
        $matchIds = [];
    }

    // Keep only valid, unique item IDs
    // - intval: convert to integer
    // - array_filter: remove anything <= 0
    // - array_unique: remove duplicates
    // - array_values: reset keys
    // - array_slice: keep only the first 5
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

        // Open a new database connection for the match query.
        $matchConn = new mysqli("localhost","root","","itmp");

        if ($matchConn->connect_error) {
            die("Database connection failed.");
        }

        $matchConn->set_charset("utf8mb4");

        // Build a comma-separated list of IDs to use in the IN clause.
        $idList = implode(",", $matchIds);

        // Fetch items in the same order as the AI ranked them.
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

// ---------- CASE 3: SHOW FILTERED RESULTS ----------
} elseif (
    isset($_GET["category"]) ||
    isset($_GET["location"]) ||
    isset($_GET["date"])
) {

    // Read filter values from the URL.
    $category = $_GET["category"] ?? "";
    $location = $_GET["location"] ?? "";
    $date = $_GET["date"] ?? "";

    // Fetch filtered items for the current page.
    $result = filter_data(
        $category,
        $location,
        $date,
        $page,
        $itemsPerPage
    );

    // Count how many items match the filter in total.
    $totalItems = total_filter_items(
        $category,
        $location,
        $date
    );

// ---------- CASE 4: SHOW SEARCH RESULTS ----------
} elseif (isset($_GET["query"]) && $_GET["query"] !== "") {

    $query = $_GET["query"];

    // Search by keyword.
    $result = search_data(
        $query,
        $page,
        $itemsPerPage
    );

    $totalItems = total_search_items($query);

// ---------- CASE 5: SHOW ALL ITEMS ----------
} else {

    // Default view: show all items with pagination.
    $result = all_data($page, $itemsPerPage);
    $totalItems = total_items();
}

// Calculate how many pages we need.
$totalPages = (int)ceil($totalItems / $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>index</title>
    <!-- Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Main stylesheet -->
    <link rel="stylesheet" href="style.css">
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

        <!-- Search by keyword. -->
        <form action="index.php#board" method="GET">
            <input type="search" name="query" placeholder="Search">
            <a href="index.php#board" class="btn">View All</a>
        </form>

        <!-- Filter menu (collapsible). -->
        <details class="filter-container">
            <summary class="filter-button">
                <i class="fas fa-filter"></i>
            </summary>
            <div class="filter-menu">

                <form action="index.php#board" method="GET">

                    <!-- Category filter -->
                    <label for="category">Category:</label>

                    <select name="category" id="category">
                        <option value="">All</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Book & Stationery">Book & Stationery</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Keys & Cards">Keys & Cards</option>
                        <option value="Accessories">Accessories</option>
                        <option value="Personal Items">Personal Items</option>
                        <option value="Others">Others</option>
                    </select>

                    <!-- Location filter -->
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

                    <!-- Date filter -->
                    <label for="date">Found Date:</label>
                    <input type="date" name="date" id="date">

                    <button type="submit" class="apply-button">
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

        <!-- Show the correct title depending on which match type was selected. -->
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

    <!-- Table header -->
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
    // If there are results, loop through them.
    if ($result && mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_array($result)) {

        // Check if the item has a valid image stored in the database.
        $imageInfo = false;
        $src = "";

        if (!empty($row["ItemImage"])) {
            $imageInfo = getimagesizefromstring($row["ItemImage"]);

            if ($imageInfo !== false) {
                // Encode the raw image bytes as a base64 data URL.
                $imageData = base64_encode($row["ItemImage"]);

                $src = "data:" . $imageInfo["mime"]
                    . ";base64," . $imageData;
            }
        }

            // (Reset source before rebuilding it.)
            $src = "";

            if ($imageInfo !== false) {
                $imageData = base64_encode($row["ItemImage"]);

                $src = "data:" . $imageInfo["mime"]
                    . ";base64," . $imageData;
            }

            // Escape item details before printing to HTML.
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

            // Image column with click-to-enlarge popup.
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

            // Check whether this item already has a claim record.
            $alreadyClaimed = has_claim($itemId);

            echo "</td>

                    <td>$itemName</td>
                    <td>$category</td>
                    <td>$location</td>
                    <td>$date</td>
                    <td>$description</td>

                    <td>";

            if ($alreadyClaimed) {
                // If already claimed, show a popup explaining it.
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
                // If not claimed, redirect user to the claim page.
                echo "<a href='claim.php?item_id=$itemId'
                        class='claim_item'>CLAIM</a>";
            }

            echo "</td>
                </tr>";
        }

    // ---------- EMPTY RESULTS: AI MATCH ----------
    } elseif ($isMatchResults) {

        echo "<tr>
                <td colspan='7'>
                    No matching items found.
                    Please try another image or description.
                </td>
              </tr>";

    // ---------- EMPTY RESULTS: SEARCH ----------
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

    // Previous page link.
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

    // Page number links.
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

    // Next page link.
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
// Send the final buffered HTML to the browser.
$html_output = ob_get_clean();
echo $html_output;
?>