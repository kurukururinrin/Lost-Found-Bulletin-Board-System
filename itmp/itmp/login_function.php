<?php
session_start();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // Retrieve the staff ID and password from the login form
        $staffid = $_POST["staffid"];
        $password = $_POST["password"];

        // Check whether the staff ID and password are both correct
        if (
            ($staffid === "admin001" && $password === "admpwd123") ||
            ($staffid === "admin002" && $password === "admpwd456") ||
            ($staffid === "admin003" && $password === "admpwd789")
        ) {
            $_SESSION["login"] = true;
            
            // Redirect to the admin page
            header("Location: admin.php");
            exit();

        } else {

            $_SESSION["error"] = "Error: Invalid Staff ID or Password. Please try again.";
            header("Location: login.php");

            exit();
        }
    }
    ?>