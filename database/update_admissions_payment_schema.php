<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDb();
    $cols = $db->query("DESCRIBE admissions")->fetchAll(PDO::FETCH_COLUMN);

    $paymentCols = [
        'payment_status'  => "VARCHAR(20) NOT NULL DEFAULT 'Unpaid'",
        'payment_amount'  => "DECIMAL(10,2) NOT NULL DEFAULT 150.00",
        'payment_method'  => "VARCHAR(50) NULL",
        'transaction_id'  => "VARCHAR(100) NULL",
        'paid_at'         => "DATETIME NULL",
        'student_id'      => "VARCHAR(50) NULL",
        'portal_password' => "VARCHAR(100) NULL",
        'exam_roll'       => "VARCHAR(50) NULL",
        'exam_date'       => "VARCHAR(100) NULL DEFAULT 'Saturday, 15 November 2026 at 10:00 AM'",
        'exam_center'     => "VARCHAR(150) NULL DEFAULT 'RMU Permanent Campus, Kushtia'"
    ];

    foreach ($paymentCols as $col => $def) {
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE admissions ADD COLUMN $col $def");
            echo "Added column $col to admissions table\n";
        } else {
            echo "Column $col already exists\n";
        }
    }

    echo "Admissions table payment schema successfully updated!\n";
} catch (Exception $e) {
    echo "Error updating admissions table: " . $e->getMessage() . "\n";
    exit(1);
}

