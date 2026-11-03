<?php
/**
 * Migration Script: Upgrade student_results table to support Full Marks, Obtained Marks, and Publish status.
 * Also cleans up any fake CGPA values for students without results.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

echo "Upgrading student_results schema...\n";

// 1. Check and add full_marks column
$colCheck = $db->query("SHOW COLUMNS FROM `student_results` LIKE 'full_marks'")->fetch();
if (!$colCheck) {
    $db->exec("ALTER TABLE `student_results` ADD COLUMN `full_marks` INT UNSIGNED NOT NULL DEFAULT 100 AFTER `credit`");
    echo "- Added `full_marks` column.\n";
}

// 2. Check and add marks (obtained marks) column
$colCheck2 = $db->query("SHOW COLUMNS FROM `student_results` LIKE 'marks'")->fetch();
if (!$colCheck2) {
    $db->exec("ALTER TABLE `student_results` ADD COLUMN `marks` DECIMAL(5,2) NULL AFTER `full_marks`");
    echo "- Added `marks` column.\n";
}

// 3. Check and add is_published column
$colCheck3 = $db->query("SHOW COLUMNS FROM `student_results` LIKE 'is_published'")->fetch();
if (!$colCheck3) {
    $db->exec("ALTER TABLE `student_results` ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `exam_term`");
    echo "- Added `is_published` column.\n";
}

// 4. Populate default marks based on letter grades for existing records
$gradeToMarks = [
    'A+' => 85.00,
    'A'  => 76.00,
    'A-' => 72.00,
    'B+' => 66.00,
    'B'  => 62.00,
    'B-' => 57.00,
    'C+' => 52.00,
    'C'  => 47.00,
    'D'  => 42.00,
    'F'  => 32.00,
];

foreach ($gradeToMarks as $grade => $defaultMarks) {
    $stmt = $db->prepare("UPDATE `student_results` SET `marks` = ?, `full_marks` = 100 WHERE `letter_grade` = ? AND (`marks` IS NULL OR `marks` = 0)");
    $stmt->execute([$defaultMarks, $grade]);
}
echo "- Populated obtained marks for existing results.\n";

// 5. Clean up fake CGPA values for students without published results
$db->exec("
    UPDATE `students` 
    SET `cgpa` = 0.00, `credits_completed` = 0 
    WHERE `id` NOT IN (SELECT DISTINCT `student_id` FROM `student_results` WHERE `is_published` = 1)
");
echo "- Reset fake CGPA to 0.00 for students with no published academic results.\n";

// 6. Recalculate true credit-weighted CGPA for students who do have published results
$studentsWithResults = $db->query("SELECT DISTINCT `student_id` FROM `student_results` WHERE `is_published` = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($studentsWithResults as $sid) {
    $calc = $db->prepare("
        SELECT 
            SUM(credit * grade_point) / NULLIF(SUM(credit), 0) AS calc_cgpa,
            SUM(CASE WHEN grade_point >= 2.00 THEN credit ELSE 0 END) AS total_cr
        FROM `student_results`
        WHERE `student_id` = ? AND `is_published` = 1
    ");
    $calc->execute([$sid]);
    $data = $calc->fetch(PDO::FETCH_ASSOC);
    if ($data && $data['calc_cgpa']) {
        $realCgpa = round((float)$data['calc_cgpa'], 2);
        $realCr = (int)$data['total_cr'];
        $upd = $db->prepare("UPDATE `students` SET `cgpa` = ?, `credits_completed` = ? WHERE `id` = ?");
        $upd->execute([$realCgpa, $realCr, $sid]);
        echo "- Recalculated true CGPA ({$realCgpa}, {$realCr} credits) for student ID #{$sid}.\n";
    }
}

echo "Database upgrade completed successfully.\n";

