<?php
session_start();
require_once 'config.php';

// ตรวจสอบสิทธิ์การแก้ไข (เฉพาะแอดมิน)
$can_edit = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');

// เชื่อมต่อฐานข้อมูล
try {
    if (!isset($conn)) {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        $conn->set_charset("utf8");
        
        if ($conn->connect_error) {
            throw new Exception("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
        }
    }
    
    // ตรวจสอบว่ามีตาราง contact_info, form_data หรือไม่
    $tables_to_check = ['contact_info', 'form_data'];

    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");

        if ($result->num_rows == 0) {
            // สร้างตาราง ถ้ายังไม่มี
            $create_table_sql = '';

            if ($table == 'contact_info') {
                $create_table_sql = "CREATE TABLE `contact_info` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `email` varchar(100) NOT NULL,
                    `phone` varchar(20) NOT NULL,
                    `address` text NOT NULL,
                    `working_hours` varchar(100) NOT NULL,
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    `updated_by` int(11) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `updated_by` (`updated_by`),
                    CONSTRAINT `contact_info_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

                // เพิ่มข้อมูลเริ่มต้นใน contact_info
                $contact_info_default_data = "INSERT INTO `contact_info` 
                    (`name`, `email`, `phone`, `address`, `working_hours`) 
                    VALUES 
                    ('HR TTV Support Team', 'support@hr.ttv', '02-123-4567', 
                     '123 ถนนวิภาวดีรังสิต แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900', 
                     'จันทร์-ศุกร์ เวลา 09:00-17:00 น.')
                ";
            } elseif ($table == 'form_data') {
                $create_table_sql = "CREATE TABLE `form_data` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) DEFAULT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `phone` varchar(20) DEFAULT NULL,
                    `message` text,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)  
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            }

            if (!empty($create_table_sql)) {
                if ($conn->query($create_table_sql) === TRUE) {
                    if (isset($contact_info_default_data)) {
                        $conn->query($contact_info_default_data);
                    }
                } else {
                    throw new Exception("การสร้างตาราง $table ล้มเหลว: " . $conn->error);
                }
            }
        }
    }
    
    // ดึงข้อมูลติดต่อจากฐานข้อมูล
    $query = "SELECT * FROM contact_info ORDER BY id LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $admin_contact = $result->fetch_assoc();
    } else {
        // กรณีไม่พบข้อมูลในฐานข้อมูล ใช้ข้อมูลเริ่มต้น
        $admin_contact = [
            'name' => 'HR TTV Support Team',
            'email' => 'support@hr.ttv',
            'phone' => '02-123-4567',
            'address' => '123 ถนนวิภาวดีรังสิต แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900',
            'working_hours' => 'จันทร์-ศุกร์ เวลา 09:00-17:00 น.'
        ];
    }
} catch (Exception $e) {
    // กรณีมีข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล ใช้ข้อมูลเริ่มต้น
    $admin_contact = [
        'name' => 'HR TTV Support Team',
        'email' => 'support@hr.ttv',
        'phone' => '02-123-4567',
        'address' => '123 ถนนวิภาวดีรังสิต แขวงจตุจักร เขตจตุจักร กรุงเทพมหานคร 10900',
        'working_hours' => 'จันทร์-ศุกร์ เวลา 09:00-17:00 น.'
    ];
    $error_message = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
}

// บันทึกการแก้ไขข้อมูลติดต่อ (เฉพาะแอดมิน)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $can_edit) {
    $new_name = $_POST['contact_name'] ?? $admin_contact['name'];
    $new_email = $_POST['contact_email'] ?? $admin_contact['email'];
    $new_phone = $_POST['contact_phone'] ?? $admin_contact['phone'];
    $new_address = $_POST['contact_address'] ?? $admin_contact['address'];
    $new_working_hours = $_POST['contact_working_hours'] ?? $admin_contact['working_hours'];
    
    try {
        // ตรวจสอบการเชื่อมต่อฐานข้อมูล
        if (!isset($conn) || $conn->connect_error) {
            throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");
        }
        
        // ตรวจสอบว่ามีข้อมูลในตารางหรือไม่
        $check_query = "SELECT COUNT(*) as count FROM contact_info";
        $check_result = $conn->query($check_query);
        $count_row = $check_result->fetch_assoc();
        $count = $count_row['count'];
        
        if ($count > 0) {
            // อัปเดตข้อมูลในฐานข้อมูล
            $stmt = $conn->prepare("UPDATE contact_info SET name = ?, email = ?, phone = ?, address = ?, working_hours = ?, updated_by = ? WHERE id = ?");
            $stmt->bind_param("sssssii", $new_name, $new_email, $new_phone, $new_address, $new_working_hours, $_SESSION['user_id'], $admin_contact['id']);
        } else {
            // สร้างข้อมูลใหม่ถ้ายังไม่มี
            $stmt = $conn->prepare("INSERT INTO contact_info (name, email, phone, address, working_hours, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $new_name, $new_email, $new_phone, $new_address, $new_working_hours, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            // อัปเดตข้อมูลในตัวแปร
            $admin_contact['name'] = $new_name;
            $admin_contact['email'] = $new_email;
            $admin_contact['phone'] = $new_phone;
            $admin_contact['address'] = $new_address;
            $admin_contact['working_hours'] = $new_working_hours;
            
            // แสดงข้อความแจ้งเตือนว่าบันทึกสำเร็จ
            $success_message = "บันทึกข้อมูลเรียบร้อยแล้ว";
        } else {
            // แสดงข้อความแจ้งเตือนว่าบันทึกไม่สำเร็จ
            $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // แสดงข้อความแจ้งเตือนว่าบันทึกไม่สำเร็จ
        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อ HR TTV</title>
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
        min-height: 100vh;
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
    
    @media (max-width: 480px) {
        .logo {
            font-size: 1.2rem;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    
    @media (max-width: 375px) {
        .logo {
            font-size: 1rem;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    
    @media (min-width: 1025px) {
        .logo {
            font-size: 1.75rem;
        }
    }
    
    @media (min-width: 1440px) {
        .logo {
            font-size: 2rem;
        }
    }
    
    @media (min-width: 1920px) {
        .logo {
            font-size: 2.25rem;
        }
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

    .container {
        max-width: 800px;
        margin: 120px auto;
        padding: 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
        transform: perspective(1000px) rotateX(10deg);
        opacity: 0;
        animation: slideIn 0.6s forwards ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: perspective(1000px) rotateX(10deg) translateY(50px);
        }
        to {
            opacity: 1;
            transform: perspective(1000px) rotateX(0) translateY(0);
        }
    }

    .close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: none;
        border: none;
        font-size: 2rem;
        color: #718096;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
    }

    .close-btn:hover {
        color: #4a5568;
        transform: rotate(90deg) scale(1.2);
    }

    .contact-header {
        text-align: center;
        margin-bottom: 2rem;
        color: #2b6cb0;
        position: relative;
    }

    .contact-header h1 {
        position: relative;
        display: inline-block;
    }

    .contact-header h1::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        transform: scaleX(0);
        transform-origin: right;
        transition: transform 0.5s ease;
    }

    .contact-header:hover h1::after {
        transform: scaleX(1);
        transform-origin: left;
    }

    .contact-info {
        background: #f7fafc;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .contact-info::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(
            45deg, 
            transparent, 
            rgba(66, 153, 225, 0.1), 
            transparent
        );
        transform: rotate(-45deg);
        animation: shine 3s infinite linear;
    }

    @keyframes shine {
        0% { transform: rotate(-45deg) translateX(-100%); }
        100% { transform: rotate(-45deg) translateX(100%); }
    }

    .contact-info h2 {
        color: #4299e1;
        margin-bottom: 1rem;
        font-size: 1.5rem;
        position: relative;
    }

    .contact-info p {
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        transition: transform 0.3s ease;
    }

    .contact-info p:hover {
        transform: translateX(10px);
    }

    .contact-info p i {
        margin-right: 1rem;
        color: #4299e1;
        font-size: 1.25rem;
    }

    .edit-form {
        display: <?php echo $can_edit ? 'block' : 'none'; ?>;
    }

    .edit-form input, 
    .edit-form textarea {
        width: 100%;
        padding: 0.75rem;
        margin-bottom: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .edit-form input:focus, 
    .edit-form textarea:focus {
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
    }

    .edit-form button {
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .edit-form button:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
    }

    .edit-form button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(
            120deg, 
            transparent, 
            rgba(255,255,255,0.3), 
            transparent
        );
        transition: all 0.5s ease;
    }

    .edit-form button:hover::before {
        left: 100%;
    }
    
    /* เพิ่มสไตล์ใหม่สำหรับการแจ้งเตือน */
    .alert {
        padding: 0.75rem 1.25rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    /* ส่วนของ menu-backdrop */
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

    /* ส่วนของ menu-btn */
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

    /* เมื่อ dropdown เปิด ให้แสดง backdrop */
    .menu-backdrop.dropdown-active {
        display: block;
        opacity: 1;
    }

    /* ปรับแต่ง responsive */
    @media (max-width: 1024px) {
        .container {
            padding: 3rem 2rem;
        }
        
        .nav-menu a, .login-btn, .nav-menu button {
            width: 110px;
            font-size: 0.95rem;
            padding: 6px 15px;
        }
        
        .user-info-toggle {
            padding: 5px 12px;
        }
        
        .user-info-toggle span {
            font-size: 0.9rem;
        }
        
        .contact-header h1 {
            font-size: 1.8rem;
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

        .nav-menu a, .nav-menu button {
            display: block;
            width: 100%;
            padding: 0.75rem 0;
            font-size: 1.1rem;
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
        
        .navbar {
            padding: 0.75rem 1rem;
        }
        
        .container {
            margin: 100px auto 40px;
            padding: 1.5rem;
        }
        
        .contact-header h1 {
            font-size: 1.5rem;
        }
        
        .contact-info {
            padding: 1.5rem;
        }
        
        .edit-form button {
            width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .nav-menu {
            width: 85%;
            padding: 70px 1.5rem 1.5rem;
        }
        
        .user-dropdown {
            width: 85%;
        }
        
        .container {
            padding: 1.25rem;
            margin: 90px auto 30px;
        }
        
        .contact-header h1 {
            font-size: 1.3rem;
        }
        
        .contact-info h2 {
            font-size: 1.2rem;
        }
        
        .contact-info {
            padding: 1.25rem;
        }
        
        .contact-info p {
            font-size: 0.9rem;
        }
        
        .edit-form input, 
        .edit-form textarea {
            padding: 0.6rem;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 375px) {
        .user-info-toggle {
            padding: 4px 10px;
        }
        
        .user-info-toggle span {
            font-size: 0.8rem;
        }
        
        .user-avatar {
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
        }
        
        .contact-header h1 {
            font-size: 1.2rem;
        }
        
        .contact-header p {
            font-size: 0.85rem;
        }
        
        .contact-info h2 {
            font-size: 1.1rem;
        }
        
        .contact-info p {
            font-size: 0.85rem;
        }
        
        .alert {
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }
    }
    
    @media (min-width: 1025px) {
        .nav-menu a, .login-btn, .nav-menu button {
            padding: 10px 25px;
            font-size: 1rem;
            height: 45px;
            line-height: 23px;
            width: 130px;
        }
    }

    @media (min-width: 1440px) {
        .nav-container {
            max-width: 1400px;
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
        .nav-container {
            max-width: 1800px;
            padding: 0 2rem;
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
    }
</style>

</head>
<body>
    <!-- เพิ่ม Backdrop สำหรับ Mobile Menu -->
    <div class="menu-backdrop" id="menuBackdrop"></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">HR TTV SUPPLYCHAIN</a>
            <button class="menu-btn" id="menuBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- แสดงเมนูสำหรับผู้ใช้ที่ล็อกอินแล้ว -->
                <div class="user-info-wrapper">
                    <div class="user-info-toggle" id="userInfoToggle">
                        <div class="user-avatar">
                            <?php echo substr($_SESSION['user_name'], 0, 1); ?>
                        </div>
                        <span>ยินดีต้อนรับ, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?></span>
                    </div>
                    <div class="user-dropdown" id="userDropdown">
                        <ul>
                            <li><a href="index.php">หน้าหลัก</a></li>
                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                <li><a href="admin/dashboard.php">แดชบอร์ด</a></li>
                            <?php else: ?>
                                <li><a href="user/dashboard.php">แดชบอร์ด</a></li>
                            <?php endif; ?>
                            <li><a href="contact.php">ติดต่อ</a></li>
                            <li><a href="logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <!-- แสดงเมนูสำหรับผู้ใช้ที่ยังไม่ได้ล็อกอิน -->
                <ul class="nav-menu" id="navMenu">
                    <li><a href="index.php">หน้าหลัก</a></li>
                    <li><a href="contact.php">ติดต่อ</a></li>
                    <li><button onclick="window.location.href='index.php?showLogin=1'">เข้าสู่ระบบ</button></li>
                </ul>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <button class="close-btn" onclick="window.location.href='index.php'" title="กลับสู่หน้าหลัก">&times;</button>
        <div class="contact-header">
            <h1>ข้อมูลติดต่อ HR TTV</h1>
            <p>หากคุณมีคำถามหรือต้องการความช่วยเหลือ</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="contact-info">
            <h2>ข้อมูลการติดต่อ</h2>
            <p><i>👤</i> <?php echo htmlspecialchars($admin_contact['name']); ?></p>
            <p><i>✉️</i> <?php echo htmlspecialchars($admin_contact['email']); ?></p>
            <p><i>📞</i> <?php echo htmlspecialchars($admin_contact['phone']); ?></p>
            <p><i>🏢</i> <?php echo htmlspecialchars($admin_contact['address']); ?></p>
            <p><i>⏰</i> เวลาทำการ: <?php echo htmlspecialchars($admin_contact['working_hours']); ?></p>
        </div>

        <?php if ($can_edit): ?>
        <div class="edit-form">
            <h2>แก้ไขข้อมูลติดต่อ</h2>
            <form method="post">
                <input type="text" name="contact_name" placeholder="ชื่อผู้ติดต่อ" value="<?php echo htmlspecialchars($admin_contact['name']); ?>">
                <input type="email" name="contact_email" placeholder="อีเมล" value="<?php echo htmlspecialchars($admin_contact['email']); ?>">
                <input type="tel" name="contact_phone" placeholder="เบอร์โทรศัพท์" value="<?php echo htmlspecialchars($admin_contact['phone']); ?>">
                <textarea name="contact_address" placeholder="ที่อยู่"><?php echo htmlspecialchars($admin_contact['address']); ?></textarea>
                <input type="text" name="contact_working_hours" placeholder="เวลาทำการ" value="<?php echo htmlspecialchars($admin_contact['working_hours']); ?>">
                <button type="submit">บันทึกการเปลี่ยนแปลง</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript สำหรับ Dropdown Menu
        document.addEventListener('DOMContentLoaded', function() {
            const userInfoToggle = document.getElementById('userInfoToggle');
            const userDropdown = document.getElementById('userDropdown');
            const menuBtn = document.getElementById('menuBtn');
            const navMenu = document.getElementById('navMenu');
            const menuBackdrop = document.getElementById('menuBackdrop');
            
            // Toggle dropdown menu เมื่อคลิกที่ user info
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
            
            // จัดการ mobile menu สำหรับผู้ใช้ที่ยังไม่ได้ล็อกอิน
            if (menuBtn && navMenu) {
                menuBtn.addEventListener('click', () => {
                    menuBtn.classList.toggle('active');
                    navMenu.classList.toggle('active');
                    menuBackdrop.classList.toggle('active');
                    
                    if (navMenu.classList.contains('active')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // จัดการปุ่มเบอร์เกอร์เมนูเมื่อผู้ใช้ล็อกอินแล้ว
            if (menuBtn && userDropdown && !navMenu) {
                menuBtn.addEventListener('click', () => {
                    menuBtn.classList.toggle('active');
                    userDropdown.classList.toggle('active');
                    menuBackdrop.classList.toggle('active');
                    
                    if (userDropdown.classList.contains('active')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // ปิดเมนูเมื่อคลิกที่ backdrop
            if (menuBackdrop) {
                menuBackdrop.addEventListener('click', () => {
                    if (menuBtn && navMenu) {
                        menuBtn.classList.remove('active');
                        navMenu.classList.remove('active');
                    }
                    if (userDropdown) {
                        userDropdown.classList.remove('active');
                    }
                    menuBackdrop.classList.remove('active');
                    menuBackdrop.classList.remove('dropdown-active');
                    document.body.style.overflow = '';
                });
            }
            
            // จัดการการคลิกที่ลิงก์ในเมนู
            document.querySelectorAll('.nav-menu a, .user-dropdown a').forEach(link => {
                link.addEventListener('click', () => {
                    if (menuBtn && navMenu) {
                        menuBtn.classList.remove('active');
                        navMenu.classList.remove('active');
                    }
                    if (userDropdown) {
                        userDropdown.classList.remove('active');
                    }
                    menuBackdrop.classList.remove('active');
                    menuBackdrop.classList.remove('dropdown-active');
                    document.body.style.overflow = '';
                });
            });
            
            // การปรับขนาดหน้าจอ
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    if (menuBtn && navMenu) {
                        menuBtn.classList.remove('active');
                        navMenu.classList.remove('active');
                    }
                    if (userDropdown) {
                        userDropdown.classList.remove('active');
                    }
                    menuBackdrop.classList.remove('active');
                    menuBackdrop.classList.remove('dropdown-active');
                    document.body.style.overflow = '';
                }
            });
            
            // ซ่อนการแจ้งเตือนหลังจาก 5 วินาที
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(function(alert) {
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            alert.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>