<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function for logging out
function logout()
{
    // Clear all login information in the session
    session_unset();
    // End the current session
    session_destroy();

    // Redirect the user back to the home page
    header("Location: index.php");
    exit();
}

// Check if the logout button is clicked
// If the logout button is clicked, call the logout function
if (isset($_POST["logout"])) {
    logout();
}

// Function for connecting to the database**might be changed later
function get_db_connection()
{
    require_once __DIR__ . '/dbconnection.php';

    return db_connect();
}

// Function for searching items
// This function is used to search for items in the database with filtering options
function searchItems($limit, $offset)
{
    // Get the search keyword from the search bar
    $search = $_GET["search"] ?? "";
    // Get the selected category from the filter menu
    $category = $_GET["category"] ?? "";
    // Get the selected found location from the filter menu
    $foundLocation = $_GET["found_location"] ?? "";
    // Get the selected found date from the filter menu
    $foundDate = $_GET["found_date"] ?? "";
    // Get the selected claim date from the filter menu
    $claimDate = $_GET["claim_date"] ?? "";

    // Get the database connection
    $conn = get_db_connection();

    // Start the SQL query
    // JOIN finder because FoundDate and FoundLocation are stored in finder table
    $sql = "SELECT item.*, finder.FoundDate, finder.FoundLocation,
            ( SELECT MAX(cl.ClaimDate)
                    FROM claim AS cl
                    WHERE cl.Item_ID = item.Item_ID ) 
            AS ClaimDate
            FROM item
            JOIN finder
                ON item.Finder_ID = finder.Finder_ID
            WHERE 1=1";

    // Create an array to store the values for the SQL placeholders
    $params = [];
    // Store the data types of the values
    $types = "";

    // Add search condition if users enter a search keyword
    if (!empty($search)) {
        $sql .= " AND item.ItemName LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }
    // Add category condition if users select a category
    if (!empty($category)) {
        $sql .= " AND item.ItemCategory = ?";
        $params[] = $category;
        $types .= "s";
    }
    // Add found location condition if users select a found location
    if (!empty($foundLocation)) {
        $sql .= " AND finder.FoundLocation = ?";
        $params[] = $foundLocation;
        $types .= "s";
    }
    // Add found date condition if users select a found date
    if (!empty($foundDate)) {
        $sql .= " AND finder.FoundDate = ?";
        $params[] = $foundDate;
        $types .= "s";
    }
    // Add claim date condition if users select a claim date
    if (!empty($claimDate)) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM claim AS cl
                    WHERE cl.Item_ID = item.Item_ID
                    AND cl.ClaimDate = ?
                  )";
        $params[] = $claimDate;
        $types .= "s";
    }

    // Add pagination
    // One row per item, with consistent pagination
    $sql .= " ORDER BY item.Item_ID ASC LIMIT ? OFFSET ?";

    // Add LIMIT and OFFSET values
    $params[] = $limit;
    $params[] = $offset;
    // Add integer types for LIMIT and OFFSET
    $types .= "ii";

    // Prepare the SQL query
    $stmt = $conn->prepare($sql);

    // Bind the values to the placeholders
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    // Execute the SQL query
    $stmt->execute();
    // Get the search results
    $result = $stmt->get_result();

    // Close the statement and database connection
    $stmt->close();
    $conn->close();

    // Return the search results
    return $result;
}

// Function for getting the total number of items
// This function counts the items based on the current search and filters
function getTotalItems()
{
    // Get the search keyword from the search bar
    $search = $_GET["search"] ?? "";
    // Get the selected category from the filter menu
    $category = $_GET["category"] ?? "";
    // Get the selected found location from the filter menu
    $foundLocation = $_GET["found_location"] ?? "";
    // Get the selected found date from the filter menu
    $foundDate = $_GET["found_date"] ?? "";
    // Get the selected claim date from the filter menu
    $claimDate = $_GET["claim_date"] ?? "";

    // Get the database connection
    $conn = get_db_connection();

    // Start the SQL query
    $sql = "SELECT COUNT(*) AS total
            FROM item
            JOIN finder
                ON item.Finder_ID = finder.Finder_ID
            WHERE 1=1";

    // Create an array to store the values for the SQL placeholders
    $params = [];
    // Store the data types of the values
    $types = "";

    // Add search condition if users enter a search keyword
    if (!empty($search)) {
        $sql .= " AND item.ItemName LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }

    // Add category condition if users select a category
    if (!empty($category)) {
        $sql .= " AND item.ItemCategory = ?";
        $params[] = $category;
        $types .= "s";
    }

    // Add found location condition if users select a found location
    if (!empty($foundLocation)) {
        $sql .= " AND finder.FoundLocation = ?";
        $params[] = $foundLocation;
        $types .= "s";
    }

    // Add found date condition if users select a found date
    if (!empty($foundDate)) {
        $sql .= " AND finder.FoundDate = ?";
        $params[] = $foundDate;
        $types .= "s";
    }

    // Add claim date condition if users select a claim date
    if (!empty($claimDate)) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM claim AS cl
                    WHERE cl.Item_ID = item.Item_ID
                    AND cl.ClaimDate = ?
                  )";
        $params[] = $claimDate;
        $types .= "s";
    }

    // Prepare the SQL query
    $stmt = $conn->prepare($sql);

    // Bind the values to the placeholders
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get the total number of items
    $row = $result->fetch_assoc();

    $stmt->close();
    $conn->close();

    return $row["total"];
}

// Function for showing or hiding items
function showItems($itemID)
{
    // Get the database connection
    $conn = get_db_connection();

    // Get the current status of the item
    $sql = "SELECT ItemStatus FROM item WHERE Item_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemID);
    $stmt->execute();

    // Get the current status
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Change the status only between Under Review and Available
    if ($row["ItemStatus"] == "Under Review") {
        $newStatus = "Available";
    } elseif ($row["ItemStatus"] == "Available") {
        $newStatus = "Under Review";
    } else {
        // Do not change other statuses
        $stmt->close();
        $conn->close();
        return;
    }

    // Update the item status
    $sql = "UPDATE item
            SET ItemStatus = ?
            WHERE Item_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $newStatus, $itemID);
    $stmt->execute();

    $stmt->close();
    $conn->close();
}

// If the show items button is clicked, items are shown at home page
if (isset($_POST["show_item"])) { 
    showItems($_POST["show_item"]); 

    $params = http_build_query([
        "page" => $_POST["page"] ?? 1,
        "search" => $_POST["search"] ?? "",
        "category" => $_POST["category"] ?? "",
        "found_location" => $_POST["found_location"] ?? "",
        "found_date" => $_POST["found_date"] ?? "",
        "claim_date" => $_POST["claim_date"] ?? ""
    ]);

    header("Location: admin.php?" . $params); 
    exit(); 
}

// Function for removing items
function removeItems($itemID)
{
    $conn = get_db_connection();

    // Delete the item from the database
    $sql = "DELETE FROM item WHERE Item_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemID);
    $stmt->execute();

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}

// If the remove confirmation button is clicked, remove the item from the database
if (isset($_POST["confirm_remove"])) { 
    removeItems($_POST["item_id"]); 

    $page = $_POST["page"] ?? 1;
    $search = $_POST["search"] ?? "";
    $category = $_POST["category"] ?? "";
    $foundLocation = $_POST["found_location"] ?? "";
    $foundDate = $_POST["found_date"] ?? "";
    $claimDate = $_POST["claim_date"] ?? "";

    $params = http_build_query([
        "page" => $page,
        "search" => $search,
        "category" => $category,
        "found_location" => $foundLocation,
        "found_date" => $foundDate,
        "claim_date" => $claimDate
    ]);

    header("Location: admin.php?" . $params); 
    exit(); 
}

// Function for updating item information
function updateItem($itemID, $itemName, $itemCategory, $itemDescription, $finderID, $foundLocation, $foundDate, $claimDate)
{
    $conn = get_db_connection();

    // Update item information in the item table
    $sql = "UPDATE item
            SET ItemName = ?, ItemCategory = ?, ItemDescription = ?
            WHERE Item_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $itemName, $itemCategory, $itemDescription, $itemID);
    $stmt->execute();

    // Update found location and found date in the finder table
    $sql = "UPDATE finder
            SET FoundLocation = ?, FoundDate = ?
            WHERE Finder_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $foundLocation, $foundDate, $finderID);
    $stmt->execute();

    // Update claim date in the claim table
    if ($claimDate !== '') {
        $sql = "UPDATE claim
                SET ClaimDate = ?
                WHERE Item_ID = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $claimDate, $itemID);
        $stmt->execute();
    }
}

// If the save button is clicked, update the item
if (isset($_POST["save_item"])) { 
    updateItem( 
        $_POST["item_id"], 
        $_POST["item_name"], 
        $_POST["item_category"], 
        $_POST["item_description"], 
        $_POST["finder_id"], 
        $_POST["found_location"], 
        $_POST["found_date"],
        $_POST["claim_date"]
    ); 

    $params = http_build_query([
        "page" => $_POST["page"] ?? 1,
        "search" => $_POST["search"] ?? "",
        "category" => $_POST["category"] ?? "",
        "found_location" => $_POST["found_location_filter"] ?? "",
        "found_date" => $_POST["found_date_filter"] ?? "",
        "claim_date" => $_POST["claim_date_filter"] ?? ""
    ]);

    header("Location: admin.php?" . $params); 
    exit(); 
}

?>