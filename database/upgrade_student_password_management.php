<?php
/**
 * Database Migration: Student Password & Credential Management Metadata
 * Rabindra Maitree University (RMU)
 * Idempotent migration adding password lifecycle metadata to `students`
 * while keeping authentication strictly one-way hashed (password_hash only).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

function pwd_mig_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    }
}

try {
    $cols = $db->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
    $colMap = array_flip($cols);

    $alterAdditions = [
        'password_updated_at'  => "ADD COLUMN password_updated_at DATETIME NULL AFTER password_hash",
        'password_updated_by'  => "ADD COLUMN password_updated_by INT UNSIGNED NULL AFTER password_updated_at",
        'password_source'      => "ADD COLUMN password_source ENUM('system_generated','admin_reset','student_updated','legacy_hash') NOT NULL DEFAULT 'legacy_hash' AFTER password_updated_by",
        'must_change_password' => "ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_source",
    ];

    foreach ($alterAdditions as $colName => $ddlClause) {
        if (!isset($colMap[$colName])) {
            $db->exec("ALTER TABLE students {$ddlClause}");
            pwd_mig_log("[OK] Added column students.{$colName}");
        } else {
            pwd_mig_log("[SKIP] Column students.{$colName} already exists.");
        }
    }

    pwd_mig_log("[DONE] Student Password Management migration completed successfully.");
} catch (Throwable $e) {
    pwd_mig_log("[ERROR] Migration failed: " . $e->getMessage());
    exit(1);
}
