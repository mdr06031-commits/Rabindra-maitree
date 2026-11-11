<?php
/**
 * Database Migration: Automatic Sequential Application ID & Admission Roll Number Engine
 * - Normalizes blank identifiers to NULL (allowing multiple NULLs in UNIQUE indexes)
 * - Resolves legacy duplicate roll numbers cleanly
 * - Adds strict database UNIQUE constraints on admissions & students identifier columns
 * - Creates `admission_sequences` & `admission_id_reservations` tables
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$dbName = (string)$db->query("SELECT DATABASE()")->fetchColumn();

echo "Running Automatic Application ID & Roll Number Migration on DB '{$dbName}'...\n";

// Helper to check if an index exists by name
$hasIndex = function (string $table, string $indexName) use ($db, $dbName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$dbName, $table, $indexName]);
    return (int)$stmt->fetchColumn() > 0;
};

// Helper to check if a single-column UNIQUE index already covers a column
$hasUniqueOnColumn = function (string $table, string $column) use ($db, $dbName): bool {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s
        WHERE s.TABLE_SCHEMA = ? AND s.TABLE_NAME = ? AND s.COLUMN_NAME = ? AND s.NON_UNIQUE = 0
          AND (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS s2
            WHERE s2.TABLE_SCHEMA = s.TABLE_SCHEMA AND s2.TABLE_NAME = s.TABLE_NAME AND s2.INDEX_NAME = s.INDEX_NAME
          ) = 1
    ");
    $stmt->execute([$dbName, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
};

// Helper to normalize empty strings to NULL and resolve duplicate non-null values
$dedupeColumn = function (string $table, string $column) use ($db): void {
    $db->exec("UPDATE `{$table}` SET `{$column}` = NULL WHERE `{$column}` IS NOT NULL AND TRIM(`{$column}`) = ''");

    $dupStmt = $db->query("
        SELECT `{$column}` AS val, COUNT(*) AS cnt
        FROM `{$table}`
        WHERE `{$column}` IS NOT NULL
        GROUP BY `{$column}`
        HAVING COUNT(*) > 1
    ");
    $dups = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dups as $d) {
        $val = (string)$d['val'];
        $rows = $db->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? ORDER BY id ASC");
        $rows->execute([$val]);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN);
        $suffixNum = 2;
        for ($i = 1; $i < count($ids); $i++) {
            $rowId = (int)$ids[$i];
            do {
                $candidate = "{$val}-{$suffixNum}";
                $chk = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
                $chk->execute([$candidate]);
                $suffixNum++;
            } while ((int)$chk->fetchColumn() > 0);
            $upd = $db->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE id = ?");
            $upd->execute([$candidate, $rowId]);
            echo "  - Disambiguated duplicate {$table}.{$column} (row #{$rowId}): '{$val}' -> '{$candidate}'\n";
        }
    }
};

// 1. Ensure schema tables exist
ensure_admission_id_generation_schema($db);
$curYear = (int)date('Y');
$db->prepare("INSERT IGNORE INTO admission_sequences (seq_year, last_app_seq, last_roll_seq, updated_at) VALUES (?, 0, 0, NOW())")
   ->execute([$curYear]);
echo "  - Ensured admission_sequences & admission_id_reservations tables (year {$curYear}).\n";

// 2. Clean empties & resolve duplicates across admissions and students
foreach ([
    ['admissions', 'application_ref'],
    ['admissions', 'application_id'],
    ['admissions', 'roll_number'],
    ['admissions', 'exam_roll'],
    ['students', 'roll_number'],
    ['students', 'application_number'],
] as [$tbl, $col]) {
    $dedupeColumn($tbl, $col);
}

// Also ensure admissions <-> students exam_roll & roll_number stay synced for id=374 / student id=366
$syncCheck = $db->query("SELECT id, roll_number, exam_roll, student_id FROM admissions WHERE id = 374 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($syncCheck && !empty($syncCheck['roll_number']) && $syncCheck['exam_roll'] !== $syncCheck['roll_number']) {
    $db->prepare("UPDATE admissions SET exam_roll = ? WHERE id = 374")->execute([$syncCheck['roll_number']]);
}

// 3. Drop legacy non-unique indexes superseded by strict UNIQUE constraints
foreach ([
    ['admissions', 'idx_adm_app_id'],
    ['admissions', 'idx_adm_roll'],
    ['students', 'idx_students_roll'],
    ['students', 'idx_students_appno'],
] as [$tbl, $idx]) {
    if ($hasIndex($tbl, $idx)) {
        $db->exec("ALTER TABLE `{$tbl}` DROP INDEX `{$idx}`");
        echo "  - Dropped non-unique index {$tbl}.{$idx}\n";
    }
}

// 4. Add strict UNIQUE constraints where not already present
$uniqueSpecs = [
    ['admissions', 'application_ref',    'uniq_adm_application_ref'],
    ['admissions', 'application_id',     'uniq_adm_application_id'],
    ['admissions', 'roll_number',        'uniq_adm_roll_number'],
    ['admissions', 'exam_roll',          'uniq_adm_exam_roll'],
    ['students',   'roll_number',        'uniq_stu_roll_number'],
    ['students',   'application_number', 'uniq_stu_app_number'],
];

foreach ($uniqueSpecs as [$tbl, $col, $idxName]) {
    if (!$hasUniqueOnColumn($tbl, $col) && !$hasIndex($tbl, $idxName)) {
        $db->exec("ALTER TABLE `{$tbl}` ADD UNIQUE INDEX `{$idxName}` (`{$col}`)");
        echo "  - Added UNIQUE index {$idxName} on {$tbl}({$col})\n";
    } else {
        echo "  - Verified UNIQUE constraint on {$tbl}({$col})\n";
    }
}

echo "Migration complete.\n";
