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

// ดึงข้อมูลการสมัครงาน
$stmt = $conn->prepare("SELECT a.*, p.title as position_title 
                       FROM applications a 
                       JOIN positions p ON a.position_id = p.id 
                       WHERE a.user_id = ? 
                       ORDER BY a.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าควบคุม - ผู้ใช้งาน</title>
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
            color: #2d3748;
            line-height: 1.6;
        }

        .navbar {
            background-color: white;
            padding: 1rem 2rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2b6cb0;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-menu {
            display: flex;
            gap: 1.5rem;
            list-style: none;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        /* รีเซ็ตสไตล์เดิมของปุ่มทั้งหมดก่อน */
        .nav-menu a, .nav-menu button, .login-btn {
            all: unset;
        }
        
        /* กำหนดสไตล์ปุ่มใหม่ให้เหมือนกันทุกปุ่ม */
        .nav-menu a, .login-btn, .nav-menu button {
            display: inline-block;
            background: white;
            padding: 8px 20px;
            border-radius: 50px;
            border: 2px solid #4299e1;
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            white-space: nowrap;
            width: 120px; /* กำหนดความกว้างแน่นอนแทนที่จะเป็น min-width */
            text-align: center;
            box-sizing: border-box; /* ให้ padding อยู่ภายใน width ที่กำหนด */
            height: 40px; /* กำหนดความสูงแน่นอน */
            line-height: 20px; /* ให้ข้อความอยู่กลางแนวตั้ง */
            font-size: 16px; /* กำหนดขนาดตัวอักษรให้เท่ากัน */
        }

        .nav-menu a:hover, .login-btn:hover, .nav-menu button:hover {
            background: #4299e1;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.2);
        }

        .nav-menu a:active, .login-btn:active, .nav-menu button:active {
            transform: translateY(0);
            box-shadow: none;
        }

        /* CSS สำหรับ navbar ใหม่ */
        .user-info-wrapper {
            position: relative;
        }

        .user-info-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(66, 153, 225, 0.1);
            padding: 6px 15px;
            border-radius: 50px;
            color: #3182ce;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-info-toggle:hover {
            background: rgba(66, 153, 225, 0.2);
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #4299e1;
            color: white;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            width: 200px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .user-dropdown ul li {
            border-bottom: 1px solid #f0f0f0;
        }

        .user-dropdown ul li:last-child {
            border-bottom: none;
        }

        .user-dropdown ul li a {
            display: block;
            padding: 12px 20px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            text-align: left;
            width: 100%;
            box-sizing: border-box;
            border: none;
            height: auto;
        }

        .user-dropdown ul li a:hover {
            background: #f7fafc;
            color: #3182ce;
            transform: none;
            box-shadow: none;
        }

        .user-dropdown ul li:last-child a {
            color: #e53e3e;
        }

        .user-dropdown ul li:last-child a:hover {
            background: #fff5f5;
        }

        /* เพิ่ม menu-backdrop */
        .menu-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(3px);
            z-index: 999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .menu-backdrop.active {
            display: block;
            opacity: 1;
        }

        .menu-backdrop.dropdown-active {
            display: block;
            opacity: 1;
        }

        .menu-btn {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            position: relative;
            z-index: 1001;
        }

        .menu-btn span {
            display: block;
            width: 25px;
            height: 2px;
            background-color: #4a5568;
            margin: 5px 0;
            transition: all 0.3s ease;
        }

        .menu-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        .menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -6px);
        }

        .main-content {
            max-width: 1200px;
            margin: 100px auto 2rem;
            padding: 0 2rem;
        }

        .greeting {
            margin-bottom: 2rem;
        }

        .greeting h1 {
            font-size: 2rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .greeting p {
            color: #718096;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-header h3 {
            color: #2d3748;
            font-size: 1.25rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fefcbf;
            color: #975a16;
        }

        .status-approved {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-rejected {
            background: #fed7d7;
            color: #c53030;
        }

        .application-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .application-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .apply-card {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .apply-card h2 {
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .apply-card p {
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .apply-btn {
            background: white;
            color: #3182ce;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* ปรับแต่ง responsive */
        @media (max-width: 1024px) {
            .main-content {
                padding: 0 2rem;
                margin-top: 100px;
            }
            
            .greeting h1 {
                font-size: 1.8rem;
            }
            
            .modal-container {
                max-width: 90%;
                width: 500px;
            }
        }

        @media (max-width: 768px) {
            .menu-btn {
                display: block;
            }

            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 70%;
                height: 100%;
                background: white;
                flex-direction: column;
                padding: 80px 2rem 2rem;
                transition: 0.3s ease;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                z-index: 1000;
            }

            .nav-menu.active {
                right: 0;
            }

            .nav-menu li {
                width: 100%;
                text-align: center;
                margin-bottom: 1rem;
            }

            .nav-menu a {
                display: block;
                width: 100%;
                padding: 0.75rem 0;
                font-size: 1.1rem;
            }

            .nav-menu a:not(.login-btn):not(.dashboard-btn):not(.logout-btn) {
                background: transparent;
                border: none;
                color: #4a5568;
                padding: 1rem 0;
            }

            .nav-menu a:not(.login-btn):not(.dashboard-btn):not(.logout-btn):hover {
                background: transparent;
                color: #4299e1;
                transform: none;
                box-shadow: none;
            }

            .login-btn, .nav-menu button {
                width: 100%;
                margin-top: 0.5rem;
                padding: 0.75rem;
                height: auto;
            }
            
            .user-info-wrapper {
                margin-right: 40px; /* ให้ space สำหรับปุ่ม menu */
            }
            
            .user-dropdown {
                position: fixed;
                top: 0;
                right: -100%;
                height: 100%;
                width: 70%;
                border-radius: 0;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                transition: right 0.3s ease;
                opacity: 1;
                visibility: visible;
                transform: none;
                padding-top: 70px;
            }
            
            .user-dropdown.active {
                right: 0;
            }
            
            .user-dropdown ul li a {
                padding: 15px 20px;
                font-size: 1.1rem;
            }

            .main-content {
                padding: 0 1rem;
                margin-top: 80px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .greeting h1 {
                font-size: 1.5rem;
            }

            .apply-card {
                padding: 1.5rem;
            }

            .apply-card h2 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .nav-menu {
                width: 85%;
                padding: 70px 1.5rem 1.5rem;
            }

            .logo {
                font-size: 1.2rem;
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .main-content {
                margin-top: 70px;
            }

            .greeting h1 {
                font-size: 1.3rem;
            }

            .greeting p {
                font-size: 0.9rem;
            }

            .dashboard-card {
                padding: 1.25rem;
            }

            .card-header h3 {
                font-size: 1.1rem;
            }

            .apply-card {
                padding: 1.25rem;
            }

            .apply-card h2 {
                font-size: 1.3rem;
            }

            .apply-card p {
                font-size: 0.9rem;
            }

            .apply-btn {
                padding: 0.6rem 1.5rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 375px) {
            .logo {
                font-size: 1rem;
                max-width: 120px;
            }

            .greeting h1 {
                font-size: 1.2rem;
            }

            .user-info-toggle {
                padding: 5px 10px;
                font-size: 0.85rem;
            }

            .user-avatar {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
            }

            .dashboard-card {
                padding: 1rem;
            }

            .application-item {
                padding: 0.75rem;
                font-size: 0.85rem;
            }

            .status-badge {
                padding: 0.2rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        @media (min-width: 1025px) {
            .main-content {
                padding: 0 2rem;
                max-width: 1200px;
                margin-top: 120px;
            }
            
            .greeting h1 {
                font-size: 2.25rem;
                margin-bottom: 1rem;
            }
            
            .greeting p {
                font-size: 1.1rem;
            }
            
            .dashboard-grid {
                gap: 2.5rem;
            }
            
            .dashboard-card {
                padding: 2rem;
                border-radius: 20px;
                transition: transform 0.4s ease, box-shadow 0.4s ease;
            }
            
            .dashboard-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            }
            
            .card-header h3 {
                font-size: 1.5rem;
            }
            
            .application-item {
                padding: 1.25rem;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .application-item:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            }
            
            .apply-card {
                padding: 3rem;
                border-radius: 20px;
            }
            
            .apply-card h2 {
                font-size: 2rem;
            }
            
            .apply-card p {
                font-size: 1.2rem;
            }
            
            .apply-btn {
                padding: 0.875rem 2.5rem;
                font-size: 1.1rem;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .apply-btn:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(66, 153, 225, 0.3);
            }
            
            .logo {
                font-size: 1.75rem;
            }
            
            .nav-menu a, .login-btn, .nav-menu button {
                padding: 10px 25px;
                font-size: 1rem;
                height: 45px;
                line-height: 23px;
                width: 130px;
            }
        }

        @media (min-width: 1440px) {
            .main-content {
                max-width: 1400px;
                padding: 0 2rem;
                margin-top: 140px;
            }
            
            .greeting h1 {
                font-size: 2.5rem;
            }
            
            .greeting p {
                font-size: 1.25rem;
            }
            
            .dashboard-grid {
                gap: 3rem;
            }
            
            .dashboard-card {
                padding: 2.5rem;
            }
            
            .card-header h3 {
                font-size: 1.75rem;
            }
            
            .application-item {
                padding: 1.5rem;
            }
            
            .apply-card {
                padding: 3.5rem;
            }
            
            .apply-card h2 {
                font-size: 2.25rem;
                margin-bottom: 1.5rem;
            }
            
            .apply-card p {
                font-size: 1.35rem;
            }
            
            .apply-btn {
                padding: 1rem 3rem;
                font-size: 1.2rem;
            }
            
            .nav-container {
                max-width: 1400px;
            }
            
            .logo {
                font-size: 2rem;
            }
            
            .nav-menu {
                gap: 2rem;
            }
            
            .nav-menu a, .login-btn, .nav-menu button {
                padding: 12px 30px;
                font-size: 1.1rem;
                height: 50px;
                line-height: 24px;
                width: 150px;
            }
        }

        @media (min-width: 1920px) {
            .main-content {
                max-width: 1800px;
                padding: 0 2rem;
                margin-top: 160px;
            }
            
            .greeting h1 {
                font-size: 3rem;
            }
            
            .greeting p {
                font-size: 1.5rem;
            }
            
            .dashboard-grid {
                gap: 4rem;
            }
            
            .dashboard-card {
                padding: 3rem;
                border-radius: 25px;
            }
            
            .card-header h3 {
                font-size: 2rem;
            }
            
            .application-item {
                padding: 1.75rem;
                border-radius: 12px;
            }
            
            .apply-card {
                padding: 4rem;
                border-radius: 30px;
            }
            
            .apply-card h2 {
                font-size: 2.75rem;
                margin-bottom: 2rem;
            }
            
            .apply-card p {
                font-size: 1.5rem;
                margin-bottom: 2.5rem;
            }
            
            .apply-btn {
                padding: 1.25rem 3.5rem;
                font-size: 1.3rem;
                border-radius: 60px;
            }
            
            .nav-container {
                max-width: 1800px;
                padding: 0 2rem;
            }
            
            .logo {
                font-size: 2.25rem;
            }
            
            .nav-menu {
                gap: 2.5rem;
            }
            
            .nav-menu a, .login-btn, .nav-menu button {
                padding: 15px 35px;
                font-size: 1.2rem;
                height: 55px;
                line-height: 25px;
                width: 170px;
                border-radius: 60px;
            }
            
            .dashboard-card:hover {
                transform: translateY(-10px) scale(1.03);
            }
            
            .application-item:hover {
                transform: translateY(-5px);
            }
            
            .apply-btn:hover {
                transform: translateY(-7px);
                box-shadow: 0 15px 30px rgba(66, 153, 225, 0.5);
            }
        }
    </style>
</head>
<body>
    <!-- เพิ่ม Backdrop สำหรับ Mobile Menu -->
    <div class="menu-backdrop" id="menuBackdrop"></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">HR TTV SUPPLYCHAIN</a>
            <button class="menu-btn" id="menuBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- แสดงเมนูสำหรับผู้ใช้ที่ล็อกอินแล้ว -->
            <div class="user-info-wrapper">
                <div class="user-info-toggle" id="userInfoToggle">
                    <div class="user-avatar">
                        <?php echo substr($user['name'], 0, 1); ?>
                    </div>
                    <span>ยินดีต้อนรับ, <?php echo explode(' ', $user['name'])[0]; ?></span>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <ul>
                        <li><a href="../index.php">หน้าหลัก</a></li>
                        <li><a href="dashboard.php">แดชบอร์ด</a></li>
                        <li><a href="profile.php">โปรไฟล์</a></li>
                        <li><a href="../positions.php">ตำแหน่งงาน</a></li>
                        <li><a href="../contact.php">ติดต่อ</a></li>
                        <li><a href="../logout.php">ออกจากระบบ</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="greeting">
            <h1>สวัสดี, <?php echo explode(' ', $user['name'])[0]; ?></h1>
            <p>ยินดีต้อนรับกลับมา</p>
        </div>

        <div class="apply-card">
            <h2>ค้นหาโอกาสใหม่</h2>
            <p>ดูตำแหน่งงานที่เปิดรับสมัครและเริ่มต้นการเดินทางใหม่กับเรา</p>
            <a href="../positions.php" class="apply-btn">ดูตำแหน่งงานที่เปิดรับ</a>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>การสมัครงานของคุณ</h3>
                    <span><?php echo count($applications); ?> ตำแหน่ง</span>
                </div>
                <div class="application-list">
                    <?php if (empty($applications)): ?>
                        <p style="text-align: center; color: #718096;">ยังไม่มีประวัติการสมัครงาน</p>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <div class="application-item">
                                <div>
                                    <div style="font-weight: 500; color: #2d3748;"><?php echo $app['position_title']; ?></div>
                                    <div style="font-size: 0.875rem; color: #718096;">
                                        สมัครเมื่อ: <?php echo date('d/m/Y', strtotime($app['created_at'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php
                                        switch($app['status']) {
                                            case 'pending':
                                                echo 'รอดำเนินการ';
                                                break;
                                            case 'approved':
                                                echo 'ผ่านการคัดเลือก';
                                                break;
                                            case 'rejected':
                                                echo 'ไม่ผ่านการคัดเลือก';
                                                break;
                                        }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>ข้อมูลส่วนตัว</h3>
                </div>
                <div style="color: #4a5568;">
                    <p><strong>อีเมล:</strong> <?php echo $user['email']; ?></p>
                    <p><strong>เบอร์โทรศัพท์:</strong> <?php echo $user['phone']; ?></p>
                    <p style="margin-top: 1rem;">
                        <a href="profile.php" style="color: #4299e1; text-decoration: none;">
                            แก้ไขข้อมูลส่วนตัว →
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // ตัวแปรสำหรับ DOM Elements
        const menuBtn = document.getElementById('menuBtn');
        const menuBackdrop = document.getElementById('menuBackdrop');
        const userInfoToggle = document.getElementById('userInfoToggle');
        const userDropdown = document.getElementById('userDropdown');

        // เพิ่มการจัดการ Dropdown Menu
        if (userInfoToggle && userDropdown) {
            userInfoToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
                
                // ในมือถือ ให้แสดง backdrop เมื่อ dropdown เปิด
                if (window.innerWidth <= 768) {
                    if (userDropdown.classList.contains('active')) {
                        menuBackdrop.classList.add('dropdown-active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        menuBackdrop.classList.remove('dropdown-active');
                        document.body.style.overflow = '';
                    }
                }
            });
        }
        
        // ปิด dropdown เมื่อคลิกที่อื่น
        document.addEventListener('click', function(e) {
            if (userDropdown && userDropdown.classList.contains('active')) {
                if (!userDropdown.contains(e.target) && e.target !== userInfoToggle) {
                    userDropdown.classList.remove('active');
                    menuBackdrop.classList.remove('dropdown-active');
                    document.body.style.overflow = '';
                }
            }
        });

        // เพิ่มการจัดการเมนูมือถือ
        if (menuBtn) {
            menuBtn.addEventListener('click', () => {
                menuBtn.classList.toggle('active');
                if (userDropdown) {
                    userDropdown.classList.toggle('active');
                }
                menuBackdrop.classList.toggle('active');
                
                // ป้องกันการเลื่อนหน้าเมื่อเมนูเปิดอยู่
                if (userDropdown.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
        }

        // ปิดเมนูเมื่อคลิกที่ backdrop
        menuBackdrop.addEventListener('click', () => {
            if (menuBtn) {
                menuBtn.classList.remove('active');
            }
            if (userDropdown) {
                userDropdown.classList.remove('active');
            }
            menuBackdrop.classList.remove('active');
            menuBackdrop.classList.remove('dropdown-active');
            document.body.style.overflow = '';
        });

        // การปรับขนาดหน้าจอ
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                if (menuBtn) {
                    menuBtn.classList.remove('active');
                }
                if (userDropdown) {
                    userDropdown.classList.remove('active');
                }
                menuBackdrop.classList.remove('active');
                menuBackdrop.classList.remove('dropdown-active');
                document.body.style.overflow = '';
            }
        });

        // แสดงการแจ้งเตือน (ถ้ามี)
        <?php if (isset($_SESSION['success_msg'])): ?>
            alert("<?php echo $_SESSION['success_msg']; ?>");
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            alert("<?php echo $_SESSION['error_msg']; ?>");
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    </script>
</body>
</html>