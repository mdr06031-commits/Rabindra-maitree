<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$cols = $db->query("SHOW COLUMNS FROM student_results LIKE 'is_estimated'")->fetchAll();
if (empty($cols)) {
    $db->exec("ALTER TABLE student_results ADD COLUMN is_estimated TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published");
    echo "Added is_estimated column.\n";
} else {
    echo "is_estimated column already exists.\n";
}

$cols2 = $db->query("SHOW COLUMNS FROM student_results LIKE 'result_type'")->fetchAll();
if (empty($cols2)) {
    $db->exec("ALTER TABLE student_results ADD COLUMN result_type ENUM('Verified', 'Estimated') NOT NULL DEFAULT 'Verified' AFTER is_estimated");
    echo "Added result_type column.\n";
} else {
    echo "result_type column already exists.\n";
}
