<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['login-email'];
    $password = $_POST['login-password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // บันทึกข้อมูลผู้ใช้ใน session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        // บันทึกเวลาล็อกอินล่าสุด
        $stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();

        // ตรวจสอบ role และ redirect ไปยังหน้าที่เหมาะสม
        if ($user['role'] == 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }
        exit();
    } else {
        header("Location: index.php?login_error=1");
        exit();
    }
}

$conn->close();
?>