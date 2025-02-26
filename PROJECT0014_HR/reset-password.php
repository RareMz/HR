<?php
session_start();
require_once 'config.php';

// ตรวจสอบว่ามี token หรือไม่
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: index.php?error=invalid_token');
    exit();
}

$token = $_GET['token'];
$error = '';
$success = false;

// ตรวจสอบ token ในฐานข้อมูล
$stmt = $conn->prepare("SELECT pr.user_id, pr.expires_at, u.email 
                         FROM password_resets pr 
                         JOIN users u ON pr.user_id = u.id 
                         WHERE pr.token = ? AND pr.expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = 'โทเค็นไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอรีเซ็ตรหัสผ่านใหม่';
} else {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
    $user_email = $row['email'];
    
    // ถ้ามีการส่งฟอร์มรีเซ็ตรหัสผ่าน
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // ตรวจสอบความถูกต้องของข้อมูล
        if (empty($password) || empty($confirm_password)) {
            $error = 'กรุณากรอกรหัสผ่านและยืนยันรหัสผ่าน';
        } elseif (strlen($password) < 6) {
            $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } elseif ($password !== $confirm_password) {
            $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            // อัพเดทรหัสผ่าน
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                // ลบ token หลังจากใช้งานแล้ว
                $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();
                
                $success = true;
            } else {
                $error = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีเซ็ตรหัสผ่าน</title>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Anuphan', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f6f8fc 0%, #edf2f7 100%);
            color: #2d3748;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2b6cb0;
            margin-bottom: 2rem;
            display: block;
            text-decoration: none;
        }

        h1 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #2d3748;
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .error {
            background-color: #fed7d7;
            color: #e53e3e;
        }

        .success {
            background-color: #c6f6d5;
            color: #38a169;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.75rem;
            text-align: left;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 1rem;
            outline: none;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:hover {
            border-color: #cbd5e0;
        }

        .form-group input:focus {
            border-color: #4299e1;
            box-shadow: 
                0 4px 20px rgba(66, 153, 225, 0.15),
                0 0 0 3px rgba(66, 153, 225, 0.08);
        }

        .form-group label {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            padding: 0 0.75rem;
            color: #718096;
            transition: all 0.2s ease;
            pointer-events: none;
        }

        .form-group input:focus + label,
        .form-group input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 0.875rem;
            color: #4299e1;
            font-weight: 500;
        }

        .form-group input::placeholder {
            opacity: 0;
        }

        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.125rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 8px 20px rgba(66, 153, 225, 0.3),
                0 0 0 2px rgba(66, 153, 225, 0.1);
        }

        .login-link {
            color: #4299e1;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .login-link:hover {
            color: #2b6cb0;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="logo">HR TTV SUPPLYCHAIN</a>
        
        <?php if ($success): ?>
            <div class="message success">
                <p>รหัสผ่านถูกเปลี่ยนเรียบร้อยแล้ว</p>
            </div>
            <p>คุณสามารถ <a href="index.php?showLogin=1" class="login-link">เข้าสู่ระบบ</a> ด้วยรหัสผ่านใหม่ได้ทันที</p>
        <?php elseif (!empty($error)): ?>
            <div class="message error">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <p>กลับไปที่ <a href="index.php" class="login-link">หน้าหลัก</a></p>
        <?php else: ?>
            <h1>รีเซ็ตรหัสผ่าน</h1>
            <p style="margin-bottom: 2rem;">กรุณากำหนดรหัสผ่านใหม่สำหรับบัญชี <?php echo htmlspecialchars($user_email); ?></p>
            
            <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder=" " required>
                    <label for="password">รหัสผ่านใหม่</label>
                </div>
                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                    <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                </div>
                <button type="submit" class="submit-btn">เปลี่ยนรหัสผ่าน</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // เพิ่มการตรวจสอบฟอร์ม
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                        return;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>