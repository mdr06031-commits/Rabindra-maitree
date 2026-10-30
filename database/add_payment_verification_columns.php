<?php
/**
 * Migration: Add sender_number and payment_screenshot columns to admissions table
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDb();
    
    // Check if sender_number column exists
    $cols = $db->query("SHOW COLUMNS FROM admissions LIKE 'sender_number'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE admissions ADD COLUMN sender_number VARCHAR(30) NULL AFTER transaction_id");
        echo "Added column 'sender_number'.\n";
    } else {
        echo "Column 'sender_number' already exists.\n";
    }

    // Check if payment_screenshot column exists
    $cols = $db->query("SHOW COLUMNS FROM admissions LIKE 'payment_screenshot'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE admissions ADD COLUMN payment_screenshot VARCHAR(255) NULL AFTER sender_number");
        echo "Added column 'payment_screenshot'.\n";
    } else {
        echo "Column 'payment_screenshot' already exists.\n";
    }

    // Ensure storage directory exists
    $payDir = STORAGE_PATH . '/uploads/payments';
    if (!is_dir($payDir)) {
        mkdir($payDir, 0755, true);
        echo "Created payments upload directory: {$payDir}\n";
    }

    echo "Database schema updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
    exit(1);
}

