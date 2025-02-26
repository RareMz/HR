<?php
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_38400422');
define('DB_PASS', 'EodMm3v94mKfz6');
define('DB_NAME', 'if0_38400422_hr_ttv');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// เพิ่มบรรทัดนี้เพื่อกำหนด charset
$conn->set_charset("utf8mb4");
?>