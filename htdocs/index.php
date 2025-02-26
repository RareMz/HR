<?php
session_start();
require_once 'config.php';

// ดึงข้อมูลตำแหน่งงานที่เปิดรับจากฐานข้อมูล
$sql = "SELECT id, title, type, location, salary_min, salary_max FROM positions WHERE status = 'open' ORDER BY id DESC";
$result = $conn->query($sql);
$positions = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // แปลงฟอร์แมตเงินเดือน
        $salary = number_format($row['salary_min']) . ' - ' . number_format($row['salary_max']) . ' บาท';
        
        $positions[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'type' => $row['type'],
            'location' => $row['location'] . ' (Hybrid)',
            'salary' => $salary
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก</title>
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

        /* ลบ CSS เดิมที่ไม่จำเป็นแล้ว */
        .user-welcome,
        .user-info,
        .user-action-buttons,
        .dashboard-btn,
        .logout-btn {
            display: none;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            z-index: 2000;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-container {
            background: rgba(255, 255, 255, 0.85);
            padding: 2.5rem 2rem;
            border-radius: 32px;
            box-shadow: 
                0 8px 32px rgba(31, 38, 135, 0.15),
                inset 0 0 32px rgba(255, 255, 255, 0.05),
                inset 0 2px 5px rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 460px;
            max-height: 85vh;
            position: relative;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(66, 153, 225, 0.5) rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .modal-container:hover {
            box-shadow: 
                0 12px 48px rgba(31, 38, 135, 0.2),
                inset 0 0 32px rgba(255, 255, 255, 0.1),
                inset 0 2px 5px rgba(255, 255, 255, 0.3);
        }
        
        .modal-container::-webkit-scrollbar {
            width: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .modal-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin: 10px;
        }
        
        .modal-container::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, rgba(66, 153, 225, 0.6), rgba(49, 130, 206, 0.8));
            border-radius: 10px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            background-clip: padding-box;
            min-height: 40px;
            transition: all 0.3s ease;
        }
        
        .modal-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, rgba(66, 153, 225, 0.8), rgba(49, 130, 206, 1));
        }

        .close-btn {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 1.75rem;
            color: #718096;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close-btn:hover {
            color: #4a5568;
        }

        .tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .tab-btn {
            padding: 0.75rem 2rem;
            background: none;
            border: none;
            color: #718096;
            font-size: 1.1rem;
            cursor: pointer;
            position: relative;
            transition: color 0.3s ease;
        }

        .tab-btn.active {
            color: #4299e1;
            font-weight: 500;
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #4299e1;
            border-radius: 3px;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .tab-btn.active::after {
            transform: scaleX(1);
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.75rem;
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
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,1) 45%, rgba(255,255,255,1) 55%, rgba(255,255,255,0) 100%);
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

        .forgot-link {
            display: block;
            text-align: right;
            color: #4299e1;
            text-decoration: none;
            margin: -0.75rem 0 2rem;
            font-size: 0.925rem;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #2b6cb0;
            text-decoration: underline;
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
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 8px 20px rgba(66, 153, 225, 0.3),
                0 0 0 2px rgba(66, 153, 225, 0.1);
        }

        .terms {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #718096;
        }

        .terms a {
            color: #4299e1;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .hero {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            padding: 6rem 2rem;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 64px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><path fill="%23FFFFFF" fill-opacity="0.05" d="M42.5,-66.2C54.9,-59.3,64.5,-46.6,71.5,-32.3C78.4,-18,82.7,-2.1,79.5,12.1C76.2,26.3,65.4,38.7,53,47.4C40.6,56.1,26.6,61.1,11.9,64.3C-2.8,67.6,-18.3,69,-32.7,64.6C-47.1,60.2,-60.5,49.9,-69.6,36.2C-78.7,22.4,-83.5,5.2,-80.9,-10.8C-78.2,-26.8,-68.1,-41.6,-55.2,-48.8C-42.3,-56,-26.6,-55.5,-11.8,-57.7C3,-59.9,30.1,-73.1,42.5,-66.2Z" transform="translate(100 100)"/></svg>') center/cover no-repeat;
            animation: rotate 60s linear infinite;
            pointer-events: none;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .hero p {
            font-size: 1.25rem;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .benefits {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin: 4rem 0;
        }

        .benefit-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .benefit-card h3 {
            color: #2b6cb0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .positions {
            margin-top: 4rem;
            scroll-margin-top: 80px; /* ให้ scroll ไม่ทับ navbar */
        }

        .positions h2 {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #2d3748;
        }

        .position-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .position-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .position-info h3 {
            color: #2d3748;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .position-meta {
            color: #718096;
            font-size: 0.925rem;
        }

        .position-meta span {
            display: inline-block;
            margin-right: 1.5rem;
        }

        .apply-btn {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
        }

        .apply-btn:active {
            transform: translateY(0);
        }

        .cta-section {
            text-align: center;
            background: linear-gradient(135deg, #ebf4ff 0%, #e6fffa 100%);
            padding: 4rem 2rem;
            margin-top: 4rem;
            border-radius: 24px;
        }

        .cta-section h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: #2b6cb0;
        }

        .cta-section p {
            margin-bottom: 2rem;
            color: #4a5568;
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

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 
                0 8px 32px rgba(31, 38, 135, 0.15),
                0 2px 8px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(150%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            max-width: 400px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            border-left: 4px solid #e53e3e;
        }

        .notification.success {
            border-left: 4px solid #48bb78;
        }

        .notification.error .notification-icon {
            background: #fed7d7;
            color: #e53e3e;
        }

        .notification.success .notification-icon {
            background: #c6f6d5;
            color: #48bb78;
        }

        .notification-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .notification-message {
            color: #718096;
            font-size: 0.875rem;
        }

        .notification-close {
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.2s ease;
        }

        .notification-close:hover {
            color: #4a5568;
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
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .position-card {
                padding: 1.75rem;
            }
            
            .benefits {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
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

            .nav-container {
                padding: 0 1rem;
            }

            .hero {
                margin-top: 60px;
                padding: 3rem 1rem;
            }

            .hero h1 {
                font-size: 2rem;
                padding: 0 1rem;
            }

            .hero p {
                font-size: 1rem;
                padding: 0 1rem;
            }

            .benefits {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                margin: 2rem 0;
            }

            .position-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .position-info {
                margin-bottom: 1rem;
            }

            .position-meta span {
                display: block;
                margin: 0.5rem 0;
            }

            .apply-btn {
                width: 100%;
                margin-top: 1rem;
            }

            .modal-container {
                padding: 2rem 1.5rem;
                width: 90%;
                max-width: 400px;
                border-radius: 20px;
            }

            .tab-btn {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }

            .close-btn {
                top: 0.75rem;
                right: 0.75rem;
            }

            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                width: auto;
                max-width: none;
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
        }

        @media (max-width: 480px) {
            .nav-menu {
                width: 85%;
                padding: 70px 1.5rem 1.5rem;
            }

            .hero h1 {
                font-size: 1.75rem;
            }

            .hero p {
                font-size: 0.9rem;
            }

            .benefit-card {
                padding: 1.25rem;
            }

            .benefit-card h3 {
                font-size: 1.25rem;
            }

            .cta-section {
                padding: 2rem 1rem;
            }

            .cta-section h2 {
                font-size: 1.5rem;
            }

            .modal-container {
                padding: 1.75rem 1.25rem;
                border-radius: 18px;
            }

            .tab-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .form-group input {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
            }

            .form-group label {
                left: 1.25rem;
                font-size: 0.9rem;
            }

            .form-group input:focus + label, 
            .form-group input:not(:placeholder-shown) + label {
                font-size: 0.8rem;
            }

            .submit-btn {
                font-size: 1rem;
                padding: 0.75rem;
            }

            .positions h2 {
                font-size: 1.5rem;
                text-align: center;
            }

            .position-card {
                padding: 1.25rem;
            }

            .position-info h3 {
                font-size: 1.25rem;
            }

            .position-meta {
                font-size: 0.85rem;
            }

            .apply-btn {
                font-size: 0.9rem;
                padding: 0.6rem 1.5rem;
            }

            .navbar {
                padding: 0.75rem 1rem;
            }

            .logo {
                font-size: 1.2rem;
            }

            .menu-btn {
                padding: 0.25rem;
            }
        }

        @media (max-width: 375px) {
            .logo {
                font-size: 1rem;
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .hero h1 {
                font-size: 1.5rem;
            }

            .hero p {
                font-size: 0.85rem;
            }

            .container {
                padding: 2rem 1rem;
            }

            .tabs {
                gap: 0.5rem;
            }

            .tab-btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
            }

            .form-group {
                margin-bottom: 1.25rem;
            }

            .form-group input {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }

            .form-group label {
                font-size: 0.85rem;
                left: 1rem;
            }

            .forgot-link {
                font-size: 0.8rem;
                margin: -0.5rem 0 1.5rem;
            }

            .submit-btn {
                font-size: 0.9rem;
            }

            .terms {
                font-size: 0.75rem;
            }

            .notification {
                padding: 0.75rem 1rem;
            }

            .notification-title {
                font-size: 0.9rem;
            }

            .notification-message {
                font-size: 0.8rem;
            }

            .notification-icon {
                width: 20px;
                height: 20px;
            }
        }

        @media (min-width: 1025px) {
            .container {
                padding: 5rem 2rem;
                max-width: 1200px;
            }
            
            .hero {
                padding: 8rem 2rem;
                margin-top: 80px;
            }
            
            .hero h1 {
                font-size: 3.5rem;
                margin-bottom: 2rem;
            }
            
            .hero p {
                font-size: 1.5rem;
                max-width: 800px;
            }
            
            .benefits {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 2.5rem;
                margin: 5rem 0;
            }
            
            .benefit-card {
                padding: 2.5rem;
                border-radius: 20px;
                transition: transform 0.4s ease, box-shadow 0.4s ease;
            }
            
            .benefit-card:hover {
                transform: translateY(-10px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            
            .position-card {
                padding: 2.5rem;
                border-radius: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: transform 0.4s ease, box-shadow 0.4s ease;
            }
            
            .position-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            
            .position-info h3 {
                font-size: 1.75rem;
                margin-bottom: 1rem;
            }
            
            .position-meta {
                font-size: 1rem;
            }
            
            .position-meta span {
                margin-right: 2rem;
            }
            
            .apply-btn {
                padding: 0.875rem 2.5rem;
                font-size: 1.1rem;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .apply-btn:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(66, 153, 225, 0.5);
            }
            
            .cta-section {
                padding: 5rem 3rem;
                border-radius: 30px;
                margin-top: 5rem;
            }
            
            .cta-section h2 {
                font-size: 2.5rem;
                margin-bottom: 1.5rem;
            }
            
            .cta-section p {
                font-size: 1.25rem;
                margin-bottom: 2.5rem;
                max-width: 800px;
                margin-left: auto;
                margin-right: auto;
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
            
            .modal-container {
                max-width: 500px;
                padding: 3rem;
                border-radius: 25px;
            }
            
            .form-group input {
                padding: 1rem 1.5rem;
                font-size: 1.1rem;
            }
            
            .form-group label {
                font-size: 1.1rem;
            }
            
            .submit-btn {
                padding: 1rem;
                font-size: 1.2rem;
            }
            
            .tab-btn {
                padding: 0.875rem 2.5rem;
                font-size: 1.2rem;
            }
        }

        @media (min-width: 1440px) {
            .container {
                max-width: 1400px;
                padding: 6rem 2rem;
            }
            
            .hero {
                padding: 10rem 2rem;
            }
            
            .hero h1 {
                font-size: 4rem;
            }
            
            .hero p {
                font-size: 1.75rem;
                max-width: 1000px;
            }
            
            .benefits {
                gap: 3rem;
                margin: 6rem 0;
            }
            
            .benefit-card {
                padding: 3rem;
            }
            
            .benefit-card h3 {
                font-size: 2rem;
                margin-bottom: 1.5rem;
            }
            
            .benefit-card p {
                font-size: 1.1rem;
                line-height: 1.8;
            }
            
            .positions h2 {
                font-size: 2.5rem;
                margin-bottom: 3rem;
            }
            
            .position-card {
                padding: 3rem;
                margin-bottom: 2.5rem;
            }
            
            .position-info h3 {
                font-size: 2rem;
            }
            
            .position-meta {
                font-size: 1.1rem;
            }
            
            .apply-btn {
                padding: 1rem 3rem;
                font-size: 1.2rem;
            }
            
            .cta-section {
                padding: 6rem 3rem;
                margin-top: 6rem;
            }
            
            .cta-section h2 {
                font-size: 3rem;
            }
            
            .cta-section p {
                font-size: 1.5rem;
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
            .container {
                max-width: 1800px;
                padding: 7rem 2rem;
            }
            
            .hero {
                padding: 12rem 2rem;
            }
            
            .hero h1 {
                font-size: 4.5rem;
            }
            
            .hero p {
                font-size: 2rem;
                max-width: 1200px;
            }
            
            .benefits {
                gap: 4rem;
                margin: 7rem 0;
            }
            
            .benefit-card {
                padding: 3.5rem;
                border-radius: 25px;
            }
            
            .benefit-card h3 {
                font-size: 2.25rem;
            }
            
            .benefit-card p {
                font-size: 1.25rem;
            }
            
            .positions h2 {
                font-size: 3rem;
            }
            
            .position-card {
                padding: 3.5rem;
                border-radius: 25px;
            }
            
            .position-info h3 {
                font-size: 2.25rem;
            }
            
            .position-meta {
                font-size: 1.25rem;
            }
            
            .position-meta span {
                margin-right: 3rem;
            }
            
            .apply-btn {
                padding: 1.25rem 3.5rem;
                font-size: 1.3rem;
                border-radius: 60px;
            }
            
            .cta-section {
                padding: 7rem 3rem;
                border-radius: 40px;
            }
            
            .cta-section h2 {
                font-size: 3.5rem;
            }
            
            .cta-section p {
                font-size: 1.75rem;
                max-width: 1000px;
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
            
            .benefit-card:hover {
                transform: translateY(-15px) scale(1.05);
            }
            
            .position-card:hover {
                transform: translateY(-10px) scale(1.02);
            }
            
            .apply-btn:hover {
                transform: translateY(-7px);
                box-shadow: 0 15px 30px rgba(66, 153, 225, 0.5);
            }
            
            .hero::after {
                content: '';
                position: absolute;
                bottom: -50px;
                left: 0;
                right: 0;
                height: 100px;
                background: linear-gradient(to bottom, rgba(237, 242, 247, 0), rgba(237, 242, 247, 1));
                pointer-events: none;
            }
            
            .benefit-card, .position-card, .cta-section {
                box-shadow: 
                    0 10px 40px rgba(0, 0, 0, 0.05),
                    0 0 0 1px rgba(0, 0, 0, 0.02);
            }
        }
    </style>

</head>
<body>

    <!-- แก้ไขส่วนการแจ้งเตือนทั้งหมด -->
    <div id="loginNotification" class="notification error">
        <div class="notification-icon">⚠️</div>
        <div class="notification-content">
            <div class="notification-title">เกิดข้อผิดพลาด</div>
            <div class="notification-message">อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาลองใหม่</div>
        </div>
        <button class="notification-close" onclick="hideNotification()">✕</button>
    </div>

    <div id="signupSuccessNotification" class="notification success">
        <div class="notification-icon">✓</div>
        <div class="notification-content">
            <div class="notification-title">สำเร็จ!</div>
            <div class="notification-message">สมัครสมาชิกเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ</div>
        </div>
        <button class="notification-close" onclick="hideSignupSuccessNotification()">✕</button>
    </div>

    <div id="emailExistsNotification" class="notification error">
        <div class="notification-icon">⚠️</div>
        <div class="notification-content">
            <div class="notification-title">ไม่สามารถสมัครสมาชิกได้</div>
            <div class="notification-message">อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่น</div>
        </div>
        <button class="notification-close" onclick="hideEmailExistsNotification()">✕</button>
    </div>

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
                        <span>ยินดีต้อนรับ, <?php echo $_SESSION['user_name']; ?></span>
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
                    <li><button onclick="showLoginModal()">เข้าสู่ระบบ</button></li>
                </ul>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <h1>มาร่วมสร้างอนาคตไปด้วยกัน</h1>
        <p>เราเชื่อว่าทุกคนมีศักยภาพที่จะเติบโตและประสบความสำเร็จ เรากำลังมองหาคนที่พร้อมจะก้าวไปข้างหน้าด้วยกัน</p>
    </section>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-container">
            <button class="close-btn" title="ปิด" id="closeModalBtn">&times;</button>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('login')">เข้าสู่ระบบ</button>
                <button class="tab-btn" onclick="switchTab('signup')">สมัครสมาชิก</button>
            </div>

            <form id="login-form" class="form-section active" action="login.php" method="post">
                <div class="form-group">
                    <input type="text" id="login-email" name="login-email" placeholder=" " required>
                    <label for="login-email">อีเมลหรือเบอร์โทรศัพท์</label>
                </div>
                <div class="form-group">
                    <input type="password" id="login-password" name="login-password" placeholder=" " required>
                    <label for="login-password">รหัสผ่าน</label>
                </div>
                <a href="#" class="forgot-link">ลืมรหัสผ่าน?</a>
                <button type="submit" class="submit-btn">เข้าสู่ระบบ</button>
            </form>

            <form id="signup-form" class="form-section" action="signup.php" method="post">
                <div class="form-group">
                    <input type="text" id="signup-name" name="signup-name" placeholder=" " required>
                    <label for="signup-name">ชื่อ-นามสกุล</label>
                </div>
                <div class="form-group">
                    <input type="email" id="signup-email" name="signup-email" placeholder=" " required>
                    <label for="signup-email">อีเมล</label>
                </div>
                <div class="form-group">
                    <input type="tel" id="signup-phone" name="signup-phone" placeholder=" " required>
                    <label for="signup-phone">เบอร์โทรศัพท์</label>
                </div>
                <div class="form-group">
                    <input type="password" id="signup-password" name="signup-password" placeholder=" " required>
                    <label for="signup-password">รหัสผ่าน</label>
                </div>
                <div class="form-group">
                    <input type="password" id="signup-confirm-password" name="signup-confirm-password" placeholder=" " required>
                    <label for="signup-confirm-password">ยืนยันรหัสผ่าน</label>
                </div>
                <button type="submit" class="submit-btn">สมัครสมาชิก</button>
                <p class="terms">
                    การสมัครสมาชิกถือว่าคุณยอมรับ <a href="#">ข้อกำหนดการใช้งาน</a> และ 
                    <a href="#">นโยบายความเป็นส่วนตัว</a>
                </p>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="benefits">
            <?php
                $benefits = [
                    [
                        'title' => 'การเติบโตในอาชีพ',
                        'desc' => 'โอกาสในการพัฒนาทักษะและความก้าวหน้าในสายอาชีพ พร้อมแผนการเติบโตที่ชัดเจน'
                    ],
                    [
                        'title' => 'สวัสดิการที่ดีที่สุด',
                        'desc' => 'ประกันสุขภาพครอบคลุม โบนัสประจำปี วันหยุดพักผ่อนที่ยืดหยุ่น และสวัสดิการอื่นๆ อีกมากมาย'
                    ],
                    [
                        'title' => 'วัฒนธรรมองค์กรที่เป็นกันเอง',
                        'desc' => 'สภาพแวดล้อมการทำงานที่เป็นมิตร เน้นการทำงานเป็นทีม และเคารพความคิดเห็นซึ่งกันและกัน'
                    ]
                ];
                
                foreach ($benefits as $benefit) {
                    echo '<div class="benefit-card">';
                    echo '<h3>' . $benefit['title'] . '</h3>';
                    echo '<p>' . $benefit['desc'] . '</p>';
                    echo '</div>';
                }
            ?>
        </div>

        <div id="positions" class="positions">
            <h2>ตำแหน่งที่เปิดรับ</h2>
            
            <?php
            // ใช้ข้อมูลที่ดึงมาจากฐานข้อมูล
            if (!empty($positions)) {
                foreach ($positions as $position) {
                    echo '<div class="position-card">';
                    echo '<div class="position-info">';
                    echo '<h3>' . htmlspecialchars($position['title']) . '</h3>';
                    echo '<div class="position-meta">';
                    echo '<span>💼 ' . htmlspecialchars($position['type']) . '</span>';
                    echo '<span>📍 ' . htmlspecialchars($position['location']) . '</span>';
                    echo '<span>💰 ' . htmlspecialchars($position['salary']) . '</span>';
                    echo '</div>';
                    echo '</div>';
                    
                    // ปรับปรุงลิงก์สมัครงาน
                    if (isset($_SESSION['user_id'])) {
                        echo '<a href="apply.php?position_id=' . $position['id'] . '" class="apply-btn">สมัครตำแหน่งนี้</a>';
                    } else {
                        echo '<button onclick="showLoginModal()" class="apply-btn">สมัครตำแหน่งนี้</button>';
                    }
                    
                    echo '</div>';
                }
            } else {
                // กรณีไม่มีข้อมูลในฐานข้อมูล
                echo '<div class="position-card">';
                echo '<div class="position-info">';
                echo '<h3>ไม่มีตำแหน่งงานที่เปิดรับในขณะนี้</h3>';
                echo '<div class="position-meta">';
                echo '<span>โปรดกลับมาตรวจสอบในภายหลัง</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="cta-section">
            <h2>พร้อมที่จะเริ่มต้นการเดินทางใหม่กับเราหรือยัง?</h2>
            <p>สมัครเลยวันนี้ และมาเป็นส่วนหนึ่งของทีมที่กำลังเติบโต</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- เปลี่ยนปุ่มไปยังแดชบอร์ดเป็นปุ่มดูตำแหน่งงาน -->
                <a href="#positions" class="apply-btn">ดูตำแหน่งงานทั้งหมด</a>
            <?php else: ?>
                <button onclick="showLoginModal()" class="apply-btn">สมัครเลย</button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const menuBtn = document.getElementById('menuBtn');
        const navMenu = document.getElementById('navMenu');
        const menuBackdrop = document.getElementById('menuBackdrop');
        const modalOverlay = document.getElementById('modalOverlay');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const userInfoToggle = document.getElementById('userInfoToggle');
        const userDropdown = document.getElementById('userDropdown');

        // สลับแท็บในฟอร์ม login/signup
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(form => form.classList.remove('active'));
            
            if (tab === 'login') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.querySelector('#login-form').classList.add('active');
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.querySelector('#signup-form').classList.add('active');
            }
        }

        // แสดง modal login
        function showLoginModal() {
            modalOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            // ถ้าเมนูมือถือเปิดอยู่ ให้ปิดก่อน
            if (navMenu) {
                navMenu.classList.remove('active');
            }
            if (menuBtn) {
                menuBtn.classList.remove('active');
            }
            if (userDropdown) {
                userDropdown.classList.remove('active');
            }
            menuBackdrop.classList.remove('active');
            menuBackdrop.classList.remove('dropdown-active');
        }

        // ซ่อน modal login
        function hideLoginModal() {
            modalOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // จัดการการแสดงผลแจ้งเตือน
        function showNotification() {
            hideLoginModal();
            const notification = document.getElementById('loginNotification');
            notification.classList.add('show');
            
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            const notification = document.getElementById('loginNotification');
            notification.classList.remove('show');
        }

        function showSignupSuccessNotification() {
            const notification = document.getElementById('signupSuccessNotification');
            notification.classList.add('show');
            
            showLoginModal();
            switchTab('login');
            
            setTimeout(() => {
                hideSignupSuccessNotification();
            }, 5000);
        }

        function hideSignupSuccessNotification() {
            const notification = document.getElementById('signupSuccessNotification');
            notification.classList.remove('show');
        }

        function showEmailExistsNotification() {
            if (modalOverlay.classList.contains('active')) {
                hideLoginModal();
            }
            
            const notification = document.getElementById('emailExistsNotification');
            notification.classList.add('show');
            
            setTimeout(() => {
                hideEmailExistsNotification();
            }, 5000);
        }

        function hideEmailExistsNotification() {
            const notification = document.getElementById('emailExistsNotification');
            notification.classList.remove('show');
        }

        // เพิ่มการจัดการ Dropdown Menu หลังล็อกอิน
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
        if (menuBtn && navMenu) {
            menuBtn.addEventListener('click', () => {
                menuBtn.classList.toggle('active');
                navMenu.classList.toggle('active');
                menuBackdrop.classList.toggle('active');
                
                // ป้องกันการเลื่อนหน้าเมื่อเมนูเปิดอยู่
                if (navMenu.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
        }

        // ปิดเมนูเมื่อคลิกที่ backdrop
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
                menuBackdrop.classList.remove('active');
                menuBackdrop.classList.remove('dropdown-active');
                document.body.style.overflow = '';
            }
        });

        // จัดการการปิด modal
        closeModalBtn.addEventListener('click', hideLoginModal);
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                hideLoginModal();
            }
        });

        // Smooth scroll เมื่อคลิกลิงก์ไปยังตำแหน่งบนหน้าเดียวกัน
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                if(this.getAttribute('href') !== '#') {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if(targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80, // ลบระยะห่างของ navbar
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });

        // แสดงข้อความแจ้งเตือนตาม URL parameters
        document.addEventListener('DOMContentLoaded', () => {
            <?php if (isset($_GET['login_error'])): ?>
                showNotification();
            <?php endif; ?>

            <?php if (isset($_GET['signup_success'])): ?>
                showSignupSuccessNotification();
            <?php endif; ?>

            <?php if (isset($_GET['signup_error']) && $_GET['signup_error'] === 'email_exists'): ?>
                showEmailExistsNotification();
            <?php endif; ?>
            
            <?php if (isset($_GET['showLogin'])): ?>
                showLoginModal();
            <?php endif; ?>
        });
        
        // จัดการการส่งฟอร์ม
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const email = document.getElementById('login-email').value.trim();
            const password = document.getElementById('login-password').value.trim();
            
            if (!email || !password) {
                e.preventDefault();
                alert('กรุณากรอกอีเมลและรหัสผ่าน');
            }
        });
        
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            const name = document.getElementById('signup-name').value.trim();
            const email = document.getElementById('signup-email').value.trim();
            const phone = document.getElementById('signup-phone').value.trim();
            const password = document.getElementById('signup-password').value.trim();
            const confirmPassword = document.getElementById('signup-confirm-password').value.trim();
            
            if (!name || !email || !phone || !password || !confirmPassword) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลให้ครบทุกช่อง');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
                return;
            }
            
            // ตรวจสอบรูปแบบเบอร์โทรศัพท์
            const phonePattern = /^[0-9\-\s]{9,15}$/;
            if (!phonePattern.test(phone)) {
                e.preventDefault();
                alert('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง');
                return;
            }
            
            // ตรวจสอบความยาวรหัสผ่าน
            if (password.length < 6) {
                e.preventDefault();
                alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                return;
            }
        });
    </script>
</body>
</html>