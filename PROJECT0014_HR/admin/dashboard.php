<?php
session_start();
require_once '../config.php';

// ตรวจสอบการล็อกอินและสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// ดึงข้อมูลสถิติ
$stats = [
    'total_applications' => 0,
    'open_positions' => 0,
    'approved' => 0,
    'pending' => 0,
    'department_applications' => [],
    'user_count' => 0,
    'job_types' => [],
    'rejected' => 0
];

// จำนวนใบสมัครทั้งหมด
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
$stats['total_applications'] = $result->fetch_assoc()['count'];

// จำนวนตำแหน่งที่เปิดรับ
$result = $conn->query("SELECT COUNT(*) as count FROM positions WHERE status = 'open'");
$stats['open_positions'] = $result->fetch_assoc()['count'];

// จำนวนใบสมัครที่ผ่านการคัดเลือก
$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
$stats['approved'] = $result->fetch_assoc()['count'];

// จำนวนใบสมัครที่ถูกปฏิเสธ
$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
$stats['rejected'] = $result->fetch_assoc()['count'];

// จำนวนใบสมัครที่รอพิจารณา
$result = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['count'];

// นับจำนวนการสมัครงานในแต่ละแผนก
$result = $conn->query("
    SELECT p.department, COUNT(*) as count 
    FROM applications a 
    JOIN positions p ON a.position_id = p.id 
    GROUP BY p.department
");
while ($row = $result->fetch_assoc()) {
    $stats['department_applications'][$row['department']] = $row['count'];
}

// นับจำนวนผู้ใช้งานทั้งหมด
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['user_count'] = $result->fetch_assoc()['count'];

// นับจำนวนตำแหน่งงานในแต่ละประเภท
$result = $conn->query("
    SELECT type, COUNT(*) as count 
    FROM positions 
    WHERE status = 'open' 
    GROUP BY type
");
while ($row = $result->fetch_assoc()) {
    $stats['job_types'][$row['type']] = $row['count'];
}

// ดึงข้อมูลใบสมัครล่าสุด
$latest_applications = $conn->query("
    SELECT a.*, p.title as position_title, u.name as applicant_name 
    FROM applications a 
    JOIN positions p ON a.position_id = p.id 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="หน้าควบคุมสำหรับผู้ดูแลระบบ HR TTV">
    <meta name="theme-color" content="#3182ce">
    <title>หน้าควบคุม - ผู้ดูแลระบบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* กำหนด CSS Variables สำหรับ Light Theme (default) */
        :root {
            /* Light Theme */
            --primary-color: #3182ce;
            --primary-dark: #2c5282;
            --accent-color: #4299e1;
            --success-color: #48bb78;
            --warning-color: #ecc94b;
            --danger-color: #e53e3e;
            
            --bg-color: #f7fafc;
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --modal-bg: #ffffff;
            --modal-overlay: rgba(0, 0, 0, 0.4);
            
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            
            --border-color: #e2e8f0;
            --divider-color: #edf2f7;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            
            --status-pending-bg: #fefcbf;
            --status-pending-text: #975a16;
            --status-approved-bg: #c6f6d5;
            --status-approved-text: #2f855a;
            --status-rejected-bg: #fed7d7;
            --status-rejected-text: #c53030;
            
            --stat-blue-bg: #ebf8ff;
            --stat-blue-text: #3182ce;
            --stat-yellow-bg: #fefcbf;
            --stat-yellow-text: #975a16;
            --stat-green-bg: #c6f6d5;
            --stat-green-text: #2f855a;
            --stat-red-bg: #fed7d7;
            --stat-red-text: #c53030;
            
            --avatar-bg: #e2e8f0;
            --avatar-text: #4a5568;
            
            --border-radius-sm: 0.375rem;
            --border-radius: 0.75rem;
            --border-radius-lg: 1rem;
            
            --transition-fast: 0.2s;
            --transition-normal: 0.3s;
            --transition-slow: 0.5s;
            
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 80px;
            
            --active-menu-bg: #ebf8ff;
            --hover-menu-bg: #f7fafc;
            --hover-item-bg: rgba(49, 130, 206, 0.05);
            --th-bg: #f7fafc;
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --primary-color: #63b3ed;
            --primary-dark: #3182ce;
            --accent-color: #4299e1;
            --success-color: #68d391;
            --warning-color: #f6e05e;
            --danger-color: #fc8181;
            
            --bg-color: #1a202c;
            --card-bg: #2d3748;
            --sidebar-bg: #2d3748;
            --modal-bg: #2d3748;
            --modal-overlay: rgba(0, 0, 0, 0.7);
            
            --text-primary: #f7fafc;
            --text-secondary: #e2e8f0;
            --text-muted: #cbd5e0;
            
            --border-color: #4a5568;
            --divider-color: #4a5568;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.4);
            
            --status-pending-bg: rgba(237, 137, 54, 0.2);
            --status-pending-text: #f6ad55;
            --status-approved-bg: rgba(72, 187, 120, 0.2);
            --status-approved-text: #68d391;
            --status-rejected-bg: rgba(229, 62, 62, 0.2);
            --status-rejected-text: #fc8181;
            
            --stat-blue-bg: rgba(66, 153, 225, 0.2);
            --stat-blue-text: #63b3ed;
            --stat-yellow-bg: rgba(236, 201, 75, 0.2);
            --stat-yellow-text: #f6e05e;
            --stat-green-bg: rgba(72, 187, 120, 0.2);
            --stat-green-text: #68d391;
            --stat-red-bg: rgba(229, 62, 62, 0.2);
            --stat-red-text: #fc8181;
            
            --avatar-bg: #4a5568;
            --avatar-text: #e2e8f0;
            
            --active-menu-bg: rgba(66, 153, 225, 0.2);
            --hover-menu-bg: #1a202c;
            --hover-item-bg: rgba(66, 153, 225, 0.1);
            --th-bg: #1a202c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Anuphan', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            position: relative;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
            color: var(--text-primary);
            transition: background-color var(--transition-normal);
        }

        /* --------- ANIMATIONS --------- */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideInLeft {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes ripple {
            0% {
                transform: scale(0);
                opacity: 0.5;
            }
            100% {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* --------- SIDEBAR --------- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            box-shadow: var(--shadow);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform var(--transition-normal) ease, width var(--transition-normal) ease, background-color var(--transition-normal);
            z-index: 100;
            scrollbar-width: thin;
            scrollbar-color: var(--text-muted) var(--divider-color);
            color: var(--text-primary);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background-color: var(--text-muted);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-track {
            background-color: var(--divider-color);
        }

        .sidebar-header {
            padding: 2rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            animation: fadeIn var(--transition-normal) ease-out forwards;
        }

        .admin-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--avatar-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--avatar-text);
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        .admin-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow);
        }

        .admin-details h3 {
            color: var(--text-primary);
            font-size: 1.125rem;
        }

        .admin-details p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-item {
            padding: 1rem 2rem;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all var(--transition-fast);
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background: var(--hover-menu-bg);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: var(--active-menu-bg);
            color: var(--primary-color);
            border-right: 3px solid var(--primary-color);
            font-weight: 500;
        }

        .menu-item::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 3px;
            height: 0;
            background: var(--primary-color);
            transition: height var(--transition-normal);
            transform: translateY(-50%);
        }

        .menu-item:hover::after {
            height: 70%;
        }

        

        /* --------- MAIN CONTENT --------- */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 2rem;
            transition: margin var(--transition-normal), padding var(--transition-normal), color var(--transition-normal);
            animation: fadeIn 0.5s ease;
            color: var(--text-primary);
        }

        .dashboard-header {
            margin-bottom: 2rem;
            animation: slideInLeft var(--transition-normal) ease;
        }

        .welcome-text h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: var(--text-muted);
        }

        /* --------- STATS GRID --------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
            animation: fadeIn var(--transition-slow) forwards, slideUp var(--transition-slow) forwards;
            opacity: 0;
            color: var(--text-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary-color);
            opacity: 0.5;
            transition: width var(--transition-normal);
        }

        .stat-card:hover::before {
            width: 10px;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all var(--transition-normal);
            box-shadow: var(--shadow-sm);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .stat-trend:hover {
            opacity: 0.8;
            transform: translateX(5px);
        }

        .trend-up { color: var(--success-color); }
        .trend-down { color: var(--danger-color); }

        /* --------- APPLICATIONS TABLE --------- */
        .applications-table {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeIn 0.8s ease forwards, slideUp 0.8s ease forwards;
            transition: all var(--transition-normal), background-color var(--transition-normal);
        }

        .applications-table:hover {
            box-shadow: var(--shadow-lg);
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--divider-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            color: var(--text-primary);
            font-size: 1.25rem;
        }

        .table-responsive {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--text-muted) var(--divider-color);
            -webkit-overflow-scrolling: touch; /* สำหรับรองรับ momentum scrolling บน iOS */
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background-color: var(--text-muted);
            border-radius: 20px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background-color: var(--divider-color);
            border-radius: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--th-bg);
            padding: 1rem;
            text-align: left;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.875rem;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 1px 0 var(--border-color);
            transition: background-color var(--transition-normal), color var(--transition-normal);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--divider-color);
            color: var(--text-primary);
            transition: all var(--transition-fast), color var(--transition-normal);
        }

        tr {
            transition: background-color var(--transition-fast);
        }

        tr:hover {
            background-color: var(--hover-item-bg);
        }

        tr:hover td {
            transform: translateX(3px);
        }

        .status-pill {
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        tr:hover .status-pill {
            transform: scale(1.05);
        }

        .status-pending {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
        }

        .status-approved {
            background: var(--status-approved-bg);
            color: var(--status-approved-text);
        }

        .status-rejected {
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: var(--card-bg);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
            margin: 0 0.15rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .action-btn.view { color: var(--primary-color); }
        .action-btn.approve { color: var(--success-color); }
        .action-btn.reject { color: var(--danger-color); }

        /* --------- LOADING SPINNER --------- */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--modal-overlay);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
            transition: all var(--transition-normal);
        }

        .loading.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border-color);
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* --------- MODAL STYLES --------- */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: var(--modal-overlay);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity var(--transition-normal);
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background-color: var(--modal-bg);
            margin: 10% auto;
            padding: 2rem;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
            transform: translateY(20px);
            opacity: 0;
            transition: all var(--transition-normal), background-color var(--transition-normal);
            color: var(--text-primary);
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .close-btn {
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition-fast);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-btn:hover {
            color: var(--primary-color);
            background-color: var(--hover-menu-bg);
        }

        .stat-item {
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius-sm);
            background-color: var(--hover-menu-bg);
            transition: all var(--transition-fast);
            animation: slideUp 0.3s ease;
            animation-fill-mode: both;
            color: var(--text-primary);
        }

        .stat-item:hover {
            transform: translateX(5px);
            background-color: var(--active-menu-bg);
        }

        /* --------- MENU TOGGLE & OVERLAY --------- */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--card-bg);
            border: none;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow);
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all var(--transition-normal);
            color: var(--text-primary);
        }

        .menu-toggle:hover {
            background: var(--hover-menu-bg);
            transform: scale(1.05);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--modal-overlay);
            z-index: 90;
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity var(--transition-normal);
        }

        /* --------- RIPPLE EFFECT --------- */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: var(--primary-color);
            opacity: 0.3;
            width: 100px;
            height: 100px;
            margin-top: -50px;
            margin-left: -50px;
            animation: ripple var(--transition-slow) linear;
            pointer-events: none;
        }

        /* --------- RESPONSIVE STYLES --------- */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            body.menu-open .overlay {
                display: block;
                opacity: 1;
            }

            body.menu-open .sidebar {
                transform: translateX(0);
            }

            body.menu-open .menu-toggle {
                left: 240px;
                background: var(--primary-color);
                color: white;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .modal-content {
                width: 90%;
                margin: 20% auto;
            }

            .welcome-text h1 {
                font-size: 1.5rem;
            }

            .action-buttons {
                justify-content: center;
            }

            .main-content {
                padding: 1.5rem;
            }

            .stat-card {
                padding: 1.25rem;
            }
            
            th, td {
                padding: 0.75rem;
            }
            
            .status-pill {
                padding: 0.25rem 0.75rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 600px) {
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding: 1.25rem;
            }
            
            .sidebar-header {
                padding: 1.5rem;
            }
            
            .menu-item {
                padding: 0.85rem 1.5rem;
            }
            
            body.menu-open .menu-toggle {
                left: 220px;
            }
            
            .theme-switcher {
                padding: 0.85rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }
            
            .welcome-text p {
                font-size: 0.9rem;
            }
            
            .welcome-text h1 {
                font-size: 1.35rem;
            }

            .action-btn {
                padding: 0.4rem;
                font-size: 0.9rem;
            }
            
            table {
                font-size: 0.9rem;
            }
            
            th {
                white-space: nowrap;
            }
            
            .modal-content {
                padding: 1.5rem;
            }
            
            .modal-header h2 {
                font-size: 1.2rem;
            }
            
            .stat-item {
                padding: 0.65rem 0.85rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 375px) {
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
            
            .stat-title {
                font-size: 0.8rem;
            }
            
            .stat-value {
                font-size: 1.35rem;
                margin-bottom: 0.35rem;
            }
            
            .stat-trend {
                font-size: 0.8rem;
            }
            
            .action-buttons {
                gap: 0.3rem;
            }
            
            .action-btn {
                padding: 0.35rem;
                font-size: 0.85rem;
                margin: 0 0.1rem;
            }
            
            .admin-avatar {
                width: 40px;
                height: 40px;
            }
            
            .admin-details h3 {
                font-size: 1rem;
            }
            
            .admin-details p {
                font-size: 0.8rem;
            }
            
            body.menu-open .menu-toggle {
                left: 200px;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.25rem;
            }
            
            .switch {
                width: 50px;
                height: 26px;
            }
            
            .slider:before {
                height: 18px;
                width: 18px;
            }
            
            input:checked + .slider:before {
                transform: translateX(24px);
            }
            
            .theme-icons {
                font-size: 1rem;
            }
        }

        @media (min-width: 1440px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2rem;
            }
            
            .stat-card {
                padding: 2rem;
            }
            
            .stat-icon {
                width: 56px;
                height: 56px;
                font-size: 1.75rem;
            }
            
            .stat-value {
                font-size: 2.25rem;
            }
            
            .welcome-text h1 {
                font-size: 2.5rem;
            }
            
            .welcome-text p {
                font-size: 1.15rem;
            }
            
            .admin-avatar {
                width: 56px;
                height: 56px;
            }
            
            .admin-details h3 {
                font-size: 1.3rem;
            }
            
            .applications-table {
                border-radius: var(--border-radius-lg);
            }
            
            table {
                font-size: 1.05rem;
            }
            
            .table-header h2 {
                font-size: 1.5rem;
            }
        }

        @media (min-width: 1920px) {
            .container {
                max-width: 1800px;
                margin: 0 auto;
            }
            
            .main-content {
                padding: 3rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2.5rem;
            }
            
            .stat-card {
                padding: 2.5rem;
            }
            
            .stat-icon {
                width: 64px;
                height: 64px;
                font-size: 2rem;
            }
            
            .stat-value {
                font-size: 2.5rem;
            }
            
            .welcome-text h1 {
                font-size: 3rem;
            }
            
            .welcome-text p {
                font-size: 1.25rem;
            }
            
            .applications-table {
                margin-top: 2.5rem;
            }
            
            .table-header {
                padding: 2rem;
            }
            
            th, td {
                padding: 1.25rem;
            }
        }

        /* รองรับ reduced motion preference */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        /* Delay for animation of stat cards */
        .stats-grid .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-grid .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-grid .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-grid .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stats-grid .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stats-grid .stat-card:nth-child(6) { animation-delay: 0.6s; }

        .stat-item:nth-child(1) { animation-delay: 0.05s; }
        .stat-item:nth-child(2) { animation-delay: 0.1s; }
        .stat-item:nth-child(3) { animation-delay: 0.15s; }
        .stat-item:nth-child(4) { animation-delay: 0.2s; }
        .stat-item:nth-child(5) { animation-delay: 0.25s; }
        .stat-item:nth-child(6) { animation-delay: 0.3s; }
        .stat-item:nth-child(7) { animation-delay: 0.35s; }
        .stat-item:nth-child(8) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Overlay สำหรับโหมดมือถือ -->
    <div class="overlay"></div>

    <!-- ปุ่มเมนูสำหรับโหมดมือถือ -->
    <button class="menu-toggle" aria-label="เปิด/ปิดเมนู">☰</button>

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                </div>
                <div class="admin-details">
                    <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                    <p>ผู้ดูแลระบบ</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <a href="../index.php" class="menu-item">
                🏠 กลับสู่หน้าหลัก
            </a>
            <a href="dashboard.php" class="menu-item active">
                📊 แดชบอร์ด
            </a>
            <a href="usermanage.php" class="menu-item">
                👥 จัดการผู้ใช้งาน
            </a>
            <a href="positions.php" class="menu-item">
                💼 จัดการตำแหน่งงาน
            </a>
            <a href="../logout.php" class="menu-item" style="color: var(--danger-color);">
                🚪 ออกจากระบบ
            </a>
        </nav>
        
        
    </aside>

    <main class="main-content">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>แดชบอร์ด</h1>
                <p>ภาพรวมของระบบและสถิติที่สำคัญ</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ใบสมัครทั้งหมด</span>
                    <div class="stat-icon" style="background: var(--stat-blue-bg); color: var(--stat-blue-text);">📝</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_applications']); ?></div>
                <div class="stat-trend" onclick="openDepartmentModal()">
                    ดูรายละเอียดแผนก
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ตำแหน่งที่เปิดรับ</span>
                    <div class="stat-icon" style="background: var(--stat-yellow-bg); color: var(--stat-yellow-text);">💼</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['open_positions']); ?></div>
                <div class="stat-trend" onclick="openJobTypeModal()">
                    ดูประเภทงาน
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ผ่านการคัดเลือก</span>
                    <div class="stat-icon" style="background: var(--stat-green-bg); color: var(--stat-green-text);">✅</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['approved']); ?></div>
                <div class="stat-trend trend-up">
                    จากใบสมัครทั้งหมด
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ถูกปฏิเสธ</span>
                    <div class="stat-icon" style="background: var(--stat-red-bg); color: var(--stat-red-text);">❌</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
                <div class="stat-trend trend-down">
                    ไม่ผ่านการคัดเลือก
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">จำนวนผู้ใช้งาน</span>
                    <div class="stat-icon" style="background: var(--stat-green-bg); color: var(--stat-green-text);">👥</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['user_count']); ?></div>
                <div class="stat-trend">
                    ลงทะเบียนทั้งหมด
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">รอการพิจารณา</span>
                    <div class="stat-icon" style="background: var(--stat-yellow-bg); color: var(--stat-yellow-text);">⏳</div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-trend">
                    ต้องได้รับการพิจารณา
                </div>
            </div>
        </div>

        <div class="applications-table">
            <div class="table-header">
                <h2>ใบสมัครล่าสุด</h2>
                <a href="applications.php" class="action-btn view">ดูทั้งหมด</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อผู้สมัคร</th>
                            <th>ตำแหน่ง</th>
                            <th>วันที่สมัคร</th>
                            <th>สถานะ</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = $latest_applications->fetch_assoc()): ?>
                            <tr data-id="<?php echo $app['id']; ?>">
                                <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['position_title']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($app['status']) {
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            $status_text = 'รอดำเนินการ';
                                            break;
                                        case 'approved':
                                            $status_class = 'status-approved';
                                            $status_text = 'ผ่านการคัดเลือก';
                                            break;
                                        case 'rejected':
                                            $status_class = 'status-rejected';
                                            $status_text = 'ไม่ผ่านการคัดเลือก';
                                            break;
                                    }
                                    ?>
                                    <span class="status-pill <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewApplication(<?php echo $app['id']; ?>)" 
                                                class="action-btn view" title="ดูรายละเอียด">👁️</button>
                                        
                                        <?php if ($app['status'] == 'pending'): ?>
                                            <button onclick="updateStatus(<?php echo $app['id']; ?>, 'approved')" 
                                                    class="action-btn approve" title="อนุมัติ">✅</button>
                                            <button onclick="updateStatus(<?php echo $app['id']; ?>, 'rejected')" 
                                                    class="action-btn reject" title="ปฏิเสธ">❌</button>
                                        <?php endif; ?>
                                        
                                        <button onclick="sendEmail(<?php echo $app['id']; ?>)" 
                                                class="action-btn" title="ส่งอีเมล">📧</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal สำหรับแสดงรายละเอียดแผนก -->
    <div id="departmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>สถิติการสมัครงานแยกตามแผนก</h2>
                <span class="close-btn" onclick="closeDepartmentModal()">&times;</span>
            </div>
            <div id="departmentStats">
                <?php foreach ($stats['department_applications'] as $dept => $count): ?>
                    <div class="stat-item">
                        <strong><?php echo htmlspecialchars($dept); ?>:</strong> 
                        <?php echo number_format($count); ?> ใบสมัคร
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับแสดงประเภทงาน -->
    <div id="jobTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ประเภทงานที่เปิดรับ</h2>
                <span class="close-btn" onclick="closeJobTypeModal()">&times;</span>
            </div>
            <div id="jobTypeStats">
                <?php foreach ($stats['job_types'] as $type => $count): ?>
                    <div class="stat-item">
                        <strong><?php echo htmlspecialchars($type); ?>:</strong> 
                        <?php echo number_format($count); ?> ตำแหน่ง
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="loading">
        <div class="loading-spinner"></div>
    </div>

    <script>
        // จัดการธีม
        const themeToggle = document.getElementById('theme-toggle');
        const htmlElement = document.documentElement;
        
        // ตรวจสอบธีมที่บันทึกไว้ใน localStorage
        function loadTheme() {
            const savedTheme = localStorage.getItem('admin-theme');
            if (savedTheme === 'dark') {
                htmlElement.setAttribute('data-theme', 'dark');
                themeToggle.checked = true;
                updateMetaThemeColor('#1a202c'); // สีพื้นหลังของ Dark Theme
            } else {
                htmlElement.removeAttribute('data-theme');
                themeToggle.checked = false;
                updateMetaThemeColor('#3182ce'); // สีเริ่มต้นของ Light Theme
            }
        }
        
        // บันทึกธีมลง localStorage
        function saveTheme(isDark) {
            if (isDark) {
                localStorage.setItem('admin-theme', 'dark');
                updateMetaThemeColor('#1a202c');
            } else {
                localStorage.setItem('admin-theme', 'light');
                updateMetaThemeColor('#3182ce');
            }
        }
        
        // อัพเดทสี theme-color สำหรับ mobile browsers
        function updateMetaThemeColor(color) {
            const metaThemeColor = document.querySelector('meta[name="theme-color"]');
            if (metaThemeColor) {
                metaThemeColor.setAttribute('content', color);
            }
        }
        
        // Event listener สำหรับการเปลี่ยนธีม
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                htmlElement.setAttribute('data-theme', 'dark');
                saveTheme(true);
            } else {
                htmlElement.removeAttribute('data-theme');
                saveTheme(false);
            }
        });
        
        // โหลดธีมเมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', loadTheme);
        
        // ฟังก์ชันเพิ่ม ripple effect ให้กับปุ่ม
        function createRipple(event) {
            const button = event.currentTarget;
            
            // ตรวจสอบว่ามี ripple element อยู่แล้วหรือไม่
            const existingRipple = button.querySelector('.ripple');
            if (existingRipple) {
                existingRipple.remove();
            }
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            // คำนวณตำแหน่ง ripple จากจุดที่คลิก
            const rect = button.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;
            
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            button.appendChild(ripple);
            
            // ลบ ripple หลังจากแสดงผล
            setTimeout(() => {
                ripple.remove();
            }, 600);
        }
        
        // สำหรับการสัมผัสบนอุปกรณ์ทัชสกรีน
        function handleTouchStart(event) {
            const touch = event.touches[0];
            const button = event.currentTarget;
            
            // ตรวจสอบว่ามี ripple element อยู่แล้วหรือไม่
            const existingRipple = button.querySelector('.ripple');
            if (existingRipple) {
                existingRipple.remove();
            }
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            
            // คำนวณตำแหน่ง ripple จากจุดที่แตะ
            const rect = button.getBoundingClientRect();
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            button.appendChild(ripple);
            
            // ลบ ripple หลังจากแสดงผล
            setTimeout(() => {
                ripple.remove();
            }, 600);
        }
        
        // แสดง/ซ่อน loading
        function toggleLoading(show = true) {
            document.querySelector('.loading').classList.toggle('active', show);
        }

        // ดูรายละเอียดใบสมัคร
        function viewApplication(id) {
            window.location.href = `application-detail.php?id=${id}`;
        }

        // อัพเดทสถานะใบสมัคร
        async function updateStatus(id, status) {
            if (!confirm('คุณต้องการเปลี่ยนสถานะใบสมัครนี้ใช่หรือไม่?')) return;

            toggleLoading(true);
            try {
                const response = await fetch('update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id, status })
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    const statusCell = row.querySelector('.status-pill');
                    const actionButtons = row.querySelector('.action-buttons');
                    
                    // อัพเดทการแสดงผลสถานะ
                    statusCell.className = `status-pill status-${status}`;
                    statusCell.textContent = status === 'approved' ? 'ผ่านการคัดเลือก' : 'ไม่ผ่านการคัดเลือก';
                    
                    // อัพเดทปุ่มดำเนินการ
                    actionButtons.innerHTML = `
                        <button onclick="viewApplication(${id})" class="action-btn view" title="ดูรายละเอียด">👁️</button>
                        <button onclick="sendEmail(${id})" class="action-btn" title="ส่งอีเมล">📧</button>
                    `;
                    
                    // เพิ่ม ripple effect และ touch events ให้กับปุ่มใหม่
                    const newButtons = actionButtons.querySelectorAll('.action-btn');
                    newButtons.forEach(btn => {
                        btn.addEventListener('click', createRipple);
                        btn.addEventListener('touchstart', handleTouchStart);
                    });
                    
                    // รีเฟรชข้อมูลสถิติ
                    location.reload();
                } else {
                    throw new Error(result.message || 'เกิดข้อผิดพลาด');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาด: ' + error.message);
            } finally {
                toggleLoading(false);
            }
        }

        // ส่งอีเมล
        function sendEmail(id) {
            window.location.href = `send-email.php?application_id=${id}`;
        }

        // จัดการ Modal แผนก
        function openDepartmentModal() {
            const modal = document.getElementById('departmentModal');
            modal.style.display = 'block';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeDepartmentModal() {
            const modal = document.getElementById('departmentModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // จัดการ Modal ประเภทงาน
        function openJobTypeModal() {
            const modal = document.getElementById('jobTypeModal');
            modal.style.display = 'block';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeJobTypeModal() {
            const modal = document.getElementById('jobTypeModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // จัดการ sidebar และ overlay
        function toggleSidebar() {
            document.body.classList.toggle('menu-open');
        }

        // ยืนยันการออกจากระบบ
        document.querySelector('a[href="../logout.php"]').addEventListener('click', (e) => {
            if (!confirm('คุณต้องการออกจากระบบใช่หรือไม่?')) {
                e.preventDefault();
            }
        });

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม ripple effect ให้กับปุ่มทั้งหมด
            const buttons = document.querySelectorAll('.action-btn, .menu-item');
            buttons.forEach(button => {
                button.addEventListener('click', createRipple);
                button.addEventListener('touchstart', handleTouchStart);
            });

            // จัดการ menu toggle
            const menuToggle = document.querySelector('.menu-toggle');
            menuToggle.addEventListener('click', toggleSidebar);
            menuToggle.addEventListener('touchstart', (e) => {
                e.preventDefault(); // ป้องกันการซูมบนบางอุปกรณ์
                toggleSidebar();
            });

            // จัดการ overlay
            const overlay = document.querySelector('.overlay');
            overlay.addEventListener('click', function() {
                document.body.classList.remove('menu-open');
            });
            overlay.addEventListener('touchstart', function(e) {
                e.preventDefault();
                document.body.classList.remove('menu-open');
            });

            // จัดการการปิด modal เมื่อคลิกนอกกรอบ
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (modal.id === 'departmentModal') {
                            closeDepartmentModal();
                        } else if (modal.id === 'jobTypeModal') {
                            closeJobTypeModal();
                        }
                    }
                });
                
                // รองรับทัชสกรีน
                modal.addEventListener('touchstart', function(e) {
                    if (e.target === this) {
                        if (modal.id === 'departmentModal') {
                            closeDepartmentModal();
                        } else if (modal.id === 'jobTypeModal') {
                            closeJobTypeModal();
                        }
                    }
                });
            });

            // แสดงผล animation สำหรับ cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${0.1 * (index + 1)}s`;
            });

            // ปรับขนาดหน้าจอเมื่อ resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024) {
                    document.body.classList.remove('menu-open');
                }
            });
            
            // ตรวจสอบ orientation change สำหรับมือถือ
            window.addEventListener('orientationchange', function() {
                // ปิด sidebar เมื่อหมุนหน้าจอ
                document.body.classList.remove('menu-open');
                
                // ปิด modals เมื่อหมุนหน้าจอ
                const departmentModal = document.getElementById('departmentModal');
                const jobTypeModal = document.getElementById('jobTypeModal');
                
                if (departmentModal && departmentModal.style.display === 'block') {
                    closeDepartmentModal();
                }
                
                if (jobTypeModal && jobTypeModal.style.display === 'block') {
                    closeJobTypeModal();
                }
            });
            
            // สำหรับอุปกรณ์ทัชสกรีน: ทำให้สามารถเลื่อนตารางโดยการแตะและลาก
            const tableResponsive = document.querySelector('.table-responsive');
            if (tableResponsive) {
                let isScrolling = false;
                let startX;
                let scrollLeft;
                
                tableResponsive.addEventListener('touchstart', function(e) {
                    isScrolling = true;
                    startX = e.touches[0].pageX - tableResponsive.offsetLeft;
                    scrollLeft = tableResponsive.scrollLeft;
                });
                
                tableResponsive.addEventListener('touchmove', function(e) {
                    if (!isScrolling) return;
                    const x = e.touches[0].pageX - tableResponsive.offsetLeft;
                    const walk = (x - startX) * 1.5; // เพิ่มความเร็วในการเลื่อน
                    tableResponsive.scrollLeft = scrollLeft - walk;
                });
                
                tableResponsive.addEventListener('touchend', function() {
                    isScrolling = false;
                });
            }
            
            // ตรวจสอบ system dark mode preference
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
            
            // หากผู้ใช้ยังไม่ได้ตั้งค่าธีมด้วยตนเอง ให้ใช้ค่าที่ระบบตั้งไว้
            if (localStorage.getItem('admin-theme') === null) {
                if (prefersDarkScheme.matches) {
                    htmlElement.setAttribute('data-theme', 'dark');
                    themeToggle.checked = true;
                    saveTheme(true);
                } else {
                    htmlElement.removeAttribute('data-theme');
                    themeToggle.checked = false;
                    saveTheme(false);
                }
            }
            
            // ติดตามการเปลี่ยนแปลง system preference
            prefersDarkScheme.addEventListener('change', (e) => {
                // เปลี่ยนธีมตาม system preference เฉพาะเมื่อผู้ใช้ไม่ได้ตั้งค่าด้วยตนเอง
                if (localStorage.getItem('admin-theme') === null) {
                    if (e.matches) {
                        htmlElement.setAttribute('data-theme', 'dark');
                        themeToggle.checked = true;
                    } else {
                        htmlElement.removeAttribute('data-theme');
                        themeToggle.checked = false;
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php 
// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();
?>