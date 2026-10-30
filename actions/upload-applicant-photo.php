<?php
/**
 * Action: Upload or Update Applicant Photograph for Application Card
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    flash_set('error', 'Session expired. Please try again.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

$ref = trim($_POST['ref'] ?? '');
$db = getDb();
$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? LIMIT 1");
$stmt->execute([$ref]);
$adm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adm) {
    flash_set('error', 'Application not found.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['photo'];
    $maxBytes = 2 * 1024 * 1024;
    if ($file['size'] <= $maxBytes) {
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (isset($allowedTypes[$mime])) {
            $ext = $allowedTypes[$mime];
            $uploadDir = BASE_PATH . '/storage/uploads/students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $cleanMobile = preg_replace('/[^0-9]/', '', $adm['mobile']);
            $filename = 'applicant_' . $cleanMobile . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                // Update admissions record
                $upd = $db->prepare("UPDATE admissions SET photo = ? WHERE id = ?");
                $upd->execute([$filename, $adm['id']]);

                // Also update student profile avatar if provisioned
                if (!empty($adm['student_id'])) {
                    $updStudent = $db->prepare("UPDATE students SET avatar = ? WHERE student_id = ?");
                    $updStudent->execute([$filename, $adm['student_id']]);
                }

                flash_set('success', 'Your photograph has been updated on your Application Card and Student Portal!');
            }
        } else {
            flash_set('error', 'Only JPG, PNG or WEBP images are allowed.');
        }
    } else {
        flash_set('error', 'Photo file size must be less than 2MB.');
    }
} else {
    flash_set('error', 'Please choose a photo to upload.');
}

header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode($ref));
exit;

