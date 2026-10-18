<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db_connect(): mysqli
{
    $host = getenv('DB_HOST') ?: 'mysql-18b78669-chongelaine852-0b16.c.aivencloud.com';
    $user = getenv('DB_USER') ?: 'avnadmin';
    $password = getenv('DB_PASSWORD') ?: 'AVNS_R7r4Uqys86H5XsnI5Aq';
    $database = getenv('DB_NAME') ?: 'itmp';
    $port = (int)(getenv('DB_PORT') ?: '28583');
    $ca = getenv('DB_SSL_CA') ?: 'C:\xampp\htdocs\itmp\ca.pem';

     if ($password === false || $password === '') {
        throw new RuntimeException('DB_PASSWORD is not configured.');
    }

    if (!is_file($ca)) {
        throw new RuntimeException('Aiven CA certificate file was not found.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    $conn->ssl_set(null, null, $ca, null, null);

    $conn->real_connect(
        $host,
        $user,
        $password,
        $database,
        $port,
        null,
        MYSQLI_CLIENT_SSL
    );

    $conn->set_charset('utf8mb4');

    return $conn;
}