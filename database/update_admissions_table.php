<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDb();
    $cols = $db->query("DESCRIBE admissions")->fetchAll(PDO::FETCH_COLUMN);

    $newCols = [
        'ssc_exam' => "VARCHAR(50) NULL DEFAULT 'S.S.C'",
        'ssc_roll' => "VARCHAR(50) NULL",
        'ssc_board' => "VARCHAR(50) NULL",
        'ssc_result_type' => "VARCHAR(50) NULL DEFAULT 'GPA(out of 5)'",
        'ssc_group' => "VARCHAR(50) NULL",
        'ssc_year' => "VARCHAR(10) NULL",
        'hsc_exam' => "VARCHAR(50) NULL DEFAULT 'H.S.C'",
        'hsc_roll' => "VARCHAR(50) NULL",
        'hsc_board' => "VARCHAR(50) NULL",
        'hsc_result_type' => "VARCHAR(50) NULL DEFAULT 'GPA(out of 5)'",
        'hsc_year' => "VARCHAR(10) NULL",
        'grad_applicable' => "TINYINT(1) NOT NULL DEFAULT 0",
        'grad_exam' => "VARCHAR(100) NULL",
        'grad_university' => "VARCHAR(255) NULL",
        'grad_subject' => "VARCHAR(150) NULL",
        'grad_result_type' => "VARCHAR(50) NULL",
        'grad_result' => "VARCHAR(50) NULL",
        'grad_year' => "VARCHAR(10) NULL",
        'grad_duration' => "VARCHAR(50) NULL"
    ];

    foreach ($newCols as $col => $def) {
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN $col $def");
            echo "Added column $col\n";
        } else {
            echo "Column $col already exists\n";
        }
    }

    $db->exec("ALTER TABLE admissions MODIFY COLUMN hsc_group VARCHAR(50) NOT NULL");
    echo "Modified hsc_group column\n";

    echo "Admissions table schema successfully updated!\n";
} catch (Exception $e) {
    echo "Error updating admissions table: " . $e->getMessage() . "\n";
    exit(1);
}

