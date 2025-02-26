<?php
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$position_id = isset($_GET['position_id']) ? intval($_GET['position_id']) : 0;
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == '1';
$draft_saved = false;
$submit_success = false;

// ตรวจสอบว่ามีตำแหน่งงานที่ระบุหรือไม่
if ($position_id <= 0) {
    header("Location: index.php");
    exit();
}

// ดึงข้อมูลตำแหน่งงาน
$stmt = $conn->prepare("SELECT * FROM positions WHERE id = ? AND status = 'open'");
$stmt->bind_param("i", $position_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$position = $result->fetch_assoc();

// ตรวจสอบว่ามีตารางที่จำเป็นหรือไม่ และสร้างถ้ายังไม่มี
// ฟังก์ชันตรวจสอบว่าตารางมีอยู่แล้วหรือไม่
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// สร้างตาราง drafts ถ้ายังไม่มี (ควรทำแค่ครั้งแรก)
try {
    if (!tableExists($conn, 'application_drafts')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `application_drafts` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `position_id` int(11) NOT NULL,
              `data` longtext DEFAULT NULL,
              `resume_path` varchar(255) DEFAULT NULL,
              `photo_path` varchar(255) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_position` (`user_id`,`position_id`),
              KEY `position_id` (`position_id`),
              CONSTRAINT `application_drafts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
              CONSTRAINT `application_drafts_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    // สร้างตาราง application_details ถ้ายังไม่มี
    if (!tableExists($conn, 'application_details')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `application_details` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `application_id` int(11) NOT NULL,
              `fullname` varchar(100) DEFAULT NULL,
              `registered_address` text DEFAULT NULL,
              `present_address` text DEFAULT NULL,
              `telephone` varchar(20) DEFAULT NULL,
              `mobile` varchar(20) DEFAULT NULL,
              `marital_status` varchar(20) DEFAULT NULL,
              `birthplace` varchar(100) DEFAULT NULL,
              `birthdate` date DEFAULT NULL,
              `age` int(3) DEFAULT NULL,
              `weight` int(3) DEFAULT NULL,
              `height` int(3) DEFAULT NULL,
              `nationality` varchar(50) DEFAULT NULL,
              `race` varchar(50) DEFAULT NULL,
              `religion` varchar(50) DEFAULT NULL,
              `id_card` varchar(20) DEFAULT NULL,
              `issued_at` varchar(100) DEFAULT NULL,
              `issued_date` date DEFAULT NULL,
              `photo_path` varchar(255) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `application_id` (`application_id`),
              CONSTRAINT `application_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    // สร้างตาราง application_education ถ้ายังไม่มี
    if (!tableExists($conn, 'application_education')) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `application_education` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `application_id` int(11) NOT NULL,
              `level` enum('secondary','vocational','university') NOT NULL,
              `period_from` varchar(20) DEFAULT NULL,
              `period_to` varchar(20) DEFAULT NULL,
              `institute` varchar(100) DEFAULT NULL,
              `degree` varchar(100) DEFAULT NULL,
              `major` varchar(100) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `application_id` (`application_id`),
              KEY `idx_education_level` (`level`),
              CONSTRAINT `application_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    // แก้ไขคอลัมน์ cover_letter เป็น longtext ถ้ายังไม่ได้แก้ไข
    $result = $conn->query("SHOW COLUMNS FROM applications LIKE 'cover_letter'");
    if ($result->num_rows > 0) {
        $column = $result->fetch_assoc();
        if ($column['Type'] !== 'longtext') {
            $conn->query("ALTER TABLE applications MODIFY COLUMN cover_letter longtext DEFAULT NULL;");
        }
    }
    
    // สร้างโฟลเดอร์สำหรับเก็บไฟล์
    $dirs = ['uploads', 'uploads/resumes', 'uploads/photos'];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
} catch (Exception $e) {
    // ถ้าเกิดข้อผิดพลาดในการสร้างตาราง ให้เพิ่ม log หรือข้ามไป
    error_log('Database setup error: ' . $e->getMessage());
}

// ตรวจสอบว่าเคยสมัครตำแหน่งนี้แล้วหรือไม่
$stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = ? AND position_id = ?");
$stmt->bind_param("ii", $user_id, $position_id);
$stmt->execute();
$result = $stmt->get_result();
$already_applied = ($result->num_rows > 0);
$application = $already_applied ? $result->fetch_assoc() : null;

// ถ้ายังไม่ได้สมัคร ตรวจสอบว่ามีข้อมูลแบบร่างไว้หรือไม่
$draft_data = null;
if (!$already_applied) {
    $stmt = $conn->prepare("SELECT * FROM application_drafts WHERE user_id = ? AND position_id = ?");
    $stmt->bind_param("ii", $user_id, $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $draft = $result->fetch_assoc();
        $draft_data = json_decode($draft['data'], true);
        // เติมข้อมูลเส้นทางของไฟล์จากข้อมูลแบบร่าง
        if (!isset($draft_data['resume_path']) && !empty($draft['resume_path'])) {
            $draft_data['resume_path'] = $draft['resume_path'];
        }
        if (!isset($draft_data['photo_path']) && !empty($draft['photo_path'])) {
            $draft_data['photo_path'] = $draft['photo_path'];
        }
    }
}

// การจัดการกับการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$already_applied) {
    $save_as_draft = isset($_POST['save_as_draft']) && $_POST['save_as_draft'] == '1';
    $submit_application = isset($_POST['submit_application']) && $_POST['submit_application'] == '1';
    
    // เก็บข้อมูลทั้งหมดจากฟอร์ม
    $application_data = [
        'personal_info' => [
            'fullname' => $_POST['fullname'] ?? '',
            'registered_address' => $_POST['registered_address'] ?? '',
            'present_address' => $_POST['present_address'] ?? '',
            'telephone' => $_POST['telephone'] ?? '',
            'mobile' => $_POST['mobile'] ?? '',
            'marital_status' => $_POST['marital_status'] ?? '',
            'birthplace' => $_POST['birthplace'] ?? '',
            'birthdate' => $_POST['birthdate'] ?? '',
            'age' => $_POST['age'] ?? '',
            'weight' => $_POST['weight'] ?? '',
            'height' => $_POST['height'] ?? '',
            'nationality' => $_POST['nationality'] ?? '',
            'race' => $_POST['race'] ?? '',
            'religion' => $_POST['religion'] ?? '',
            'id_card' => $_POST['id_card'] ?? '',
            'issued_at' => $_POST['issued_at'] ?? '',
            'issued_date' => $_POST['issued_date'] ?? ''
        ],
        'education' => [
            'secondary' => [
                'from' => $_POST['secondary_from'] ?? '',
                'to' => $_POST['secondary_to'] ?? '',
                'institute' => $_POST['secondary_institute'] ?? '',
                'degree' => $_POST['secondary_degree'] ?? '',
                'major' => $_POST['secondary_major'] ?? ''
            ],
            'vocational' => [
                'from' => $_POST['vocational_from'] ?? '',
                'to' => $_POST['vocational_to'] ?? '',
                'institute' => $_POST['vocational_institute'] ?? '',
                'degree' => $_POST['vocational_degree'] ?? '',
                'major' => $_POST['vocational_major'] ?? ''
            ],
            'university' => [
                'from' => $_POST['university_from'] ?? '',
                'to' => $_POST['university_to'] ?? '',
                'institute' => $_POST['university_institute'] ?? '',
                'degree' => $_POST['university_degree'] ?? '',
                'major' => $_POST['university_major'] ?? ''
            ]
        ],
        'cover_letter' => $_POST['cover_letter'] ?? ''
    ];
    
    $resume_path = null;
    $photo_path = null;
    
    // ดึงข้อมูลเดิมในกรณีที่เป็นการแก้ไข draft
    if ($draft_data) {
        // ตรวจสอบการมีอยู่ของ draft ในฐานข้อมูล
        $stmt = $conn->prepare("SELECT resume_path, photo_path FROM application_drafts WHERE user_id = ? AND position_id = ?");
        $stmt->bind_param("ii", $user_id, $position_id);
        $stmt->execute();
        $draft_file_result = $stmt->get_result();
        
        if ($draft_file_result->num_rows > 0) {
            $draft_files = $draft_file_result->fetch_assoc();
            $resume_path = $draft_files['resume_path'];
            $photo_path = $draft_files['photo_path'];
        }
    }
    
    // จัดการกับการอัพโหลดไฟล์ประวัติ (resume)
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $upload_dir = 'uploads/resumes/';
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $new_filename = 'resume_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            // ลบไฟล์เก่าถ้ามี
            if ($resume_path && file_exists($resume_path)) {
                unlink($resume_path);
            }
            $resume_path = $target_file;
        }
    }
    
    // จัดการกับการอัพโหลดรูปถ่าย (photo)
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $upload_dir = 'uploads/photos/';
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_filename = 'photo_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            // ลบไฟล์เก่าถ้ามี
            if ($photo_path && file_exists($photo_path)) {
                unlink($photo_path);
            }
            $photo_path = $target_file;
        }
    }
    
    // เพิ่มข้อมูลไฟล์ลงในข้อมูลแอพพลิเคชัน
    $application_data['resume_path'] = $resume_path;
    $application_data['photo_path'] = $photo_path;
    
    // แปลงข้อมูลเป็น JSON
    $application_data_json = json_encode($application_data, JSON_UNESCAPED_UNICODE);
    
    if ($save_as_draft) {
        // บันทึกเป็นแบบร่าง
        // ตรวจสอบว่ามี draft อยู่แล้วหรือไม่
        $stmt = $conn->prepare("SELECT id FROM application_drafts WHERE user_id = ? AND position_id = ?");
        $stmt->bind_param("ii", $user_id, $position_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // อัพเดต draft ที่มีอยู่
            $draft_id = $result->fetch_assoc()['id'];
            $stmt = $conn->prepare("UPDATE application_drafts SET data = ?, resume_path = ?, photo_path = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssi", $application_data_json, $resume_path, $photo_path, $draft_id);
        } else {
            // สร้าง draft ใหม่
            $stmt = $conn->prepare("INSERT INTO application_drafts (user_id, position_id, data, resume_path, photo_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $user_id, $position_id, $application_data_json, $resume_path, $photo_path);
        }
        
        if ($stmt->execute()) {
            $draft_saved = true;
        } else {
            $error_message = "เกิดข้อผิดพลาดในการบันทึกแบบร่าง กรุณาลองใหม่ (" . $stmt->error . ")";
        }
    } elseif ($submit_application) {
        // ส่งใบสมัครจริง
        
        // ตรวจสอบว่ามีข้อมูลหลักที่จำเป็นหรือไม่
        $required_fields = [
            ['name' => 'fullname', 'label' => 'ชื่อ-นามสกุล'],
            ['name' => 'mobile', 'label' => 'เบอร์มือถือ']
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($application_data['personal_info'][$field['name']])) {
                $missing_fields[] = $field['label'];
            }
        }
        
        // ตรวจสอบไฟล์ resume ถ้าไม่มีก็ให้แจ้งเตือน
        if (empty($resume_path)) {
            $missing_fields[] = 'ไฟล์ประวัติส่วนตัว (PDF)';
        }
        
        if (count($missing_fields) > 0) {
            $error_message = "กรุณากรอกข้อมูลที่จำเป็น: " . implode(", ", $missing_fields);
        } else {
            // บันทึกการสมัครงาน
            $cover_letter = $application_data['cover_letter'];
            $stmt = $conn->prepare("INSERT INTO applications (user_id, position_id, resume_path, cover_letter, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiss", $user_id, $position_id, $resume_path, $cover_letter);
            
            if ($stmt->execute()) {
                $application_id = $conn->insert_id;
                
                // บันทึกข้อมูลลงในตาราง application_details
                $stmt = $conn->prepare("
                    INSERT INTO application_details 
                    (application_id, fullname, registered_address, present_address, telephone, mobile, 
                    marital_status, birthplace, birthdate, age, weight, height, nationality, race, 
                    religion, id_card, issued_at, issued_date, photo_path) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $birthdate = !empty($application_data['personal_info']['birthdate']) ? 
                    $application_data['personal_info']['birthdate'] : null;
                    
                $issued_date = !empty($application_data['personal_info']['issued_date']) ? 
                    $application_data['personal_info']['issued_date'] : null;
                    
                $age = !empty($application_data['personal_info']['age']) ? 
                    intval($application_data['personal_info']['age']) : null;
                    
                $weight = !empty($application_data['personal_info']['weight']) ? 
                    intval($application_data['personal_info']['weight']) : null;
                    
                $height = !empty($application_data['personal_info']['height']) ? 
                    intval($application_data['personal_info']['height']) : null;
                
                $stmt->bind_param(
                    "issssssssiiisssssss", 
                    $application_id,
                    $application_data['personal_info']['fullname'],
                    $application_data['personal_info']['registered_address'],
                    $application_data['personal_info']['present_address'],
                    $application_data['personal_info']['telephone'],
                    $application_data['personal_info']['mobile'],
                    $application_data['personal_info']['marital_status'],
                    $application_data['personal_info']['birthplace'],
                    $birthdate,
                    $age,
                    $weight,
                    $height,
                    $application_data['personal_info']['nationality'],
                    $application_data['personal_info']['race'],
                    $application_data['personal_info']['religion'],
                    $application_data['personal_info']['id_card'],
                    $application_data['personal_info']['issued_at'],
                    $issued_date,
                    $photo_path
                );
                
                $stmt->execute();
                
                // บันทึกข้อมูลการศึกษา (มัธยมศึกษา)
                if (!empty($application_data['education']['secondary']['institute'])) {
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO application_education 
                            (application_id, level, period_from, period_to, institute, degree, major) 
                            VALUES (?, 'secondary', ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->bind_param(
                            "isssss", 
                            $application_id,
                            $application_data['education']['secondary']['from'],
                            $application_data['education']['secondary']['to'],
                            $application_data['education']['secondary']['institute'],
                            $application_data['education']['secondary']['degree'],
                            $application_data['education']['secondary']['major']
                        );
                        
                        $stmt->execute();
                    } catch (Exception $e) {
                        error_log('Error saving secondary education: ' . $e->getMessage());
                    }
                }
                
                // บันทึกข้อมูลการศึกษา (อาชีวศึกษา)
                if (!empty($application_data['education']['vocational']['institute'])) {
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO application_education 
                            (application_id, level, period_from, period_to, institute, degree, major) 
                            VALUES (?, 'vocational', ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->bind_param(
                            "isssss", 
                            $application_id,
                            $application_data['education']['vocational']['from'],
                            $application_data['education']['vocational']['to'],
                            $application_data['education']['vocational']['institute'],
                            $application_data['education']['vocational']['degree'],
                            $application_data['education']['vocational']['major']
                        );
                        
                        $stmt->execute();
                    } catch (Exception $e) {
                        error_log('Error saving vocational education: ' . $e->getMessage());
                    }
                }
                
                // บันทึกข้อมูลการศึกษา (มหาวิทยาลัย)
                if (!empty($application_data['education']['university']['institute'])) {
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO application_education 
                            (application_id, level, period_from, period_to, institute, degree, major) 
                            VALUES (?, 'university', ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->bind_param(
                            "isssss", 
                            $application_id,
                            $application_data['education']['university']['from'],
                            $application_data['education']['university']['to'],
                            $application_data['education']['university']['institute'],
                            $application_data['education']['university']['degree'],
                            $application_data['education']['university']['major']
                        );
                        
                        $stmt->execute();
                    } catch (Exception $e) {
                        error_log('Error saving university education: ' . $e->getMessage());
                    }
                }
                
                // ลบแบบร่าง (ถ้ามี)
                $stmt = $conn->prepare("DELETE FROM application_drafts WHERE user_id = ? AND position_id = ?");
                $stmt->bind_param("ii", $user_id, $position_id);
                $stmt->execute();
                
                $submit_success = true;
                
                // หลังจากบันทึกสำเร็จ ให้รีโหลดหน้าเพื่อแสดงสถานะ "สมัครสำเร็จ"
                if (!isset($_GET['submitted'])) {
                    header("Location: apply.php?position_id=$position_id&submitted=1");
                    exit();
                }
            } else {
                $error_message = "เกิดข้อผิดพลาดในการสมัครงาน กรุณาลองใหม่: " . $stmt->error;
            }
        }
    }
}

// หากมีการส่งใบสมัครสำเร็จและมีพารามิเตอร์ submitted จะถือว่าการสมัครสำเร็จ
if (isset($_GET['submitted']) && $_GET['submitted'] == '1') {
    $submit_success = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครตำแหน่ง <?php echo htmlspecialchars($position['title']); ?></title>
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

        .container {
            max-width: 800px;
            margin: 120px auto 60px;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .position-header {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .position-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .position-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .position-meta span {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
        }

        .position-details {
            margin-bottom: 2rem;
        }

        .position-details h2 {
            color: #2b6cb0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .requirements {
            background: #f7fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .requirements h2 {
            color: #2b6cb0;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .requirements ul {
            list-style-type: none;
        }

        .requirements li {
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .requirements li::before {
            content: '✓';
            color: #48bb78;
            position: absolute;
            left: 0;
        }

        .application-form {
            margin-top: 2rem;
        }

        .application-form h2 {
            color: #2b6cb0;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .form-group label.required::after {
            content: " *";
            color: #e53e3e;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
            outline: none;
        }

        .form-group textarea {
            height: 150px;
            resize: vertical;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .draft-btn {
            background: white;
            color: #4299e1;
            border: 2px solid #4299e1;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            flex: 1;
        }

        .draft-btn:hover {
            background: rgba(66, 153, 225, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.1);
        }

        .submit-btn {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            flex: 1;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
        }

        .submit-btn:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .error-message {
            background: #fed7d7;
            color: #e53e3e;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .application-status {
            background: #e6fffa;
            border: 2px solid #81e6d9;
            color: #2c7a7b;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .application-status h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .back-btn {
            display: inline-block;
            background: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            border: 2px solid #4299e1;
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            margin-top: 1rem;
        }

        .back-btn:hover {
            background: #4299e1;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(66, 153, 225, 0.2);
        }

        /* สไตล์เพิ่มเติมจาก userrecruitmentform.html */
        .form-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            align-items: flex-start;
        }

        .logo-title {
            flex: 1;
            text-align: center !important;
        }

        .photo-area {
            width: 120px;
            height: 150px;
            border: 2px dashed #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #718096;
            font-size: 0.875rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .photo-area input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .photo-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .form-section {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-section-toggle {
            position: absolute;
            top: 0;
            right: 0;
            background: none;
            border: none;
            color: #4299e1;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-title {
            font-size: 1.25rem;
            color: #2d3748;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-title .section-indicator {
            font-size: 0.875rem;
            color: #718096;
            margin-left: auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            margin-top: 0.5rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .education-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .education-table th,
        .education-table td {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: left;
        }

        .education-table th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 500;
        }

        .education-table td input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.925rem;
        }

        .period-inputs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .progress-container {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 10px;
            background: linear-gradient(90deg, #4299e1, #3182ce);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #718096;
        }

        /* ปรับแต่ง responsive */
        @media (max-width: 1024px) {
            .container {
                padding: 1.5rem;
                margin: 100px auto 40px;
            }
            
            .position-header h1 {
                font-size: 1.8rem;
            }
            
            .position-meta span {
                font-size: 0.85rem;
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

            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
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
                margin: 90px auto 30px;
                padding: 1.25rem;
            }
            
            .position-header {
                padding: 1.5rem;
            }
            
            .position-header h1 {
                font-size: 1.5rem;
            }
            
            .position-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .requirements,
            .position-details {
                padding: 1.25rem;
            }
            
            .requirements h2,
            .position-details h2,
            .application-form h2 {
                font-size: 1.3rem;
            }
            
            .submit-btn,
            .back-btn {
                width: 100%;
                padding: 0.7rem;
                font-size: 1rem;
            }

            .form-row {
                gap: 1rem;
            }

            .radio-group {
                flex-direction: column;
                gap: 0.5rem;
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
            
            .position-header h1 {
                font-size: 1.3rem;
            }
            
            .position-meta span {
                font-size: 0.8rem;
            }
            
            .requirements li,
            .position-details p {
                font-size: 0.9rem;
            }
            
            .form-group label {
                font-size: 0.9rem;
            }
            
            .form-group input,
            .form-group textarea {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .application-status h3 {
                font-size: 1.1rem;
            }
            
            .application-status p {
                font-size: 0.9rem;
            }

            .form-header {
                flex-direction: column;
            }

            .photo-area {
                margin: 1rem auto;
            }

            .education-table {
                font-size: 0.8rem;
            }

            .education-table th, 
            .education-table td {
                padding: 0.5rem;
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
            
            .position-header h1 {
                font-size: 1.2rem;
            }
            
            .requirements h2,
            .position-details h2,
            .application-form h2 {
                font-size: 1.2rem;
            }
            
            .submit-btn,
            .back-btn {
                font-size: 0.9rem;
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
        </div>
    </nav>

    <div class="container">
        <div class="position-header">
            <h1><?php echo htmlspecialchars($position['title']); ?></h1>
            <p><?php echo htmlspecialchars($position['department']); ?></p>
            <div class="position-meta">
                <span>💼 <?php echo htmlspecialchars($position['type']); ?></span>
                <span>📍 <?php echo htmlspecialchars($position['location']); ?></span>
                <span>💰 <?php echo number_format($position['salary_min']); ?> - <?php echo number_format($position['salary_max']); ?> บาท</span>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($draft_saved): ?>
            <div class="success-message">
                <h3>บันทึกแบบร่างเรียบร้อยแล้ว</h3>
                <p>คุณสามารถกลับมาแก้ไขและส่งใบสมัครได้ในภายหลัง</p>
            </div>
        <?php endif; ?>

        <?php if ($submit_success): ?>
            <div class="success-message">
                <h3>ส่งใบสมัครเรียบร้อยแล้ว</h3>
                <p>ขอบคุณสำหรับการสมัคร เราจะติดต่อกลับโดยเร็วที่สุด</p>
                <a href="user/dashboard.php" class="back-btn">ไปยังแดชบอร์ด</a>
            </div>
        <?php elseif ($already_applied): ?>
            <div class="application-status">
                <h3>คุณได้สมัครตำแหน่งนี้แล้ว</h3>
                <p>สถานะการสมัคร: 
                    <?php 
                    switch($application['status']) {
                        case 'pending':
                            echo 'อยู่ระหว่างการพิจารณา';
                            break;
                        case 'approved':
                            echo 'ผ่านการพิจารณา';
                            break;
                        case 'rejected':
                            echo 'ไม่ผ่านการพิจารณา';
                            break;
                        default:
                            echo 'ไม่ทราบสถานะ';
                    }
                    ?>
                </p>
                <p>วันที่สมัคร: <?php echo date('d/m/Y H:i', strtotime($application['created_at'])); ?></p>
                <a href="user/dashboard.php" class="back-btn">ไปยังแดชบอร์ด</a>
            </div>
        <?php else: ?>
            <div class="position-details">
                <h2>รายละเอียดงาน</h2>
                <p><?php echo nl2br(htmlspecialchars($position['description'])); ?></p>
            </div>

            <div class="requirements">
                <h2>คุณสมบัติที่ต้องการ</h2>
                <ul>
                    <?php 
                    $requirements = explode("\n", $position['requirements']);
                    foreach ($requirements as $requirement) {
                        if (trim($requirement) !== '') {
                            echo '<li>' . htmlspecialchars(trim($requirement, "- \t\n\r\0\x0B")) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>

            <?php if (!$submit_success): ?>
                <div class="application-form">
                    <h2>สมัครตำแหน่งนี้</h2>
                    
                    <!-- Progress bar -->
                    <div class="progress-label">
                        <span>ความคืบหน้าการกรอกข้อมูล</span>
                        <span id="progressPercentage">0%</span>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" id="applicationForm">
                        <!-- เพิ่มส่วนฟอร์มจาก userrecruitmentform.html -->
                        <div class="form-header">
                            <div class="logo-title">
                                <h1 style="color: #2b6cb0; font-size: 1.5rem; margin-bottom: 1rem;">
                                    TTV SUPPLYCHAIN Co., Ltd.
                                </h1>
                                <h2 style="font-size: 1.25rem; color: #2d3748;">
                                    ใบสมัครงาน / APPLICATION FOR EMPLOYMENT
                                </h2>
                            </div>
                            <div class="photo-area" id="photoArea">
                                <input type="file" accept="image/*" id="photoInput" name="photo">
                                <div class="placeholder">
                                    ภาพถ่าย (Photo)<br>
                                    ขนาด 1 นิ้ว
                                </div>
                                <?php if ($draft_data && !empty($draft_data['photo_path'])): ?>
                                    <img id="photoPreview" style="display: block;" src="<?php echo htmlspecialchars($draft_data['photo_path']); ?>">
                                <?php else: ?>
                                    <img id="photoPreview" style="display: none;">
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ข้อมูลส่วนตัว -->
                        <div class="form-section" id="personalSection">
                            <h3 class="form-title">
                                รายละเอียดส่วนตัวผู้สมัคร / Personal Details
                                <span class="section-indicator">ส่วนที่ 1/3</span>
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="required">ชื่อ-นามสกุล / Name</label>
                                    <input type="text" name="fullname" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['fullname']) : (isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>ที่อยู่ตามทะเบียนบ้าน / Registered Address</label>
                                    <input type="text" name="registered_address" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['registered_address']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>ที่อยู่ปัจจุบัน / Present Address</label>
                                    <input type="text" name="present_address" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['present_address']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>โทรศัพท์ / Telephone</label>
                                    <input type="tel" name="telephone" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['telephone']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="required">มือถือ / Mobile</label>
                                    <input type="tel" name="mobile" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['mobile']) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>สถานภาพการสมรส / Marital Status</label>
                                    <div class="radio-group">
                                        <label class="radio-option">
                                            <input type="radio" name="marital_status" value="single" <?php echo $draft_data && $draft_data['personal_info']['marital_status'] == 'single' ? 'checked' : ''; ?>>
                                            โสด / Single
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="marital_status" value="married" <?php echo $draft_data && $draft_data['personal_info']['marital_status'] == 'married' ? 'checked' : ''; ?>>
                                            แต่งงาน / Married
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="marital_status" value="widowed" <?php echo $draft_data && $draft_data['personal_info']['marital_status'] == 'widowed' ? 'checked' : ''; ?>>
                                            หม้าย / Widowed
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>เกิดที่จังหวัด / Place of Birth</label>
                                    <input type="text" name="birthplace" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['birthplace']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>วันเกิด / Date of Birth</label>
                                    <input type="date" name="birthdate" id="birthdate" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['birthdate']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>อายุ / Age</label>
                                    <input type="number" name="age" id="age" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['age']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>น้ำหนัก / Weight</label>
                                    <input type="number" name="weight" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['weight']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>ส่วนสูง / Height</label>
                                    <input type="number" name="height" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['height']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>สัญชาติ / Nationality</label>
                                    <input type="text" name="nationality" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['nationality']) : 'ไทย'; ?>">
                                </div>
                                <div class="form-group">
                                    <label>เชื้อชาติ / Race</label>
                                    <input type="text" name="race" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['race']) : 'ไทย'; ?>">
                                </div>
                                <div class="form-group">
                                    <label>ศาสนา / Religion</label>
                                    <input type="text" name="religion" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['religion']) : ''; ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>เลขบัตรประชาชน / ID Card No.</label>
                                    <input type="text" name="id_card" maxlength="13" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['id_card']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>ออกให้ ณ / Issued at</label>
                                    <input type="text" name="issued_at" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['issued_at']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label>วันที่ออกบัตร / Issued Date</label>
                                    <input type="date" name="issued_date" value="<?php echo $draft_data ? htmlspecialchars($draft_data['personal_info']['issued_date']) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- ประวัติการศึกษา -->
                        <div class="form-section" id="educationSection">
                            <h3 class="form-title">
                                ประวัติการศึกษา / Educational Background
                                <span class="section-indicator">ส่วนที่ 2/3</span>
                            </h3>
                            
                            <table class="education-table">
                                <thead>
                                    <tr>
                                        <th>ประเภทการศึกษา<br>Education</th>
                                        <th>ระยะเวลา Period<br>ตั้งแต่ From / ถึง To</th>
                                        <th>ชื่อสถานที่การศึกษาและที่ตั้ง<br>Institute Name and Location</th>
                                        <th>วุฒิที่ได้รับ<br>Certificate Diploma/Degree</th>
                                        <th>วิชาเอก<br>Major Subject</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>มัธยมศึกษา<br>Secondary</td>
                                        <td>
                                            <div class="period-inputs">
                                                <input type="text" name="secondary_from" placeholder="From" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['secondary']['from']) : ''; ?>">
                                                <span>/</span>
                                                <input type="text" name="secondary_to" placeholder="To" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['secondary']['to']) : ''; ?>">
                                            </div>
                                        </td>
                                        <td><input type="text" name="secondary_institute" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['secondary']['institute']) : ''; ?>"></td>
                                        <td><input type="text" name="secondary_degree" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['secondary']['degree']) : ''; ?>"></td>
                                        <td><input type="text" name="secondary_major" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['secondary']['major']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>อาชีวะศึกษา<br>Commercial/Vocational</td>
                                        <td>
                                            <div class="period-inputs">
                                                <input type="text" name="vocational_from" placeholder="From" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['vocational']['from']) : ''; ?>">
                                                <span>/</span>
                                                <input type="text" name="vocational_to" placeholder="To" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['vocational']['to']) : ''; ?>">
                                            </div>
                                        </td>
                                        <td><input type="text" name="vocational_institute" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['vocational']['institute']) : ''; ?>"></td>
                                        <td><input type="text" name="vocational_degree" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['vocational']['degree']) : ''; ?>"></td>
                                        <td><input type="text" name="vocational_major" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['vocational']['major']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>มหาวิทยาลัย<br>University</td>
                                        <td>
                                            <div class="period-inputs">
                                                <input type="text" name="university_from" placeholder="From" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['university']['from']) : ''; ?>">
                                                <span>/</span>
                                                <input type="text" name="university_to" placeholder="To" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['university']['to']) : ''; ?>">
                                            </div>
                                        </td>
                                        <td><input type="text" name="university_institute" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['university']['institute']) : ''; ?>"></td>
                                        <td><input type="text" name="university_degree" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['university']['degree']) : ''; ?>"></td>
                                        <td><input type="text" name="university_major" value="<?php echo $draft_data ? htmlspecialchars($draft_data['education']['university']['major']) : ''; ?>"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- ส่วนของไฟล์ PDF และจดหมายสมัครงาน -->
                        <div class="form-section" id="documentsSection">
                            <h3 class="form-title">
                                เอกสารประกอบการสมัคร / Application Documents
                                <span class="section-indicator">ส่วนที่ 3/3</span>
                            </h3>
                            
                            <div class="form-group">
                                <label class="required">ประวัติส่วนตัว (PDF เท่านั้น)</label>
                                <input type="file" id="resume" name="resume" accept=".pdf" <?php echo $draft_data && !empty($draft_data['resume_path']) ? '' : 'required'; ?>>
                                <?php if ($draft_data && !empty($draft_data['resume_path'])): ?>
                                    <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #4a5568;">
                                        เอกสารที่อัพโหลดไว้: <?php echo basename($draft_data['resume_path']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>จดหมายสมัครงาน</label>
                                <textarea id="cover_letter" name="cover_letter" placeholder="แนะนำตัวเองและบอกเหตุผลที่คุณเหมาะสมกับตำแหน่งนี้"><?php echo $draft_data ? htmlspecialchars($draft_data['cover_letter']) : ''; ?></textarea>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" name="save_as_draft" value="1" class="draft-btn">บันทึกแบบร่าง</button>
                                <button type="submit" name="submit_application" value="1" class="submit-btn">ส่งใบสมัคร</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript สำหรับ Dropdown Menu
        document.addEventListener('DOMContentLoaded', function() {
            const userInfoToggle = document.getElementById('userInfoToggle');
            const userDropdown = document.getElementById('userDropdown');
            const menuBtn = document.getElementById('menuBtn');
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
            
            // จัดการปุ่มเบอร์เกอร์เมนู
            if (menuBtn) {
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
            
            // จัดการการคลิกที่ลิงก์ในเมนู
            document.querySelectorAll('.user-dropdown a').forEach(link => {
                link.addEventListener('click', () => {
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

            // จัดการการอัพโหลดรูปภาพ
            const photoInput = document.getElementById('photoInput');
            const photoPreview = document.getElementById('photoPreview');
            const photoPlaceholder = document.querySelector('.placeholder');
            
            if (photoInput && photoPreview) {
                // ตรวจสอบว่ามีภาพอยู่แล้วหรือไม่
                if (photoPreview.src && photoPreview.src !== '' && photoPreview.src.indexOf('data:image') !== -1) {
                    photoPreview.style.display = 'block';
                    if (photoPlaceholder) {
                        photoPlaceholder.style.display = 'none';
                    }
                }
                
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        if (!file.type.startsWith('image/')) {
                            alert('กรุณาอัพโหลดไฟล์รูปภาพเท่านั้น');
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function(e) {
                            photoPreview.src = e.target.result;
                            photoPreview.style.display = 'block';
                            if (photoPlaceholder) {
                                photoPlaceholder.style.display = 'none';
                            }
                        };

                        reader.readAsDataURL(file);
                    }
                });
            }

            // คำนวณอายุอัตโนมัติจากวันเกิด
            const birthdateInput = document.getElementById('birthdate');
            const ageInput = document.getElementById('age');
            
            if (birthdateInput && ageInput) {
                // คำนวณอายุตอนโหลดเพจหากมีวันเกิดอยู่แล้ว
                if (birthdateInput.value) {
                    calculateAge();
                }
                
                birthdateInput.addEventListener('change', calculateAge);
                
                function calculateAge() {
                    const birthdate = new Date(birthdateInput.value);
                    const today = new Date();
                    let age = today.getFullYear() - birthdate.getFullYear();
                    const monthDiff = today.getMonth() - birthdate.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                        age--;
                    }
                    
                    if (age >= 0) {
                        ageInput.value = age;
                    }
                }
            }

            // ตรวจสอบเลขบัตรประชาชน
            const idCardInput = document.querySelector('input[name="id_card"]');
            if (idCardInput) {
                idCardInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);
                });
            }
            
            // จัดการกับ progress bar
            function updateProgressBar() {
                const form = document.getElementById('applicationForm');
                if (!form) return;
                
                const inputs = form.querySelectorAll('input[type="text"], input[type="tel"], input[type="number"], input[type="date"], input[type="radio"]:checked, textarea');
                let filledCount = 0;
                
                inputs.forEach(input => {
                    if (input.value.trim() !== '') {
                        filledCount++;
                    }
                });
                
                // ตรวจสอบไฟล์ที่อัพโหลด
                const resumeInput = document.getElementById('resume');
                const photoInput = document.getElementById('photoInput');
                
                if (resumeInput && resumeInput.files.length > 0) {
                    filledCount++;
                } else if (<?php echo $draft_data && !empty($draft_data['resume_path']) ? 'true' : 'false'; ?>) {
                    // นับว่ามีไฟล์ resume แล้วจาก draft
                    filledCount++;
                }
                
                if (photoInput && photoInput.files.length > 0) {
                    filledCount++;
                } else if (<?php echo $draft_data && !empty($draft_data['photo_path']) ? 'true' : 'false'; ?>) {
                    // นับว่ามีรูปภาพแล้วจาก draft
                    filledCount++;
                }
                
                // นับจำนวนฟิลด์ทั้งหมด
                const totalFields = 25; // จำนวนฟิลด์ทั้งหมด (ประมาณ)
                
                // คำนวณเปอร์เซ็นต์ความคืบหน้า
                const progress = Math.min(Math.round((filledCount / totalFields) * 100), 100);
                
                const progressBar = document.getElementById('progressBar');
                const progressPercentage = document.getElementById('progressPercentage');
                
                if (progressBar && progressPercentage) {
                    progressBar.style.width = `${progress}%`;
                    progressPercentage.textContent = `${progress}%`;
                }
            }
            
            // เรียกใช้ฟังก์ชันตอนโหลดเพจและเมื่อข้อมูลเปลี่ยน
            updateProgressBar();
            
            const formInputs = document.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                input.addEventListener('change', updateProgressBar);
                input.addEventListener('input', updateProgressBar);
            });
        });
    </script>
</body>
</html>