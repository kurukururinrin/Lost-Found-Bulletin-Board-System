<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><link rel="stylesheet" href="style.css">
    </head>

    <header>
        <nav class="navbar">
            <a href="index.php"><i class="fa-solid fa-house-chimney"></i>HOME</a>
            <a href="report.php"><i class="fa-solid fa-file-lines"></i>REPORT LOST ITEM</a>
            <a href="match.php"><i class="fa-solid fa-code-compare"></i>MATCH ITEMS</a>
            <a href="policy.php"><i class="fa-solid fa-key"></i>TERMS AND CONDITIONS</a>
            <a href="login.php"><i class="fa-solid fa-user"></i>ADMIN</a>
        </nav>
    </header>
    
    <body>

        <div class="login-container">
            <div class="login-box">

                <!-- Login form goes here -->
                <h1>Admin Login</h1>
                <form action="login_function.php" method="POST">

                    <!-- Error message goes here -->
                    <?php
                    session_start();

                    // Check whether an error message exists
                    if (isset($_SESSION["error"])) {
                    ?>
                        <div class="error-message">
                            <?php echo $_SESSION["error"]; ?>
                        </div>
                    <?php
                        // Remove the error message after displaying it
                        unset($_SESSION["error"]);
                    }
                    ?>

                    <!-- Staff ID field goes here -->
                    <label for="staffid">Staff ID</label>
                    <input type="text"
                        id="staffid"
                        name="staffid"
                        placeholder="Enter Staff ID (e.g. 12345678)"
                        required><br><br>

                    <!-- Password field goes here -->
                    <label for="password">Password</label>
                    <input type="password"
                        id="password"
                        name="password"
                        placeholder="Enter Password"
                        required><br><br>

                    <!-- Login button goes here -->
                    <button type="submit" class="login-button">Login</button>
                </form>

            </div>
    </body>
</html>