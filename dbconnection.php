<?php

function db_connect()
{
    $conn = new mysqli("localhost", "root", "", "itmp");

    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }

    return $conn;
}

if (!function_exists('get_db_connection')) {
    function get_db_connection()
    {
        return db_connect();
    }
}