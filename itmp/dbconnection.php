<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db_connect(): mysqli
{
    $host = getenv('DB_HOST') ?: 'sql213.infinityfree.com';
    $user = getenv('DB_USER') ?: 'if0_43207743';
    $password = getenv('DB_PASSWORD') ?: 'scL0OruaswcGrI';
    $database = getenv('DB_NAME') ?: 'if0_43207743_itmp';
    $port = (int)(getenv('DB_PORT') ?: '3306');

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $conn->real_connect(
        $host,
        $user,
        $password,
        $database,
        $port
    );

    $conn->set_charset('utf8mb4');

    return $conn;
}