<?php
/**
 * config.php - Core system configuration
 * 
 * เปรียบเสมือนศูนย์ควบคุมการซ้อมวิ่ง - กำหนดทุกอย่างตั้งแต่ pace, fuel, และเส้นทาง
 * ในโค้ดก็เช่นกัน - นี่คือจุดรวมการตั้งค่าระบบทั้งหมด และการเชื่อมต่อฐานข้อมูล
 */

// ค่าคงที่สำหรับการเชื่อมต่อฐานข้อมูล - เหมือนสเปคของรองเท้าวิ่งคู่โปรด
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hr_ttv');

// เชื่อมต่อฐานข้อมูล - เหมือนการสวมรองเท้าวิ่งและเริ่มวอร์มอัพ
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ตรวจสอบการเชื่อมต่อ - เช็คว่าผูกเชือกรองเท้าดีแล้วหรือยัง?
if ($conn->connect_error) {
    errorLog("Database connection failed: " . $conn->connect_error);
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// กำหนดค่า charset ให้รองรับภาษาไทย - เตรียมร่างกายให้พร้อมกับเส้นทางที่ท้าทาย
$conn->set_charset("utf8mb4");

// ค่าคงที่สำคัญของระบบ - เหมือน race strategy ที่ต้องวางแผนล่วงหน้า
define('SITE_KEY', 'hr_ttv_supply_chain_2025'); // สำหรับการเข้ารหัส
define('UPLOAD_PATH', __DIR__ . '/uploads/');   // พาธสำหรับอัปโหลดไฟล์
define('MAX_FILE_SIZE', 5 * 1024 * 1024);       // ขนาดไฟล์สูงสุด (5MB)
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']); // นามสกุลไฟล์ที่อนุญาต

// ตั้งค่า session - เหมือนการตั้งค่า smart watch ก่อนวิ่ง
if (!session_id()) {
    session_start();
    // เพิ่มความปลอดภัยให้กับ session - เหมือนการวิ่งในสวนสาธารณะที่มีการรักษาความปลอดภัย
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
}

/**
 * Redirect to a specific URL
 * เหมือนการเปลี่ยนเส้นทางวิ่งกลางคัน เมื่อพบเส้นทางที่ดีกว่า
 *
 * @param string $url The URL to redirect to
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if a user is logged in
 * เหมือนการเช็คว่าเรายังอยู่ใน race หรือไม่
 *
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the current user is an admin
 * เหมือนการเช็คว่าเราเป็น elite runner หรือไม่
 *
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Log error messages
 * เหมือนบันทึกข้อผิดพลาดในการวิ่งเพื่อปรับปรุงในอนาคต
 *
 * @param string $message The error message
 * @param string $severity The severity level (ERROR, WARNING, INFO)
 * @return void
 */
function errorLog($message, $severity = 'ERROR') {
    $logFile = __DIR__ . '/logs/error.log';
    $dir = dirname($logFile);
    
    // สร้างโฟลเดอร์เก็บล็อกถ้ายังไม่มี - เหมือนการเตรียมกระเป๋าเก็บของก่อนวิ่ง
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$severity] - $message" . PHP_EOL;
    
    // บันทึกล็อก - เหมือนการบันทึกสถิติการวิ่งเพื่อดูความก้าวหน้า
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Sanitize data to prevent XSS attacks
 * เหมือนการทำความสะอาดเส้นทางวิ่งให้ปลอดภัย ปราศจากหินหรือสิ่งกีดขวาง
 *
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data
 */
function clean($data) {
    // ถ้าเป็น array ให้ทำความสะอาดทุกค่าในอาร์เรย์ - เหมือนเช็คอุปกรณ์วิ่งทุกชิ้น
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = clean($value);
        }
        return $data;
    }
    
    // ทำความสะอาดข้อมูล - เหมือนการทำความสะอาดรองเท้าวิ่งให้พร้อมใช้งาน
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a random token
 * เหมือนการสร้าง race bib number เฉพาะตัวสำหรับแต่ละคน
 *
 * @param int $length The length of the token
 * @return string The generated token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate file upload
 * เหมือนการตรวจสอบอุปกรณ์ก่อนวิ่งมาราธอน
 *
 * @param array $file The $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return array Result with status and message
 */
function validateFile($file, $allowedTypes = null, $maxSize = null) {
    // ถ้าไม่ได้กำหนดประเภทไฟล์และขนาดไฟล์ ให้ใช้ค่าเริ่มต้น
    if ($allowedTypes === null) {
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png'
        ];
    }
    
    if ($maxSize === null) {
        $maxSize = MAX_FILE_SIZE;
    }
    
    // ตรวจสอบความผิดพลาดในการอัปโหลด - เหมือนตรวจสอบปัญหาก่อนวิ่ง
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในไฟล์ php.ini',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
            UPLOAD_ERR_EXTENSION => 'การอัปโหลดถูกหยุดโดย extension'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์';
        
        return ['status' => false, 'message' => $errorMessage];
    }
    
    // ตรวจสอบขนาดไฟล์ - เหมือนการตรวจสอบว่าน้ำหนักรองเท้าเบาพอหรือไม่
    if ($file['size'] > $maxSize) {
        return [
            'status' => false, 
            'message' => 'ไฟล์มีขนาดใหญ่เกินไป (ขนาดสูงสุด: ' . formatFileSize($maxSize) . ')'
        ];
    }
    
    // ตรวจสอบประเภทไฟล์ - เหมือนการตรวจสอบว่าใช้รองเท้าถูกประเภทหรือไม่
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileType = $finfo->file($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        return [
            'status' => false, 
            'message' => 'ประเภทไฟล์ไม่ได้รับอนุญาต (อนุญาตเฉพาะ: ' . implode(', ', $allowedTypes) . ')'
        ];
    }
    
    // ทุกอย่างถูกต้อง - พร้อมวิ่ง!
    return ['status' => true, 'message' => 'ไฟล์ถูกต้อง'];
}

/**
 * Format file size to human-readable
 * เหมือนการแปลงระยะทางวิ่งให้อ่านง่าย (จาก m เป็น km)
 *
 * @param int $bytes File size in bytes
 * @param int $precision Decimal precision
 * @return string Formatted file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Debug function to print variables
 * เหมือนการวิเคราะห์ข้อมูลจาก GPS watch ระหว่างการซ้อมวิ่ง
 *
 * @param mixed $var Variable to debug
 * @param bool $die Whether to stop execution after debugging
 * @return void
 */
function debug($var, $die = false) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    
    if ($die) {
        die('Debug stopped');
    }
}

/**
 * Format date to Thai date format
 * เหมือนการแปลงเวลาวิ่งให้อ่านง่ายขึ้น
 * 
 * @param string $date Date in Y-m-d format
 * @param bool $showTime Whether to show time
 * @return string Formatted Thai date
 */
function thaiDate($date, $showTime = false) {
    $thaiMonths = [
        '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp) + 543; // Convert to Buddhist era
    
    $formattedDate = $day . ' ' . $thaiMonths[$month] . ' พ.ศ. ' . $year;
    
    if ($showTime) {
        $formattedDate .= ' ' . date('H:i น.', $timestamp);
    }
    
    return $formattedDate;
}
?>