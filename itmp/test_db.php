
<?php
require_once __DIR__ . '/dbconnection.php';

try {
    $conn = db_connect();

    foreach (['finder', 'item'] as $table) {
        $result = $conn->query(
            "SELECT TABLE_NAME, ENGINE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '$table'"
        );

        $row = $result->fetch_assoc();

        echo htmlspecialchars($row['TABLE_NAME'])
            . ': '
            . htmlspecialchars($row['ENGINE'] ?? 'Unknown')
            . '<br>';
    }

    $conn->close();

} catch (Throwable $e) {
    error_log("Engine check failed: " . $e->getMessage());
    echo "Check failed. Please check the Apache error log.";
}
?>
