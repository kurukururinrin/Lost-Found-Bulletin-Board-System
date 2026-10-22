<?php

/**
 * Create and return a new database connection.
 * Stops the script if the connection fails.
 */
function db_connect()
{
    // Open a new MySQL connection using mysqli.
    $conn = new mysqli("localhost", "root", "", "itmp");

    // Check if the connection failed and stop the script with an error.
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    // Return the active connection object.
    return $conn;
}

// Only define the wrapper function if it does not already exist.
// This prevents "Cannot redeclare function" fatal errors if the file is included twice.
if (!function_exists('get_db_connection')) {
    
    /**
     * Alias / wrapper for db_connect().
     * Some files call get_db_connection() instead of db_connect().
     */
    function get_db_connection()
    {
        return db_connect();
    }
}