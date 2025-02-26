<?php
header('Content-Type: application/json');
session_start();
require_once '../config.php';

// Validate admin session
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$applicationId = $data['applicationId'];
$newStatus = $data['status'];

// Validate input
if (!in_array($newStatus, ['pending', 'approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Update application status
$stmt = $conn->prepare("
    UPDATE applications 
    SET status = ?, 
        updated_at = CURRENT_TIMESTAMP, 
        updated_by = ? 
    WHERE id = ?
");
$stmt->bind_param("sii", $newStatus, $_SESSION['user_id'], $applicationId);

if ($stmt->execute()) {
    // Log status change
    $logStmt = $conn->prepare("
        INSERT INTO application_status_logs 
        (application_id, old_status, new_status, changed_by) 
        VALUES (?, ?, ?, ?)
    ");
    
    // First, get the old status
    $oldStatusStmt = $conn->prepare("SELECT status FROM applications WHERE id = ?");
    $oldStatusStmt->bind_param("i", $applicationId);
    $oldStatusStmt->execute();
    $result = $oldStatusStmt->get_result();
    $oldStatus = $result->fetch_assoc()['status'];

    $logStmt->bind_param("issi", $applicationId, $oldStatus, $newStatus, $_SESSION['user_id']);
    $logStmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
}