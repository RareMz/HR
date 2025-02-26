<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['signup-name'];
    $email = $_POST['signup-email'];
    $phone = $_POST['signup-phone'];
    $password = $_POST['signup-password'];
    $confirm_password = $_POST['signup-confirm-password'];
    $role = 'user'; // กำหนดค่าเริ่มต้นเป็น user

    if ($password !== $confirm_password) {
        header("Location: index.php?signup_error=password_mismatch");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: index.php?signup_error=email_exists");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $role);

    if ($stmt->execute()) {
        header("Location: index.php?signup_success=1");
        exit();
    } else {
        header("Location: index.php?signup_error=database_error");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>