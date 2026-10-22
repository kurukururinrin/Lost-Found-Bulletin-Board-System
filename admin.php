<?php
// This session is used to store and check user login status
session_start();

// Check if the user is logged in successfully
// $_SESSION["login"] = true if the user is logged in
// $_SESSION["login"] = false if the user is not logged in
// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION["login"]) || $_SESSION["login"] !== true) {
    header("Location: login.php");
    exit();
}

require_once "admin_functions.php";
cleanupExpiredClaims();

// Get the item ID that the user wants to edit
$editItem = $_GET["edit_item"] ?? "";
$removeItem = $_GET["remove"] ?? "";

// Get the current page number
$page = isset($_GET["page"]) ? (int)$_GET["page"] : 1;

// Make sure the page number is at least 1
if ($page < 1) {
    $page = 1;
}

// Set the number of items displayed on each page
$itemsPerPage = 5;

// Calculate the starting position of the current page
$offset = ($page - 1) * $itemsPerPage;

// Get the items for the current page
$result = searchItems($itemsPerPage, $offset);

// Get the total number of items based on the current search and filters
$totalItems = getTotalItems();

// Calculate the total number of pages
$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));

// Prevent users from accessing a page beyond the last page
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $itemsPerPage;

    $result = searchItems($itemsPerPage, $offset);
}

// Store the current search and filter values
$search = $_GET["search"] ?? "";
$category = $_GET["category"] ?? "";
$foundLocation = $_GET["found_location"] ?? "";
$foundDate = $_GET["found_date"] ?? "";
$claimDate = $_GET["claim_date"] ?? "";
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Page</title>
        <link rel="stylesheet" href="admin_style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    </head>
    <body>

        <div class="admin-container">
            <div class="admin-header">
                <!-- SEARCH + FILTER -->
                <form action="admin.php" method="GET" class="search-filter-form">
                    <!-- SEARCH BAR -->
                    <div class="searchbar-container">
                        <input type="text"
                            id="searchbar"
                            name="search"
                            placeholder="Search...">
                    </div>
                    <!-- VIEW ALL -->
                    <a href="admin.php" class="viewall-button">
                            View All
                    </a>
                    <!-- FILTER -->
                    <details class="filter-container">
                        <summary class="filter-button">
                            <i class="fas fa-filter"></i>
                        </summary>
                        <div class="filter-menu">
                            <!-- CATEGORY -->
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="Electronics" <?php if ($category == "Electronics") echo "selected"; ?>>Electronics</option>
                                <option value="Book & Stationery" <?php if ($category == "Book & Stationery") echo "selected"; ?>>Book & Stationery</option>
                                <option value="Clothing" <?php if ($category == "Clothing") echo "selected"; ?>>Clothing</option>
                                <option value="Keys & Cards" <?php if ($category == "Keys & Cards") echo "selected"; ?>>Keys & Cards</option>
                                <option value="Accessories" <?php if ($category == "Accessories") echo "selected"; ?>>Accessories</option>
                                <option value="Personal Items" <?php if ($category == "Personal Items") echo "selected"; ?>>Personal Items</option>
                                <option value="Others" <?php if ($category == "Others") echo "selected"; ?>>Others</option>
                            </select>
                            <!-- FOUND LOCATION -->
                            <label for="found_location">Found Location</label>
                            <select id="found_location" name="found_location">
                                <option value="">All Locations</option>
                                <option value="A1" <?php if ($foundLocation == "A1") echo "selected"; ?>>A1</option>
                                <option value="A2" <?php if ($foundLocation == "A2") echo "selected"; ?>>A2</option>
                                <option value="A3" <?php if ($foundLocation == "A3") echo "selected"; ?>>A3</option>
                                <option value="A4" <?php if ($foundLocation == "A4") echo "selected"; ?>>A4</option>
                                <option value="A5" <?php if ($foundLocation == "A5") echo "selected"; ?>>A5</option>
                                <option value="B1" <?php if ($foundLocation == "B1") echo "selected"; ?>>B1</option>
                                <option value="D6" <?php if ($foundLocation == "D6") echo "selected"; ?>>D6</option>
                                <option value="LY3" <?php if ($foundLocation == "LY3") echo "selected"; ?>>LY3</option>
                            </select>
                            <!-- FOUND DATE -->
                            <label for="found_date">Found Date</label>
                            <input type="date" id="found_date" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">
                            <!-- CLAIM DATE -->
                            <label for="claim_date">Claim Date</label>
                            <input type="date" id="claim_date" name="claim_date" value="<?php echo htmlspecialchars($claimDate); ?>">                            
                            <!-- APPLY -->
                            <button type="submit" class="apply-button">Apply Filters</button>
                        </div>
                    </details>
                </form>
                
                <!-- MANAGE CLAIMS ** -->
                <a href="admin_claims.php" class="viewall-button">
                    Manage Claims
                </a>

                <!-- LOGOUT -->
                <form action="admin_functions.php" method="POST">
                    <button type="submit" name="logout" class="logout-button">
                        Logout
                    </button>
                </form>
                
            </div>

            <!--LOST ITEMS-->
            <div class="admin-content">
                <div class="table-container">
                    <section class="table-header">
                        <h2>Lost Item Bulletin Board</h2>
                    </section>
                    <table>
                        <thead>
                            <tr>
                                <th>Item Picture</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Found Location</th>
                                <th>Found Date</th>
                                <th>Claim Date</th>
                                <th>Description</th>
                                <th>Actions</th>
                                <th>
                                    <div class="header-space"></div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Lost items data will be populated here using php -->
                            <?php
                                while ($row = $result->fetch_assoc()) {
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($row["ItemImage"])) { ?>
                                            <?php
                                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                                            $imageType = $finfo->buffer($row["ItemImage"]);
                                            ?>
                                            <input type="checkbox" id="image<?php echo $row["Item_ID"]; ?>" class="image-checkbox">
                                            <label for="image<?php echo $row["Item_ID"]; ?>">
                                                <img src="data:<?php echo $imageType; ?>;base64,<?php echo base64_encode($row["ItemImage"]); ?>"
                                                    alt="Item">
                                            </label>
                                            <div class="image-popup">
                                                <label for="image<?php echo $row["Item_ID"]; ?>" class="popup-background"></label>
                                                <img src="data:<?php echo $imageType; ?>;base64,<?php echo base64_encode($row["ItemImage"]); ?>"
                                                    alt="Item">
                                            </div>
                                        <?php } else { ?>
                                            No Image
                                        <?php } ?>
                                    </td>

                                    <!-- ITEM NAME -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <input form="editForm<?php echo $row["Item_ID"]; ?>"
                                                type="text"
                                                name="item_name"
                                                value="<?php echo htmlspecialchars($row["ItemName"]); ?>">
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars($row["ItemName"]); ?>
                                        <?php } ?>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <select form="editForm<?php echo $row["Item_ID"]; ?>" name="item_category">
                                                <option value="Electronics" <?php if ($row["ItemCategory"] == "Electronics") echo "selected"; ?>>Electronics</option>
                                                <option value="Book & Stationery" <?php if ($row["ItemCategory"] == "Book & Stationery") echo "selected"; ?>>Book & Stationery</option>
                                                <option value="Clothing" <?php if ($row["ItemCategory"] == "Clothing") echo "selected"; ?>>Clothing</option>
                                                <option value="Keys & Cards" <?php if ($row["ItemCategory"] == "Keys & Cards") echo "selected"; ?>>Keys & Cards</option>
                                                <option value="Accessories" <?php if ($row["ItemCategory"] == "Accessories") echo "selected"; ?>>Accessories</option>
                                                <option value="Personal Items" <?php if ($row["ItemCategory"] == "Personal Items") echo "selected"; ?>>Personal Items</option>
                                                <option value="Others" <?php if ($row["ItemCategory"] == "Others") echo "selected"; ?>>Others</option>
                                            </select>
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars($row["ItemCategory"]); ?>
                                        <?php } ?>
                                    </td>

                                    <!-- FOUND LOCATION -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <select form="editForm<?php echo $row["Item_ID"]; ?>" name="found_location">
                                                <option value="A1" <?php if ($row["FoundLocation"] == "A1") echo "selected"; ?>>A1</option>
                                                <option value="A2" <?php if ($row["FoundLocation"] == "A2") echo "selected"; ?>>A2</option>
                                                <option value="A3" <?php if ($row["FoundLocation"] == "A3") echo "selected"; ?>>A3</option>
                                                <option value="A4" <?php if ($row["FoundLocation"] == "A4") echo "selected"; ?>>A4</option>
                                                <option value="A5" <?php if ($row["FoundLocation"] == "A5") echo "selected"; ?>>A5</option>
                                                <option value="B1" <?php if ($row["FoundLocation"] == "B1") echo "selected"; ?>>B1</option>
                                                <option value="D6" <?php if ($row["FoundLocation"] == "D6") echo "selected"; ?>>D6</option>
                                                <option value="LY3" <?php if ($row["FoundLocation"] == "LY3") echo "selected"; ?>>LY3</option>
                                            </select>
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars($row["FoundLocation"]); ?>
                                        <?php } ?>
                                    </td>

                                    <!-- FOUND DATE -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <input form="editForm<?php echo $row["Item_ID"]; ?>"
                                                type="date"
                                                name="found_date"
                                                value="<?php echo htmlspecialchars($row["FoundDate"]); ?>">
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars($row["FoundDate"]); ?>
                                        <?php } ?>
                                    </td>

                                    <!-- CLAIM DATE -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <input form="editForm<?php echo $row["Item_ID"]; ?>"
                                                type="date"
                                                name="claim_date"
                                                value="<?php echo htmlspecialchars($row["ClaimDate"] ?? ''); ?>">
                                        <?php } else { ?>
                                            <?php if (!empty($row["ClaimDate"])) { ?>
                                                <?php echo htmlspecialchars($row["ClaimDate"]); ?>
                                            <?php } else { ?>
                                                —
                                            <?php } ?>
                                        <?php } ?>
                                    </td>

                                    <!-- DESCRIPTION -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <textarea form="editForm<?php echo $row["Item_ID"]; ?>"
                                                name="item_description"><?php echo htmlspecialchars($row["ItemDescription"]); ?></textarea>
                                        <?php } else { ?>
                                            <?php echo htmlspecialchars($row["ItemDescription"]); ?>
                                        <?php } ?>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="actions">
                                            <!-- SHOW BUTTON -->
                                            <form action="admin_functions.php" method="POST">

                                                <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                <input type="hidden" name="found_location" value="<?php echo htmlspecialchars($foundLocation); ?>">
                                                <input type="hidden" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">
                                                <input type="hidden" name="claim_date" value="<?php echo htmlspecialchars($claimDate); ?>">
                                            
                                                <?php if ($row["ItemStatus"] == "Available") { ?>
                                                    <button type="submit" 
                                                            name="show_item" 
                                                            value="<?php echo $row["Item_ID"]; ?>" 
                                                            class="show-button found-button">
                                                        <i class="fa-solid fa-eye-slash"></i>Hide
                                                    </button>
                                                <?php } else { ?>
                                                    <button type="submit" 
                                                            name="show_item" 
                                                            value="<?php echo $row["Item_ID"]; ?>" 
                                                            class="show-button">
                                                        <i class="fa-solid fa-eye"></i>Show
                                                    </button>
                                                <?php } ?>

                                            </form>

                                            <!-- REMOVE BUTTON --> 
                                            <form action="admin.php" method="GET">  

                                                <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                <input type="hidden" name="found_location" value="<?php echo htmlspecialchars($foundLocation); ?>">
                                                <input type="hidden" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">

                                                <button type="submit" name="remove" value="<?php echo $row["Item_ID"]; ?>" class="remove-button">  
                                                    <i class="fa-solid fa-circle-xmark"></i>Remove  
                                                </button>  

                                            </form>
                                        </div>
                                    </td>

                                    <!-- EDIT / SAVE -->
                                    <td>
                                        <?php if ($editItem == $row["Item_ID"]) { ?>
                                            <form action="admin_functions.php" method="POST" id="editForm<?php echo $row["Item_ID"]; ?>"> 
                                                <input type="hidden" name="item_id" value="<?php echo $row["Item_ID"]; ?>"> 
                                                <input type="hidden" name="finder_id" value="<?php echo $row["Finder_ID"]; ?>">
                                                <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                <input type="hidden" name="found_location_filter" value="<?php echo htmlspecialchars($foundLocation); ?>">
                                                <input type="hidden" name="found_date_filter" value="<?php echo htmlspecialchars($foundDate); ?>">
                                                <input type="hidden" name="claim_date_filter" value="<?php echo htmlspecialchars($claimDate); ?>">
                                            </form>

                                            <button form="editForm<?php echo $row["Item_ID"]; ?>" type="submit" name="save_item" class="save-button">
                                                <i class="fa-solid fa-save"></i>
                                            </button>
                                        <?php } else { ?> 
                                            <form action="admin.php" method="GET"> 
                                                <input type="hidden" name="edit_item" value="<?php echo $row["Item_ID"]; ?>">
                                                <input type="hidden" name="page" value="<?php echo $page; ?>">
                                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                                <input type="hidden" name="found_location" value="<?php echo htmlspecialchars($foundLocation); ?>">
                                                <input type="hidden" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">
                                                <input type="hidden" name="claim_date" value="<?php echo htmlspecialchars($claimDate); ?>">

                                                <button type="submit" class="edit-button"> 
                                                    <i class="fa-solid fa-pen"></i> 
                                                </button> 
                                            </form> 
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <!-- PAGINATION -->
                    <div class="pagination" id="pagination">

                        <?php
                        // Create the current search and filter parameters
                        $paginationParams = [
                            "search" => $search,
                            "category" => $category,
                            "found_location" => $foundLocation,
                            "found_date" => $foundDate,
                            "claim_date" => $claimDate
                        ];

                        // Remove empty values
                        $paginationParams = array_filter($paginationParams, function ($value) {
                            return $value !== "";
                        });
                        ?>

                        <!-- PREVIOUS PAGE -->
                        <?php if ($page > 1) { ?>
                            <?php
                            $paginationParams["page"] = $page - 1;
                            ?>
                            <a href="admin.php?<?php echo http_build_query($paginationParams); ?>">&lt;</a>
                        <?php } ?>

                        <!-- PAGE NUMBERS -->
                        <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
                            <?php
                            $paginationParams["page"] = $i;
                            ?>
                            <a href="admin.php?<?php echo http_build_query($paginationParams); ?>"
                            class="<?php echo ($page == $i) ? 'active-page' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php } ?>

                        <!-- NEXT PAGE -->
                        <?php if ($page < $totalPages) { ?>
                            <?php
                            $paginationParams["page"] = $page + 1;
                            ?>
                            <a href="admin.php?<?php echo http_build_query($paginationParams); ?>">&gt;</a>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- REMOVE ITEM CONFIRMATION POPUP -->
        <?php if ($removeItem != "") { ?>
            <div class="remove-popup">
                <div class="remove-popup-box">
                    <h3>Confirm Remove</h3>
                    <p>Are you sure you want to remove this item?</p>

                    <div class="remove-popup-buttons">
                        <!-- YES -->
                        <form action="admin_functions.php" method="POST"> 
                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($removeItem); ?>">
                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <input type="hidden" name="found_location" value="<?php echo htmlspecialchars($foundLocation); ?>">
                            <input type="hidden" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">
                            <input type="hidden" name="claim_date" value="<?php echo htmlspecialchars($claimDate); ?>">
                            <button type="submit" name="confirm_remove" class="confirm-remove-button">Yes</button> 
                        </form>

                        <!-- CANCEL -->
                        <form action="admin.php" method="GET">

                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <input type="hidden" name="found_location" value="<?php echo htmlspecialchars($foundLocation); ?>">
                            <input type="hidden" name="found_date" value="<?php echo htmlspecialchars($foundDate); ?>">
                            <input type="hidden" name="claim_date" value="<?php echo htmlspecialchars($claimDate); ?>">
                            <button type="submit" class="cancel-remove-button">Cancel</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>

    </body>
</html>