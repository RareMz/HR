<?php
session_start();
require_once '../config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลใบสมัครงานจาก URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$application_id) {
    header("Location: applications.php");
    exit();
}

// ดึงข้อมูลใบสมัครงาน
$stmt = $conn->prepare("SELECT a.*, p.title as position_title 
                        FROM applications a
                        JOIN positions p ON a.position_id = p.id
                        WHERE a.id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    header("Location: applications.php");
    exit();
}

// ตรวจสอบสิทธิ์ admin หรือ เจ้าของใบสมัคร
if ($_SESSION['user_role'] !== 'admin' && $application['user_id'] != $_SESSION['user_id']) {
    header("Location: applications.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดใบสมัครงาน - ระบบรับสมัครงาน</title>
    <link rel="stylesheet" href="../static/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 960px;
        }
        .page-title {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .section-container {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .section-title {
            color: #333;
            margin-bottom: 20px;
        }
        .info-list li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="page-title">รายละเอียดใบสมัครงาน</h1>

        <div class="section-container">
            <h2 class="section-title">ข้อมูลทั่วไป</h2>
            <ul class="info-list">
                <li><strong>หมายเลขใบสมัคร:</strong> <?php echo $application['id']; ?></li>
                <li><strong>ตำแหน่ง:</strong> <?php echo $application['position_title']; ?></li>
                <li><strong>วันที่สมัคร:</strong> <?php echo date('d/m/Y', strtotime($application['created_at'])); ?></li>
                <li><strong>สถานะ:</strong> <?php echo $application['status']; ?></li>
                <?php if ($application['resume_path']): ?>
                    <li>
                        <strong>เรซูเม่:</strong> 
                        <a href="<?php echo '../' . $application['resume_path']; ?>" target="_blank">
                            ดูเรซูเม่
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($application['cover_letter']): ?>
                    <li>
                        <strong>Cover Letter:</strong><br>
                        <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <?php
        // ดึงข้อมูลรายละเอียดผู้สมัคร
        $stmt = $conn->prepare("SELECT * FROM application_details WHERE application_id = ?");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $detail = $stmt->get_result()->fetch_assoc();
        ?>

        <?php if ($detail): ?>
            <div class="section-container">
                <h2 class="section-title">ข้อมูลส่วนตัว</h2>
                <ul class="info-list">
                    <li><strong>ชื่อ-นามสกุล:</strong> <?php echo $detail['fullname']; ?></li>
                    <li><strong>ที่อยู่ตามทะเบียนบ้าน:</strong> <?php echo $detail['registered_address']; ?></li>
                    <li><strong>ที่อยู่ปัจจุบัน:</strong> <?php echo $detail['present_address']; ?></li>
                    <li><strong>โทรศัพท์บ้าน:</strong> <?php echo $detail['telephone']; ?></li>
                    <li><strong>โทรศัพท์มือถือ:</strong> <?php echo $detail['mobile']; ?></li>
                    <li><strong>สถานภาพการสมรส:</strong> <?php echo $detail['marital_status']; ?></li>
                    <li><strong>สถานที่เกิด:</strong> <?php echo $detail['birthplace']; ?></li>
                    <li><strong>วันเกิด:</strong> <?php echo $detail['birthdate']; ?></li>
                    <li><strong>อายุ:</strong> <?php echo $detail['age']; ?> ปี</li>
                    <li><strong>น้ำหนัก:</strong> <?php echo $detail['weight']; ?> กก.</li>
                    <li><strong>ส่วนสูง:</strong> <?php echo $detail['height']; ?> ซม.</li>
                    <li><strong>สัญชาติ:</strong> <?php echo $detail['nationality']; ?></li>
                    <li><strong>เชื้อชาติ:</strong> <?php echo $detail['race']; ?></li>
                    <li><strong>ศาสนา:</strong> <?php echo $detail['religion']; ?></li>
                    <li><strong>บัตรประชาชนเลขที่:</strong> <?php echo $detail['id_card']; ?></li>
                    <li><strong>ออกให้ ณ:</strong> <?php echo $detail['issued_at']; ?></li>
                    <li><strong>วันออกบัตร:</strong> <?php echo $detail['issued_date']; ?></li>
                    <?php if ($detail['photo_path']): ?>
                        <li>
                            <strong>รูปถ่าย:</strong><br>
                            <img src="<?php echo '../' . $detail['photo_path']; ?>" alt="รูปถ่ายผู้สมัคร" width="200">
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        // ดึงข้อมูลประวัติการศึกษา
        $stmt = $conn->prepare("SELECT * FROM application_education WHERE application_id = ?");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $educations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>

        <?php if (!empty($educations)): ?>
            <div class="section-container">
                <h2 class="section-title">ประวัติการศึกษา</h2>
                <?php foreach ($educations as $education): ?>
                    <h4><?php echo $education['level']; ?></h4>
                    <ul class="info-list">
                        <li><strong>สถาบัน:</strong> <?php echo $education['institute']; ?></li>
                        <li><strong>ระยะเวลา:</strong> <?php echo $education['period_from']; ?> - <?php echo $education['period_to']; ?></li>
                        <li><strong>วุฒิ:</strong> <?php echo $education['degree']; ?></li>
                        <li><strong>สาขา:</strong> <?php echo $education['major']; ?></li>
                    </ul>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <a href="applications.php" class="btn btn-primary">ย้อนกลับ</a>
        </div>
    </div>

    <script src="../static/js/bootstrap.bundle.min.js"></script>
</body>
</html>