<?php
/* show all data on board */    
function all_data($page = 1 , $itemsPerPage = 5) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        $offset = ($page -1) * $itemsPerPage;
        $sql = "SELECt item.*, finder.FoundDate, finder.FoundLocation
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                WHERE item.ItemStatus = 'Available'
                LIMIT $offset, $itemsPerPage";
        $result = mysqli_query($dbconnection, $sql);

        return $result;
}

/* search */    
function search_data($query, $page, $itemsPerPage = 5) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        // Calculate which row to start from
        $offset = ($page - 1) * $itemsPerPage;
        $sql = "SELECt item.*, finder.FoundDate, finder.FoundLocation
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID 
                WHERE item.ItemStatus = 'Available'
                AND item.ItemName LIKE '%$query%'
                LIMIT $offset, $itemsPerPage";
        $result = mysqli_query($dbconnection, $sql);

        return $result;
}

/*count total number of items */
function total_items() {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        $sql = "SELECT COUNT(*) AS total
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                WHERE item.ItemStatus = 'Available'";
        $result = mysqli_query($dbconnection,$sql);
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
}

/*count total number of search results */
function total_search_items($query) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        $sql = "SELECT COUNT(*) AS total
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                WHERE item.ItemStatus = 'Available'
                AND item.ItemName LIKE '%$query%'";
        $result = mysqli_query($dbconnection,$sql);
        $row = mysqli_fetch_assoc($result);
        return $row['total'];
}

/* filter */
function filter_data($category, $location, $date, $page = 1, $itemsPerPage = 5) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        $offset = ($page - 1) * $itemsPerPage;
        $conditions = ["item.ItemStatus = 'Available'"];

        if ($category !== "") {
                $conditions[] = "item.ItemCategory = '$category'";
        }

        if ($location !== "") {
                $conditions[] = "finder.FoundLocation = '$location'";
        }

        if ($date !== "") {
                $conditions[] = "finder.FoundDate = '$date'";
        }
        $where = "";

        if (count($conditions) > 0) {
                $where = "WHERE " . implode(" AND ", $conditions);
        }

        $sql = "SELECT item.*, finder.FoundDate, finder.FoundLocation
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                $where
                LIMIT $offset, $itemsPerPage";
        $result = mysqli_query($dbconnection, $sql);

        return $result;
}


/* count filtered items */
function total_filter_items($category, $location, $date) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());
        $conditions = ["item.ItemStatus = 'Available'"];

        if ($category !== "") {
                $conditions[] = "item.ItemCategory = '$category'";
        }

        if ($location !== "") {
                $conditions[] = "finder.FoundLocation = '$location'";
        }

        if ($date !== "") {
                $conditions[] = "finder.FoundDate = '$date'";
        }

        $where = "";

        if (count($conditions) > 0) {
                $where = "WHERE " . implode(" AND ", $conditions);
        }

        $sql = "SELECT COUNT(*) AS total
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                $where";
        $result = mysqli_query($dbconnection, $sql);
        $row = mysqli_fetch_assoc($result);

        return $row['total'];
}

/* show one specific item */
function single_item_data($item_id) {

        $dbconnection = new mysqli('localhost','root','','itmp') OR die (mysqli_connect_error());

        $sql = "SELECT item.*, finder.FoundDate, finder.FoundLocation
                FROM item
                JOIN finder ON item.Finder_ID = finder.Finder_ID
                WHERE item.Item_ID = $item_id";

        $result = mysqli_query($dbconnection, $sql);

        return $result;
}


/* CHECK IF ITEM ALREADY HAS A CLAIM */

function has_claim($item_id) {
    $dbconnection = new mysqli('localhost','root','','itmp')
        OR die(mysqli_connect_error());

    $sql = "SELECT COUNT(*) AS total
            FROM `claim`
            WHERE Item_ID = ?
            AND Claimant_ID IS NOT NULL";

    $stmt = mysqli_prepare($dbconnection, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return ((int)$row['total']) > 0;
}
?>