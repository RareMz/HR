<?php
session_start();
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// ดึงข้อมูลผู้ใช้
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว - HR TTV SUPPLYCHAIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Anuphan', sans-serif;
        }

        body {
            background: #f7fafc;
            min-height: 100vh;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #4a5568;
        }

        .main-content {
            max-width: 800px;
            margin: 100px auto 2rem;
            padding: 0 2rem;
        }

        .page-title {
            margin-bottom: 2rem;
            color: #2d3748;
        }

        .profile-form {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .save-btn {
            background: #4299e1;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .save-btn:hover {
            background: #3182ce;
        }

        .back-btn {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }

            .main-content {
                padding: 0 1rem;
            }

            .button-group {
                flex-direction: column;
            }

            .save-btn, .back-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" style="color: #2b6cb0; text-decoration: none; font-weight: 600; font-size: 1.25rem;">
                HR TTV SUPPLYCHAIN
            </a>
            <div class="user-menu">
                <div class="user-avatar"><?php echo substr($user['name'], 0, 1); ?></div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <h1 class="page-title">แก้ไขข้อมูลส่วนตัว</h1>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_msg'];
                    unset($_SESSION['success_msg']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error_msg'];
                    unset($_SESSION['error_msg']);
                ?>
            </div>
        <?php endif; ?>

        <form class="profile-form" action="../update_profile.php" method="POST">
            <div class="form-group">
                <label for="name">ชื่อ-นามสกุล</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">เบอร์โทรศัพท์</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            </div>

            <div class="form-group">
                <label for="line_id">ไลน์ไอดี (ถ้ามี)</label>
                <input type="text" id="line_id" name="line_id" value="<?php echo htmlspecialchars($user['line_id'] ?? ''); ?>">
            </div>

            <div class="button-group">
                <button type="submit" class="save-btn">บันทึกการเปลี่ยนแปลง</button>
                <a href="dashboard.php" class="back-btn">ย้อนกลับ</a>
            </div>
        </form>
    </main>
</body>
</html>