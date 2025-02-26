<?php
session_start();
require_once 'config.php';

// ตรวจสอบว่ามีการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        header('Location: index.php?showForgot=1&error=empty_email');
        exit();
    }
    
    // ตรวจสอบว่าอีเมลมีอยู่ในระบบหรือไม่
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // ไม่พบอีเมลในระบบ
        header('Location: index.php?showForgot=1&error=email_not_found');
        exit();
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $user_name = $user['name'];
    
    // สร้างโทเค็นสำหรับรีเซ็ตรหัสผ่าน
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // บันทึกโทเค็นลงในฐานข้อมูล
    // สร้างตาราง password_resets ถ้ายังไม่มี
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // ลบโทเค็นเก่าของผู้ใช้นี้ (ถ้ามี)
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // เพิ่มโทเค็นใหม่
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $token, $expires);
    
    if ($stmt->execute()) {
        // สร้าง URL สำหรับรีเซ็ตรหัสผ่าน
        $reset_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                     "://" . $_SERVER['HTTP_HOST'] . 
                     dirname($_SERVER['PHP_SELF']) . 
                     "/reset-password.php?token={$token}";
        
        // ในระบบจริง จะส่งอีเมลที่นี่
        $to = $email;
        $subject = "รีเซ็ตรหัสผ่าน HR TTV SUPPLYCHAIN";
        $message = "
        <html>
        <head>
            <title>รีเซ็ตรหัสผ่าน</title>
        </head>
        <body>
            <p>สวัสดี {$user_name},</p>
            <p>เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ</p>
            <p>คลิกที่ลิงก์ด้านล่างเพื่อรีเซ็ตรหัสผ่านของคุณ:</p>
            <p><a href='{$reset_url}'>รีเซ็ตรหัสผ่าน</a></p>
            <p>ลิงก์นี้จะหมดอายุในหนึ่งชั่วโมง</p>
            <p>หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน คุณสามารถเพิกเฉยต่ออีเมลนี้ได้</p>
            <p>ขอแสดงความนับถือ,<br>ทีม HR TTV SUPPLYCHAIN</p>
        </body>
        </html>
        ";
        
        // ตั้งค่า headers สำหรับอีเมล HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: HR TTV SUPPLYCHAIN <noreply@hr.ttv>" . "\r\n";
        
        // ส่งอีเมล (uncomment ในระบบจริง)
        // mail($to, $subject, $message, $headers);
        
        // สำหรับการทดสอบ เราจะบันทึกข้อมูลลงใน session
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_url'] = $reset_url;
        
        header('Location: index.php?email_sent=1');
        exit();
    } else {
        header('Location: index.php?showForgot=1&error=system');
        exit();
    }
} else {
    // ถ้าเข้าถึงหน้านี้โดยตรง ให้เปลี่ยนเส้นทางไปยังหน้าแรก
    header('Location: index.php?showForgot=1');
    exit();
}
?>