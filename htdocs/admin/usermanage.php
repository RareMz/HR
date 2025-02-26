<?php
session_start();
require_once '../config.php';

// ตรวจสอบการล็อกอินและสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// จัดการการลบผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    if ($user_id != $_SESSION['user_id']) {
        $conn->begin_transaction();
        
        try {
            // 1. ลบ application_status_logs
            $stmt = $conn->prepare("DELETE FROM application_status_logs WHERE changed_by = ? OR application_id IN (SELECT id FROM applications WHERE user_id = ?)");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            
            // 2. ลบ applications
            $stmt = $conn->prepare("DELETE FROM applications WHERE user_id = ? OR updated_by = ?");
            $stmt->bind_param("ii", $user_id, $user_id);
            $stmt->execute();
            
            // 3. อัพเดต positions
            $stmt = $conn->prepare("UPDATE positions SET created_by = NULL WHERE created_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $stmt = $conn->prepare("UPDATE positions SET updated_by = NULL WHERE updated_by = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // 4. ลบผู้ใช้
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success'] = "ลบผู้ใช้และข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "ไม่สามารถลบบัญชีของตัวเองได้";
    }
    
    header("Location: usermanage.php");
    exit();
}

// จัดการการแก้ไขข้อมูลผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['edit_name']);
    $email = filter_var(trim($_POST['edit_email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['edit_phone']);
    
    // Validation
    if (empty($name) || strlen($name) > 100) {
        $_SESSION['error'] = "กรุณาระบุชื่อให้ถูกต้อง (ไม่เกิน 100 ตัวอักษร)";
        header("Location: usermanage.php");
        exit();
    }
    
    if (!$email) {
        $_SESSION['error'] = "รูปแบบอีเมลไม่ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    if (empty($phone) || !preg_match("/^[0-9-]{10,20}$/", $phone)) {
        $_SESSION['error'] = "กรุณาระบุเบอร์โทรศัพท์ให้ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    // ตรวจสอบอีเมลซ้ำ (ยกเว้นอีเมลของผู้ใช้คนเดิม)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "อีเมลนี้มีในระบบแล้ว";
        header("Location: usermanage.php");
        exit();
    }

    // อัพเดตข้อมูล
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "อัพเดตข้อมูลผู้ใช้เรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัพเดตข้อมูล";
    }
    
    header("Location: usermanage.php");
    exit();
}

// จัดการการเปลี่ยนสิทธิ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];
    
    if (!in_array($new_role, ['user', 'admin'])) {
        $_SESSION['error'] = "สิทธิ์ไม่ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "เปลี่ยนสิทธิ์ผู้ใช้เรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเปลี่ยนสิทธิ์";
        }
    } else {
        $_SESSION['error'] = "ไม่สามารถเปลี่ยนสิทธิ์ของตัวเองได้";
    }
    
    header("Location: usermanage.php");
    exit();
}

// จัดการการเพิ่มผู้ใช้ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    // Validation
    if (empty($name) || strlen($name) > 100) {
        $_SESSION['error'] = "กรุณาระบุชื่อให้ถูกต้อง (ไม่เกิน 100 ตัวอักษร)";
        header("Location: usermanage.php");
        exit();
    }
    
    if (!$email) {
        $_SESSION['error'] = "รูปแบบอีเมลไม่ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    if (empty($phone) || !preg_match("/^[0-9-]{10,20}$/", $phone)) {
        $_SESSION['error'] = "กรุณาระบุเบอร์โทรศัพท์ให้ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    if (!in_array($role, ['user', 'admin'])) {
        $_SESSION['error'] = "สิทธิ์ไม่ถูกต้อง";
        header("Location: usermanage.php");
        exit();
    }
    
    // ตรวจสอบอีเมลซ้ำ
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "อีเมลนี้มีในระบบแล้ว";
        header("Location: usermanage.php");
        exit();
    }
    
    // เพิ่มผู้ใช้ใหม่
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $phone, $password, $role);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "เพิ่มผู้ใช้เรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการเพิ่มผู้ใช้";
    }
    
    header("Location: usermanage.php");
    exit();
}

// การแบ่งหน้าและดึงข้อมูลผู้ใช้
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// นับจำนวนผู้ใช้ทั้งหมดสำหรับ pagination
$count_query = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
$count_params = [];
$count_types = "";

if (!empty($search)) {
    $search_param = "%{$search}%";
    $count_query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $count_types .= "sss";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

if (!empty($status)) {
    if ($status === 'active') {
        $count_query .= " AND u.role = 'admin'";
    } else if ($status === 'inactive') {
        $count_query .= " AND u.role = 'user'";
    }
}

$count_stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// ดึงข้อมูลผู้ใช้
$users_query = "
    SELECT 
        u.*,
        COUNT(DISTINCT a.id) as total_applications,
        COUNT(DISTINCT CASE WHEN a.status = 'approved' THEN a.id END) as approved_applications
    FROM users u
    LEFT JOIN applications a ON u.id = a.user_id
    WHERE 1=1
";

// เพิ่มเงื่อนไขการค้นหา
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%{$search}%";
    $users_query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $types .= "sss";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    if ($status === 'active') {
        $users_query .= " AND u.role = 'admin'";
    } else if ($status === 'inactive') {
        $users_query .= " AND u.role = 'user'";
    }
}

$users_query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($users_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - ระบบรับสมัครงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3182ce;
            --primary-dark: #2c5282;
            --accent-color: #4299e1;
            --success-color: #48bb78;
            --warning-color: #ecc94b;
            --danger-color: #e53e3e;
            --gray-100: #f7fafc;
            --gray-200: #edf2f7;
            --gray-300: #e2e8f0;
            --gray-400: #cbd5e0;
            --gray-500: #a0aec0;
            --gray-600: #718096;
            --gray-700: #4a5568;
            --gray-800: #2d3748;
            --white: #ffffff;
            --transition-fast: 0.2s;
            --transition-normal: 0.3s;
            --transition-slow: 0.5s;
            --box-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --border-radius-sm: 0.375rem;
            --border-radius: 0.75rem;
            --border-radius-lg: 1rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Anuphan', sans-serif;
        }

        body {
            background: var(--gray-100);
            min-height: 100vh;
            display: flex;
            position: relative;
            overflow-x: hidden;
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

        @keyframes slideInRight {
            from { transform: translateX(20px); opacity: 0; }
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
            width: 280px;
            background: var(--white);
            box-shadow: var(--box-shadow);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform var(--transition-normal) ease;
            z-index: 100;
        }

        .sidebar-header {
            padding: 2rem;
            border-bottom: 1px solid var(--gray-200);
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
            background: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--gray-700);
            transition: all var(--transition-fast);
            box-shadow: var(--box-shadow-sm);
        }

        .admin-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--box-shadow);
        }

        .admin-details h3 {
            color: var(--gray-800);
            font-size: 1.125rem;
        }

        .admin-details p {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-item {
            padding: 1rem 2rem;
            color: var(--gray-700);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all var(--transition-fast);
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background: var(--gray-100);
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: #ebf8ff;
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
            margin-left: 280px;
            flex-grow: 1;
            padding: 2rem;
            transition: margin var(--transition-normal);
            animation: fadeIn 0.5s ease;
            max-width: 1400px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            animation: slideInLeft var(--transition-normal) ease;
        }

        .page-title {
            font-size: 1.875rem;
            color: var(--gray-800);
        }

        .add-user-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-normal);
            box-shadow: var(--box-shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .add-user-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        /* --------- FILTERS --------- */
        .filters {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
            animation: slideUp var(--transition-normal) ease;
            transition: all var(--transition-normal);
        }

        .filters:hover {
            box-shadow: var(--box-shadow-lg);
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: all var(--transition-fast);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
            transform: translateY(-2px);
        }

        .filter-selects {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-selects select {
            min-width: 150px;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            background: var(--white);
            transition: all var(--transition-fast);
        }

        .filter-selects select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
            transform: translateY(-2px);
        }

        .filter-btn, .clear-btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            white-space: nowrap;
            transition: all var(--transition-normal);
            box-shadow: var(--box-shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .filter-btn {
            background: var(--primary-color);
            color: var(--white);
            border: none;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .clear-btn {
            background: var(--gray-300);
            color: var(--gray-700);
            border: none;
            text-decoration: none;
        }

        .clear-btn:hover {
            background: var(--gray-400);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .search-results {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--gray-600);
            animation: fadeIn var(--transition-normal) ease;
        }

        /* --------- ALERTS --------- */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1rem;
            position: relative;
            animation: slideInRight 0.5s ease;
            transition: all var(--transition-normal);
            box-shadow: var(--box-shadow-sm);
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        /* --------- TABLE --------- */
        .table-container {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            scrollbar-width: thin;
            scrollbar-color: var(--gray-400) var(--gray-200);
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background-color: var(--gray-400);
            border-radius: 20px;
        }

        .table-container::-webkit-scrollbar-track {
            background-color: var(--gray-200);
            border-radius: 20px;
        }

        .table-responsive {
            min-width: 100%;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--gray-400) var(--gray-200);
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background-color: var(--gray-400);
            border-radius: 20px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background-color: var(--gray-200);
            border-radius: 20px;
        }

        .users-table {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: fadeIn 0.8s ease forwards, slideUp 0.8s ease forwards;
            transition: all var(--transition-normal);
        }

        .users-table:hover {
            box-shadow: var(--box-shadow-lg);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            transition: all var(--transition-fast);
        }

        th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 1px 0 var(--gray-300);
        }

        td {
            color: var(--gray-800);
            border-bottom: 1px solid var(--gray-200);
        }

        tr {
            transition: background-color var(--transition-fast);
        }

        tr:hover {
            background-color: rgba(49, 130, 206, 0.05);
        }

        tr:hover td {
            transform: translateX(3px);
        }

        .user-role {
            display: inline-block;
            padding: 0.35rem 0.9rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-fast);
            box-shadow: var(--box-shadow-sm);
        }

        tr:hover .user-role {
            transform: scale(1.05);
        }

        .role-admin {
            background: #c6f6d5;
            color: #2f855a;
        }

        .role-user {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge {
            display: inline-block;
            transition: all var(--transition-fast);
        }

        tr:hover .badge {
            transform: scale(1.1);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            background: var(--white);
            color: var(--gray-700);
            cursor: pointer;
            transition: all var(--transition-fast);
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow-sm);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }

        .delete-btn {
            color: var(--danger-color);
            border-color: #fed7d7;
        }

        .delete-btn:hover {
            background: #fff5f5;
            border-color: #feb2b2;
        }

        /* --------- PAGINATION --------- */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.5rem;
            flex-wrap: wrap;
            animation: fadeIn var(--transition-slow) ease;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            color: var(--gray-700);
            text-decoration: none;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .pagination a:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-sm);
        }

        .pagination .active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            font-weight: 500;
        }

        /* --------- MODAL --------- */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            padding: 1rem;
            overflow-y: auto;
            opacity: 0;
            transition: opacity var(--transition-normal);
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            max-width: 500px;
            width: 90%;
            margin: 2rem auto;
            padding: 2rem;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            opacity: 0;
            transition: all var(--transition-normal);
            box-shadow: var(--box-shadow-lg);
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: all var(--transition-fast);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: var(--primary-color);
            background-color: var(--gray-100);
        }

        /* --------- FORM --------- */
        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            color: var(--gray-800);
            transition: all var(--transition-fast);
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* --------- MENU TOGGLE & OVERLAY --------- */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--white);
            border: none;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--box-shadow);
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all var(--transition-normal);
        }

        .menu-toggle:hover {
            background: var(--gray-100);
            transform: scale(1.05);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 90;
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity var(--transition-normal);
        }

        /* --------- RIPPLE EFFECT --------- */
        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
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
                color: var(--white);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .add-user-btn {
                width: 100%;
                justify-content: center;
            }

            .filter-form {
                flex-direction: column;
            }

            .filter-selects {
                flex-direction: column;
                width: 100%;
            }

            .filter-selects select, 
            .filter-selects button,
            .filter-selects a {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .action-btn {
                width: 100%;
                text-align: center;
            }

            .modal-content {
                width: 95%;
                margin: 5% auto;
                padding: 1.5rem;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.5rem;
            }

            .filters {
                padding: 1rem;
            }

            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .pagination a {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
        }

        /* Delay for animation */
        .stat-item:nth-child(1) { animation-delay: 0.05s; }
        .stat-item:nth-child(2) { animation-delay: 0.1s; }
        .stat-item:nth-child(3) { animation-delay: 0.15s; }
        .stat-item:nth-child(4) { animation-delay: 0.2s; }
        .stat-item:nth-child(5) { animation-delay: 0.25s; }
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
            <a href="dashboard.php" class="menu-item">
                📊 แดชบอร์ด
            </a>
            <a href="usermanage.php" class="menu-item active">
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
        <div class="page-header">
            <h1 class="page-title">จัดการผู้ใช้งาน</h1>
            <button onclick="showAddUserModal()" class="add-user-btn">➕ เพิ่มผู้ใช้ใหม่</button>
        </div>

        <!-- เพิ่มส่วนฟิลเตอร์ -->
        <div class="filters">
            <form action="" method="GET" class="filter-form">
                <div class="search-box">
                    <input type="text" name="search" id="search-input" placeholder="ค้นหาชื่อ, อีเมล, เบอร์โทร..." 
                           value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
                <div class="filter-selects">
                    <select name="status" id="status-filter">
                        <option value="">ทั้งหมด</option>
                        <option value="active" <?php echo ($status ?? '') === 'active' ? 'selected' : ''; ?>>ผู้ดูแลระบบ</option>
                        <option value="inactive" <?php echo ($status ?? '') === 'inactive' ? 'selected' : ''; ?>>ผู้ใช้งานทั่วไป</option>
                    </select>
                    <button type="submit" class="filter-btn">🔍 ค้นหา</button>
                    <?php if (!empty($search) || !empty($status)): ?>
                        <a href="?" class="clear-btn">❌ ล้างการค้นหา</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if (!empty($search) || !empty($status)): ?>
                <div class="search-results">
                    พบ <?php echo number_format($total_users); ?> รายการ
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="users-table">
            <div class="table-container">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อ-นามสกุล</th>
                                <th>อีเมล</th>
                                <th>เบอร์โทร</th>
                                <th>สิทธิ์</th>
                                <th style="text-align: center">ใบสมัคร</th>
                                <th style="text-align: center">ผ่านคัดเลือก</th>
                                <th>วันที่สมัคร</th>
                                <th>เข้าระบบล่าสุด</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td>
                                        <span class="user-role <?php echo $user['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center">
                                        <span class="badge" style="background: var(--gray-300); padding: 0.25rem 0.5rem; border-radius: 999px;">
                                            <?php echo number_format($user['total_applications']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center">
                                        <span class="badge" style="background: #c6f6d5; color: #2f855a; padding: 0.25rem 0.5rem; border-radius: 999px;">
                                            <?php echo number_format($user['approved_applications']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="showEditModal(<?php echo $user['id']; ?>, 
                                                '<?php echo htmlspecialchars($user['name']); ?>', 
                                                '<?php echo htmlspecialchars($user['email']); ?>', 
                                                '<?php echo htmlspecialchars($user['phone']); ?>')" 
                                                class="action-btn" title="แก้ไขข้อมูล">
                                                ✏️
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="showChangeRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')" 
                                                        class="action-btn" title="เปลี่ยนสิทธิ์">
                                                    🔄
                                                </button>
                                                <button onclick="confirmDelete(<?php echo $user['id']; ?>, <?php echo $user['total_applications']; ?>)" 
                                                        class="action-btn delete-btn" title="ลบผู้ใช้">
                                                    🗑️
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=1<?php echo (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status=' . urlencode($status) : ''); ?>" title="หน้าแรก">«</a>
                <a href="?page=<?php echo $page-1; ?><?php echo (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status=' . urlencode($status) : ''); ?>" title="หน้าก่อนหน้า">‹</a>
            <?php endif; ?>
            
            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status=' . urlencode($status) : ''); ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?><?php echo (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status=' . urlencode($status) : ''); ?>" title="หน้าถัดไป">›</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($status) ? '&status=' . urlencode($status) : ''); ?>" title="หน้าสุดท้าย">»</a>
            <?php endif; ?>
        </div>

        <!-- Modal เพิ่มผู้ใช้ -->
        <div id="addUserModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">เพิ่มผู้ใช้ใหม่</h2>
                    <span class="close" onclick="hideAddUserModal()">&times;</span>
                </div>
                <form id="addUserForm" action="" method="POST" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label for="name">ชื่อ-นามสกุล <span style="color: var(--danger-color)">*</span></label>
                        <input type="text" id="name" name="name" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email">อีเมล <span style="color: var(--danger-color)">*</span></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">เบอร์โทรศัพท์ <span style="color: var(--danger-color)">*</span></label>
                        <input type="tel" id="phone" name="phone" required pattern="[0-9-]{10,20}">
                    </div>
                    <div class="form-group">
                        <label for="password">รหัสผ่าน <span style="color: var(--danger-color)">*</span></label>
                        <input type="password" id="password" name="password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">ยืนยันรหัสผ่าน <span style="color: var(--danger-color)">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="role">สิทธิ์การใช้งาน <span style="color: var(--danger-color)">*</span></label>
                        <select id="role" name="role" required>
                            <option value="user">ผู้ใช้งานทั่วไป</option>
                            <option value="admin">ผู้ดูแลระบบ</option>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="hideAddUserModal()">ยกเลิก</button>
                        <button type="submit" name="add_user" class="btn btn-primary">เพิ่มผู้ใช้</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal แก้ไขข้อมูลผู้ใช้ -->
        <div id="editUserModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">แก้ไขข้อมูลผู้ใช้</h2>
                    <span class="close" onclick="hideEditModal()">&times;</span>
                </div>
                <form id="editUserForm" action="" method="POST" onsubmit="return validateEditForm()">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="form-group">
                        <label for="edit_name">ชื่อ-นามสกุล <span style="color: var(--danger-color)">*</span></label>
                        <input type="text" id="edit_name" name="edit_name" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">อีเมล <span style="color: var(--danger-color)">*</span></label>
                        <input type="email" id="edit_email" name="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">เบอร์โทรศัพท์ <span style="color: var(--danger-color)">*</span></label>
                        <input type="tel" id="edit_phone" name="edit_phone" required pattern="[0-9-]{10,20}">
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="hideEditModal()">ยกเลิก</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal เปลี่ยนสิทธิ์ -->
        <div id="changeRoleModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">เปลี่ยนสิทธิ์ผู้ใช้</h2>
                    <span class="close" onclick="hideChangeRoleModal()">&times;</span>
                </div>
                <form action="" method="POST">
                    <input type="hidden" id="user_id_role" name="user_id">
                    <div class="form-group">
                        <label for="new_role">สิทธิ์การใช้งานใหม่</label>
                        <select id="new_role" name="new_role" required>
                            <option value="user">ผู้ใช้งานทั่วไป</option>
                            <option value="admin">ผู้ดูแลระบบ</option>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="hideChangeRoleModal()">ยกเลิก</button>
                        <button type="submit" name="change_role" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form สำหรับลบผู้ใช้ -->
        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="user_id" id="delete_user_id">
            <input type="hidden" name="delete_user" value="1">
        </form>
    </main>

    <script>
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
        
        // ตรวจสอบฟอร์มเพิ่มผู้ใช้
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const phone = document.getElementById('phone').value;
            
            if (password !== confirmPassword) {
                alert('รหัสผ่านไม่ตรงกัน');
                return false;
            }
            
            if (password.length < 8) {
                alert('รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
                return false;
            }
            
            if (!phone.match(/^[0-9-]{10,20}$/)) {
                alert('เบอร์โทรศัพท์ไม่ถูกต้อง');
                return false;
            }
            
            return true;
        }

        // ฟังก์ชันสำหรับ Modal เพิ่มผู้ใช้
        function showAddUserModal() {
            const modal = document.getElementById('addUserModal');
            modal.style.display = 'block';
            document.getElementById('addUserForm').reset();
            document.body.style.overflow = 'hidden'; // ป้องกันการเลื่อนหน้าหลัก
            
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function hideAddUserModal() {
            const modal = document.getElementById('addUserModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('addUserForm').reset();
                document.body.style.overflow = ''; // คืนค่าการเลื่อนหน้าหลัก
            }, 300);
        }

        // ฟังก์ชันสำหรับ Modal แก้ไขข้อมูล
        function showEditModal(userId, name, email, phone) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            
            const modal = document.getElementById('editUserModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function hideEditModal() {
            const modal = document.getElementById('editUserModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('editUserForm').reset();
                document.body.style.overflow = '';
            }, 300);
        }

        function validateEditForm() {
            const phone = document.getElementById('edit_phone').value;
            
            if (!phone.match(/^[0-9-]{10,20}$/)) {
                alert('เบอร์โทรศัพท์ไม่ถูกต้อง');
                return false;
            }
            
            return true;
        }

        // ฟังก์ชันสำหรับ Modal เปลี่ยนสิทธิ์
        function showChangeRoleModal(userId, currentRole) {
            document.getElementById('user_id_role').value = userId;
            document.getElementById('new_role').value = currentRole;
            
            const modal = document.getElementById('changeRoleModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function hideChangeRoleModal() {
            const modal = document.getElementById('changeRoleModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }

        // ฟังก์ชันยืนยันการลบผู้ใช้
        function confirmDelete(userId, totalApplications) {
            let message = `คุณต้องการลบผู้ใช้นี้ใช่หรือไม่?\n\nการลบข้อมูลจะไม่สามารถกู้คืนได้`;
            if (totalApplications > 0) {
                message += `\n\nผู้ใช้นี้มีใบสมัครงาน ${totalApplications} ใบ ที่จะถูกลบไปด้วย!`;
            }
            if (confirm(message)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }

        // จัดการการกรอกเบอร์โทรศัพท์
        function formatPhoneNumber(input) {
            let phone = input.value.replace(/\D/g, '');
            if (phone.length > 3) {
                phone = phone.slice(0,3) + "-" + phone.slice(3);
            }
            input.value = phone;
        }

        // จัดการ Sidebar บนมือถือ
        function toggleSidebar() {
            document.body.classList.toggle('menu-open');
        }

        // ฟังก์ชันสำหรับค้นหาแบบ Real-time
        function filterTable() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const rows = document.querySelectorAll('.users-table tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const phone = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const roleSpan = row.querySelector('.user-role');
                const role = roleSpan ? roleSpan.textContent.toLowerCase() : '';

                // เช็คการค้นหา
                const matchSearch = searchTerm === '' || 
                    name.includes(searchTerm) || 
                    email.includes(searchTerm) || 
                    phone.includes(searchTerm);

                // เช็คสถานะ
                const matchStatus = statusFilter === '' || 
                    (statusFilter === 'active' && role.includes('ผู้ดูแลระบบ')) ||
                    (statusFilter === 'inactive' && role.includes('ผู้ใช้งานทั่วไป'));

                // แสดงหรือซ่อนแถว
                const isVisible = matchSearch && matchStatus;
                row.style.display = isVisible ? '' : 'none';
                
                if (isVisible) {
                    visibleCount++;
                    
                    // เพิ่ม animation เมื่อกรองข้อมูล
                    row.style.animation = 'none';
                    void row.offsetWidth; // Trigger reflow
                    row.style.animation = `fadeIn 0.3s ease forwards, slideUp 0.3s ease forwards`;
                    row.style.animationDelay = `${0.05 * visibleCount}s`;
                }
            });

            // อัพเดทจำนวนผลลัพธ์
            updateResultCount(visibleCount);
        }

        // ฟังก์ชันอัพเดทจำนวนผลลัพธ์
        function updateResultCount(count) {
            let resultCountElement = document.querySelector('.search-results');
            
            if (!resultCountElement) {
                resultCountElement = document.createElement('div');
                resultCountElement.classList.add('search-results');
                document.querySelector('.filters').appendChild(resultCountElement);
            }
            
            resultCountElement.textContent = `พบ ${count} รายการ`;
            resultCountElement.style.animation = 'none';
            void resultCountElement.offsetWidth; // Trigger reflow
            resultCountElement.style.animation = 'fadeIn 0.3s ease';
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            // เพิ่ม ripple effect ให้กับปุ่มทั้งหมด
            const buttons = document.querySelectorAll('.btn, .action-btn, .menu-item, .add-user-btn, .filter-btn, .clear-btn, .pagination a');
            buttons.forEach(button => {
                button.addEventListener('click', createRipple);
            });

            // จัดการการกรอกเบอร์โทรศัพท์
            document.getElementById('phone').addEventListener('input', function() {
                formatPhoneNumber(this);
            });

            document.getElementById('edit_phone').addEventListener('input', function() {
                formatPhoneNumber(this);
            });

            // จัดการ menu toggle
            const menuToggle = document.querySelector('.menu-toggle');
            menuToggle.addEventListener('click', toggleSidebar);

            // จัดการ overlay
            const overlay = document.querySelector('.overlay');
            overlay.addEventListener('click', function() {
                document.body.classList.remove('menu-open');
            });

            // ค้นหาแบบ Real-time
            const searchInput = document.getElementById('search-input');
            const statusFilter = document.getElementById('status-filter');
            
            searchInput.addEventListener('input', filterTable);
            statusFilter.addEventListener('change', filterTable);

            // จัดการการปิด modal เมื่อคลิกนอกกรอบ
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        if (modal.id === 'addUserModal') {
                            hideAddUserModal();
                        } else if (modal.id === 'editUserModal') {
                            hideEditModal();
                        } else if (modal.id === 'changeRoleModal') {
                            hideChangeRoleModal();
                        }
                    }
                });
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

            // ปรับขนาดหน้าจอเมื่อ resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024) {
                    document.body.classList.remove('menu-open');
                }
            });

            // เพิ่ม animation ให้กับแถวตาราง
            const tableRows = document.querySelectorAll('.users-table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.animation = `fadeIn 0.5s ease forwards, slideUp 0.5s ease forwards`;
                row.style.animationDelay = `${0.05 * (index + 1)}s`;
                row.style.opacity = '0';
            });
        });
    </script>
</body>
</html>