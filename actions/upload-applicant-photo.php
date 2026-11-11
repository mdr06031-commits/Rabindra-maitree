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
$stmt = $db->prepare("SELECT * FROM admissions WHERE application_ref = ? OR application_id = ? OR roll_number = ? LIMIT 1");
$stmt->execute([$ref, $ref, $ref]);
$adm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adm) {
    flash_set('error', 'Application not found.');
    header("Location: " . APP_URL . "/admission-status.php");
    exit;
}

if (!can_access_admission_document($adm)) {
    log_audit('ADMISSION_PHOTO_IDOR_BLOCKED', "Blocked unauthorized photo upload attempt for application {$adm['application_ref']}");
    flash_set('error', 'Access denied. Please verify your application ownership before updating the photograph.');
    header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode((string)$adm['application_ref']));
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
            $oldPhoto = basename(trim((string)($adm['photo'] ?? '')));
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

                // Safely unlink orphaned custom applicant/student photo if no record references it
                if ($oldPhoto !== '' && $oldPhoto !== $filename && (strpos($oldPhoto, 'applicant_') === 0 || strpos($oldPhoto, 'student_') === 0)) {
                    $chkAdm = $db->prepare("SELECT COUNT(*) FROM admissions WHERE photo = ?");
                    $chkAdm->execute([$oldPhoto]);
                    $chkSt = $db->prepare("SELECT COUNT(*) FROM students WHERE avatar = ?");
                    $chkSt->execute([$oldPhoto]);
                    if ((int)$chkAdm->fetchColumn() === 0 && (int)$chkSt->fetchColumn() === 0 && is_file($uploadDir . $oldPhoto)) {
                        @unlink($uploadDir . $oldPhoto);
                    }
                }

                authorize_applicant_session_ref((string)$adm['application_ref'], $adm['application_id'] ?? null, $adm['roll_number'] ?? null);
                refresh_admission_application_pdf_async((int)$adm['id'], $db);

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

header("Location: " . APP_URL . "/admission-status.php?ref=" . urlencode((string)$adm['application_ref']));
exit;

