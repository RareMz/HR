<?php
session_start();
require_once '../config.php';

// ตรวจสอบการล็อกอินและสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// ฟังก์ชันสำหรับการตรวจสอบข้อมูลนำเข้า
function validatePositionData($title, $department) {
    $errors = [];
    
    if (empty($title) || strlen($title) > 100) {
        $errors[] = "กรุณาระบุชื่อตำแหน่งให้ถูกต้อง (ไม่เกิน 100 ตัวอักษร)";
    }
    
    if (empty($department)) {
        $errors[] = "กรุณาระบุแผนก";
    }
    
    return $errors;
}

// ฟังก์ชันสำหรับเตรียมข้อมูลตำแหน่งงานจากฟอร์ม
function preparePositionData($isEdit = false) {
    $prefix = $isEdit ? 'edit_' : '';
    
    return [
        'title' => trim($_POST[$prefix . 'title']),
        'department' => trim($_POST[$prefix . 'department']),
        'description' => trim($_POST[$prefix . 'description']),
        'requirements' => trim($_POST[$prefix . 'requirements']),
        'type' => trim($_POST[$prefix . 'type']),
        'location' => trim($_POST[$prefix . 'location']),
        'salary_min' => isset($_POST[$prefix . 'salary_min']) && $_POST[$prefix . 'salary_min'] !== '' ? intval($_POST[$prefix . 'salary_min']) : null,
        'salary_max' => isset($_POST[$prefix . 'salary_max']) && $_POST[$prefix . 'salary_max'] !== '' ? intval($_POST[$prefix . 'salary_max']) : null,
        'status' => trim($_POST[$prefix . 'status'])
    ];
}

// จัดการการเพิ่มตำแหน่งงานใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    $positionData = preparePositionData();
    $errors = validatePositionData($positionData['title'], $positionData['department']);
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO positions (title, department, description, requirements, type, location, salary_min, salary_max, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssiiis", 
            $positionData['title'], 
            $positionData['department'], 
            $positionData['description'], 
            $positionData['requirements'], 
            $positionData['type'], 
            $positionData['location'], 
            $positionData['salary_min'], 
            $positionData['salary_max'], 
            $positionData['status'], 
            $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "เพิ่มตำแหน่งงานเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเพิ่มตำแหน่งงาน: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    header("Location: positions.php");
    exit();
}

// จัดการการแก้ไขตำแหน่งงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_position'])) {
    $position_id = intval($_POST['position_id']);
    $positionData = preparePositionData(true);
    $errors = validatePositionData($positionData['title'], $positionData['department']);
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE positions SET title = ?, department = ?, description = ?, requirements = ?, type = ?, location = ?, salary_min = ?, salary_max = ?, status = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ?");
        $stmt->bind_param("ssssssiisii", 
            $positionData['title'], 
            $positionData['department'], 
            $positionData['description'], 
            $positionData['requirements'], 
            $positionData['type'], 
            $positionData['location'], 
            $positionData['salary_min'], 
            $positionData['salary_max'], 
            $positionData['status'], 
            $_SESSION['user_id'], 
            $position_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "อัพเดตตำแหน่งงานเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัพเดตข้อมูล: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    header("Location: positions.php");
    exit();
}

// จัดการการเปิด/ปิดรับสมัคร
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $position_id = intval($_POST['position_id']);
    $new_status = $_POST['new_status'] === 'open' ? 'open' : 'closed';
    
    $stmt = $conn->prepare("UPDATE positions SET status = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $position_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = $new_status === 'open' ? "เปิดรับสมัครตำแหน่งงานเรียบร้อยแล้ว" : "ปิดรับสมัครตำแหน่งงานเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัพเดตสถานะ: " . $conn->error;
    }
    
    header("Location: positions.php");
    exit();
}

// จัดการการลบตำแหน่งงาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_position'])) {
    $position_id = intval($_POST['position_id']);
    
    // ตรวจสอบว่ามีใบสมัครที่เกี่ยวข้องกับตำแหน่งนี้หรือไม่
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE position_id = ?");
    $stmt->bind_param("i", $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $application_count = $result->fetch_assoc()['count'];
    
    if ($application_count > 0) {
        $_SESSION['error'] = "ไม่สามารถลบตำแหน่งนี้ได้เนื่องจากมีใบสมัครที่เกี่ยวข้อง $application_count ใบ";
    } else {
        // ลบตำแหน่งงาน
        $stmt = $conn->prepare("DELETE FROM positions WHERE id = ?");
        $stmt->bind_param("i", $position_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "ลบตำแหน่งงานเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $conn->error;
        }
    }
    
    header("Location: positions.php");
    exit();
}

// การค้นหาตำแหน่งงาน
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// ดึงข้อมูลตำแหน่งงาน
$positions_query = "
    SELECT 
        p.*,
        COUNT(DISTINCT a.id) as total_applications,
        u_created.name as created_by_name
    FROM positions p
    LEFT JOIN applications a ON p.id = a.position_id
    LEFT JOIN users u_created ON p.created_by = u_created.id
    WHERE 1=1
";

// เพิ่มเงื่อนไขการค้นหา
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%{$search}%";
    $positions_query .= " AND (p.title LIKE ? OR p.department LIKE ?)";
    $types .= "ss";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    $positions_query .= " AND p.status = ?";
    $types .= "s";
    $params[] = $status;
}

$positions_query .= " GROUP BY p.id ORDER BY p.created_at DESC";

$stmt = $conn->prepare($positions_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$positions_result = $stmt->get_result();

// นับจำนวนตำแหน่งงานทั้งหมด
$stmt = $conn->query("SELECT COUNT(*) as total FROM positions");
$total_positions = $stmt->fetch_assoc()['total'];

// นับจำนวนตำแหน่งงานที่เปิดรับสมัคร
$stmt = $conn->query("SELECT COUNT(*) as total FROM positions WHERE status = 'open'");
$open_positions = $stmt->fetch_assoc()['total'];

// นับจำนวนตำแหน่งงานที่ปิดรับสมัคร
$stmt = $conn->query("SELECT COUNT(*) as total FROM positions WHERE status = 'closed'");
$closed_positions = $stmt->fetch_assoc()['total'];

// นับจำนวนตำแหน่งงานแยกตามแผนก
$dept_query = $conn->query("SELECT department, COUNT(*) as count FROM positions GROUP BY department ORDER BY count DESC");
$departments = [];
while ($row = $dept_query->fetch_assoc()) {
    $departments[$row['department']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="หน้าจัดการตำแหน่งงาน - ระบบรับสมัครงาน HR TTV">
    <meta name="theme-color" content="#3182ce">
    <title>จัดการตำแหน่งงาน - ระบบรับสมัครงาน</title>
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

        /* --------- THEME SWITCHER --------- */
.theme-switcher {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--divider-color);
    margin-top: 1rem;
}

.theme-container {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 10px;
    background-color: var(--bg-color);
    border-radius: 50px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0; /* เพิ่มเส้นขอบสำหรับโหมดสว่าง */
}

.theme-icon {
    font-size: 1.2rem;
    line-height: 1;
}

.theme-icon.sun {
    color: #F6AD55;
}

.theme-icon.moon {
    color: #F6E05E;
}

.switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 26px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #E2E8F0;
    transition: .3s;
    border-radius: 34px;
    border: 1px solid #cbd5e0; /* เพิ่มเส้นขอบให้กับสไลเดอร์ */
}

.slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0; /* เพิ่มเส้นขอบให้กับปุ่ม */
}

input:checked + .slider {
    background-color: #63B3ED;
    border-color: #4299e1; /* ปรับสีขอบเมื่อเปิดใช้งาน */
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Dark theme adjustments */
[data-theme="dark"] .theme-container {
    background-color: #2D3748;
    border-color: #4a5568; /* ปรับสีขอบสำหรับโหมดมืด */
}

[data-theme="dark"] .slider {
    background-color: #4A5568;
    border-color: #2d3748; /* ปรับสีขอบสไลเดอร์สำหรับโหมดมืด */
}

[data-theme="dark"] .slider:before {
    border-color: #4a5568; /* ปรับสีขอบปุ่มสำหรับโหมดมืด */
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

        /* --------- SEARCH & FILTERS --------- */
        .search-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-box {
            flex-grow: 1;
            display: flex;
            align-items: center;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all var(--transition-normal);
        }

        .search-box:focus-within {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .search-input {
            flex-grow: 1;
            padding: 0.8rem 1rem;
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .search-input:focus {
            outline: none;
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 1.25rem;
            cursor: pointer;
            transition: background-color var(--transition-fast);
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        .filter-dropdown {
            min-width: 150px;
            padding: 0.8rem 1rem;
            border: none;
            background-color: var(--card-bg);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .filter-dropdown:focus {
            outline: none;
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .add-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 1.25rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
            transition: all var(--transition-normal);
        }

        .add-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* --------- POSITIONS TABLE --------- */
        .positions-table {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeIn 0.8s ease forwards, slideUp 0.8s ease forwards;
            transition: all var(--transition-normal), background-color var(--transition-normal);
        }

        .positions-table:hover {
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

        .status-open {
            background: var(--status-approved-bg);
            color: var(--status-approved-text);
        }

        .status-closed {
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
        }

        .status-draft {
            background: var(--status-pending-bg);
            color: var(--status-pending-text);
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
        .action-btn.edit { color: var(--primary-color); }
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

        /* --------- ALERTS --------- */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            animation: slideInLeft 0.5s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: var(--status-approved-bg);
            color: var(--status-approved-text);
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: var(--status-rejected-bg);
            color: var(--status-rejected-text);
            border-left: 4px solid var(--danger-color);
        }

        /* --------- MODAL STYLES --------- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--modal-overlay);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 20;
            padding: 1rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-normal);
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal {
            background-color: var(--modal-bg);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            box-shadow: var(--shadow-lg);
            transform: translateY(20px);
            transition: all var(--transition-normal), background-color var(--transition-normal);
            color: var(--text-primary);
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            color: var(--text-muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color var(--transition-fast);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: none;
            border: none;
        }

        .modal-close:hover {
            color: var(--primary-color);
            background-color: var(--hover-menu-bg);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background-color: var(--card-bg);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .input-group .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--divider-color);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--hover-menu-bg);
        }

        .no-positions {
            padding: 3rem 2rem;
            text-align: center;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .no-positions h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
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
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex !important;
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
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .input-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-footer {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
            }

            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-btn {
                padding: 0.5rem;
                width: 100%;
                text-align: center;
            }
        }

        /* Delay for animation of stat cards */
        .stats-grid .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-grid .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-grid .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-grid .stat-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>

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
            <a href="dashboard.php" class="menu-item">
                📊 แดชบอร์ด
            </a>
            <a href="usermanage.php" class="menu-item">
                👥 จัดการผู้ใช้งาน
            </a>
            <a href="positions.php" class="menu-item active">
                💼 จัดการตำแหน่งงาน
            </a>
            <a href="../logout.php" class="menu-item" style="color: var(--danger-color);">
                🚪 ออกจากระบบ
            </a>
        </nav>
        
        <!-- Theme Switcher -->
<div class="theme-switcher">
    <div class="theme-container">
        <span class="theme-icon sun">☀️</span>
        <label class="switch">
            <input type="checkbox" id="theme-toggle">
            <span class="slider"></span>
        </label>
        <span class="theme-icon moon">🌙</span>
    </div>
</div>
    </aside>

    <main class="main-content">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>จัดการตำแหน่งงาน</h1>
                <p>จัดการตำแหน่งงานที่เปิดรับสมัคร ติดตามสถานะและการสมัคร</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ตำแหน่งทั้งหมด</span>
                    <div class="stat-icon" style="background: var(--stat-blue-bg); color: var(--stat-blue-text);">📝</div>
                </div>
                <div class="stat-value"><?php echo number_format($total_positions); ?></div>
                <div class="stat-trend">
                    ตำแหน่งทั้งหมดในระบบ
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">เปิดรับสมัคร</span>
                    <div class="stat-icon" style="background: var(--stat-green-bg); color: var(--stat-green-text);">✅</div>
                </div>
                <div class="stat-value"><?php echo number_format($open_positions); ?></div>
                <div class="stat-trend trend-up">
                    ตำแหน่งที่เปิดรับสมัคร
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">ปิดรับสมัคร</span>
                    <div class="stat-icon" style="background: var(--stat-red-bg); color: var(--stat-red-text);">❌</div>
                </div>
                <div class="stat-value"><?php echo number_format($closed_positions); ?></div>
                <div class="stat-trend trend-down">
                    ตำแหน่งที่ปิดรับสมัคร
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">แผนกที่รับสมัคร</span>
                    <div class="stat-icon" style="background: var(--stat-yellow-bg); color: var(--stat-yellow-text);">🏢</div>
                </div>
                <div class="stat-value"><?php echo count($departments); ?></div>
                <div class="stat-trend" onclick="openDepartmentModal()">
                    ดูรายละเอียดแผนก
                </div>
            </div>
        </div>

        <div class="search-filters">
            <form action="" method="GET" class="search-box">
                <input type="text" name="search" class="search-input" placeholder="ค้นหาตำแหน่งงาน..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">🔍</button>
            </form>
            
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <select name="status" id="statusFilter" class="filter-dropdown" onchange="window.location.href='?status='+this.value+'<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>'">
                    <option value="" <?php echo empty($status) ? 'selected' : ''; ?>>ทุกสถานะ</option>
                    <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>เปิดรับสมัคร</option>
                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>ปิดรับสมัคร</option>
                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>แบบร่าง</option>
                </select>
                
                <button class="add-btn" onclick="showAddModal()">
                    ➕ เพิ่มตำแหน่งงานใหม่
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span>✅</span> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <span>❌</span> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if ($positions_result->num_rows > 0): ?>
            <div class="positions-table">
                <div class="table-header">
                    <h2>รายการตำแหน่งงาน</h2>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อตำแหน่ง</th>
                                <th>แผนก</th>
                                <th>ประเภท</th>
                                <th>สถานที่</th>
                                <th>เงินเดือน</th>
                                <th>สถานะ</th>
                                <th>ใบสมัคร</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($position = $positions_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($position['title']); ?></td>
                                    <td><?php echo htmlspecialchars($position['department']); ?></td>
                                    <td><?php echo htmlspecialchars($position['type']); ?></td>
                                    <td><?php echo htmlspecialchars($position['location'] ?: 'กรุงเทพฯ'); ?></td>
                                    <td>
                                        <?php 
                                            if ($position['salary_min'] && $position['salary_max']) {
                                                echo number_format($position['salary_min']) . ' - ' . number_format($position['salary_max']);
                                            } elseif ($position['salary_min']) {
                                                echo number_format($position['salary_min']) . '+';
                                            } elseif ($position['salary_max']) {
                                                echo 'ไม่เกิน ' . number_format($position['salary_max']);
                                            } else {
                                                echo 'ตามตกลง';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch($position['status']) {
                                                case 'open':
                                                    $status_class = 'status-open';
                                                    $status_text = 'เปิดรับสมัคร';
                                                    break;
                                                case 'closed':
                                                    $status_class = 'status-closed';
                                                    $status_text = 'ปิดรับสมัคร';
                                                    break;
                                                case 'draft':
                                                    $status_class = 'status-draft';
                                                    $status_text = 'แบบร่าง';
                                                    break;
                                            }
                                        ?>
                                        <span class="status-pill <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php echo $position['total_applications']; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($position['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="showViewModal(<?php echo $position['id']; ?>)" 
                                                    class="action-btn view" title="ดูรายละเอียด">👁️</button>
                                            <button onclick="showEditModal(<?php echo $position['id']; ?>)" 
                                                    class="action-btn edit" title="แก้ไขข้อมูล">✏️</button>
                                                    
                                            <form method="post" style="display: inline;" onsubmit="return confirmToggleStatus('<?php echo $position['status']; ?>')">
                                                <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $position['status'] === 'open' ? 'closed' : 'open'; ?>">
                                                <button type="submit" name="toggle_status" class="action-btn <?php echo $position['status'] === 'open' ? 'reject' : 'approve'; ?>" title="<?php echo $position['status'] === 'open' ? 'ปิดรับสมัคร' : 'เปิดรับสมัคร'; ?>">
                                                    <?php echo $position['status'] === 'open' ? '⏸️' : '▶️'; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if ($position['total_applications'] == 0): ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirmDelete()">
                                                    <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">
                                                    <button type="submit" name="delete_position" class="action-btn reject" title="ลบตำแหน่งงาน">🗑️</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="no-positions">
                <h3>ไม่พบตำแหน่งงาน</h3>
                <p>ยังไม่มีตำแหน่งงานในระบบ หรือไม่มีตำแหน่งงานที่ตรงกับเงื่อนไขการค้นหา</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal แสดงรายละเอียดแผนก -->
    <div id="departmentModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดแผนก</h2>
                <button class="modal-close" onclick="closeDepartmentModal()">&times;</button>
            </div>
            <div>
                <?php foreach ($departments as $dept => $count): ?>
                    <div style="padding: 0.75rem; border-bottom: 1px solid var(--divider-color); display: flex; justify-content: space-between;">
                        <strong><?php echo htmlspecialchars($dept); ?></strong>
                        <span><?php echo $count; ?> ตำแหน่ง</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDepartmentModal()">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มตำแหน่งงาน -->
    <div id="addModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">เพิ่มตำแหน่งงานใหม่</h2>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label" for="title">ชื่อตำแหน่ง <span style="color: var(--danger-color)">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="department">แผนก <span style="color: var(--danger-color)">*</span></label>
                    <input type="text" id="department" name="department" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="type">ประเภทงาน</label>
                    <select id="type" name="type" class="form-select">
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Internship">Internship</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="location">สถานที่ทำงาน</label>
                    <input type="text" id="location" name="location" class="form-input" value="กรุงเทพฯ">
                </div>
                
                <div class="input-group">
                    <div class="form-group">
                        <label class="form-label" for="salary_min">เงินเดือนขั้นต่ำ</label>
                        <input type="number" id="salary_min" name="salary_min" class="form-input" placeholder="บาท">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="salary_max">เงินเดือนขั้นสูง</label>
                        <input type="number" id="salary_max" name="salary_max" class="form-input" placeholder="บาท">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">รายละเอียดงาน</label>
                    <textarea id="description" name="description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="requirements">คุณสมบัติผู้สมัคร</label>
                    <textarea id="requirements" name="requirements" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="status">สถานะ <span style="color: var(--danger-color)">*</span></label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="open">เปิดรับสมัคร</option>
                        <option value="closed">ปิดรับสมัคร</option>
                        <option value="draft">แบบร่าง</option>
                    </select>
                </div>
                
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">ยกเลิก</button>
                    <button type="submit" name="add_position" class="btn btn-primary">เพิ่มตำแหน่งงาน</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal แก้ไขตำแหน่งงาน -->
    <div id="editModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">แก้ไขตำแหน่งงาน</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="" method="POST" id="editForm">
                <input type="hidden" name="position_id" id="edit_position_id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_title">ชื่อตำแหน่ง <span style="color: var(--danger-color)">*</span></label>
                    <input type="text" id="edit_title" name="edit_title" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_department">แผนก <span style="color: var(--danger-color)">*</span></label>
                    <input type="text" id="edit_department" name="edit_department" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_type">ประเภทงาน</label>
                    <select id="edit_type" name="edit_type" class="form-select">
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Internship">Internship</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_location">สถานที่ทำงาน</label>
                    <input type="text" id="edit_location" name="edit_location" class="form-input">
                </div>
                
                <div class="input-group">
                    <div class="form-group">
                        <label class="form-label" for="edit_salary_min">เงินเดือนขั้นต่ำ</label>
                        <input type="number" id="edit_salary_min" name="edit_salary_min" class="form-input" placeholder="บาท">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_salary_max">เงินเดือนขั้นสูง</label>
                        <input type="number" id="edit_salary_max" name="edit_salary_max" class="form-input" placeholder="บาท">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_description">รายละเอียดงาน</label>
                    <textarea id="edit_description" name="edit_description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_requirements">คุณสมบัติผู้สมัคร</label>
                    <textarea id="edit_requirements" name="edit_requirements" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="edit_status">สถานะ <span style="color: var(--danger-color)">*</span></label>
                    <select id="edit_status" name="edit_status" class="form-select" required>
                        <option value="open">เปิดรับสมัคร</option>
                        <option value="closed">ปิดรับสมัคร</option>
                        <option value="draft">แบบร่าง</option>
                    </select>
                </div>
                
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">ยกเลิก</button>
                    <button type="submit" name="edit_position" class="btn btn-primary">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal ดูรายละเอียดตำแหน่งงาน -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">รายละเอียดตำแหน่งงาน</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div>
                <h3 id="view_title" style="font-size: 1.25rem; color: var(--primary-color); margin-bottom: 1rem;"></h3>
                
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">แผนก</div>
                            <div id="view_department" style="font-weight: 500;"></div>
                        </div>
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">ประเภทงาน</div>
                            <div id="view_type" style="font-weight: 500;"></div>
                        </div>
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">สถานที่ทำงาน</div>
                            <div id="view_location" style="font-weight: 500;"></div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">เงินเดือน</div>
                            <div id="view_salary" style="font-weight: 500;"></div>
                        </div>
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">สถานะ</div>
                            <div><span id="view_status" class="status-pill"></span></div>
                        </div>
                        <div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">วันที่สร้าง</div>
                            <div id="view_created_at" style="font-weight: 500;"></div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="margin-bottom: 0.5rem; border-bottom: 1px solid var(--divider-color); padding-bottom: 0.5rem;">รายละเอียดงาน</h4>
                    <div id="view_description" style="white-space: pre-line; padding: 0.5rem 0;"></div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <h4 style="margin-bottom: 0.5rem; border-bottom: 1px solid var(--divider-color); padding-bottom: 0.5rem;">คุณสมบัติผู้สมัคร</h4>
                    <div id="view_requirements" style="white-space: pre-line; padding: 0.5rem 0;"></div>
                </div>
            </div>
            <div class="form-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">ปิด</button>
                <button type="button" class="btn btn-primary" onclick="viewToEdit()">แก้ไข</button>
            </div>
        </div>
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
        
        // แสดง/ซ่อน loading
        function toggleLoading(show = true) {
            document.querySelector('.loading').classList.toggle('active', show);
        }

        // จัดการ Modal แผนก
        function openDepartmentModal() {
            document.getElementById('departmentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDepartmentModal() {
            document.getElementById('departmentModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // จัดการ Modal เพิ่มตำแหน่งงาน
        function showAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // จัดการ Modal แก้ไขตำแหน่งงาน
        function showEditModal(id) {
            toggleLoading(true);
            
            fetch(`get_position.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const position = data.position;
                        
                        document.getElementById('edit_position_id').value = position.id;
                        document.getElementById('edit_title').value = position.title;
                        document.getElementById('edit_department').value = position.department;
                        document.getElementById('edit_type').value = position.type;
                        document.getElementById('edit_location').value = position.location || 'กรุงเทพฯ';
                        document.getElementById('edit_salary_min').value = position.salary_min;
                        document.getElementById('edit_salary_max').value = position.salary_max;
                        document.getElementById('edit_description').value = position.description;
                        document.getElementById('edit_requirements').value = position.requirements;
                        document.getElementById('edit_status').value = position.status;
                        
                        document.getElementById('editModal').classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('เกิดข้อผิดพลาดในการดึงข้อมูล: ' + data.message);
                    }
                    toggleLoading(false);
                })
                .catch(error => {
                    alert('เกิดข้อผิดพลาด: ' + error);
                    toggleLoading(false);
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // จัดการ Modal ดูรายละเอียด
        function showViewModal(id) {
            toggleLoading(true);
            
            fetch(`get_position.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const position = data.position;
                        
                        // เก็บ ID ไว้ใช้ในการแก้ไข
                        document.getElementById('viewModal').dataset.positionId = position.id;
                        
                        document.getElementById('view_title').textContent = position.title;
                        document.getElementById('view_department').textContent = position.department;
                        document.getElementById('view_type').textContent = position.type;
                        document.getElementById('view_location').textContent = position.location || 'กรุงเทพฯ';
                        
                        // แสดงเงินเดือน
                        let salaryText = 'ตามตกลง';
                        if (position.salary_min && position.salary_max) {
                            salaryText = `${number_format(position.salary_min)} - ${number_format(position.salary_max)} บาท`;
                        } else if (position.salary_min) {
                            salaryText = `${number_format(position.salary_min)} บาทขึ้นไป`;
                        } else if (position.salary_max) {
                            salaryText = `ไม่เกิน ${number_format(position.salary_max)} บาท`;
                        }
                        document.getElementById('view_salary').textContent = salaryText;
                        
                        // แสดงสถานะ
                        const statusEl = document.getElementById('view_status');
                        statusEl.textContent = position.status === 'open' ? 'เปิดรับสมัคร' : 
                                            position.status === 'closed' ? 'ปิดรับสมัคร' : 'แบบร่าง';
                        statusEl.className = 'status-pill ' + 
                                            (position.status === 'open' ? 'status-open' : 
                                            position.status === 'closed' ? 'status-closed' : 'status-draft');
                        
                        document.getElementById('view_created_at').textContent = formatDate(position.created_at);
                        document.getElementById('view_description').textContent = position.description || 'ไม่มีข้อมูล';
                        document.getElementById('view_requirements').textContent = position.requirements || 'ไม่มีข้อมูล';
                        
                        document.getElementById('viewModal').classList.add('active');
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('เกิดข้อผิดพลาดในการดึงข้อมูล: ' + data.message);
                    }
                    toggleLoading(false);
                })
                .catch(error => {
                    alert('เกิดข้อผิดพลาด: ' + error);
                    toggleLoading(false);
                });
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // เปลี่ยนจากดูรายละเอียดเป็นแก้ไข
        function viewToEdit() {
            const id = document.getElementById('viewModal').dataset.positionId;
            closeViewModal();
            setTimeout(() => {
                showEditModal(id);
            }, 300);
        }

        // ฟังก์ชันยืนยันการเปลี่ยนสถานะ
        function confirmToggleStatus(currentStatus) {
            const message = currentStatus === 'open' 
                ? 'คุณต้องการปิดรับสมัครตำแหน่งนี้ใช่หรือไม่?' 
                : 'คุณต้องการเปิดรับสมัครตำแหน่งนี้ใช่หรือไม่?';
            return confirm(message);
        }

        // ฟังก์ชันยืนยันการลบ
        function confirmDelete() {
            return confirm('คุณต้องการลบตำแหน่งงานนี้ใช่หรือไม่?\nการลบข้อมูลจะไม่สามารถกู้คืนได้');
        }

        // ฟังก์ชันจัดรูปแบบตัวเลข
        function number_format(number) {
            return new Intl.NumberFormat('th-TH').format(number);
        }

        // ฟังก์ชันจัดรูปแบบวันที่
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // ยืนยันการออกจากระบบ
        document.querySelector('a[href="../logout.php"]').addEventListener('click', (e) => {
            if (!confirm('คุณต้องการออกจากระบบใช่หรือไม่?')) {
                e.preventDefault();
            }
        });

        // จัดการ sidebar และ overlay
        function toggleSidebar() {
            document.body.classList.toggle('menu-open');
        }

        // โหลดธีมเมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', loadTheme);

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // จัดการ menu toggle
            const menuToggle = document.querySelector('.menu-toggle');
            menuToggle.addEventListener('click', toggleSidebar);

            // จัดการ overlay
            const overlay = document.querySelector('.overlay');
            overlay.addEventListener('click', function() {
                document.body.classList.remove('menu-open');
            });

            // ปิด Modal เมื่อคลิกนอกกรอบ
            const modals = document.querySelectorAll('.modal-overlay');
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (modal.id === 'departmentModal') {
                            closeDepartmentModal();
                        } else if (modal.id === 'addModal') {
                            closeAddModal();
                        } else if (modal.id === 'editModal') {
                            closeEditModal();
                        } else if (modal.id === 'viewModal') {
                            closeViewModal();
                        }
                    }
                });
            });

            // แสดงผล animation สำหรับ cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${0.1 * (index + 1)}s`;
            });

            // ปิดการแจ้งเตือนอัตโนมัติ
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function() {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 300);
                    });
                }, 3000);
            }

            // ตรวจสอบ orientation change สำหรับมือถือ
            window.addEventListener('orientationchange', function() {
                // ปิด sidebar เมื่อหมุนหน้าจอ
                document.body.classList.remove('menu-open');
            });
        });
    </script>
</body>
</html>