<?php
/**
 * Global Utility and Security Functions
 */

require_once __DIR__ . '/database.php';

/**
 * Escape HTML output to prevent XSS
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build a cacheable asset URL versioned by file modification timestamp
 */
function rmu_asset_url(string $relativePath): string {
    $clean = ltrim($relativePath, '/');
    $fullPath = BASE_PATH . '/' . $clean;
    $ver = is_file($fullPath) ? (string)(@filemtime($fullPath) ?: '1') : '1';
    return APP_URL . '/' . $clean . '?v=' . rawurlencode($ver);
}

/**
 * Sanitize admin-authored HTML notice/news content to neutralize Stored XSS vectors
 * while preserving safe academic formatting markup.
 */
function sanitize_rich_html(?string $html): string {
    $raw = (string)$html;
    if (trim($raw) === '') {
        return '';
    }

    // Strip executable containers and their inner bodies first
    $cleaned = preg_replace('/<\s*(script|style|iframe|object|embed|svg|math|applet|form|template)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $raw);
    $cleaned = preg_replace('/<\s*(script|style|iframe|object|embed|svg|math|applet|form|template|base|meta|link)\b[^>]*\/?>/is', '', (string)$cleaned);

    if (class_exists('DOMDocument')) {
        $prevErr = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="__rmu_sanitize_root__">' . $cleaned . '</div>';
        if ($dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            $blockedTags = [
                'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
                'applet', 'form', 'input', 'button', 'textarea', 'select',
                'option', 'meta', 'link', 'base', 'template', 'frame', 'frameset'
            ];
            $xpath = new DOMXPath($dom);

            foreach ($blockedTags as $tag) {
                $nodes = $xpath->query('//' . $tag);
                if ($nodes) {
                    foreach (iterator_to_array($nodes) as $node) {
                        if ($node->parentNode) {
                            $node->parentNode->removeChild($node);
                        }
                    }
                }
            }

            $allElements = $xpath->query('//*');
            if ($allElements) {
                foreach ($allElements as $el) {
                    if (!$el instanceof DOMElement || !$el->hasAttributes()) {
                        continue;
                    }
                    $toRemove = [];
                    foreach (iterator_to_array($el->attributes) as $attr) {
                        $attrName = strtolower($attr->name);
                        $attrVal = trim($attr->value);
                        if (strpos($attrName, 'on') === 0 || in_array($attrName, ['srcdoc', 'formaction', 'xlink:href'], true)) {
                            $toRemove[] = $attr->name;
                            continue;
                        }
                        if (in_array($attrName, ['href', 'src', 'action', 'cite', 'poster', 'background'], true)) {
                            $decoded = html_entity_decode(preg_replace('/[\x00-\x20]+/', '', $attrVal), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            if (preg_match('/^(javascript|vbscript|data):/i', $decoded)) {
                                $toRemove[] = $attr->name;
                            }
                        } elseif ($attrName === 'style') {
                            $decodedStyle = html_entity_decode($attrVal, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            if (preg_match('/(expression\s*\(|javascript\s*:|vbscript\s*:|@import|behavior\s*:|-moz-binding)/i', $decodedStyle)) {
                                $toRemove[] = $attr->name;
                            }
                        }
                    }
                    foreach ($toRemove as $remAttr) {
                        $el->removeAttribute($remAttr);
                    }
                    if (strtolower($el->tagName) === 'a' && $el->hasAttribute('target') && strtolower($el->getAttribute('target')) === '_blank') {
                        $el->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            }

            $root = $dom->getElementById('__rmu_sanitize_root__');
            if ($root) {
                $out = '';
                foreach ($root->childNodes as $child) {
                    $out .= $dom->saveHTML($child);
                }
                libxml_clear_errors();
                libxml_use_internal_errors($prevErr);
                return $out;
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);
    }

    // Fallback conservative allowlist if DOM extension unavailable
    $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h3><h4><h5><h6><table><thead><tbody><tr><th><td><a><div><span><blockquote><hr>';
    $fallback = strip_tags((string)$cleaned, $allowed);
    $fallback = preg_replace('/\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string)$fallback);
    return (string)preg_replace('/\b(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):[^"\']*\2/i', '', (string)$fallback);
}

/**
 * Generate CSRF Token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render CSRF hidden input
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get Site Setting from Database
 */
function get_setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $db = getDb();
            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // fallback
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Convert string to URL slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Generate a unique URL slug for a given catalog table (`faculties`, `departments`, `programs`, etc.).
 */
function generate_unique_slug(PDO $db, string $table, string $text, int $excludeId = 0): string {
    $allowedTables = ['faculties', 'departments', 'programs', 'notices', 'events', 'news'];
    if (!in_array($table, $allowedTables, true)) {
        return slugify($text) . '-' . time();
    }
    $base = slugify($text);
    $candidate = $base;
    $suffix = 2;
    $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE slug = ? AND id != ?");
    while ($suffix <= 100) {
        $stmt->execute([$candidate, $excludeId]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

/**
 * Format date for university notices and events
 */
function format_date(string $date, string $format = 'M d, Y'): string {
    if (empty($date) || $date === '0000-00-00') return '';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}

/**
 * Flash messaging helpers
 */
function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function flash_has(string $type): bool {
    return isset($_SESSION['flash'][$type]);
}

/**
 * Authentication and Role Checks
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['user_name'] ?? '',
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'email'     => $_SESSION['user_email'] ?? ''
    ];
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_set('error', 'Please log in to access the administrative dashboard.');
        header("Location: " . APP_URL . "/admin/login.php");
        exit;
    }
}

function require_role(array $allowedRoles): void {
    require_login();
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        flash_set('error', 'Access denied. You do not possess the required administrative clearance.');
        header("Location: " . APP_URL . "/admin/dashboard.php");
        exit;
    }
}

function current_admin_role(): string {
    return (string)($_SESSION['user_role'] ?? '');
}

function is_admin_manager_role(?string $role = null): bool {
    $r = $role ?? current_admin_role();
    return in_array($r, ['superadmin', 'admin'], true);
}

function require_admin_manager_role(): void {
    require_role(['superadmin', 'admin']);
}

function get_pending_admissions_count(): int {
    try {
        $db = getDb();
        return (int)$db->query("SELECT COUNT(*) FROM admissions WHERE status IN ('Submitted','Pending','Under Review')")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Log Administrative Action
 */
function log_audit(string $action, string $details = '', ?int $overrideUserId = null): void {
    try {
        $db = getDb();
        $userId = $overrideUserId ?? ($_SESSION['user_id'] ?? null);
        if ($userId !== null) {
            $userId = (int)$userId;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

/**
 * Bangladeshi Mobile Number Validation
 * Accepts 11 digits starting with 013-019
 */
function validate_bd_mobile(string $mobile): bool {
    $cleaned = preg_replace('/[^0-9]/', '', $mobile);
    if (str_starts_with($cleaned, '8801')) {
        $cleaned = '01' . substr($cleaned, 4);
    }
    return (bool)preg_match('/^01[3-9]\d{8}$/', $cleaned);
}

/**
 * Normalize Bangladeshi Mobile Number
 */
function normalize_bd_mobile(string $mobile): string {
    $cleaned = preg_replace('/[^0-9]/', '', $mobile);
    if (str_starts_with($cleaned, '8801')) {
        $cleaned = '01' . substr($cleaned, 4);
    }
    return $cleaned;
}

/**
 * Validate GPA (Between 1.00 and 5.00)
 */
function validate_gpa($gpa): bool {
    if (!is_numeric($gpa)) return false;
    $val = (float)$gpa;
    return $val >= 1.00 && $val <= 5.00;
}

/**
 * Generate Sequential Application ID / Tracking Reference Number
 * Format: RMU-2026-0001
 */
function generate_application_ref(PDO $db): string {
    ensure_admission_id_generation_schema($db);
    $year = (int)date('Y');
    if ($db->inTransaction()) {
        return allocate_next_application_ref_locked($db, $year);
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $db->beginTransaction();
            $ref = allocate_next_application_ref_locked($db, $year);
            $db->commit();
            return $ref;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!is_retryable_db_concurrency_exception($e) || $attempt === 4) {
                throw $e;
            }
            usleep(25000);
        }
    }
    return format_sequential_application_id($year, 1);
}

/**
 * Fetch Faculties with their Departments for Menus
 */
function get_faculties_menu(): array {
    static $tree = null;
    if ($tree !== null) return $tree;
    $tree = [];
    try {
        $db = getDb();
        $faculties = $db->query("SELECT id, slug, name_en, name_bn FROM faculties WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
        $deptStmt = $db->prepare("SELECT id, faculty_id, slug, name_en, name_bn, code FROM departments WHERE faculty_id = ? AND is_active = 1 ORDER BY sort_order ASC");
        foreach ($faculties as $f) {
            $deptStmt->execute([$f['id']]);
            $depts = $deptStmt->fetchAll();
            $tree[] = [
                'faculty'     => $f,
                'departments' => $depts
            ];
        }
    } catch (Exception $e) {
        // fallback empty
    }
    return $tree;
}

/**
 * Truncate words with ellipsis
 */
function truncate_words(string $text, int $limit = 25): string {
    $clean = strip_tags($text);
    $words = explode(' ', $clean);
    if (count($words) > $limit) {
        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
    return $clean;
}

/**
 * Fetch Categorized Academic Programs for Navigation Menu Dropdown
 */
function get_programs_menu(): array {
    static $menu = null;
    if ($menu !== null) return $menu;
    $menu = ['undergraduate' => [], 'postgraduate' => []];
    try {
        $db = getDb();
        $stmt = $db->query("SELECT id, slug, title_en, degree_level, duration FROM programs WHERE is_active = 1 ORDER BY FIELD(degree_level, 'Undergraduate', 'Postgraduate'), sort_order ASC");
        while ($row = $stmt->fetch()) {
            if ($row['degree_level'] === 'Undergraduate') {
                $menu['undergraduate'][] = $row;
            } else {
                $menu['postgraduate'][] = $row;
            }
        }
    } catch (Exception $e) {
        // fallback empty
    }
    return $menu;
}

/**
 * Official Academic Programs & 4-Year Subject Curriculums
 */
function get_all_programs_curriculum(): array {
    static $curriculums = null;
    if ($curriculums !== null) return $curriculums;

    $curriculums = [
        'ba-in-bangla' => [
            'id' => 1,
            'title' => 'BA (Honours) in Bangla',
            'slug' => 'ba-in-bangla',
            'department' => 'Department of Bangla',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'BAN 101', 'name' => 'History of Bangla Literature (Ancient & Medieval Periods)'],
                    ['code' => 'BAN 102', 'name' => 'Introduction to Bangla Linguistics, Phonetics & Grammar'],
                    ['code' => 'BAN 103', 'name' => 'Bangla Folklore, Heritage & Oral Literature'],
                    ['code' => 'BAN 104', 'name' => 'Bangla Prose Composition, Rhetoric & Prosody']
                ],
                '2nd Year' => [
                    ['code' => 'BAN 201', 'name' => 'Modern Bangla Literature (1801–1947: Renaissance to Partition)'],
                    ['code' => 'BAN 202', 'name' => '19th & 20th Century Bangla Poetry (Madhusudan to Jibanananda)'],
                    ['code' => 'BAN 203', 'name' => 'Evolution of Bangla Drama & Theatre Practices'],
                    ['code' => 'BAN 204', 'name' => 'Modern Bangla Prose, Essays & Periodicals']
                ],
                '3rd Year' => [
                    ['code' => 'BAN 301', 'name' => 'Rabindra Literature: Novels, Short Stories & Philosophy'],
                    ['code' => 'BAN 302', 'name' => 'Kazi Nazrul Islam: Rebel Aesthetics, Poetry & Music'],
                    ['code' => 'BAN 303', 'name' => 'Eastern & Western Literary Criticism and Aesthetics'],
                    ['code' => 'BAN 304', 'name' => 'Bangla Fiction & Modern Short Story Traditions']
                ],
                '4th Year' => [
                    ['code' => 'BAN 401', 'name' => 'Literature of Bangladesh Liberation War (1971) & Post-Independence Era'],
                    ['code' => 'BAN 402', 'name' => 'South Asian & Comparative Literature in Translation'],
                    ['code' => 'BAN 403', 'name' => 'Contemporary Bangla Fiction & Modern Poetry'],
                    ['code' => 'BAN 404', 'name' => 'Research Methodology, Fieldwork & Academic Monograph']
                ]
            ]
        ],
        'ba-in-english' => [
            'id' => 2,
            'title' => 'BA (Honours) in English',
            'slug' => 'ba-in-english',
            'department' => 'Department of English',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ENG 101', 'name' => 'Introduction to English Poetry & Versification'],
                    ['code' => 'ENG 102', 'name' => 'Introduction to Prose & Non-Fictional Writing'],
                    ['code' => 'ENG 103', 'name' => 'Fundamentals of English Drama & Performance'],
                    ['code' => 'ENG 104', 'name' => 'Advanced English Language Skills, Phonetics & Composition']
                ],
                '2nd Year' => [
                    ['code' => 'ENG 201', 'name' => '16th & 17th Century English Poetry and Drama (Shakespeare & Marlowe)'],
                    ['code' => 'ENG 202', 'name' => '18th Century English Literature, Prose & Satire'],
                    ['code' => 'ENG 203', 'name' => 'Romantic Poetry (Wordsworth, Coleridge, Keats, Shelley, Byron)'],
                    ['code' => 'ENG 204', 'name' => 'Introduction to English Linguistics & Applied Syntax']
                ],
                '3rd Year' => [
                    ['code' => 'ENG 301', 'name' => 'Victorian Poetry and the Rise of the Victorian Novel'],
                    ['code' => 'ENG 302', 'name' => '20th Century Modernist Poetry and Fiction (Yeats, Eliot, Woolf, Joyce)'],
                    ['code' => 'ENG 303', 'name' => 'American Literature: Puritans to Postmodernists'],
                    ['code' => 'ENG 304', 'name' => 'History of Western Literary Theory & Classical Criticism']
                ],
                '4th Year' => [
                    ['code' => 'ENG 401', 'name' => 'Modern & Contemporary Critical Theory (Post-Structuralism to Cultural Studies)'],
                    ['code' => 'ENG 402', 'name' => 'Postcolonial Literatures in English (Africa, Caribbean, South Asia)'],
                    ['code' => 'ENG 403', 'name' => 'English Language Teaching (ELT) Methodology & Testing'],
                    ['code' => 'ENG 404', 'name' => 'Shakespeare: Selected Tragedies, Comedies & Critical Perspectives'],
                    ['code' => 'ENG 405', 'name' => 'Senior Seminar & Research Monograph']
                ]
            ]
        ],
        'ba-in-music' => [
            'id' => 3,
            'title' => 'BA (Honours) in Music',
            'slug' => 'ba-in-music',
            'department' => 'Department of Music',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'MUS 101', 'name' => 'Fundamentals of Classical Ragas, Swaras & Tala Systems'],
                    ['code' => 'MUS 102', 'name' => 'Rabindra Sangeet: Puja, Prem, Swadesh & Prakriti Stages'],
                    ['code' => 'MUS 103', 'name' => 'Folk Music Traditions of Greater Bengal (Baul, Bhatiali, Bhawaiya)'],
                    ['code' => 'MUS 104', 'name' => 'Voice Culture, Riyaz Techniques & Pitch Precision (Practical)']
                ],
                '2nd Year' => [
                    ['code' => 'MUS 201', 'name' => 'Khayal & Dhrupad Vocal Forms: Bandish & Improvised Alap'],
                    ['code' => 'MUS 202', 'name' => 'Nazrul Sangeet: Classical, Ragpradhan, Ghazal & Mass Songs'],
                    ['code' => 'MUS 203', 'name' => 'Bengali Musical Notation (Akarmatrik System) & History of Music'],
                    ['code' => 'MUS 204', 'name' => 'Traditional Folk Instruments: Harmonium, Tanpura, Tabla & Dotara']
                ],
                '3rd Year' => [
                    ['code' => 'MUS 301', 'name' => 'Advanced Ragas: Thumri, Dadra & Semi-Classical Expressions'],
                    ['code' => 'MUS 302', 'name' => 'Tagore Musical Dance Dramas (Chitrangada, Chandalika, Shyama)'],
                    ['code' => 'MUS 303', 'name' => 'Mystical Traditions: Lalon Shai, Hason Raja & Shah Abdul Karim'],
                    ['code' => 'MUS 304', 'name' => 'Composition Techniques, Western Harmony & World Music Traditions']
                ],
                '4th Year' => [
                    ['code' => 'MUS 401', 'name' => 'Modern Bengali Songs (Adhunik Gaan) & Recording Studio Artistry'],
                    ['code' => 'MUS 402', 'name' => 'Musicology, Aesthetics, Philosophy & Acoustics of Sound'],
                    ['code' => 'MUS 403', 'name' => 'Applied Music Technology & Digital Audio Workstations (DAW)'],
                    ['code' => 'MUS 404', 'name' => 'Graduation Solo Recital & Research Dissertation']
                ]
            ]
        ],
        'ba-in-fine-arts' => [
            'id' => 4,
            'title' => 'BFA (Honours) in Fine Arts',
            'slug' => 'ba-in-fine-arts',
            'department' => 'Department of Fine Arts',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ART 101', 'name' => 'Fundamentals of Drawing, Object Study & Perspective Anatomy'],
                    ['code' => 'ART 102', 'name' => 'Color Theory, Chromatic Relationships & 2D Composition'],
                    ['code' => 'ART 103', 'name' => '3D Form, Spatial Structure & Clay Modeling Basics'],
                    ['code' => 'ART 104', 'name' => 'History of Eastern, Bengal & Ancient Asian Art Traditions']
                ],
                '2nd Year' => [
                    ['code' => 'ART 201', 'name' => 'Figurative & Portrait Drawing from Live Models'],
                    ['code' => 'ART 202', 'name' => 'Oil & Acrylic Painting Techniques: Still Life & Landscape'],
                    ['code' => 'ART 203', 'name' => 'Printmaking: Relief, Woodcut & Linocut Studio Workshop'],
                    ['code' => 'ART 204', 'name' => 'History of Western Art: Classical Antiquity to Impressionism']
                ],
                '3rd Year' => [
                    ['code' => 'ART 301', 'name' => 'Advanced Painting: Experimental Composition & Color Field'],
                    ['code' => 'ART 302', 'name' => 'Printmaking: Intaglio, Etching, Aquatint & Silk Screen Printing'],
                    ['code' => 'ART 303', 'name' => 'Sculpture Studio: Metal Fabrication, Terracotta & Wood Carving'],
                    ['code' => 'ART 304', 'name' => 'Modern Art Movements: Cubism, Surrealism & Abstract Expressionism']
                ],
                '4th Year' => [
                    ['code' => 'ART 401', 'name' => 'Contemporary Art Practices, Conceptual Art & Public Installations'],
                    ['code' => 'ART 402', 'name' => 'Curatorial Studies, Gallery Administration & Exhibition Design'],
                    ['code' => 'ART 403', 'name' => 'South Asian Contemporary Visual Culture & Digital Media Art'],
                    ['code' => 'ART 404', 'name' => 'Senior Studio Thesis, Graduation Exhibition & Artist Portfolio']
                ]
            ]
        ],
        'bba' => [
            'id' => 5,
            'title' => 'BBA (Honours) in Business Administration',
            'slug' => 'bba',
            'department' => 'Department of Business Administration',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'BBA 101', 'name' => 'Introduction to Modern Business & Organizations'],
                    ['code' => 'BBA 102', 'name' => 'Principles of Financial Accounting I & II'],
                    ['code' => 'BBA 103', 'name' => 'Microeconomics & Macroeconomic Principles'],
                    ['code' => 'BBA 104', 'name' => 'Business Mathematics & Commercial Computing Applications']
                ],
                '2nd Year' => [
                    ['code' => 'BBA 201', 'name' => 'Principles of Marketing & Consumer Behavior Analysis'],
                    ['code' => 'BBA 202', 'name' => 'Principles of Finance & Working Capital Management'],
                    ['code' => 'BBA 203', 'name' => 'Organizational Behavior & Human Resource Management'],
                    ['code' => 'BBA 204', 'name' => 'Business Statistics & Decision Analysis'],
                    ['code' => 'BBA 205', 'name' => 'Business Law, Contract Acts & Corporate Regulations']
                ],
                '3rd Year' => [
                    ['code' => 'BBA 301', 'name' => 'Managerial Accounting, Cost Estimation & Budgeting'],
                    ['code' => 'BBA 302', 'name' => 'Financial Markets, Banking Institutions & Credit Appraisal'],
                    ['code' => 'BBA 303', 'name' => 'Strategic Marketing, Brand Management & Supply Chain Logistics'],
                    ['code' => 'BBA 304', 'name' => 'Management Information Systems (MIS) & E-Commerce'],
                    ['code' => 'BBA 305', 'name' => 'Entrepreneurship, Innovation & Startup Incubation']
                ],
                '4th Year' => [
                    ['code' => 'BBA 401', 'name' => 'Strategic Corporate Management, Business Policy & Simulation'],
                    ['code' => 'BBA 402', 'name' => 'International Business Management & Global Trade'],
                    ['code' => 'BBA 403', 'name' => 'Business Ethics, CSR & Sustainable Business Practices'],
                    ['code' => 'BBA 404', 'name' => 'Digital Marketing Analytics, SEO & Social Media Campaigns'],
                    ['code' => 'BBA 405', 'name' => 'Capstone Internship Practicum, Research Monograph & Comprehensive Viva']
                ]
            ]
        ],
        'bsc-in-agriculture' => [
            'id' => 6,
            'title' => 'BSc (Honours) in Agriculture',
            'slug' => 'bsc-in-agriculture',
            'department' => 'Department of Agriculture',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'AGR 101', 'name' => 'Fundamentals of Agronomy & Crop Production Science'],
                    ['code' => 'AGR 102', 'name' => 'Agricultural Chemistry & Soil Science Foundations'],
                    ['code' => 'AGR 103', 'name' => 'Agricultural Botany: Morphology, Anatomy & Taxonomy'],
                    ['code' => 'AGR 104', 'name' => 'Principles of Horticulture & Nursery Management'],
                    ['code' => 'AGR 105', 'name' => 'Introduction to Agricultural Entomology & Beneficial Insects']
                ],
                '2nd Year' => [
                    ['code' => 'AGR 201', 'name' => 'Genetics, Cytology & Plant Breeding Fundamentals'],
                    ['code' => 'AGR 202', 'name' => 'Soil Fertility, Plant Nutrition & Fertilizer Technology'],
                    ['code' => 'AGR 203', 'name' => 'Plant Pathology & Crop Disease Epidemiology'],
                    ['code' => 'AGR 204', 'name' => 'Farm Machinery, Irrigation Systems & Drainage Engineering'],
                    ['code' => 'AGR 205', 'name' => 'Agricultural Microbiology, Bio-fertilizers & Composting']
                ],
                '3rd Year' => [
                    ['code' => 'AGR 301', 'name' => 'Agronomy of Major Field Crops (Cereals, Pulses, Oilseeds & Fiber)'],
                    ['code' => 'AGR 302', 'name' => 'Commercial Olericulture, Pomology & Floriculture'],
                    ['code' => 'AGR 303', 'name' => 'Economic Crop Entomology & Pesticide Toxicology'],
                    ['code' => 'AGR 304', 'name' => 'Agricultural Extension Education & Participatory Rural Appraisal'],
                    ['code' => 'AGR 305', 'name' => 'Post-Harvest Handling, Processing & Cold Storage Technology']
                ],
                '4th Year' => [
                    ['code' => 'AGR 401', 'name' => 'Agribusiness Management, Supply Chain Logistics & Agricultural Marketing'],
                    ['code' => 'AGR 402', 'name' => 'Agricultural Biotechnology, Tissue Culture & Molecular Breeding'],
                    ['code' => 'AGR 403', 'name' => 'Integrated Pest Management (IPM) & Biological Controls'],
                    ['code' => 'AGR 404', 'name' => 'Climate-Resilient Agriculture, Agroforestry & Environmental Sustainability'],
                    ['code' => 'AGR 405', 'name' => 'Field Practicum, Agricultural Extension Camp & Research Thesis']
                ]
            ]
        ],
        'bsc-in-cse' => [
            'id' => 7,
            'title' => 'BSc in Computer Science and Engineering (CSE)',
            'slug' => 'bsc-in-cse',
            'department' => 'Department of Computer Science and Engineering',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'CSE 101', 'name' => 'Structured Programming Language (C / C++) with Lab'],
                    ['code' => 'CSE 102', 'name' => 'Discrete Mathematics & Graph Theory'],
                    ['code' => 'CSE 103', 'name' => 'Electrical Circuits & Basic Electronics with Lab'],
                    ['code' => 'CSE 104', 'name' => 'Differential Calculus, Integral Calculus & Analytical Geometry'],
                    ['code' => 'CSE 105', 'name' => 'Physics: Waves, Electromagnetism & Modern Physics']
                ],
                '2nd Year' => [
                    ['code' => 'CSE 201', 'name' => 'Data Structures with Lab (Linear & Non-Linear Structures)'],
                    ['code' => 'CSE 202', 'name' => 'Object-Oriented Programming (Java / Python) with Lab'],
                    ['code' => 'CSE 203', 'name' => 'Digital Logic Design & Circuit Synthesis with Lab'],
                    ['code' => 'CSE 204', 'name' => 'Database Management Systems (DBMS) & SQL with Lab'],
                    ['code' => 'CSE 205', 'name' => 'Design & Analysis of Algorithms with Lab'],
                    ['code' => 'CSE 206', 'name' => 'Linear Algebra, Matrices & Differential Equations']
                ],
                '3rd Year' => [
                    ['code' => 'CSE 301', 'name' => 'Operating Systems & System Programming with Lab'],
                    ['code' => 'CSE 302', 'name' => 'Microprocessors, Microcontrollers & Embedded Systems with Lab'],
                    ['code' => 'CSE 303', 'name' => 'Computer Networks, TCP/IP Protocols & Socket Programming with Lab'],
                    ['code' => 'CSE 304', 'name' => 'Software Engineering, Design Patterns & Agile Methodologies'],
                    ['code' => 'CSE 305', 'name' => 'Theory of Computation, Formal Languages & Automata'],
                    ['code' => 'CSE 306', 'name' => 'Web Technologies & Full-Stack Application Development with Lab']
                ],
                '4th Year' => [
                    ['code' => 'CSE 401', 'name' => 'Artificial Intelligence & Machine Learning with Lab'],
                    ['code' => 'CSE 402', 'name' => 'Compiler Design, Syntax Analysis & Code Generation with Lab'],
                    ['code' => 'CSE 403', 'name' => 'Network Security, Cryptography & Cyber Defense Systems'],
                    ['code' => 'CSE 404', 'name' => 'Cloud Computing, Virtualization & Distributed Architectures'],
                    ['code' => 'CSE 405', 'name' => 'Senior Capstone Project / Undergraduate Research Thesis (Phase I & II)']
                ]
            ]
        ],
        'bsc-in-ict' => [
            'id' => 8,
            'title' => 'BSc in Information and Communication Technology (ICT)',
            'slug' => 'bsc-in-ict',
            'department' => 'Department of Information and Communication Technology',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ICT 101', 'name' => 'Introduction to Information and Communication Technology'],
                    ['code' => 'ICT 102', 'name' => 'Structured Computer Programming with C/C++ & Lab'],
                    ['code' => 'ICT 103', 'name' => 'Basic Electrical & Electronic Engineering with Lab'],
                    ['code' => 'ICT 104', 'name' => 'Engineering Mathematics (Differential Calculus & Linear Algebra)'],
                    ['code' => 'ICT 105', 'name' => 'Applied Physics for Information & Communication Systems']
                ],
                '2nd Year' => [
                    ['code' => 'ICT 201', 'name' => 'Data Structures & Algorithmic Problem Solving with Lab'],
                    ['code' => 'ICT 202', 'name' => 'Object Oriented Design & Dynamic Web Application Development with Lab'],
                    ['code' => 'ICT 203', 'name' => 'Digital Electronics, Logic Design & Pulse Circuits with Lab'],
                    ['code' => 'ICT 204', 'name' => 'Relational Database Management Systems & Architecture with Lab'],
                    ['code' => 'ICT 205', 'name' => 'Signals, Linear Systems & Fourier Transforms']
                ],
                '3rd Year' => [
                    ['code' => 'ICT 301', 'name' => 'Data Communications & Modern Computer Networks with Lab'],
                    ['code' => 'ICT 302', 'name' => 'Telecommunication Engineering, Switching & Signaling Systems'],
                    ['code' => 'ICT 303', 'name' => 'Wireless & Mobile Cellular Communications (4G/5G Architectures)'],
                    ['code' => 'ICT 304', 'name' => 'Internet of Things (IoT), Sensor Networks & Microcontrollers with Lab'],
                    ['code' => 'ICT 305', 'name' => 'Information System Security, Cryptographic Protocols & Network Defense']
                ],
                '4th Year' => [
                    ['code' => 'ICT 401', 'name' => 'Optical Fiber Communication Systems, Photonics & Microwave Engineering with Lab'],
                    ['code' => 'ICT 402', 'name' => 'Cloud Infrastructure, Server Virtualization & DevOps Automation'],
                    ['code' => 'ICT 403', 'name' => 'Applied Artificial Intelligence, Data Mining & Big Data Analytics with Lab'],
                    ['code' => 'ICT 404', 'name' => 'Cyber Law, ICT Ethics, Digital Forensics & Intellectual Property'],
                    ['code' => 'ICT 405', 'name' => 'Senior Capstone Design Project / Engineering Thesis & Comprehensive Defense']
                ]
            ]
        ],
        'bsc-in-eee' => [
            'id' => 9,
            'title' => 'Electrical and Electronic Engineering (EEE)',
            'slug' => 'bsc-in-eee',
            'department' => 'Department of Electrical and Electronic Engineering',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'EEE 101', 'name' => 'Electrical Circuits I & Circuit Simulation Lab'],
                    ['code' => 'EEE 102', 'name' => 'Structured C Programming for Engineers with Lab'],
                    ['code' => 'EEE 103', 'name' => 'Engineering Physics (Electromagnetism & Waves)'],
                    ['code' => 'EEE 104', 'name' => 'Differential Calculus, Coordinate Geometry & Differential Equations']
                ],
                '2nd Year' => [
                    ['code' => 'EEE 201', 'name' => 'Electrical Circuits II & Network Analysis with Lab'],
                    ['code' => 'EEE 202', 'name' => 'Analog Electronics I & Semiconductor Devices with Lab'],
                    ['code' => 'EEE 203', 'name' => 'Digital Electronics & Logic Design with Lab'],
                    ['code' => 'EEE 204', 'name' => 'Signals and Linear Systems Analysis'],
                    ['code' => 'EEE 205', 'name' => 'Engineering Mathematics (Complex Variables & Linear Algebra)']
                ],
                '3rd Year' => [
                    ['code' => 'EEE 301', 'name' => 'Electromagnetic Fields and Waves'],
                    ['code' => 'EEE 302', 'name' => 'Electrical Machines I & II (Transformers & AC/DC Motors) with Lab'],
                    ['code' => 'EEE 303', 'name' => 'Microprocessor Systems & Interfacing (Intel 8086 & ARM) with Lab'],
                    ['code' => 'EEE 304', 'name' => 'Power System Analysis & Transmission Engineering with Lab'],
                    ['code' => 'EEE 305', 'name' => 'Control Systems Engineering & State Variable Analysis']
                ],
                '4th Year' => [
                    ['code' => 'EEE 401', 'name' => 'Power Electronics & Industrial Drives with Lab'],
                    ['code' => 'EEE 402', 'name' => 'Telecommunication Engineering & Digital Signal Processing (DSP)'],
                    ['code' => 'EEE 403', 'name' => 'Renewable Energy Technologies & Solar Photovoltaic Systems'],
                    ['code' => 'EEE 404', 'name' => 'Power Plant Engineering & High Voltage Protection'],
                    ['code' => 'EEE 405', 'name' => 'Capstone Senior Engineering Project / Research Thesis (Parts I & II)']
                ]
            ]
        ],
        'bsc-in-microbiology' => [
            'id' => 10,
            'title' => 'Microbiology — BSc (Honours)',
            'slug' => 'bsc-in-microbiology',
            'department' => 'Department of Microbiology',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'MIC 101', 'name' => 'Fundamentals of General Microbiology & Laboratory Techniques'],
                    ['code' => 'MIC 102', 'name' => 'Microbial Chemistry, Biomolecules & Enzymology'],
                    ['code' => 'MIC 103', 'name' => 'Basic Cell Biology, Microscopy & Staining Methods'],
                    ['code' => 'MIC 104', 'name' => 'Biostatistics & Applied Mathematics for Life Sciences']
                ],
                '2nd Year' => [
                    ['code' => 'MIC 201', 'name' => 'Microbial Genetics & Molecular Biology Foundations'],
                    ['code' => 'MIC 202', 'name' => 'Bacteriology & Systematics of Prokaryotes with Lab'],
                    ['code' => 'MIC 203', 'name' => 'Virology: Plant, Animal & Bacteriophage Biology'],
                    ['code' => 'MIC 204', 'name' => 'Mycology & Phycology (Fungi and Algae)'],
                    ['code' => 'MIC 205', 'name' => 'Microbial Ecology & Environmental Microbiology']
                ],
                '3rd Year' => [
                    ['code' => 'MIC 301', 'name' => 'Immunology & Immunotechnology with Practical'],
                    ['code' => 'MIC 302', 'name' => 'Medical Microbiology & Pathogenic Mechanisms'],
                    ['code' => 'MIC 303', 'name' => 'Food & Dairy Microbiology, Quality Control Standards'],
                    ['code' => 'MIC 304', 'name' => 'Industrial Microbiology & Fermentation Biotechnology'],
                    ['code' => 'MIC 305', 'name' => 'Pharmaceutical Microbiology & Antimicrobial Chemotherapy']
                ],
                '4th Year' => [
                    ['code' => 'MIC 401', 'name' => 'Clinical Microbiology, Diagnostic Pathology & Infection Control'],
                    ['code' => 'MIC 402', 'name' => 'Recombinant DNA Technology & Genetic Engineering with Lab'],
                    ['code' => 'MIC 403', 'name' => 'Genomics, Proteomics & Bioinformatics in Microbiology'],
                    ['code' => 'MIC 404', 'name' => 'Biosafety, Bioethics & Research Quality Management'],
                    ['code' => 'MIC 405', 'name' => 'Undergraduate Research Project & Laboratory Dissertation']
                ]
            ]
        ],
        'physical-education-sports-science' => [
            'id' => 11,
            'title' => 'Physical Education and Sports Science',
            'slug' => 'physical-education-sports-science',
            'department' => 'Department of Physical Education and Sports Science',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'PES 101', 'name' => 'History and Principles of Physical Education and Sports'],
                    ['code' => 'PES 102', 'name' => 'Human Anatomy and Physiology in Exercise'],
                    ['code' => 'PES 103', 'name' => 'Foundations of Track and Field Athletics (Practical)'],
                    ['code' => 'PES 104', 'name' => 'Health Education, First Aid & Safety Measures']
                ],
                '2nd Year' => [
                    ['code' => 'PES 201', 'name' => 'Kinesiology and Biomechanics of Human Movement'],
                    ['code' => 'PES 202', 'name' => 'Sports Psychology and Motor Learning'],
                    ['code' => 'PES 203', 'name' => 'Team Sports I: Football, Cricket & Volleyball (Rules & Officiating)'],
                    ['code' => 'PES 204', 'name' => 'Gymnastics, Calisthenics & Physical Fitness Conditioning']
                ],
                '3rd Year' => [
                    ['code' => 'PES 301', 'name' => 'Exercise Physiology, Metabolic Testing & Fitness Assessment'],
                    ['code' => 'PES 302', 'name' => 'Sports Nutrition, Dietetics & Athletic Weight Management'],
                    ['code' => 'PES 303', 'name' => 'Team Sports II: Basketball, Handball, Badminton & Swimming'],
                    ['code' => 'PES 304', 'name' => 'Sports Pedagogy, Curriculum Design & Teaching Methods'],
                    ['code' => 'PES 305', 'name' => 'Prevention and Management of Sports Injuries & Sports Massage']
                ],
                '4th Year' => [
                    ['code' => 'PES 401', 'name' => 'Sports Training Methodology & Periodization'],
                    ['code' => 'PES 402', 'name' => 'Sports Management, Administration & Tournament Organization'],
                    ['code' => 'PES 403', 'name' => 'Adapted Physical Education for Specially Abled Athletes'],
                    ['code' => 'PES 404', 'name' => 'Measurement, Evaluation & Statistics in Physical Education'],
                    ['code' => 'PES 405', 'name' => 'Coaching Practicum, Field Internship & Undergraduate Thesis']
                ]
            ]
        ]
    ];

    return $curriculums;
}

/**
 * Get Curriculum for a specific program slug
 */
function get_program_curriculum_by_slug(string $slug): ?array {
    $all = get_all_programs_curriculum();
    return $all[$slug] ?? null;
}

/**
 * ========================================================
 * STUDENT PORTAL HELPER FUNCTIONS
 * ========================================================
 */

function clear_student_session(): void {
    unset(
        $_SESSION['student_id'],
        $_SESSION['student_pk_id'],
        $_SESSION['student_name'],
        $_SESSION['student_email'],
        $_SESSION['student_session_version']
    );
}

function is_student_logged_in(): bool {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']) && !empty($_SESSION['student_pk_id']);
}

function get_logged_in_student(): ?array {
    if (!is_student_logged_in()) {
        return null;
    }
    $pkId = (int)($_SESSION['student_pk_id'] ?? 0);
    if ($pkId <= 0) {
        clear_student_session();
        return null;
    }
    $db = getDb();
    $stmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.slug AS program_slug, p.degree_level,
               p.degree_type AS prog_degree_type, p.duration AS prog_duration,
               p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters,
               d.name_en AS department_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$pkId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        clear_student_session();
        return null;
    }

    // Strictly block Suspended, Deactivated, and Deleted student accounts & destroy any active session
    if (in_array($student['status'], ['Suspended', 'Deactivated', 'Deleted'], true)) {
        if ($student['status'] === 'Deactivated') {
            $_SESSION['student_auth_error'] = 'Your student portal account has been deactivated by university administration. Please contact the Registrar / Controller of Examinations.';
        } elseif ($student['status'] === 'Deleted') {
            $_SESSION['student_auth_error'] = 'This student portal account has been permanently deleted and access has been revoked.';
        } else {
            $_SESSION['student_auth_error'] = 'Your student portal account is currently suspended. Contact administration.';
        }
        clear_student_session();
        return null;
    }

    // Immediate session invalidation check when session_version was tracked on login
    if (isset($_SESSION['student_session_version']) && (int)$_SESSION['student_session_version'] !== (int)($student['session_version'] ?? 1)) {
        $_SESSION['student_auth_error'] = 'Your portal session was invalidated due to an administrative account security update. Please sign in again.';
        clear_student_session();
        return null;
    }

    return $student;
}

function require_student_login(): void {
    $student = get_logged_in_student();
    if (!$student) {
        $msg = $_SESSION['student_auth_error'] ?? 'Please log in with your Student ID and Password to access the Student Portal.';
        unset($_SESSION['student_auth_error']);
        flash_set('error', $msg);
        header('Location: ' . APP_URL . '/student/login.php');
        exit;
    }
}

function get_semester_ordinal_title(int $semNumber): string {
    if ($semNumber <= 0) {
        return "Semester {$semNumber}";
    }
    $mod100 = $semNumber % 100;
    if ($mod100 >= 11 && $mod100 <= 13) {
        $suffix = 'th';
    } else {
        switch ($semNumber % 10) {
            case 1:  $suffix = 'st'; break;
            case 2:  $suffix = 'nd'; break;
            case 3:  $suffix = 'rd'; break;
            default: $suffix = 'th'; break;
        }
    }
    return "{$semNumber}{$suffix} Semester";
}

function get_semester_number(string $name): int {
    if (preg_match('/\b(1[0-2]|[1-9])(?:st|nd|rd|th)?\s*Semester\b/i', $name, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/\bSemester\s*(1[0-2]|[1-9])\b/i', $name, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/\b(1[0-2]|[1-9])\b/', $name, $m)) {
        return (int)$m[1];
    }
    return 99;
}

/**
 * Calculate UGC Uniform Letter Grade, Grade Point, and Result Status from Marks (out of 100)
 */
function get_configured_grading_rules(): array {
    static $cachedRules = null;
    if ($cachedRules !== null) {
        return $cachedRules;
    }
    try {
        $db = getDb();
        $stmt = $db->query("SELECT * FROM grading_rules WHERE is_active = 1 ORDER BY sort_order ASC, min_marks DESC");
        $cachedRules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $cachedRules = [];
    }
    return $cachedRules ?: [];
}

function calculate_grade_from_marks(float $marks, float $fullMarks = 100.0): array {
    if ($fullMarks <= 0.0) {
        $fullMarks = 100.0;
    }
    $percentage = ($marks / $fullMarks) * 100.0;
    $percentage = max(0.00, min(100.00, round($percentage, 4)));
    $rules = get_configured_grading_rules();
    if (!empty($rules)) {
        foreach ($rules as $r) {
            $min = (float)$r['min_marks'];
            $max = (float)$r['max_marks'];
            if ($percentage >= $min && ($percentage <= $max || ($max >= 99.0 && $percentage <= 100.0))) {
                $status = ($r['letter_grade'] === 'F' || (float)$r['grade_point'] <= 0.0) ? 'Fail' : 'Pass';
                return [
                    'letter_grade' => $r['letter_grade'],
                    'grade_point'  => (float)$r['grade_point'],
                    'status'       => $status,
                    'remarks'      => $r['remarks'] ?? ($status === 'Pass' ? 'Pass' : 'Fail'),
                    'percentage'   => round($percentage, 2),
                ];
            }
        }
    }

    if ($percentage >= 80.00) {
        return ['letter_grade' => 'A+', 'grade_point' => 4.00, 'status' => 'Pass', 'remarks' => 'Outstanding', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 75.00) {
        return ['letter_grade' => 'A',  'grade_point' => 3.75, 'status' => 'Pass', 'remarks' => 'Excellent', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 70.00) {
        return ['letter_grade' => 'A-', 'grade_point' => 3.50, 'status' => 'Pass', 'remarks' => 'Very Good', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 65.00) {
        return ['letter_grade' => 'B+', 'grade_point' => 3.25, 'status' => 'Pass', 'remarks' => 'Good', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 60.00) {
        return ['letter_grade' => 'B',  'grade_point' => 3.00, 'status' => 'Pass', 'remarks' => 'Satisfactory', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 55.00) {
        return ['letter_grade' => 'B-', 'grade_point' => 2.75, 'status' => 'Pass', 'remarks' => 'Above Average', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 50.00) {
        return ['letter_grade' => 'C+', 'grade_point' => 2.50, 'status' => 'Pass', 'remarks' => 'Average', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 45.00) {
        return ['letter_grade' => 'C',  'grade_point' => 2.25, 'status' => 'Pass', 'remarks' => 'Pass', 'percentage' => round($percentage, 2)];
    } elseif ($percentage >= 40.00) {
        return ['letter_grade' => 'D',  'grade_point' => 2.00, 'status' => 'Pass', 'remarks' => 'Conditional Pass', 'percentage' => round($percentage, 2)];
    } else {
        return ['letter_grade' => 'F',  'grade_point' => 0.00, 'status' => 'Fail', 'remarks' => 'Fail', 'percentage' => round($percentage, 2)];
    }
}

/**
 * Recalculate credit-weighted CGPA & completed credits for a student and sync with database
 */
function recalculate_student_cgpa(int $studentId): array {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_courses,
            SUM(credit) AS total_registered_credits,
            SUM(CASE WHEN grade_point >= 2.00 THEN credit ELSE 0 END) AS total_earned_credits,
            SUM(credit * grade_point) AS total_grade_points
        FROM student_results
        WHERE student_id = ? AND is_published = 1
    ");
    $stmt->execute([$studentId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalCourses = (int)($stats['total_courses'] ?? 0);
    $totalRegCredits = (float)($stats['total_registered_credits'] ?? 0);
    $totalEarnedCredits = (float)($stats['total_earned_credits'] ?? 0);
    $totalGradePoints = (float)($stats['total_grade_points'] ?? 0);

    $cgpa = ($totalCourses > 0 && $totalRegCredits > 0)
        ? round($totalGradePoints / $totalRegCredits, 2)
        : 0.00;

    $upd = $db->prepare("UPDATE students SET cgpa = ?, credits_completed = ? WHERE id = ?");
    $upd->execute([$cgpa, (int)$totalEarnedCredits, $studentId]);

    if (function_exists('sync_student_semester_results')) {
        sync_student_semester_results($studentId);
    }

    return [
        'cgpa' => $cgpa,
        'credits_completed' => (int)$totalEarnedCredits,
        'total_registered_credits' => $totalRegCredits,
        'total_grade_points' => $totalGradePoints,
        'total_courses' => $totalCourses,
    ];
}

/**
 * Get comprehensive N-semester (2, 3, 4, or 8) breakdown and cumulative statistics for a student
 */
function get_student_academic_summary(int $studentId, bool $publishedOnly = true): array {
    $db = getDb();
    
    // Fetch student profile
    $stStmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.slug AS program_slug, p.degree_level,
               p.degree_type AS prog_degree_type, p.duration AS prog_duration,
               p.duration_years AS prog_duration_years, p.total_semesters AS prog_total_semesters,
               d.name_en AS department_name, f.name_en AS faculty_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN faculties f ON d.faculty_id = f.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return [
            'has_results' => false,
            'results_hidden' => false,
            'results_suspended' => false,
            'student' => null,
            'semesters' => [],
            'semester_schedule' => [],
            'program_structure' => ['total_semesters' => 8, 'degree_type' => 'Bachelor / Honours', 'duration_label' => '4 Years (8 Semesters)'],
        ];
    }

    $progStructure = function_exists('get_student_program_structure')
        ? get_student_program_structure($student)
        : [
            'degree_type' => $student['degree_type'] ?? 'Bachelor / Honours',
            'duration_label' => $student['program_duration'] ?? ($student['prog_duration'] ?? '4 Years (8 Semesters)'),
            'total_semesters' => (int)($student['total_semesters'] ?? ($student['prog_total_semesters'] ?? 8)),
            'duration_years' => (float)($student['prog_duration_years'] ?? 4.0),
        ];
    $configuredSemesters = max(1, (int)($progStructure['total_semesters'] ?? 8));
    $totalRequiredCredits = (int)($student['total_credits_required'] ?: ($progStructure['total_credits'] ?? ($configuredSemesters * 15)));

    $buildSchedule = function(array $bySemNum) use ($configuredSemesters, $student) {
        $sched = [];
        for ($s = 1; $s <= $configuredSemesters; $s++) {
            $ordLabel = get_semester_ordinal_title($s);
            if (isset($bySemNum[$s])) {
                $semInfo = $bySemNum[$s];
                $sched[$s] = [
                    'semester_number'    => $s,
                    'semester_name'      => $semInfo['semester_name'],
                    'short_name'         => $ordLabel,
                    'completion_status'  => 'Completed',
                    'sgpa'               => $semInfo['sgpa'],
                    'registered_credits' => $semInfo['registered_credits'],
                    'earned_credits'     => $semInfo['earned_credits'],
                    'is_estimated'       => $semInfo['is_estimated'],
                    'academic_session'   => $semInfo['academic_session'] ?? ($student['academic_session'] ?? ($student['batch'] ?? '')),
                ];
            } else {
                $sched[$s] = [
                    'semester_number'    => $s,
                    'semester_name'      => $ordLabel,
                    'short_name'         => $ordLabel,
                    'completion_status'  => 'Pending',
                    'sgpa'               => 0.00,
                    'registered_credits' => 0.0,
                    'earned_credits'     => 0.0,
                    'is_estimated'       => false,
                    'academic_session'   => $student['academic_session'] ?? ($student['batch'] ?? ''),
                ];
            }
        }
        return $sched;
    };

    $buildAllSemesters = function(array $bySemNum) use ($configuredSemesters, $student) {
        $all = [];
        $defaultSess = $student['academic_session'] ?? ($student['batch'] ?? '');
        for ($s = 1; $s <= $configuredSemesters; $s++) {
            $ordLabel = get_semester_ordinal_title($s);
            if (isset($bySemNum[$s])) {
                $semInfo = $bySemNum[$s];
                $all[$s] = [
                    'semester_number'    => $s,
                    'semester_name'      => $semInfo['semester_name'],
                    'ordinal_title'      => $ordLabel,
                    'academic_session'   => $semInfo['academic_session'] ?? $defaultSess,
                    'has_courses'        => !empty($semInfo['courses']),
                    'courses'            => $semInfo['courses'],
                    'registered_credits' => $semInfo['registered_credits'],
                    'earned_credits'     => $semInfo['earned_credits'],
                    'semester_points'           => $semInfo['semester_points'],
                    'sgpa'                      => $semInfo['sgpa'],
                    'cumulative_reg_credits'    => $semInfo['cumulative_reg_credits'] ?? 0.0,
                    'cumulative_earned_credits' => $semInfo['cumulative_earned_credits'] ?? 0.0,
                    'cumulative_points'         => $semInfo['cumulative_points'] ?? 0.0,
                    'cumulative_cgpa'           => $semInfo['cumulative_cgpa'],
                    'status'                    => $semInfo['status'],
                    'is_published'              => !empty($semInfo['all_published']),
                    'any_published'             => !empty($semInfo['any_published']),
                    'publication_status'        => $semInfo['publication_status'] ?? 'Published',
                    'is_estimated'              => !empty($semInfo['is_estimated']),
                ];
            } else {
                $all[$s] = [
                    'semester_number'           => $s,
                    'semester_name'             => $ordLabel,
                    'ordinal_title'             => $ordLabel,
                    'academic_session'          => $defaultSess,
                    'has_courses'               => false,
                    'courses'                   => [],
                    'registered_credits'        => 0.0,
                    'earned_credits'            => 0.0,
                    'semester_points'           => 0.0,
                    'sgpa'                      => null,
                    'cumulative_reg_credits'    => null,
                    'cumulative_earned_credits' => null,
                    'cumulative_points'         => null,
                    'cumulative_cgpa'           => null,
                    'status'             => 'Not Published Yet',
                    'is_published'       => false,
                    'any_published'      => false,
                    'publication_status' => 'Not Published Yet',
                    'is_estimated'       => false,
                ];
            }
        }
        return $all;
    };

    $resultsVisible = !isset($student['results_visible']) || (int)$student['results_visible'] === 1;
    $resultsSuspended = isset($student['result_access_status']) && $student['result_access_status'] === 'Suspended';

    // When Student Portal (publishedOnly) is active and result access is Suspended, strictly conceal all results
    if ($publishedOnly && $resultsSuspended) {
        return [
            'has_results' => false,
            'results_hidden' => false,
            'results_visible' => $resultsVisible ? 1 : 0,
            'results_hidden_reason' => $student['results_hidden_reason'] ?? '',
            'results_suspended' => true,
            'results_suspended_reason' => $student['result_suspended_reason'] ?? 'Your result access has been temporarily suspended by the university administration.',
            'student' => $student,
            'program_structure' => $progStructure,
            'configured_semesters' => $configuredSemesters,
            'semesters' => [],
            'all_semesters' => [],
            'semester_gpas' => [],
            'semester_schedule' => [],
            'final_cgpa' => 0.00,
            'total_registered_credits' => 0.0,
            'total_earned_credits' => 0.0,
            'total_required_credits' => $totalRequiredCredits,
            'total_grade_points' => 0.0,
            'published_semesters_count' => 0,
            'overall_academic_status' => 'Result Access Suspended',
            'has_estimated_results' => false,
            'disclaimer_label' => '',
        ];
    }

    // When Student Portal (publishedOnly) is active and admin hid results, hide output without touching DB data
    if ($publishedOnly && !$resultsVisible) {
        return [
            'has_results' => false,
            'results_hidden' => true,
            'results_visible' => 0,
            'results_hidden_reason' => $student['results_hidden_reason'] ?? '',
            'results_suspended' => false,
            'results_suspended_reason' => '',
            'student' => $student,
            'program_structure' => $progStructure,
            'configured_semesters' => $configuredSemesters,
            'semesters' => [],
            'all_semesters' => [],
            'semester_gpas' => [],
            'semester_schedule' => [],
            'final_cgpa' => 0.00,
            'total_registered_credits' => 0.0,
            'total_earned_credits' => 0.0,
            'total_required_credits' => $totalRequiredCredits,
            'total_grade_points' => 0.0,
            'published_semesters_count' => 0,
            'overall_academic_status' => 'Results Hidden',
            'has_estimated_results' => false,
            'disclaimer_label' => '',
        ];
    }

    $sql = "SELECT * FROM student_results WHERE student_id = ?";
    if ($publishedOnly) {
        $sql .= " AND is_published = 1";
    }
    $sql .= " ORDER BY id ASC";

    $rStmt = $db->prepare($sql);
    $rStmt->execute([$studentId]);
    $allResults = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allResults)) {
        return [
            'has_results' => false,
            'results_hidden' => !$resultsVisible,
            'results_visible' => $resultsVisible ? 1 : 0,
            'results_hidden_reason' => $student['results_hidden_reason'] ?? '',
            'results_suspended' => $resultsSuspended,
            'results_suspended_reason' => $student['result_suspended_reason'] ?? '',
            'student' => $student,
            'program_structure' => $progStructure,
            'configured_semesters' => $configuredSemesters,
            'semesters' => [],
            'all_semesters' => $buildAllSemesters([]),
            'semester_gpas' => [],
            'semester_schedule' => $buildSchedule([]),
            'final_cgpa' => 0.00,
            'total_registered_credits' => 0.0,
            'total_earned_credits' => 0.0,
            'total_required_credits' => $totalRequiredCredits,
            'total_grade_points' => 0.0,
            'published_semesters_count' => 0,
            'overall_academic_status' => 'Pending Publication',
            'has_estimated_results' => false,
            'disclaimer_label' => '',
        ];
    }

    // Group by canonical semester while keeping existing semester label intact
    $grouped = [];
    $semNumToKey = [];
    foreach ($allResults as $res) {
        $rawSemKey = trim((string)$res['semester']);
        $sn = get_semester_number($rawSemKey);
        if ($sn >= 1 && $sn <= max(12, $configuredSemesters)) {
            if (!isset($semNumToKey[$sn])) {
                $semNumToKey[$sn] = $rawSemKey;
            }
            $semKey = $semNumToKey[$sn];
        } else {
            $semKey = $rawSemKey !== '' ? $rawSemKey : 'Unassigned Semester';
        }
        $grouped[$semKey][] = $res;
    }

    // Sort chronologically 1 to N
    uksort($grouped, function($a, $b) {
        return get_semester_number($a) <=> get_semester_number($b);
    });

    $runningRegCredits = 0.0;
    $runningEarnedCredits = 0.0;
    $runningGradePoints = 0.0;
    $semestersData = [];
    $semesterGpas = [];
    $bySemNum = [];
    $hasAnyEstimated = false;
    $hasAnyFailedCourse = false;
    $publishedSemestersCount = 0;

    foreach ($grouped as $semName => $coursesList) {
        $semNum = get_semester_number($semName);
        $semRegCr = 0.0;
        $semEarnedCr = 0.0;
        $semPts = 0.0;
        $processedCourses = [];
        $hasFailedCourse = false;
        $semHasEstimated = false;
        $allPub = true;
        $anyPub = false;
        $anyReview = false;
        $semSession = $student['academic_session'] ?? ($student['batch'] ?? '');

        foreach ($coursesList as $c) {
            $cr = (float)$c['credit'];
            $obtainedMarks = isset($c['marks']) && $c['marks'] !== null ? (float)$c['marks'] : null;
            $fullMarks = isset($c['full_marks']) ? (int)$c['full_marks'] : 100;
            $gp = (float)$c['grade_point'];
            $grade = trim($c['letter_grade']);
            $ptsSecured = round($cr * $gp, 2);
            $status = ($gp >= 2.00) ? 'Pass' : 'Fail';
            $isEst = !empty($c['is_estimated']);
            $isPub = !empty($c['is_published']);
            $pubSt = $c['publish_status'] ?? ($isPub ? 'Published' : 'Draft');
            if ($isPub) {
                $anyPub = true;
            } else {
                $allPub = false;
            }
            if ($pubSt === 'Under Review') {
                $anyReview = true;
            }
            if ($isEst) {
                $semHasEstimated = true;
                $hasAnyEstimated = true;
            }
            if (!empty($c['academic_session'])) {
                $semSession = $c['academic_session'];
            }

            if ($status === 'Fail') {
                $hasFailedCourse = true;
                $hasAnyFailedCourse = true;
            }

            $semRegCr += $cr;
            if ($status === 'Pass') {
                $semEarnedCr += $cr;
            }
            $semPts += $ptsSecured;

            $processedCourses[] = [
                'id' => (int)$c['id'],
                'course_code' => $c['course_code'],
                'course_name' => $c['course_name'],
                'credit' => $cr,
                'full_marks' => $fullMarks,
                'marks' => $obtainedMarks,
                'letter_grade' => $grade,
                'grade_point' => $gp,
                'points_secured' => $ptsSecured,
                'status' => $status,
                'exam_term' => $c['exam_term'],
                'academic_session' => $c['academic_session'] ?? $semSession,
                'publication_date' => $c['publication_date'] ?? null,
                'published_at' => $c['published_at'] ?? null,
                'is_published' => $isPub,
                'publish_status' => $pubSt,
                'is_estimated' => $isEst,
                'result_type' => $c['result_type'] ?? ($isEst ? 'Estimated' : 'Verified'),
                'correction_reason' => $c['correction_reason'] ?? '',
            ];
        }

        $sgpa = ($semRegCr > 0) ? round($semPts / $semRegCr, 2) : 0.00;
        $semResultStatus = $hasFailedCourse ? 'PROMOTED (WITH CARRY)' : 'PASSED';
        $semPubStatusLabel = $allPub ? 'Published' : ($anyPub ? 'Partial Draft' : ($anyReview ? 'Under Review' : 'Draft'));

        $runningRegCredits += $semRegCr;
        $runningEarnedCredits += $semEarnedCr;
        $runningGradePoints += $semPts;
        $runningCgpa = ($runningRegCredits > 0) ? round($runningGradePoints / $runningRegCredits, 2) : 0.00;

        $semEntry = [
            'semester_number' => $semNum,
            'semester_name' => $semName,
            'academic_session' => $semSession,
            'courses' => $processedCourses,
            'registered_credits' => $semRegCr,
            'earned_credits' => $semEarnedCr,
            'semester_points' => $semPts,
            'sgpa' => $sgpa,
            'status' => $semResultStatus,
            'cumulative_reg_credits' => $runningRegCredits,
            'cumulative_earned_credits' => $runningEarnedCredits,
            'cumulative_points' => $runningGradePoints,
            'cumulative_cgpa' => $runningCgpa,
            'is_estimated' => $semHasEstimated,
            'all_published' => $allPub,
            'any_published' => $anyPub,
            'publication_status' => $semPubStatusLabel,
        ];

        $semestersData[$semName] = $semEntry;
        $semesterGpas[$semNum] = $sgpa;
        if ($semNum >= 1 && $semNum <= max(12, $configuredSemesters)) {
            $bySemNum[$semNum] = $semEntry;
            if ($semNum <= $configuredSemesters && $anyPub) {
                $publishedSemestersCount++;
            }
        }
    }

    $finalCgpa = ($runningRegCredits > 0) ? round($runningGradePoints / $runningRegCredits, 2) : 0.00;
    if ($resultsSuspended) {
        $overallAcademicStatus = 'Result Access Suspended';
    } elseif (($student['status'] ?? '') === 'Graduated') {
        $overallAcademicStatus = 'Graduated';
    } elseif ($publishedSemestersCount >= $configuredSemesters && !$hasAnyFailedCourse) {
        $overallAcademicStatus = 'All Semesters Completed';
    } elseif ($hasAnyFailedCourse) {
        $overallAcademicStatus = 'In Progress (Carry / Retake Pending)';
    } elseif ($publishedSemestersCount > 0) {
        $overallAcademicStatus = "In Progress ({$publishedSemestersCount} of {$configuredSemesters} Semesters)";
    } else {
        $overallAcademicStatus = 'Draft / Pending Publication';
    }

    return [
        'has_results' => true,
        'results_hidden' => !$resultsVisible,
        'results_visible' => $resultsVisible ? 1 : 0,
        'results_hidden_reason' => $student['results_hidden_reason'] ?? '',
        'results_suspended' => $resultsSuspended,
        'results_suspended_reason' => $student['result_suspended_reason'] ?? '',
        'student' => $student,
        'program_structure' => $progStructure,
        'configured_semesters' => $configuredSemesters,
        'semesters' => $semestersData,
        'all_semesters' => $buildAllSemesters($bySemNum),
        'semester_gpas' => $semesterGpas,
        'semester_schedule' => $buildSchedule($bySemNum),
        'final_cgpa' => $finalCgpa,
        'total_registered_credits' => $runningRegCredits,
        'total_earned_credits' => $runningEarnedCredits,
        'total_required_credits' => $totalRequiredCredits,
        'total_grade_points' => $runningGradePoints,
        'published_semesters_count' => $publishedSemestersCount,
        'overall_academic_status' => $overallAcademicStatus,
        'has_estimated_results' => $hasAnyEstimated,
        'disclaimer_label' => $hasAnyEstimated ? 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT' : '',
    ];
}

function get_student_avatar_url(?array $student): string {
    if (!empty($student['avatar'])) {
        $avatarFile = $student['avatar'];
        $filePath = BASE_PATH . '/storage/uploads/students/' . $avatarFile;
        if (file_exists($filePath)) {
            return APP_URL . '/storage/uploads/students/' . $avatarFile;
        }
    }
    return APP_URL . '/assets/images/default-student.svg';
}

function get_admission_photo_url(?string $photoFilename): string {
    if (!empty($photoFilename)) {
        $path = BASE_PATH . '/storage/uploads/students/' . $photoFilename;
        if (file_exists($path)) {
            return APP_URL . '/storage/uploads/students/' . $photoFilename;
        }
    }
    return APP_URL . '/assets/images/default-student.svg';
}

function get_payment_screenshot_url(?string $filename): ?string {
    if (!empty($filename)) {
        $path = BASE_PATH . '/storage/uploads/payments/' . $filename;
        if (file_exists($path)) {
            return APP_URL . '/storage/uploads/payments/' . $filename;
        }
        $path2 = BASE_PATH . '/storage/uploads/' . $filename;
        if (file_exists($path2)) {
            return APP_URL . '/storage/uploads/' . $filename;
        }
    }
    return null;
}

function get_admission_document_url(?string $docFilename): ?string {
    $raw = trim((string)$docFilename);
    if ($raw === '') {
        return null;
    }
    $clean = ltrim(str_replace('\\', '/', $raw), '/');
    if (strpos($clean, '..') !== false) {
        return null;
    }
    if (file_exists(BASE_PATH . '/' . $clean)) {
        return APP_URL . '/' . $clean;
    }
    $base = basename($clean);
    if ($base !== '' && file_exists(BASE_PATH . '/storage/uploads/documents/' . $base)) {
        return APP_URL . '/storage/uploads/documents/' . $base;
    }
    if (strpos($clean, 'storage/') === 0) {
        return APP_URL . '/' . $clean;
    }
    return APP_URL . '/storage/uploads/documents/' . $base;
}

/**
 * Generate next sequential Student ID for Fall 2026 intake
 */
function generate_next_student_id(PDO $db): string {
    $stmt = $db->query("
        SELECT MAX(CAST(SUBSTRING(student_id, 4) AS UNSIGNED))
        FROM students
        WHERE student_id REGEXP '^RMU26[0-9]{5}$'
    ");
    $maxNum = (int)$stmt->fetchColumn();
    $next = ($maxNum >= 2601004) ? ($maxNum + 1) : 2601004;

    $chkStudent = $db->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
    $chkAdm = $db->prepare("SELECT COUNT(*) FROM admissions WHERE student_id = ?");
    while (true) {
        $candidate = 'RMU' . $next;
        $chkStudent->execute([$candidate]);
        $chkAdm->execute([$candidate]);
        if ((int)$chkStudent->fetchColumn() === 0 && (int)$chkAdm->fetchColumn() === 0) {
            return $candidate;
        }
        $next++;
    }
}

/**
 * Generate a cryptographically secure random password for Student Portal accounts.
 * Excludes visually ambiguous characters (0/O, 1/l/I) and keeps clipboard/URL safety.
 */
function generate_secure_student_password(int $length = 12): string {
    $length  = max(10, min(32, $length));
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghjkmnpqrstuvwxyz';
    $digits  = '23456789';
    $special = '@#!*-';
    $all     = $upper . $lower . $digits . $special;

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    $allLen = strlen($all);
    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $all[random_int(0, $allLen - 1)];
    }

    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $chars[$i];
        $chars[$i] = $chars[$j];
        $chars[$j] = $tmp;
    }

    return implode('', $chars);
}

/**
 * Generate secure initial random password for newly provisioned Student Portal accounts
 */
function generate_initial_student_password(): string {
    return generate_secure_student_password(12);
}

/**
 * ========================================================
 * ACADEMIC SESSION, PASSING YEAR & EXPECTED COMPLETION ENGINE
 * ========================================================
 */

function parse_academic_session_year(?string $sessionName): ?int {
    $raw = trim((string)$sessionName);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $raw, $m)) {
        return (int)$m[1];
    }
    return null;
}

function format_duration_years_label(float $years, int $semesters): string {
    $cleanYears = max(0.5, min(6.0, round($years, 1)));
    $isWhole = abs($cleanYears - round($cleanYears)) < 0.05;
    $yVal = $isWhole ? (string)(int)round($cleanYears) : number_format($cleanYears, 1, '.', '');
    $unit = ((float)$yVal === 1.0) ? 'Year' : 'Years';
    $semUnit = ($semesters === 1) ? 'Semester' : 'Semesters';
    return "{$yVal} {$unit} ({$semesters} {$semUnit})";
}

function get_academic_sessions(bool $enabledOnly = false, string $order = 'DESC'): array {
    try {
        $db = getDb();
        $dir = (strtoupper(trim($order)) === 'ASC') ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM academic_sessions";
        if ($enabledOnly) {
            $sql .= " WHERE is_enabled = 1";
        }
        $sql .= " ORDER BY sort_order {$dir}, id {$dir}";
        $stmt = $db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_current_academic_session(): ?array {
    try {
        $db = getDb();
        $row = $db->query("SELECT * FROM academic_sessions WHERE is_current = 1 ORDER BY is_enabled DESC, sort_order DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $row = $db->query("SELECT * FROM academic_sessions WHERE is_enabled = 1 ORDER BY sort_order DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_current_academic_session_name(string $fallback = 'Fall 2026'): string {
    $cur = get_current_academic_session();
    return (!empty($cur['session_name'])) ? trim((string)$cur['session_name']) : $fallback;
}

function get_passing_years(bool $enabledOnly = false, string $order = 'DESC'): array {
    try {
        $db = getDb();
        $dir = (strtoupper(trim($order)) === 'ASC') ? 'ASC' : 'DESC';
        $sql = "SELECT * FROM passing_years";
        if ($enabledOnly) {
            $sql .= " WHERE is_enabled = 1";
        }
        $sql .= " ORDER BY year_value {$dir}, sort_order {$dir}";
        $stmt = $db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Online Admission Application Record Years start from 2015 (RMU establishment year),
 * independent of Academic Session (2011+) and Passing Year (2011+) configurations.
 */
function get_application_record_years(int $minYear = 2015, ?int $maxYear = null, string $order = 'DESC'): array {
    $start = max(2015, $minYear);
    $curY = (int)date('Y');
    $end = $maxYear !== null ? max($curY + 2, $maxYear) : ($curY + 2);
    $years = range($start, $end);
    if (strtoupper(trim($order)) !== 'ASC') {
        rsort($years);
    }
    return $years;
}

function build_admission_original_snapshot(array $data): string {
    $payload = [
        'application_year'      => !empty($data['application_year']) ? (int)$data['application_year'] : max(2015, (int)date('Y')),
        'program_id'            => !empty($data['program_id']) ? (int)$data['program_id'] : null,
        'interested_subject'    => (string)($data['interested_subject'] ?? ''),
        'degree_type'           => (string)($data['degree_type'] ?? 'Bachelor / Honours'),
        'academic_session'      => (string)($data['academic_session'] ?? ''),
        'duration_years'        => isset($data['duration_years']) && $data['duration_years'] !== '' ? (float)$data['duration_years'] : null,
        'course_duration'       => (string)($data['course_duration'] ?? ''),
        'total_semesters'       => isset($data['total_semesters']) && $data['total_semesters'] !== '' ? (int)$data['total_semesters'] : null,
        'expected_passing_year' => isset($data['expected_passing_year']) && $data['expected_passing_year'] !== '' ? (int)$data['expected_passing_year'] : null,
        'passing_year'          => isset($data['passing_year']) && trim((string)$data['passing_year']) !== '' ? trim((string)$data['passing_year']) : null,
        'submitted_at'          => (string)($data['submitted_at'] ?? date('Y-m-d H:i:s')),
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
}

function is_academic_session_enabled(string $sessionName): bool {
    $clean = trim($sessionName);
    if ($clean === '') {
        return false;
    }
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM academic_sessions WHERE LOWER(TRIM(session_name)) = LOWER(?) AND is_enabled = 1");
        $stmt->execute([$clean]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function is_passing_year_enabled($yearValue): bool {
    $raw = trim((string)$yearValue);
    if ($raw === '' || !ctype_digit($raw)) {
        return false;
    }
    $yr = (int)$raw;
    if ($yr < 1950 || $yr > 2100) {
        return false;
    }
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM passing_years WHERE year_value = ? AND is_enabled = 1");
        $stmt->execute([$yr]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Compute Advisory Expected Passing Year = Academic Session Year + Course Duration.
 * Advisory only — must never automatically overwrite a student's selected Passing Year.
 */
function calculate_expected_passing_year(?string $academicSession, float $durationYears): ?array {
    $sessionYear = parse_academic_session_year($academicSession);
    if ($sessionYear === null || $durationYears <= 0) {
        return null;
    }
    $cleanDur = max(0.5, min(6.0, round($durationYears, 1)));
    $expectedYear = $sessionYear + (int)round($cleanDur);
    $durText = (abs($cleanDur - round($cleanDur)) < 0.05)
        ? (string)(int)round($cleanDur)
        : number_format($cleanDur, 1, '.', '');

    return [
        'session_year'   => $sessionYear,
        'duration_years' => $cleanDur,
        'expected_year'  => $expectedYear,
        'formula'        => "{$sessionYear} + {$durText} = {$expectedYear}",
    ];
}

/**
 * Evaluate difference between a student's selected Passing Year and Expected Passing Year.
 */
function evaluate_passing_year_difference(?string $academicSession, $selectedPassingYear, float $durationYears, bool $isHistoricalApproved = false): array {
    $calc = calculate_expected_passing_year($academicSession, $durationYears);
    $expectedYear = $calc ? (int)$calc['expected_year'] : null;
    $rawSel = trim((string)$selectedPassingYear);
    $selectedYear = ($rawSel !== '' && ctype_digit($rawSel)) ? (int)$rawSel : null;

    if ($expectedYear === null || $selectedYear === null) {
        return [
            'has_comparison'      => false,
            'session_year'        => $calc['session_year'] ?? null,
            'duration_years'      => $calc['duration_years'] ?? round($durationYears, 1),
            'expected_year'       => $expectedYear,
            'selected_year'       => $selectedYear,
            'variance'            => null,
            'is_discrepancy'      => false,
            'needs_review'        => false,
            'historical_approved' => $isHistoricalApproved,
            'formula'             => $calc['formula'] ?? null,
            'status_key'          => $isHistoricalApproved ? 'approved_historical' : 'incomplete',
            'status_label'        => $isHistoricalApproved
                ? 'Historical Approved'
                : ($selectedYear === null ? 'No Passing Year Recorded' : 'Session Year Not Specified'),
        ];
    }

    $variance = $selectedYear - $expectedYear;
    $differs = ($variance !== 0);

    if (!$differs) {
        $statusKey = 'match';
        $statusLabel = 'Matches Expected';
    } elseif ($isHistoricalApproved) {
        $statusKey = 'approved_historical';
        $statusLabel = 'Historical Approved';
    } else {
        $statusKey = 'differs';
        $sign = $variance > 0 ? '+' : '';
        $statusLabel = "Differs ({$sign}{$variance} yr)";
    }

    return [
        'has_comparison'      => true,
        'session_year'        => $calc['session_year'],
        'duration_years'      => $calc['duration_years'],
        'expected_year'       => $expectedYear,
        'selected_year'       => $selectedYear,
        'variance'            => $variance,
        'is_discrepancy'      => $differs,
        'needs_review'        => ($differs && !$isHistoricalApproved),
        'historical_approved' => $isHistoricalApproved,
        'formula'             => $calc['formula'],
        'status_key'          => $statusKey,
        'status_label'        => $statusLabel,
    ];
}

function log_academic_edit_history(string $entityType, int $entityId, ?string $studentIdCode, ?int $adminId, string $fieldName, ?string $oldValue, ?string $newValue, string $reason): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO academic_edit_history (entity_type, entity_id, student_id_code, admin_id, field_name, old_value, new_value, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$entityType, $entityId, $studentIdCode, $adminId, $fieldName, $oldValue, $newValue, $reason]);
    } catch (Throwable $e) {
        error_log("log_academic_edit_history error: " . $e->getMessage());
        return false;
    }
}

function get_academic_edit_history(?string $entityType = null, ?int $entityId = null, ?string $studentIdCode = null, int $limit = 50): array {
    try {
        $db = getDb();
        $sql = "
            SELECT h.*, u.username AS admin_username, u.full_name AS admin_full_name
            FROM academic_edit_history h
            LEFT JOIN users u ON h.admin_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($entityType !== null && $entityType !== '' && $entityId !== null && $entityId > 0) {
            if ($studentIdCode !== null && trim($studentIdCode) !== '') {
                $sql .= " AND ((h.entity_type = ? AND h.entity_id = ?) OR h.student_id_code = ?)";
                $params[] = $entityType;
                $params[] = $entityId;
                $params[] = trim($studentIdCode);
            } else {
                $sql .= " AND h.entity_type = ? AND h.entity_id = ?";
                $params[] = $entityType;
                $params[] = $entityId;
            }
        } elseif ($studentIdCode !== null && trim($studentIdCode) !== '') {
            $sql .= " AND h.student_id_code = ?";
            $params[] = trim($studentIdCode);
        } elseif ($entityType !== null && $entityType !== '') {
            $sql .= " AND h.entity_type = ?";
            $params[] = $entityType;
        }
        $sql .= " ORDER BY h.id DESC LIMIT " . max(1, min(200, (int)$limit));
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function approve_historical_academic_record(string $entityType, int $recordId, int $adminId, string $note = ''): array {
    $db = getDb();
    $cleanType = ($entityType === 'admission') ? 'admission' : 'student';
    $cleanNote = trim($note) !== '' ? trim($note) : 'Historical academic session and passing year record verified and approved by administrator';

    if ($cleanType === 'admission') {
        $stmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1");
        $stmt->execute([$recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Admission record not found.'];
        }
        $oldFlag = (int)($row['historical_record_approved'] ?? 0);
        $db->prepare("
            UPDATE admissions
            SET historical_record_approved = 1,
                historical_approved_by = ?,
                historical_approved_at = NOW(),
                historical_approval_note = ?
            WHERE id = ?
        ")->execute([$adminId, $cleanNote, $recordId]);

        $stCode = !empty($row['student_id']) ? (string)$row['student_id'] : null;
        if ($stCode) {
            $db->prepare("
                UPDATE students
                SET historical_record_approved = 1,
                    historical_approved_by = ?,
                    historical_approved_at = NOW(),
                    historical_approval_note = ?
                WHERE student_id = ?
            ")->execute([$adminId, $cleanNote, $stCode]);
        }

        log_academic_edit_history('admission', $recordId, $stCode, $adminId, 'historical_record_approved', (string)$oldFlag, '1', $cleanNote);
        log_audit('HISTORICAL_RECORD_APPROVED', "Approved historical passing year record for Admission #{$recordId} ({$row['application_ref']} - {$row['full_name']}). Selected Passing Year: " . ($row['passing_year'] ?: 'N/A') . ". Note: {$cleanNote}", $adminId);
        return ['success' => true, 'entity_type' => 'admission', 'record_id' => $recordId];
    }

    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$recordId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }
    $oldFlag = (int)($row['historical_record_approved'] ?? 0);
    $db->prepare("
        UPDATE students
        SET historical_record_approved = 1,
            historical_approved_by = ?,
            historical_approved_at = NOW(),
            historical_approval_note = ?
        WHERE id = ?
    ")->execute([$adminId, $cleanNote, $recordId]);

    if (!empty($row['student_id'])) {
        $db->prepare("
            UPDATE admissions
            SET historical_record_approved = 1,
                historical_approved_by = ?,
                historical_approved_at = NOW(),
                historical_approval_note = ?
            WHERE student_id = ?
        ")->execute([$adminId, $cleanNote, $row['student_id']]);
    }

    log_academic_edit_history('student', $recordId, $row['student_id'], $adminId, 'historical_record_approved', (string)$oldFlag, '1', $cleanNote);
    log_audit('HISTORICAL_RECORD_APPROVED', "Approved historical passing year record for Student #{$recordId} ({$row['student_id']} - {$row['full_name']}). Selected Passing Year: " . ($row['passing_year'] ?: 'N/A') . ". Note: {$cleanNote}", $adminId);
    return ['success' => true, 'entity_type' => 'student', 'record_id' => $recordId];
}

function update_academic_record_with_audit(string $entityType, int $recordId, array $updates, int $adminId, string $reason): array {
    $cleanReason = trim($reason);
    if ($cleanReason === '') {
        return ['success' => false, 'error' => 'A mandatory reason is required for every official academic record update.'];
    }

    $db = getDb();
    $cleanType = ($entityType === 'admission') ? 'admission' : 'student';

    if ($cleanType === 'admission') {
        $stmt = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1");
        $stmt->execute([$recordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'error' => 'Admission record not found.'];
        }

        $newSession = array_key_exists('academic_session', $updates)
            ? trim((string)$updates['academic_session'])
            : (string)$row['academic_session'];
        if ($newSession === '') {
            return ['success' => false, 'error' => 'Academic Session cannot be empty.'];
        }

        $newPassingYear = array_key_exists('passing_year', $updates)
            ? (trim((string)$updates['passing_year']) !== '' ? trim((string)$updates['passing_year']) : null)
            : ($row['passing_year'] !== null ? (string)$row['passing_year'] : null);

        $newProgramId = array_key_exists('program_id', $updates) && (int)$updates['program_id'] > 0
            ? (int)$updates['program_id']
            : (int)($row['program_id'] ?? 0);
        $programChanged = ($newProgramId !== (int)($row['program_id'] ?? 0));

        $newDeptId = $row['department_id'] ? (int)$row['department_id'] : null;
        $newSubject = (string)($row['interested_subject'] ?? '');
        $newDegreeType = array_key_exists('degree_type', $updates) && trim((string)$updates['degree_type']) !== ''
            ? trim((string)$updates['degree_type'])
            : (string)($row['degree_type'] ?? 'Bachelor / Honours');
        $durYears = (!empty($row['duration_years']) && (float)$row['duration_years'] > 0)
            ? (float)$row['duration_years']
            : (($newDegreeType === 'B.Ed') ? 1.0 : (($newDegreeType === "Master's") ? 2.0 : 4.0));
        $newTotalSemesters = (!empty($row['total_semesters']) && (int)$row['total_semesters'] > 0)
            ? (int)$row['total_semesters']
            : (($newDegreeType === 'B.Ed') ? 2 : (($newDegreeType === "Master's") ? 4 : 8));

        if ($newProgramId > 0) {
            $pStruct = get_program_academic_structure($newProgramId);
            if (!empty($pStruct['program'])) {
                $newDeptId = (int)$pStruct['program']['department_id'];
                $newSubject = (string)$pStruct['program_title'];
                if (!array_key_exists('degree_type', $updates) || trim((string)$updates['degree_type']) === '') {
                    $newDegreeType = $programChanged ? $pStruct['degree_type'] : ($row['degree_type'] ?: $pStruct['degree_type']);
                }
                if ($programChanged || empty($row['duration_years'])) {
                    $durYears = (float)$pStruct['duration_years'];
                }
                if ($programChanged || empty($row['total_semesters'])) {
                    $newTotalSemesters = (int)$pStruct['total_semesters'];
                }
            }
        }
        if (array_key_exists('duration_years', $updates) && is_numeric($updates['duration_years']) && (float)$updates['duration_years'] > 0) {
            $durYears = max(0.5, min(6.0, round((float)$updates['duration_years'], 1)));
        }
        if (array_key_exists('total_semesters', $updates) && is_numeric($updates['total_semesters']) && (int)$updates['total_semesters'] > 0) {
            $newTotalSemesters = max(1, min(12, (int)$updates['total_semesters']));
        }
        $newCourseDuration = format_duration_years_label($durYears, $newTotalSemesters);

        $newApplicationYear = !empty($row['application_year'])
            ? (int)$row['application_year']
            : (!empty($row['created_at']) ? max(2015, (int)date('Y', strtotime($row['created_at']))) : (int)date('Y'));
        if (array_key_exists('application_year', $updates) && trim((string)$updates['application_year']) !== '') {
            $rawAppYr = trim((string)$updates['application_year']);
            if (!ctype_digit($rawAppYr) || (int)$rawAppYr < 2015 || (int)$rawAppYr > 2100) {
                return ['success' => false, 'error' => 'Online application record year must start from 2015 or later.'];
            }
            $newApplicationYear = (int)$rawAppYr;
        }

        // Preserve immutable original applicant submission snapshot before applying admin updates
        $existingSnapshot = !empty($row['original_submission_json'])
            ? (string)$row['original_submission_json']
            : build_admission_original_snapshot([
                'application_year'      => $row['application_year'] ?? (!empty($row['created_at']) ? max(2015, (int)date('Y', strtotime($row['created_at']))) : (int)date('Y')),
                'program_id'            => $row['program_id'] ?? null,
                'interested_subject'    => $row['interested_subject'] ?? '',
                'degree_type'           => $row['degree_type'] ?? 'Bachelor / Honours',
                'academic_session'      => $row['academic_session'] ?? '',
                'duration_years'        => $row['duration_years'] ?? $durYears,
                'course_duration'       => $row['course_duration'] ?? format_duration_years_label($durYears, $newTotalSemesters),
                'total_semesters'       => $row['total_semesters'] ?? $newTotalSemesters,
                'expected_passing_year' => $row['expected_passing_year'] ?? null,
                'passing_year'          => $row['passing_year'] ?? null,
                'submitted_at'          => $row['created_at'] ?? date('Y-m-d H:i:s'),
            ]);

        $expCalc = calculate_expected_passing_year($newSession, $durYears);
        $newExpectedYear = $expCalc ? (int)$expCalc['expected_year'] : null;

        $oldDurFmt = $row['duration_years'] !== null ? number_format((float)$row['duration_years'], 1, '.', '') : '';
        $newDurFmt = number_format($durYears, 1, '.', '');

        $diffs = [];
        $compareMap = [
            'application_year'      => [$row['application_year'] !== null ? (string)$row['application_year'] : '', (string)$newApplicationYear],
            'academic_session'      => [$row['academic_session'], $newSession],
            'passing_year'          => [$row['passing_year'], $newPassingYear],
            'program_id'            => [(string)($row['program_id'] ?? ''), (string)($newProgramId ?: '')],
            'degree_type'           => [$row['degree_type'], $newDegreeType],
            'duration_years'        => [$oldDurFmt, $newDurFmt],
            'total_semesters'       => [(string)($row['total_semesters'] ?? ''), (string)$newTotalSemesters],
            'expected_passing_year' => [$row['expected_passing_year'] !== null ? (string)$row['expected_passing_year'] : null, $newExpectedYear !== null ? (string)$newExpectedYear : null],
        ];
        foreach ($compareMap as $field => $pair) {
            if ((string)($pair[0] ?? '') !== (string)($pair[1] ?? '')) {
                $diffs[$field] = $pair;
            }
        }

        $db->prepare("
            UPDATE admissions
            SET application_year = ?,
                academic_session = ?,
                passing_year = ?,
                program_id = ?,
                department_id = ?,
                interested_subject = ?,
                degree_type = ?,
                duration_years = ?,
                course_duration = ?,
                total_semesters = ?,
                expected_passing_year = ?,
                original_submission_json = COALESCE(original_submission_json, ?)
            WHERE id = ?
        ")->execute([
            $newApplicationYear,
            $newSession,
            $newPassingYear,
            ($newProgramId > 0 ? $newProgramId : null),
            $newDeptId,
            $newSubject,
            $newDegreeType,
            $durYears,
            $newCourseDuration,
            $newTotalSemesters,
            $newExpectedYear,
            $existingSnapshot,
            $recordId
        ]);

        $stCode = !empty($row['student_id']) ? (string)$row['student_id'] : null;
        if ($stCode) {
            $db->prepare("
                UPDATE students
                SET academic_session = ?,
                    passing_year = ?,
                    expected_passing_year = ?,
                    program_id = COALESCE(?, program_id),
                    department_id = COALESCE(?, department_id),
                    degree_type = ?,
                    duration_years = ?,
                    program_duration = ?,
                    total_semesters = ?
                WHERE student_id = ?
            ")->execute([
                $newSession,
                $newPassingYear,
                $newExpectedYear,
                ($newProgramId > 0 ? $newProgramId : null),
                $newDeptId,
                $newDegreeType,
                $durYears,
                $newCourseDuration,
                $newTotalSemesters,
                $stCode
            ]);
            $linkedPkStmt = $db->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
            $linkedPkStmt->execute([$stCode]);
            $linkedPk = (int)$linkedPkStmt->fetchColumn();
            if ($linkedPk > 0 && function_exists('sync_student_semester_results')) {
                sync_student_semester_results($linkedPk);
            }
        }

        foreach ($diffs as $field => $pair) {
            log_academic_edit_history('admission', $recordId, $stCode, $adminId, $field, $pair[0] !== null ? (string)$pair[0] : null, $pair[1] !== null ? (string)$pair[1] : null, $cleanReason);
        }
        if (empty($diffs)) {
            log_academic_edit_history('admission', $recordId, $stCode, $adminId, 'official_verification_note', null, 'Confirmed', $cleanReason);
        }
        log_audit('OFFICIAL_ACADEMIC_RECORD_EDITED', "Official academic update on Admission #{$recordId} ({$row['application_ref']}). Reason: {$cleanReason}", $adminId);
        return [
            'success'               => true,
            'changes'               => count($diffs),
            'expected_passing_year' => $newExpectedYear,
            'duration_years'        => $durYears,
            'total_semesters'       => $newTotalSemesters,
            'course_duration'       => $newCourseDuration,
        ];
    }

    // Student entity edit
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$recordId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $curStruct = get_student_program_structure($row);
    $newSession = array_key_exists('academic_session', $updates)
        ? trim((string)$updates['academic_session'])
        : (string)($row['academic_session'] ?? $row['batch'] ?? '');
    if ($newSession === '') {
        return ['success' => false, 'error' => 'Academic Session cannot be empty.'];
    }

    $newPassingYear = array_key_exists('passing_year', $updates)
        ? (trim((string)$updates['passing_year']) !== '' ? trim((string)$updates['passing_year']) : null)
        : ($row['passing_year'] !== null ? (string)$row['passing_year'] : null);

    $newProgramId = array_key_exists('program_id', $updates) && (int)$updates['program_id'] > 0
        ? (int)$updates['program_id']
        : (int)($row['program_id'] ?? 1);
    $pStruct = get_program_academic_structure($newProgramId);
    $programChanged = ($newProgramId !== (int)($row['program_id'] ?? 0));

    $newDeptId = !empty($pStruct['program']['department_id']) ? (int)$pStruct['program']['department_id'] : (int)($row['department_id'] ?? 1);
    $newDegreeType = (array_key_exists('degree_type', $updates) && trim((string)$updates['degree_type']) !== '')
        ? trim((string)$updates['degree_type'])
        : ($programChanged ? $pStruct['degree_type'] : (string)($row['degree_type'] ?? $pStruct['degree_type']));

    $newTotalSemesters = (array_key_exists('total_semesters', $updates) && (int)$updates['total_semesters'] > 0)
        ? max(1, min(12, (int)$updates['total_semesters']))
        : ($programChanged ? (int)$pStruct['total_semesters'] : (int)($row['total_semesters'] ?? $pStruct['total_semesters']));

    $newDurationYears = (array_key_exists('duration_years', $updates) && is_numeric($updates['duration_years']) && (float)$updates['duration_years'] > 0)
        ? max(0.5, min(6.0, round((float)$updates['duration_years'], 1)))
        : ($programChanged
            ? (float)$pStruct['duration_years']
            : (!empty($row['duration_years']) ? (float)$row['duration_years'] : (float)$curStruct['duration_years']));

    $newProgramDuration = format_duration_years_label($newDurationYears, $newTotalSemesters);
    $newCreditsReq = $programChanged ? (int)($pStruct['total_credits'] ?? $row['total_credits_required']) : (int)($row['total_credits_required'] ?? 128);

    $expCalc = calculate_expected_passing_year($newSession, $newDurationYears);
    $newExpectedYear = $expCalc ? (int)$expCalc['expected_year'] : null;

    $newBatch = ($row['batch'] === '' || $row['batch'] === $row['academic_session']) ? $newSession : $row['batch'];
    if (array_key_exists('batch', $updates) && trim((string)$updates['batch']) !== '') {
        $newBatch = trim((string)$updates['batch']);
    }

    $diffs = [];
    $oldDurVal = $row['duration_years'] !== null ? number_format((float)$row['duration_years'], 1, '.', '') : number_format((float)$curStruct['duration_years'], 1, '.', '');
    $newDurVal = number_format($newDurationYears, 1, '.', '');
    $compareMap = [
        'academic_session'      => [$row['academic_session'], $newSession],
        'passing_year'          => [$row['passing_year'], $newPassingYear],
        'program_id'            => [(string)($row['program_id'] ?? ''), (string)$newProgramId],
        'degree_type'           => [$row['degree_type'], $newDegreeType],
        'duration_years'        => [$oldDurVal, $newDurVal],
        'total_semesters'       => [(string)($row['total_semesters'] ?? ''), (string)$newTotalSemesters],
        'expected_passing_year' => [$row['expected_passing_year'] !== null ? (string)$row['expected_passing_year'] : null, $newExpectedYear !== null ? (string)$newExpectedYear : null],
    ];
    foreach ($compareMap as $field => $pair) {
        if ((string)($pair[0] ?? '') !== (string)($pair[1] ?? '')) {
            $diffs[$field] = $pair;
        }
    }

    $db->prepare("
        UPDATE students
        SET academic_session = ?,
            batch = ?,
            passing_year = ?,
            program_id = ?,
            department_id = ?,
            degree_type = ?,
            duration_years = ?,
            program_duration = ?,
            total_semesters = ?,
            total_credits_required = ?,
            expected_passing_year = ?
        WHERE id = ?
    ")->execute([
        $newSession,
        $newBatch,
        $newPassingYear,
        $newProgramId,
        $newDeptId,
        $newDegreeType,
        $newDurationYears,
        $newProgramDuration,
        $newTotalSemesters,
        $newCreditsReq,
        $newExpectedYear,
        $recordId
    ]);

    if (!empty($row['student_id'])) {
        $db->prepare("
            UPDATE admissions
            SET academic_session = ?,
                passing_year = ?,
                expected_passing_year = ?,
                program_id = ?,
                department_id = ?,
                degree_type = ?,
                duration_years = ?,
                course_duration = ?,
                total_semesters = ?
            WHERE student_id = ?
        ")->execute([
            $newSession,
            $newPassingYear,
            $newExpectedYear,
            $newProgramId,
            $newDeptId,
            $newDegreeType,
            $newDurationYears,
            $newProgramDuration,
            $newTotalSemesters,
            $row['student_id']
        ]);
    }

    if (function_exists('sync_student_semester_results')) {
        sync_student_semester_results($recordId);
    }

    foreach ($diffs as $field => $pair) {
        log_academic_edit_history('student', $recordId, $row['student_id'], $adminId, $field, $pair[0] !== null ? (string)$pair[0] : null, $pair[1] !== null ? (string)$pair[1] : null, $cleanReason);
    }
    if (empty($diffs)) {
        log_academic_edit_history('student', $recordId, $row['student_id'], $adminId, 'official_verification_note', null, 'Confirmed', $cleanReason);
    }
    log_audit('OFFICIAL_ACADEMIC_RECORD_EDITED', "Official academic update on Student #{$recordId} ({$row['student_id']} - {$row['full_name']}). Reason: {$cleanReason}", $adminId);
    return ['success' => true, 'changes' => count($diffs), 'expected_passing_year' => $newExpectedYear];
}

/**
 * Provision new Student account and enrolled courses upon Admission Acceptance / Fee Payment
 */
function provision_student_from_admission(int $admissionId): ?array {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM admissions WHERE id = ?");
    $stmt->execute([$admissionId]);
    $adm = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$adm) return null;

    ensure_admission_id_generation_schema($db);

    if (!empty($adm['student_id']) && !empty($adm['portal_password'])) {
        $studentId = $adm['student_id'];
        $plainPassword = $adm['portal_password'];
        $examRoll = !empty($adm['roll_number']) ? trim((string)$adm['roll_number']) : trim((string)($adm['exam_roll'] ?? ''));
        if ($examRoll === '') {
            $examRoll = generate_next_admission_roll_number($db, (int)date('Y', strtotime($adm['created_at'] ?? 'now')));
            $db->prepare("UPDATE admissions SET roll_number = ?, exam_roll = ? WHERE id = ?")->execute([$examRoll, $examRoll, $admissionId]);
        }
    } else {
        $studentId = generate_next_student_id($db);
        $plainPassword = generate_initial_student_password();
        $examRoll = !empty($adm['roll_number']) ? trim((string)$adm['roll_number']) : trim((string)($adm['exam_roll'] ?? ''));
        if ($examRoll === '') {
            $examRoll = generate_next_admission_roll_number($db, (int)date('Y', strtotime($adm['created_at'] ?? 'now')));
        }
        $ensureAppId = !empty($adm['application_id']) ? trim((string)$adm['application_id']) : trim((string)($adm['application_ref'] ?? ''));

        $upd = $db->prepare("
            UPDATE admissions
            SET student_id = ?,
                portal_password = ?,
                application_id = COALESCE(NULLIF(TRIM(application_id), ''), ?),
                roll_number = COALESCE(NULLIF(TRIM(roll_number), ''), ?),
                exam_roll = COALESCE(NULLIF(TRIM(exam_roll), ''), ?)
            WHERE id = ?
        ");
        $upd->execute([$studentId, $plainPassword, $ensureAppId, $examRoll, $examRoll, $admissionId]);
    }

    // Check if student already created in students table
    $chk = $db->prepare("SELECT id FROM students WHERE student_id = ?");
    $chk->execute([$studentId]);
    $existingStudentId = $chk->fetchColumn();

    if (!$existingStudentId) {
        $subject = $adm['interested_subject'] ?? '';
        $prog = null;
        if (!empty($adm['program_id'])) {
            $pStmt = $db->prepare("SELECT id, department_id, title_en, degree_type, duration, duration_years, total_semesters, total_credits FROM programs WHERE id = ? LIMIT 1");
            $pStmt->execute([$adm['program_id']]);
            $prog = $pStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$prog && !empty($subject)) {
            $progStmt = $db->prepare("SELECT id, department_id, title_en, degree_type, duration, duration_years, total_semesters, total_credits FROM programs WHERE title_en = ? OR title_en LIKE ? LIMIT 1");
            $progStmt->execute([$subject, "%$subject%"]);
            $prog = $progStmt->fetch(PDO::FETCH_ASSOC);
        }

        $programId = $prog ? (int)$prog['id'] : (!empty($adm['program_id']) ? (int)$adm['program_id'] : 1);
        $departmentId = $prog ? (int)$prog['department_id'] : (!empty($adm['department_id']) ? (int)$adm['department_id'] : 6);
        $progStruct = get_program_academic_structure($programId);
        $degreeType = !empty($adm['degree_type']) ? trim((string)$adm['degree_type']) : $progStruct['degree_type'];
        $durationYears = (!empty($adm['duration_years']) && (float)$adm['duration_years'] > 0)
            ? (float)$adm['duration_years']
            : (float)($progStruct['duration_years'] ?? 4.0);
        $totalSemesters = (!empty($adm['total_semesters']) && (int)$adm['total_semesters'] > 0)
            ? (int)$adm['total_semesters']
            : (int)$progStruct['total_semesters'];
        $progDuration = !empty($adm['course_duration'])
            ? trim((string)$adm['course_duration'])
            : format_duration_years_label($durationYears, $totalSemesters);
        $totalReqCredits = (int)($progStruct['total_credits'] ?? 140);

        $session = !empty($adm['academic_session']) ? trim((string)$adm['academic_session']) : get_current_academic_session_name('Fall 2026');
        // CRITICAL: Never automatically overwrite or fall back Passing Year to HSC year or expected year
        $passingYear = (isset($adm['passing_year']) && trim((string)$adm['passing_year']) !== '') ? trim((string)$adm['passing_year']) : null;
        $expCalc = calculate_expected_passing_year($session, $durationYears);
        $expectedPassingYear = !empty($adm['expected_passing_year'])
            ? (int)$adm['expected_passing_year']
            : ($expCalc['expected_year'] ?? null);

        $histApproved = (int)($adm['historical_record_approved'] ?? 0);
        $histApprovedBy = !empty($adm['historical_approved_by']) ? (int)$adm['historical_approved_by'] : null;
        $histApprovedAt = !empty($adm['historical_approved_at']) ? $adm['historical_approved_at'] : null;
        $histApprovalNote = !empty($adm['historical_approval_note']) ? $adm['historical_approval_note'] : null;

        $fatherName = $adm['father_name'] ?? null;
        $motherName = $adm['mother_name'] ?? null;
        $dob = !empty($adm['date_of_birth']) ? $adm['date_of_birth'] : null;
        $gender = !empty($adm['gender']) ? $adm['gender'] : 'Male';
        $presentAddr = $adm['present_address'] ?? null;
        $permAddr = $adm['permanent_address'] ?? null;

        $email = !empty($adm['email']) ? $adm['email'] : (strtolower($studentId) . '@student.rmu.ac.bd');
        $emailChk = $db->prepare("SELECT id FROM students WHERE email = ?");
        $emailChk->execute([$email]);
        if ($emailChk->fetchColumn()) {
            $email = strtolower($studentId) . '@student.rmu.ac.bd';
        }

        ensure_student_password_schema($db);
        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $provAdminId = (!empty($_SESSION['user_id']) && function_exists('is_admin_manager_role') && is_admin_manager_role())
            ? (int)$_SESSION['user_id']
            : null;
        $avatar = !empty($adm['photo']) ? $adm['photo'] : null;
        $appNumber = !empty($adm['application_id']) ? trim((string)$adm['application_id']) : (!empty($adm['application_ref']) ? trim((string)$adm['application_ref']) : null);

        // Ensure unique roll_number and application_number before inserting into students table
        if ($examRoll !== '') {
            $dupRollChk = $db->prepare("SELECT id FROM students WHERE roll_number = ? LIMIT 1");
            $dupRollChk->execute([$examRoll]);
            if ($dupRollChk->fetchColumn()) {
                $examRoll = generate_next_admission_roll_number($db, (int)date('Y', strtotime($adm['created_at'] ?? 'now')));
                $db->prepare("UPDATE admissions SET roll_number = ?, exam_roll = ? WHERE id = ?")->execute([$examRoll, $examRoll, $admissionId]);
            }
        }
        if ($appNumber !== null && $appNumber !== '') {
            $dupAppChk = $db->prepare("SELECT id FROM students WHERE application_number = ? LIMIT 1");
            $dupAppChk->execute([$appNumber]);
            if ($dupAppChk->fetchColumn()) {
                $appNumber = generate_application_ref($db);
                $db->prepare("UPDATE admissions SET application_id = ?, application_ref = ? WHERE id = ?")->execute([$appNumber, $appNumber, $admissionId]);
            }
        }

        $insStudent = $db->prepare("
            INSERT INTO students (
                student_id, roll_number, application_number, username,
                full_name, father_name, mother_name, email, phone, password_hash,
                password_updated_at, password_updated_by, password_source, must_change_password,
                program_id, degree_type, department_id, batch, academic_session, duration_years, program_duration, total_semesters,
                current_year, current_semester, cgpa, results_visible, result_status, result_access_status,
                credits_completed, total_credits_required, dob, gender, home_district, passing_year, expected_passing_year,
                historical_record_approved, historical_approved_by, historical_approved_at, historical_approval_note,
                present_address, permanent_address, status, session_version, avatar
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                NOW(), ?, 'system_generated', 1,
                ?, ?, ?, ?, ?, ?, ?, ?,
                '1st Year', '1st Semester', 0.00, 1, 'Pending', 'Active',
                0, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 'Active', 1, ?
            )
        ");
        $insStudent->execute([
            $studentId, $examRoll, $appNumber, $studentId,
            $adm['full_name'], $fatherName, $motherName, $email, $adm['mobile'], $passwordHash,
            $provAdminId,
            $programId, $degreeType, $departmentId, $session, $session, $durationYears, $progDuration, $totalSemesters,
            $totalReqCredits, $dob, $gender, $adm['home_district'], $passingYear, $expectedPassingYear,
            $histApproved, $histApprovedBy, $histApprovedAt, $histApprovalNote,
            $presentAddr, $permAddr, $avatar
        ]);
        $newStudentPk = (int)$db->lastInsertId();

        if ($provAdminId !== null) {
            store_admin_temp_student_password($newStudentPk, $plainPassword, $provAdminId, $passwordHash);
        }

        if (function_exists('sync_student_semester_results')) {
            sync_student_semester_results($newStudentPk);
        }

        // Send Welcome & ID Generation notification to student
        create_student_notification(
            $newStudentPk,
            "Admission Accepted — Welcome to RMU!",
            "Congratulations! Your admission application has been approved. Your official Student ID is {$studentId}. You can now view your enrolled courses, fee invoices, and academic results.",
            'general'
        );

        // Seed 4 sample 1st-semester courses from program curriculum (or fallback)
        $curr = function_exists('get_program_semester_curriculum') ? get_program_semester_curriculum($programId, $totalSemesters) : [];
        $sem1Curr = $curr[1] ?? [];
        if (count($sem1Curr) >= 4) {
            $courses = [];
            foreach (array_slice($sem1Curr, 0, 4) as $ci => $cc) {
                $courses[] = [$cc['code'], $cc['name'], (float)($cc['credit'] ?? 3.0), 'RMU Faculty Instructor', 'Sun & Tue 10:00 AM', 'Room ' . (301 + $ci)];
            }
        } else {
            $courses = [
                ['CSE 101', 'Introduction to Computer Science & Problem Solving', 3.0, 'Prof. Dr. M. A. Rahman', 'Sun & Tue 10:00 AM', 'Room 301'],
                ['CSE 102', 'Structured Programming Language (C)', 3.0, 'Engr. Tareq Ahmed', 'Mon & Wed 11:30 AM', 'Lab 2'],
                ['MAT 101', 'Differential and Integral Calculus', 3.0, 'Dr. Shamsul Huda', 'Sun & Wed 02:00 PM', 'Room 205'],
                ['ENG 101', 'English Reading and Composition Skills', 3.0, 'Ms. Sabrina Akter', 'Mon & Thu 03:30 PM', 'Room 304']
            ];
        }
        $insCourse = $db->prepare("
            INSERT INTO student_courses (
                student_id, course_code, course_name, credit,
                instructor_name, class_schedule, room_no, attendance_percent, semester, status
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, 90, '1st Semester', 'Enrolled'
            )
        ");
        foreach ($courses as $c) {
            $insCourse->execute([
                $newStudentPk, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]
            ]);
        }
    }

    return [
        'student_id'      => $studentId,
        'portal_password' => $plainPassword,
        'exam_roll'       => $examRoll
    ];
}

/**
 * Sync verified academic fields from an Admission record to its already-linked Student row.
 */
function sync_existing_student_from_admission(int $admissionId): ?int {
    $db = getDb();
    $chk = $db->prepare("SELECT * FROM admissions WHERE id = ? LIMIT 1");
    $chk->execute([$admissionId]);
    $admRow = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$admRow || empty($admRow['student_id'])) {
        return null;
    }

    $stLookup = $db->prepare("SELECT id, program_id FROM students WHERE student_id = ? LIMIT 1");
    $stLookup->execute([$admRow['student_id']]);
    $stRec = $stLookup->fetch(PDO::FETCH_ASSOC);
    if (!$stRec) {
        return null;
    }

    $progStruct = get_program_academic_structure((int)($admRow['program_id'] ?: ($stRec['program_id'] ?: 1)));
    $admDurYrs = !empty($admRow['duration_years']) ? (float)$admRow['duration_years'] : (float)$progStruct['duration_years'];
    $admTotSems = !empty($admRow['total_semesters']) ? (int)$admRow['total_semesters'] : (int)$progStruct['total_semesters'];
    $admDurLbl = !empty($admRow['course_duration']) ? $admRow['course_duration'] : format_duration_years_label($admDurYrs, $admTotSems);
    $admDegType = !empty($admRow['degree_type']) ? $admRow['degree_type'] : $progStruct['degree_type'];
    $admSession = !empty($admRow['academic_session']) ? trim((string)$admRow['academic_session']) : null;
    $admExpCalc = $admSession ? calculate_expected_passing_year($admSession, $admDurYrs) : null;
    $admExpYr = !empty($admRow['expected_passing_year']) ? (int)$admRow['expected_passing_year'] : ($admExpCalc['expected_year'] ?? null);
    $admPassingYear = (isset($admRow['passing_year']) && trim((string)$admRow['passing_year']) !== '') ? trim((string)$admRow['passing_year']) : '';

    $db->prepare("
        UPDATE students
        SET program_id = COALESCE(NULLIF(?, 0), program_id),
            department_id = COALESCE(NULLIF(?, 0), department_id),
            degree_type = COALESCE(NULLIF(?, ''), degree_type),
            academic_session = COALESCE(NULLIF(TRIM(?), ''), academic_session),
            batch = COALESCE(NULLIF(TRIM(?), ''), batch),
            duration_years = COALESCE(NULLIF(?, 0), duration_years),
            program_duration = COALESCE(NULLIF(TRIM(?), ''), program_duration),
            total_semesters = COALESCE(NULLIF(?, 0), total_semesters),
            expected_passing_year = COALESCE(?, expected_passing_year),
            roll_number = COALESCE(NULLIF(TRIM(roll_number), ''), NULLIF(?, '')),
            application_number = COALESCE(NULLIF(TRIM(application_number), ''), NULLIF(?, '')),
            passing_year = COALESCE(NULLIF(TRIM(?), ''), passing_year),
            historical_record_approved = CASE WHEN ? = 1 THEN 1 ELSE historical_record_approved END,
            historical_approved_by = COALESCE(?, historical_approved_by),
            historical_approved_at = COALESCE(?, historical_approved_at),
            historical_approval_note = COALESCE(NULLIF(?, ''), historical_approval_note)
        WHERE id = ?
    ")->execute([
        (int)($admRow['program_id'] ?? 0),
        (int)($admRow['department_id'] ?? 0),
        $admDegType,
        $admSession ?? '',
        $admSession ?? '',
        $admDurYrs,
        $admDurLbl,
        $admTotSems,
        $admExpYr,
        $admRow['roll_number'] ?? '',
        $admRow['application_id'] ?: ($admRow['application_ref'] ?? ''),
        $admPassingYear,
        (int)($admRow['historical_record_approved'] ?? 0),
        !empty($admRow['historical_approved_by']) ? (int)$admRow['historical_approved_by'] : null,
        !empty($admRow['historical_approved_at']) ? $admRow['historical_approved_at'] : null,
        $admRow['historical_approval_note'] ?? '',
        (int)$stRec['id']
    ]);

    sync_student_semester_results((int)$stRec['id']);
    return (int)$stRec['id'];
}

/**
 * Result Management & Student Notifications Helpers
 */
function create_student_notification(int $studentId, string $title, string $message, string $type = 'result_published', ?int $relatedId = null): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO student_notifications (student_id, title, message, type, related_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$studentId, $title, $message, $type, $relatedId]);
    } catch (Exception $e) {
        return false;
    }
}

function get_student_notifications(int $studentId, bool $unreadOnly = false, int $limit = 10): array {
    try {
        $db = getDb();
        $visStmt = $db->prepare("SELECT COALESCE(results_visible, 1) AS results_visible, COALESCE(result_access_status, 'Active') AS result_access_status FROM students WHERE id = ? LIMIT 1");
        $visStmt->execute([$studentId]);
        $stFlags = $visStmt->fetch(PDO::FETCH_ASSOC) ?: ['results_visible' => 1, 'result_access_status' => 'Active'];
        $resultsVisible = (int)$stFlags['results_visible'];
        $resultsSuspended = ($stFlags['result_access_status'] === 'Suspended');

        $pubStmt = $db->prepare("SELECT COUNT(*) FROM student_results WHERE student_id = ? AND is_published = 1");
        $pubStmt->execute([$studentId]);
        $publishedCount = (int)$pubStmt->fetchColumn();

        $sql = "SELECT * FROM student_notifications WHERE student_id = ?";
        if ($resultsVisible === 0 || $resultsSuspended || $publishedCount === 0) {
            $sql .= " AND type NOT IN ('result_published', 'grade_correction', 'result')";
        }
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function get_student_unread_notifications_count(int $studentId): int {
    try {
        $db = getDb();
        $visStmt = $db->prepare("SELECT COALESCE(results_visible, 1) AS results_visible, COALESCE(result_access_status, 'Active') AS result_access_status FROM students WHERE id = ? LIMIT 1");
        $visStmt->execute([$studentId]);
        $stFlags = $visStmt->fetch(PDO::FETCH_ASSOC) ?: ['results_visible' => 1, 'result_access_status' => 'Active'];
        $resultsVisible = (int)$stFlags['results_visible'];
        $resultsSuspended = ($stFlags['result_access_status'] === 'Suspended');

        $pubStmt = $db->prepare("SELECT COUNT(*) FROM student_results WHERE student_id = ? AND is_published = 1");
        $pubStmt->execute([$studentId]);
        $publishedCount = (int)$pubStmt->fetchColumn();

        $sql = "SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = 0";
        if ($resultsVisible === 0 || $resultsSuspended || $publishedCount === 0) {
            $sql .= " AND type NOT IN ('result_published', 'grade_correction', 'result')";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function mark_student_notifications_read(int $studentId, ?int $notificationId = null): bool {
    try {
        $db = getDb();
        if ($notificationId !== null) {
            $stmt = $db->prepare("UPDATE student_notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
            return $stmt->execute([$notificationId, $studentId]);
        } else {
            $stmt = $db->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ?");
            return $stmt->execute([$studentId]);
        }
    } catch (Exception $e) {
        return false;
    }
}

function log_result_modification(?int $resultId, int $studentId, int $adminId, string $courseCode, string $semester, string $fieldName, ?string $oldValue, string $newValue, string $reason): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO result_modification_history 
            (result_id, student_id, admin_id, course_code, semester, field_name, old_value, new_value, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$resultId, $studentId, $adminId, $courseCode, $semester, $fieldName, $oldValue, $newValue, $reason]);
    } catch (Exception $e) {
        return false;
    }
}

function get_pending_result_requests_count(): int {
    try {
        $db = getDb();
        return (int)$db->query("SELECT COUNT(*) FROM result_requests WHERE status = 'Pending'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * ========================================================
 * ONE-CLICK CGPA UPDATE & ESTIMATED RESULT GENERATOR ENGINE
 * ========================================================
 */

/**
 * Resolve degree type, duration label, duration years, total semesters, and per-semester credit caps for a program.
 * - Bachelor / Honours: 4 Years / 8 Semesters
 * - B.Ed: Default 1 Year / 2 Semesters (Admin-configurable to 3 Semesters)
 * - Master's: Admin-configurable 1 Year / 2 Semesters, 1.5 Years / 3 Semesters, or 2 Years / 4 Semesters
 */
function get_program_academic_structure(int $programId, ?int $overrideSemesters = null, ?float $overrideDurationYears = null): array {
    $db = getDb();
    $progStmt = $db->prepare("
        SELECT p.*, d.name_en AS dept_name, d.slug AS dept_slug, d.code AS dept_code
        FROM programs p
        LEFT JOIN departments d ON p.department_id = d.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $progStmt->execute([$programId]);
    $prog = $progStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $slug = strtolower((string)($prog['slug'] ?? ''));
    $degreeLevel = trim((string)($prog['degree_level'] ?? 'Undergraduate'));
    $rawType = trim((string)($prog['degree_type'] ?? ''));

    if ($rawType === '') {
        if ($slug === 'b-ed' || stripos((string)($prog['title_en'] ?? ''), 'b.ed') !== false) {
            $degreeType = 'B.Ed';
        } elseif (strtolower($degreeLevel) === 'postgraduate') {
            $degreeType = "Master's";
        } else {
            $degreeType = 'Bachelor / Honours';
        }
    } else {
        $degreeType = $rawType;
    }

    if ($overrideSemesters !== null && $overrideSemesters > 0) {
        $totalSemesters = max(1, min(12, $overrideSemesters));
    } elseif (!empty($prog['total_semesters'])) {
        $totalSemesters = max(1, min(12, (int)$prog['total_semesters']));
    } elseif ($degreeType === 'B.Ed') {
        $totalSemesters = 2;
    } elseif ($degreeType === "Master's" || strtolower($degreeLevel) === 'postgraduate') {
        $totalSemesters = 4;
    } else {
        $totalSemesters = 8;
    }

    if ($overrideDurationYears !== null && $overrideDurationYears > 0) {
        $durationYears = max(0.5, min(6.0, round($overrideDurationYears, 1)));
    } elseif ($overrideSemesters !== null && $overrideSemesters > 0) {
        if (isset($prog['total_semesters'], $prog['duration_years']) && (int)$prog['total_semesters'] === $totalSemesters && (float)$prog['duration_years'] > 0) {
            $durationYears = (float)$prog['duration_years'];
        } elseif ($degreeType === 'B.Ed') {
            $durationYears = ($totalSemesters === 3) ? 1.5 : (($totalSemesters === 2) ? 1.0 : round($totalSemesters * 0.5, 1));
        } elseif ($degreeType === "Master's") {
            $durationYears = ($totalSemesters === 4) ? 2.0 : (($totalSemesters === 3) ? 1.5 : (($totalSemesters === 2) ? 1.0 : round($totalSemesters * 0.5, 1)));
        } else {
            $durationYears = ($totalSemesters === 8) ? 4.0 : round($totalSemesters * 0.5, 1);
        }
    } elseif (isset($prog['duration_years']) && (float)$prog['duration_years'] > 0) {
        $durationYears = (float)$prog['duration_years'];
    } else {
        $durationYears = ($totalSemesters === 8 ? 4.0 : ($totalSemesters === 3 ? 1.5 : ($totalSemesters === 4 ? 2.0 : ($totalSemesters === 2 ? 1.0 : round($totalSemesters * 0.5, 1)))));
    }

    $durationLabel = (!empty($prog['duration']) && $overrideSemesters === null && $overrideDurationYears === null)
        ? trim((string)$prog['duration'])
        : format_duration_years_label($durationYears, $totalSemesters);

    $semesterCredits = [];
    if (!empty($prog['semester_credits_json'])) {
        $decoded = json_decode((string)$prog['semester_credits_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $sNum = (int)$k;
                if ($sNum >= 1 && $sNum <= $totalSemesters && (float)$v > 0) {
                    $semesterCredits[$sNum] = (float)$v;
                }
            }
        }
    }

    return [
        'program'          => $prog,
        'program_id'       => $programId,
        'program_title'    => $prog['title_en'] ?? 'Program',
        'program_slug'     => $slug,
        'dept_name'        => $prog['dept_name'] ?? '',
        'degree_level'     => $degreeLevel,
        'degree_type'      => $degreeType,
        'duration_years'   => $durationYears,
        'duration_label'   => $durationLabel,
        'total_semesters'  => $totalSemesters,
        'total_credits'    => (int)($prog['total_credits'] ?? ($totalSemesters * 15)),
        'semester_credits' => $semesterCredits,
    ];
}

function get_student_program_structure(array $student, ?int $overrideSemesters = null, ?float $overrideDurationYears = null): array {
    $programId = (int)($student['program_id'] ?? 1);
    $struct = get_program_academic_structure($programId, $overrideSemesters, $overrideDurationYears);

    $progSemesters = !empty($struct['program']['total_semesters']) ? (int)$struct['program']['total_semesters'] : $struct['total_semesters'];

    // Ignore uninitialized table column defaults (degree_type='Bachelor / Honours', total_semesters=8, NULL duration_years/program_duration)
    // when the student's actual program_id is Master's / B.Ed or configured with a different semester count.
    $isUninitializedStudentDefaults = (
        empty($student['duration_years'])
        && empty($student['program_duration'])
        && trim((string)($student['degree_type'] ?? 'Bachelor / Honours')) === 'Bachelor / Honours'
        && (int)($student['total_semesters'] ?? 8) === 8
        && ($struct['degree_type'] !== 'Bachelor / Honours' || $progSemesters !== 8)
    );

    if ($overrideSemesters === null && !empty($student['total_semesters']) && !$isUninitializedStudentDefaults) {
        $stSem = (int)$student['total_semesters'];
        if ($stSem >= 1 && $stSem <= 12) {
            $struct['total_semesters'] = $stSem;
        }
    }

    if ($overrideDurationYears === null && !empty($student['duration_years']) && (float)$student['duration_years'] > 0) {
        $struct['duration_years'] = max(0.5, min(6.0, round((float)$student['duration_years'], 1)));
    } elseif ($overrideSemesters === null && $overrideDurationYears === null && !$isUninitializedStudentDefaults && !empty($student['total_semesters']) && (int)$student['total_semesters'] !== $progSemesters) {
        $stSem = (int)$student['total_semesters'];
        if ($stSem >= 1 && $stSem <= 12) {
            $struct['duration_years'] = round($stSem * 0.5, 1);
        }
    }

    if (!empty($student['degree_type']) && !$isUninitializedStudentDefaults) {
        $struct['degree_type'] = trim((string)$student['degree_type']);
    }

    if (
        $overrideSemesters === null
        && $overrideDurationYears === null
        && !empty($student['program_duration'])
        && $struct['total_semesters'] === (int)($student['total_semesters'] ?? $struct['total_semesters'])
        && (empty($student['duration_years']) || abs((float)$student['duration_years'] - $struct['duration_years']) < 0.05)
    ) {
        $struct['duration_label'] = trim((string)$student['program_duration']);
    } else {
        $struct['duration_label'] = format_duration_years_label((float)$struct['duration_years'], (int)$struct['total_semesters']);
    }

    return $struct;
}

/**
 * Application Acceptance Gate:
 * Only online applications Accepted by the Admin may proceed to Result Management.
 * Directly admin-enrolled students without a linked admission record remain permitted.
 */
function can_manage_student_results(int $studentId): array {
    $db = getDb();
    $stStmt = $db->prepare("SELECT id, student_id, application_number, status FROM students WHERE id = ? LIMIT 1");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['allowed' => false, 'reason' => 'Student record not found.', 'admission' => null];
    }
    if ($student['status'] === 'Deleted') {
        return ['allowed' => false, 'reason' => 'This student account has been permanently deleted.', 'admission' => null];
    }

    $adm = null;
    $appNo = trim((string)($student['application_number'] ?? ''));
    if ($appNo !== '') {
        $admStmt = $db->prepare("
            SELECT id, application_ref, application_id, status
            FROM admissions
            WHERE student_id = ? OR application_ref = ? OR application_id = ?
            ORDER BY FIELD(status, 'Accepted', 'Under Review', 'Pending', 'Submitted', 'Additional Information Required', 'Rejected'), id DESC
            LIMIT 1
        ");
        $admStmt->execute([$student['student_id'], $appNo, $appNo]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } else {
        $admStmt = $db->prepare("
            SELECT id, application_ref, application_id, status
            FROM admissions
            WHERE student_id = ?
            ORDER BY FIELD(status, 'Accepted', 'Under Review', 'Pending', 'Submitted', 'Additional Information Required', 'Rejected'), id DESC
            LIMIT 1
        ");
        $admStmt->execute([$student['student_id']]);
        $adm = $admStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($adm && $adm['status'] !== 'Accepted') {
        return [
            'allowed' => false,
            'reason'  => "Only accepted applications can proceed to Result Management. Linked Application ({$adm['application_ref']}) is currently '{$adm['status']}'.",
            'admission' => $adm,
        ];
    }

    return ['allowed' => true, 'reason' => '', 'admission' => $adm];
}

/**
 * Map a valid UGC Grade Point to realistic marks and official letter grade
 */
function grade_point_to_sample_marks(float $gp): array {
    if ($gp >= 4.00) {
        return ['marks' => round(80.0 + (mt_rand(0, 120) / 10.0), 1), 'letter_grade' => 'A+'];
    } elseif ($gp >= 3.75) {
        return ['marks' => round(75.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'A'];
    } elseif ($gp >= 3.50) {
        return ['marks' => round(70.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'A-'];
    } elseif ($gp >= 3.25) {
        return ['marks' => round(65.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'B+'];
    } elseif ($gp >= 3.00) {
        return ['marks' => round(60.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'B'];
    } elseif ($gp >= 2.75) {
        return ['marks' => round(55.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'B-'];
    } elseif ($gp >= 2.50) {
        return ['marks' => round(50.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'C+'];
    } elseif ($gp >= 2.25) {
        return ['marks' => round(45.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'C'];
    } else {
        return ['marks' => round(40.0 + (mt_rand(0, 45) / 10.0), 1), 'letter_grade' => 'D'];
    }
}

/**
 * Synchronize student_semester_results & students.result_status for a student without modifying result rows
 */
function sync_student_semester_results(int $studentId): void {
    try {
        $db = getDb();
        $stStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stStmt->execute([$studentId]);
        $student = $stStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            return;
        }

        $struct = get_student_program_structure($student);
        $totalSemesters = max(1, (int)$struct['total_semesters']);

        $resStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
        $resStmt->execute([$studentId]);
        $rows = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        $bySemPublished = [];
        $bySemAll = [];
        $hasPublished = false;
        $hasEstimated = false;
        $hasVerified = false;
        foreach ($rows as $r) {
            $sNum = get_semester_number((string)$r['semester']);
            if ($sNum < 1 || $sNum > $totalSemesters) {
                $sNum = min($totalSemesters, max(1, $sNum));
            }
            $bySemAll[$sNum][] = $r;
            if (!empty($r['is_published'])) {
                $hasPublished = true;
                $bySemPublished[$sNum][] = $r;
            }
            if (!empty($r['is_estimated'])) $hasEstimated = true;
            else $hasVerified = true;
        }

        // Remove stale extra semesters if program duration changed
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ? AND (semester_number < 1 OR semester_number > ?)")
           ->execute([$studentId, $totalSemesters]);

        $upStmt = $db->prepare("
            INSERT INTO student_semester_results (
                student_id, semester_number, semester_name, academic_session,
                credit_hours, semester_gpa, completion_status, result_type, is_published, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                semester_name = VALUES(semester_name),
                academic_session = VALUES(academic_session),
                credit_hours = VALUES(credit_hours),
                semester_gpa = VALUES(semester_gpa),
                completion_status = VALUES(completion_status),
                result_type = VALUES(result_type),
                is_published = VALUES(is_published),
                published_at = VALUES(published_at)
        ");

        $defaultSession = !empty($student['academic_session']) ? $student['academic_session'] : (!empty($student['batch']) ? $student['batch'] : get_current_academic_session_name('Fall 2026'));

        for ($s = 1; $s <= $totalSemesters; $s++) {
            $pubRows = $bySemPublished[$s] ?? [];
            $allSemRows = $bySemAll[$s] ?? [];
            $semTitle = get_semester_ordinal_title($s);
            if (!empty($pubRows)) {
                $crSum = 0.0;
                $ptSum = 0.0;
                $semEst = false;
                $semPubAt = null;
                $semSess = $defaultSession;
                foreach ($pubRows as $sr) {
                    $cCr = (float)$sr['credit'];
                    $crSum += $cCr;
                    $ptSum += ($cCr * (float)$sr['grade_point']);
                    if (!empty($sr['is_estimated'])) $semEst = true;
                    if (!empty($sr['published_at'])) $semPubAt = $sr['published_at'];
                    if (!empty($sr['academic_session'])) $semSess = $sr['academic_session'];
                }
                $sgpa = ($crSum > 0) ? round($ptSum / $crSum, 2) : 0.00;
                $upStmt->execute([
                    $studentId, $s, $semTitle, $semSess,
                    round($crSum, 1), $sgpa, 'Completed', ($semEst ? 'Estimated' : 'Verified'), 1, $semPubAt
                ]);
            } elseif (!empty($allSemRows)) {
                $semEstAll = false;
                $semSess = $defaultSession;
                foreach ($allSemRows as $sr) {
                    if (!empty($sr['is_estimated'])) $semEstAll = true;
                    if (!empty($sr['academic_session'])) $semSess = $sr['academic_session'];
                }
                $upStmt->execute([
                    $studentId, $s, $semTitle, $semSess,
                    0.0, 0.00, 'Pending', ($semEstAll ? 'Estimated' : 'Verified'), 0, null
                ]);
            } else {
                $upStmt->execute([
                    $studentId, $s, $semTitle, $defaultSession,
                    0.0, 0.00, 'Pending', 'Estimated', 0, null
                ]);
            }
        }

        // Sync summary result_status cleanly
        $isSuspended = (isset($student['result_access_status']) && $student['result_access_status'] === 'Suspended');
        if ($isSuspended) {
            $newStatus = 'Suspended';
        } elseif ($hasPublished) {
            $newStatus = 'Published';
        } elseif ($hasEstimated) {
            $newStatus = 'Estimated';
        } elseif ($hasVerified) {
            $newStatus = 'Verified';
        } else {
            $newStatus = 'Pending';
        }
        $db->prepare("UPDATE students SET result_status = ? WHERE id = ?")->execute([$newStatus, $studentId]);
    } catch (Throwable $e) {
        error_log("sync_student_semester_results error: " . $e->getMessage());
    }
}

/**
 * Get standard semester-wise curriculum for any university program.
 * Supports Bachelor's (8 Semesters), B.Ed (2 or 3 Semesters), and Master's (2, 3, or 4 Semesters).
 */
function get_program_semester_curriculum(int $programId, ?int $semesterCount = null): array {
    $struct = get_program_academic_structure($programId, $semesterCount);
    $prog = $struct['program'];
    $slug = $struct['program_slug'];
    $degreeType = $struct['degree_type'];
    $totalSemesters = $struct['total_semesters'];
    $isMaster = ($degreeType === "Master's" || strtolower((string)($prog['degree_level'] ?? '')) === 'postgraduate');
    $curriculum = [];

    // 1. CSE
    if ($slug === 'bsc-in-cse') {
        $curriculum = [
            1 => [
                ['code' => 'CSE 101', 'name' => 'Structured Programming Language (C)', 'credit' => 3.0],
                ['code' => 'CSE 102', 'name' => 'Structured Programming Laboratory', 'credit' => 1.5],
                ['code' => 'MAT 101', 'name' => 'Differential and Integral Calculus', 'credit' => 3.0],
                ['code' => 'ENG 101', 'name' => 'English Reading and Composition Skills', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'CSE 103', 'name' => 'Discrete Mathematics for Computing', 'credit' => 3.0],
                ['code' => 'CSE 104', 'name' => 'Object Oriented Programming (C++)', 'credit' => 3.0],
                ['code' => 'CSE 105', 'name' => 'Object Oriented Programming Lab', 'credit' => 1.5],
                ['code' => 'PHY 101', 'name' => 'Physics for Engineers (Mechanics & Waves)', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'CSE 201', 'name' => 'Data Structures & Algorithms', 'credit' => 3.0],
                ['code' => 'CSE 202', 'name' => 'Data Structures Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 203', 'name' => 'Digital Logic Design & Circuits', 'credit' => 3.0],
                ['code' => 'EEE 101', 'name' => 'Basic Electrical Engineering & Circuit Analysis', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'CSE 205', 'name' => 'Design & Analysis of Algorithms', 'credit' => 3.0],
                ['code' => 'CSE 206', 'name' => 'Database Management Systems (DBMS)', 'credit' => 3.0],
                ['code' => 'CSE 207', 'name' => 'Database Management Systems Lab', 'credit' => 1.5],
                ['code' => 'MAT 201', 'name' => 'Linear Algebra & Complex Variables', 'credit' => 3.0]
            ],
            5 => [
                ['code' => 'CSE 301', 'name' => 'Operating Systems & System Programming', 'credit' => 3.0],
                ['code' => 'CSE 302', 'name' => 'Operating Systems Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 303', 'name' => 'Microprocessors, Microcontrollers & Interfacing', 'credit' => 3.0],
                ['code' => 'CSE 304', 'name' => 'Software Engineering & System Architecture', 'credit' => 3.0]
            ],
            6 => [
                ['code' => 'CSE 305', 'name' => 'Computer Networks & TCP/IP Protocols', 'credit' => 3.0],
                ['code' => 'CSE 306', 'name' => 'Computer Networks Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 307', 'name' => 'Theory of Computation & Automata', 'credit' => 3.0],
                ['code' => 'CSE 308', 'name' => 'Web Technologies & Distributed Cloud Systems', 'credit' => 3.0]
            ],
            7 => [
                ['code' => 'CSE 401', 'name' => 'Artificial Intelligence & Machine Learning', 'credit' => 3.0],
                ['code' => 'CSE 402', 'name' => 'Artificial Intelligence Lab', 'credit' => 1.5],
                ['code' => 'CSE 403', 'name' => 'Compiler Design & Syntax Analysis', 'credit' => 3.0],
                ['code' => 'CSE 404', 'name' => 'Cyber Security, Cryptography & Network Defense', 'credit' => 3.0]
            ],
            8 => [
                ['code' => 'CSE 405', 'name' => 'Cloud Computing & High Performance Architectures', 'credit' => 3.0],
                ['code' => 'CSE 406', 'name' => 'Data Science & Big Data Analytics', 'credit' => 3.0],
                ['code' => 'CSE 407', 'name' => 'Senior Capstone Project & Engineering Thesis', 'credit' => 4.0],
                ['code' => 'CSE 408', 'name' => 'Comprehensive Academic Defense & Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 2. BBA
    elseif ($slug === 'bba') {
        $curriculum = [
            1 => [
                ['code' => 'BBA 101', 'name' => 'Introduction to Modern Business & Organizations', 'credit' => 3.0],
                ['code' => 'BBA 102', 'name' => 'Principles of Financial Accounting', 'credit' => 3.0],
                ['code' => 'BBA 103', 'name' => 'Microeconomic Principles for Decision Making', 'credit' => 3.0],
                ['code' => 'BBA 104', 'name' => 'Business Mathematics & Commercial Computing', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'BBA 105', 'name' => 'Macroeconomics & Global Monetary Policy', 'credit' => 3.0],
                ['code' => 'BBA 106', 'name' => 'Managerial Accounting & Cost Estimation', 'credit' => 3.0],
                ['code' => 'BBA 107', 'name' => 'Principles of Marketing & Consumer Analysis', 'credit' => 3.0],
                ['code' => 'BBA 108', 'name' => 'Business Communication & Public Relations', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'BBA 201', 'name' => 'Principles of Finance & Capital Budgeting', 'credit' => 3.0],
                ['code' => 'BBA 202', 'name' => 'Organizational Behavior & Human Resources', 'credit' => 3.0],
                ['code' => 'BBA 203', 'name' => 'Business Statistics & Probability Analysis', 'credit' => 3.0],
                ['code' => 'BBA 204', 'name' => 'Commercial Law, Contract Acts & Ethics', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'BBA 205', 'name' => 'Corporate Finance & Working Capital Management', 'credit' => 3.0],
                ['code' => 'BBA 206', 'name' => 'Consumer Behavior & Market Research', 'credit' => 3.0],
                ['code' => 'BBA 207', 'name' => 'Operations & Quality Management', 'credit' => 3.0],
                ['code' => 'BBA 208', 'name' => 'Management Information Systems (MIS)', 'credit' => 3.0]
            ],
            5 => [
                ['code' => 'BBA 301', 'name' => 'International Business & Global Logistics', 'credit' => 3.0],
                ['code' => 'BBA 302', 'name' => 'Financial Markets, Banking Institutions & Credit', 'credit' => 3.0],
                ['code' => 'BBA 303', 'name' => 'Strategic Marketing & Brand Management', 'credit' => 3.0],
                ['code' => 'BBA 304', 'name' => 'Corporate Taxation, Auditing & Compliance', 'credit' => 3.0]
            ],
            6 => [
                ['code' => 'BBA 305', 'name' => 'Supply Chain Management & Procurement', 'credit' => 3.0],
                ['code' => 'BBA 306', 'name' => 'Investment Analysis & Portfolio Management', 'credit' => 3.0],
                ['code' => 'BBA 307', 'name' => 'Digital Marketing Analytics & E-Commerce', 'credit' => 3.0],
                ['code' => 'BBA 308', 'name' => 'Corporate Governance & Social Responsibility', 'credit' => 3.0]
            ],
            7 => [
                ['code' => 'BBA 401', 'name' => 'Entrepreneurship & Startup Venture Incubation', 'credit' => 3.0],
                ['code' => 'BBA 402', 'name' => 'Strategic Management & Competitive Policy', 'credit' => 3.0],
                ['code' => 'BBA 403', 'name' => 'Project Appraisal & Feasibility Study', 'credit' => 3.0],
                ['code' => 'BBA 404', 'name' => 'Business Research Methodology & Seminar', 'credit' => 3.0]
            ],
            8 => [
                ['code' => 'BBA 405', 'name' => 'Corporate Policy & Strategic Simulation', 'credit' => 3.0],
                ['code' => 'BBA 406', 'name' => 'International Financial Management', 'credit' => 3.0],
                ['code' => 'BBA 407', 'name' => 'Senior Internship Practicum & Monograph', 'credit' => 4.0],
                ['code' => 'BBA 408', 'name' => 'Comprehensive Academic Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 3. B.Ed (Bachelor of Education - 2 Semesters default, 3 Semesters configurable)
    elseif ($slug === 'b-ed' || $degreeType === 'B.Ed') {
        $bedSem1 = [
            ['code' => 'EDU 101', 'name' => 'Foundations & Philosophy of Education', 'credit' => 3.0],
            ['code' => 'EDU 102', 'name' => 'Educational Psychology & Cognitive Learning', 'credit' => 3.0],
            ['code' => 'EDU 103', 'name' => 'Curriculum Design, Pedagogy & Lesson Planning', 'credit' => 3.0],
            ['code' => 'EDU 104', 'name' => 'Instructional Technology & Classroom Management', 'credit' => 3.0]
        ];
        if ($totalSemesters === 2) {
            $curriculum = [
                1 => $bedSem1,
                2 => [
                    ['code' => 'EDU 201', 'name' => 'Measurement, Assessment & Evaluation in Education', 'credit' => 3.0],
                    ['code' => 'EDU 202', 'name' => 'Inclusive Education & Special Needs Pedagogy', 'credit' => 3.0],
                    ['code' => 'EDU 203', 'name' => 'Subject Teaching Methodology & School Governance', 'credit' => 3.0],
                    ['code' => 'EDU 204', 'name' => 'Supervised Teaching Practicum & Action Research', 'credit' => 4.0],
                    ['code' => 'EDU 205', 'name' => 'Comprehensive Classroom Defense & Viva Voce', 'credit' => 1.0]
                ]
            ];
        } else {
            $curriculum = [
                1 => $bedSem1,
                2 => [
                    ['code' => 'EDU 201', 'name' => 'Measurement, Assessment & Evaluation in Education', 'credit' => 3.0],
                    ['code' => 'EDU 202', 'name' => 'Inclusive Education & Special Needs Pedagogy', 'credit' => 3.0],
                    ['code' => 'EDU 203', 'name' => 'Educational Research Methods & Guidance Counseling', 'credit' => 3.0],
                    ['code' => 'EDU 204', 'name' => 'Subject Teaching Methodology & Curriculum Inquiry', 'credit' => 3.0]
                ],
                3 => [
                    ['code' => 'EDU 301', 'name' => 'Comparative Education, Ethics & Policy Studies', 'credit' => 3.0],
                    ['code' => 'EDU 302', 'name' => 'Digital Pedagogy & Blended Classroom Innovation', 'credit' => 3.0],
                    ['code' => 'EDU 303', 'name' => 'Supervised Teaching Practicum & Action Research', 'credit' => 4.0],
                    ['code' => 'EDU 304', 'name' => 'Comprehensive Classroom Defense & Viva Voce', 'credit' => 1.0]
                ]
            ];
        }
    }
    // 4. MBA (Master of Business Administration - 2, 3, or 4 Semesters)
    elseif ($slug === 'mba' || ($isMaster && strpos(strtolower($slug), 'business') !== false)) {
        $base = [
            1 => [
                ['code' => 'MBA 501', 'name' => 'Advanced Managerial Economics & Quantitative Methods', 'credit' => 3.0],
                ['code' => 'MBA 502', 'name' => 'Strategic Financial Accounting & Reporting', 'credit' => 3.0],
                ['code' => 'MBA 503', 'name' => 'Organizational Leadership & Strategic HRM', 'credit' => 3.0],
                ['code' => 'MBA 504', 'name' => 'Corporate Governance, Law & Ethical Leadership', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MBA 505', 'name' => 'Advanced Corporate Finance & Valuation Analysis', 'credit' => 3.0],
                ['code' => 'MBA 506', 'name' => 'Strategic Marketing & Consumer Analytics', 'credit' => 3.0],
                ['code' => 'MBA 507', 'name' => 'Global Supply Chain & Operations Excellence', 'credit' => 3.0],
                ['code' => 'MBA 508', 'name' => 'Enterprise Analytics, Big Data & Digital Transformation', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MBA 601', 'name' => 'Global Competitive Strategy & M&A Policy', 'credit' => 3.0],
                ['code' => 'MBA 602', 'name' => 'Venture Capital, Innovation & Private Equity', 'credit' => 3.0],
                ['code' => 'MBA 603', 'name' => 'Applied Business Research Methods & Econometrics', 'credit' => 3.0],
                ['code' => 'MBA 604', 'name' => 'International Financial Markets & Risk Hedging', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MBA 605', 'name' => 'Corporate Strategy Simulation & Case Analysis', 'credit' => 3.0],
                ['code' => 'MBA 606', 'name' => 'Executive Leadership & Sustainable Enterprise', 'credit' => 3.0],
                ['code' => 'MBA 607', 'name' => 'Master\'s Graduate Thesis / Capstone Applied Project', 'credit' => 4.0],
                ['code' => 'MBA 608', 'name' => 'Comprehensive Oral Examination & Viva Voce', 'credit' => 1.0]
            ]
        ];
        if ($totalSemesters === 2) {
            $curriculum = [1 => $base[1], 2 => $base[4]];
        } elseif ($totalSemesters === 3) {
            $curriculum = [1 => $base[1], 2 => $base[2], 3 => $base[4]];
        } else {
            $curriculum = $base;
        }
    }
    // 5. MA in English (2, 3, or 4 Semesters)
    elseif ($slug === 'ma-in-english') {
        $base = [
            1 => [
                ['code' => 'MAE 501', 'name' => 'Advanced Critical Theory & Cultural Studies', 'credit' => 3.0],
                ['code' => 'MAE 502', 'name' => 'Modernist & Postmodern Fiction and Poetry', 'credit' => 3.0],
                ['code' => 'MAE 503', 'name' => 'Postcolonial Discourse & World Literatures', 'credit' => 3.0],
                ['code' => 'MAE 504', 'name' => 'Advanced Stylistics, Discourse Analysis & Pragmatics', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MAE 505', 'name' => 'Shakespeare in Performance, Adaptation & Critique', 'credit' => 3.0],
                ['code' => 'MAE 506', 'name' => 'Diaspora Writings & Transnational Narratives', 'credit' => 3.0],
                ['code' => 'MAE 507', 'name' => 'Applied Linguistics & Second Language Acquisition', 'credit' => 3.0],
                ['code' => 'MAE 508', 'name' => 'Advanced Literary Research Methodology & Bibliography', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MAE 601', 'name' => 'Feminist Poetics, Gender Studies & Queer Theory', 'credit' => 3.0],
                ['code' => 'MAE 602', 'name' => 'South Asian English Literature & Partition Studies', 'credit' => 3.0],
                ['code' => 'MAE 603', 'name' => 'Translation Studies: Hermeneutics & Practicum', 'credit' => 3.0],
                ['code' => 'MAE 604', 'name' => 'ELT Testing, Curriculum Design & Pedagogy', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MAE 605', 'name' => 'Environmental Humanities & Ecocritical Literatures', 'credit' => 3.0],
                ['code' => 'MAE 606', 'name' => 'Contemporary World Poetry & Cultural Resistance', 'credit' => 3.0],
                ['code' => 'MAE 607', 'name' => 'Master of Arts Graduate Dissertation & Defense', 'credit' => 4.0],
                ['code' => 'MAE 608', 'name' => 'Graduate Comprehensive Viva Voce', 'credit' => 1.0]
            ]
        ];
        if ($totalSemesters === 2) {
            $curriculum = [1 => $base[1], 2 => $base[4]];
        } elseif ($totalSemesters === 3) {
            $curriculum = [1 => $base[1], 2 => $base[2], 3 => $base[4]];
        } else {
            $curriculum = $base;
        }
    }
    // 6. MA in Bangla (2, 3, or 4 Semesters)
    elseif ($slug === 'ma-in-bangla') {
        $base = [
            1 => [
                ['code' => 'MAB 501', 'name' => 'Comparative Literary Theory & Aesthetics', 'credit' => 3.0],
                ['code' => 'MAB 502', 'name' => 'Ancient & Medieval Bangla Poetics and Manuscript Studies', 'credit' => 3.0],
                ['code' => 'MAB 503', 'name' => 'Modern Bangla Poetry: Rabindranath, Nazrul & Jibanananda', 'credit' => 3.0],
                ['code' => 'MAB 504', 'name' => 'Bangla Sociolinguistics & Historical Dialectology', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MAB 505', 'name' => 'Literature of the Liberation War & Post-Colonial Bangladesh', 'credit' => 3.0],
                ['code' => 'MAB 506', 'name' => 'Evolution of Bangla Drama, Theatre & Folk Performances', 'credit' => 3.0],
                ['code' => 'MAB 507', 'name' => 'Modern Bangla Prose, Periodicals & Intellectual History', 'credit' => 3.0],
                ['code' => 'MAB 508', 'name' => 'Research Methodology in Bangla Literary Studies', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MAB 601', 'name' => 'Tagore Studies: Philosophy, Aesthetics & Cultural Impact', 'credit' => 3.0],
                ['code' => 'MAB 602', 'name' => 'Folklore of Kushtia & Lalon Shahi Mystical Heritage', 'credit' => 3.0],
                ['code' => 'MAB 603', 'name' => 'Contemporary Bangla Fiction & Novelistic Traditions', 'credit' => 3.0],
                ['code' => 'MAB 604', 'name' => 'Comparative South Asian Literature in Translation', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MAB 605', 'name' => 'Semiotics & Modern Literary Hermeneutics', 'credit' => 3.0],
                ['code' => 'MAB 606', 'name' => 'Bangla Lexicography, Orthography & Digital Linguistics', 'credit' => 3.0],
                ['code' => 'MAB 607', 'name' => 'Master\'s Research Dissertation & Public Defense', 'credit' => 4.0],
                ['code' => 'MAB 608', 'name' => 'Comprehensive Academic Viva Voce', 'credit' => 1.0]
            ]
        ];
        if ($totalSemesters === 2) {
            $curriculum = [1 => $base[1], 2 => $base[4]];
        } elseif ($totalSemesters === 3) {
            $curriculum = [1 => $base[1], 2 => $base[2], 3 => $base[4]];
        } else {
            $curriculum = $base;
        }
    }
    // 7. Master of Physical Education (MPEd - 2, 3, or 4 Semesters)
    elseif ($slug === 'mped') {
        $base = [
            1 => [
                ['code' => 'MPE 501', 'name' => 'Advanced Exercise Physiology & Cardiorespiratory Testing', 'credit' => 3.0],
                ['code' => 'MPE 502', 'name' => 'Advanced Sports Biomechanics & Cinematographic Analysis', 'credit' => 3.0],
                ['code' => 'MPE 503', 'name' => 'Sports Psychology, Peak Performance & Mental Conditioning', 'credit' => 3.0],
                ['code' => 'MPE 504', 'name' => 'Advanced Research Methodology & Sports Statistics', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MPE 505', 'name' => 'High Performance Athletic Training & Periodization', 'credit' => 3.0],
                ['code' => 'MPE 506', 'name' => 'Sports Medicine, Rehabilitation & Therapeutic Modalities', 'credit' => 3.0],
                ['code' => 'MPE 507', 'name' => 'Sports Nutrition, Ergogenic Aids & Metabolic Testing', 'credit' => 3.0],
                ['code' => 'MPE 508', 'name' => 'Sports Law, Ethics & Anti-Doping Regulations', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MPE 601', 'name' => 'Strategic Sports Management & Global Governance', 'credit' => 3.0],
                ['code' => 'MPE 602', 'name' => 'Motor Learning, Neural Control & Skill Acquisition', 'credit' => 3.0],
                ['code' => 'MPE 603', 'name' => 'Fitness Technology, Wearable Sensors & Sports Analytics', 'credit' => 3.0],
                ['code' => 'MPE 604', 'name' => 'Adapted Physical Activities & Paralympic Sports Science', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MPE 605', 'name' => 'Olympic Movement, Elite Coaching Practicum & Supervision', 'credit' => 3.0],
                ['code' => 'MPE 606', 'name' => 'Facility Design, Event Management & Sports Marketing', 'credit' => 3.0],
                ['code' => 'MPE 607', 'name' => 'Master\'s Research Thesis & Scientific Defense', 'credit' => 4.0],
                ['code' => 'MPE 608', 'name' => 'Comprehensive Oral Defense & Viva Voce', 'credit' => 1.0]
            ]
        ];
        if ($totalSemesters === 2) {
            $curriculum = [1 => $base[1], 2 => $base[4]];
        } elseif ($totalSemesters === 3) {
            $curriculum = [1 => $base[1], 2 => $base[2], 3 => $base[4]];
        } else {
            $curriculum = $base;
        }
    }
    // 7b. BSS (Honours) in Social Science
    elseif ($slug === 'bss-honours') {
        $curriculum = [
            1 => [
                ['code' => 'SOC 101', 'name' => 'Introduction to Sociology & Social Thought', 'credit' => 3.0],
                ['code' => 'SOC 102', 'name' => 'Principles of Public Administration & Governance', 'credit' => 3.0],
                ['code' => 'SOC 103', 'name' => 'Foundations of Economics & Development Studies', 'credit' => 3.0],
                ['code' => 'ENG 101', 'name' => 'Academic English & Analytical Writing', 'credit' => 3.0],
            ],
            2 => [
                ['code' => 'SOC 104', 'name' => 'Social History of Bengal & Cultural Heritage', 'credit' => 3.0],
                ['code' => 'SOC 105', 'name' => 'Classical Political Theory & Constitutional Law', 'credit' => 3.0],
                ['code' => 'SOC 106', 'name' => 'Quantitative Statistics for Social Research', 'credit' => 3.0],
                ['code' => 'SOC 107', 'name' => 'Social Psychology & Community Dynamics', 'credit' => 3.0],
            ],
            3 => [
                ['code' => 'SOC 201', 'name' => 'Classical & Contemporary Sociological Theories', 'credit' => 3.0],
                ['code' => 'SOC 202', 'name' => 'Rural & Urban Sociology of South Asia', 'credit' => 3.0],
                ['code' => 'SOC 203', 'name' => 'Qualitative & Ethnographic Research Methods', 'credit' => 3.0],
                ['code' => 'SOC 204', 'name' => 'Population Studies & Demography', 'credit' => 3.0],
            ],
            4 => [
                ['code' => 'SOC 205', 'name' => 'Gender, Development & Social Stratification', 'credit' => 3.0],
                ['code' => 'SOC 206', 'name' => 'Local Government & Decentralization Policy', 'credit' => 3.0],
                ['code' => 'SOC 207', 'name' => 'Social Data Analysis & Computing Lab', 'credit' => 3.0],
                ['code' => 'SOC 208', 'name' => 'Environmental Sociology & Climate Resilience', 'credit' => 3.0],
            ],
            5 => [
                ['code' => 'SOC 301', 'name' => 'Globalization, State & Social Transformation', 'credit' => 3.0],
                ['code' => 'SOC 302', 'name' => 'Criminology, Deviance & Social Justice', 'credit' => 3.0],
                ['code' => 'SOC 303', 'name' => 'Public Policy Analysis & Program Evaluation', 'credit' => 3.0],
                ['code' => 'SOC 304', 'name' => 'Media, Communication & Digital Society', 'credit' => 3.0],
            ],
            6 => [
                ['code' => 'SOC 305', 'name' => 'Industrial Sociology & Labor Relations', 'credit' => 3.0],
                ['code' => 'SOC 306', 'name' => 'NGOs, Civil Society & Sustainable Development', 'credit' => 3.0],
                ['code' => 'SOC 307', 'name' => 'Disaster Risk Reduction & Social Vulnerability', 'credit' => 3.0],
                ['code' => 'SOC 308', 'name' => 'Survey Design & Applied Fieldwork Practicum', 'credit' => 3.0],
            ],
            7 => [
                ['code' => 'SOC 401', 'name' => 'Comparative Social Policy & Welfare Systems', 'credit' => 3.0],
                ['code' => 'SOC 402', 'name' => 'Migration, Diaspora & Transnational Communities', 'credit' => 3.0],
                ['code' => 'SOC 403', 'name' => 'Human Rights, Law & Social Advocacy', 'credit' => 3.0],
                ['code' => 'SOC 404', 'name' => 'Senior Research Seminar & Proposal Writing', 'credit' => 3.0],
            ],
            8 => [
                ['code' => 'SOC 405', 'name' => 'Contemporary Issues in Bangladesh Society', 'credit' => 3.0],
                ['code' => 'SOC 406', 'name' => 'Social Entrepreneurship & Community Leadership', 'credit' => 3.0],
                ['code' => 'SOC 407', 'name' => 'BSS Honours Research Monograph & Field Thesis', 'credit' => 4.0],
                ['code' => 'SOC 408', 'name' => 'Comprehensive Academic Defense & Viva Voce', 'credit' => 1.0],
            ],
        ];
    }
    // 8. General fallback for any other program (e.g. EEE, ICT, English, Bangla, Music, Fine Arts, PES, Agriculture, Microbiology)
    else {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $slug), 0, 3));
        if (empty($prefix) || strlen($prefix) < 2) $prefix = 'GEN';

        for ($s = 1; $s <= $totalSemesters; $s++) {
            $curriculum[$s] = [
                ['code' => "{$prefix} {$s}01", 'name' => "Core Subject {$s}.1 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$prefix} {$s}02", 'name' => "Core Subject {$s}.2 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$prefix} {$s}03", 'name' => "Applied Subject / Laboratory {$s}.3", 'credit' => ($s % 2 === 0 ? 1.5 : 3.0)],
                ['code' => "{$prefix} {$s}04", 'name' => ($s === $totalSemesters ? 'Senior Monograph & Comprehensive Viva' : "Academic Subject {$s}.4"), 'credit' => 3.0]
            ];
        }
    }

    // Ensure any extended semester count (up to 12) has curriculum courses
    $fallbackPrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $slug), 0, 3));
    if (empty($fallbackPrefix) || strlen($fallbackPrefix) < 2) {
        $fallbackPrefix = 'RMU';
    }
    for ($s = 1; $s <= $totalSemesters; $s++) {
        if (empty($curriculum[$s])) {
            $curriculum[$s] = [
                ['code' => "{$fallbackPrefix} {$s}01", 'name' => "Advanced Core Course {$s}.1 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$fallbackPrefix} {$s}02", 'name' => "Advanced Core Course {$s}.2 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$fallbackPrefix} {$s}03", 'name' => "Applied Seminar / Laboratory {$s}.3", 'credit' => ($s % 2 === 0 ? 1.5 : 3.0)],
                ['code' => "{$fallbackPrefix} {$s}04", 'name' => ($s === $totalSemesters ? 'Final Capstone Thesis & Viva Voce' : "Specialized Elective {$s}.4"), 'credit' => 3.0],
            ];
        }
    }

    // Slice cleanly to configured semester count
    $sliced = [];
    for ($s = 1; $s <= $totalSemesters; $s++) {
        if (isset($curriculum[$s])) {
            $sliced[$s] = $curriculum[$s];
        }
    }

    // Apply optional Admin per-semester credit configuration cleanly (distributing evenly across courses in multiples of 0.5)
    if (!empty($struct['semester_credits'])) {
        foreach ($struct['semester_credits'] as $sNum => $targetSemCr) {
            if (!isset($sliced[$sNum]) || empty($sliced[$sNum]) || $targetSemCr < 3.0) {
                continue;
            }
            $sumCr = 0.0;
            foreach ($sliced[$sNum] as $cc) {
                $sumCr += (float)$cc['credit'];
            }
            $diff = round($targetSemCr - $sumCr, 1);
            if (abs($diff) >= 0.5) {
                $cnt = count($sliced[$sNum]);
                $perCourse = round($targetSemCr / $cnt * 2) / 2;
                $perCourse = max(1.0, min(6.0, $perCourse));
                $running = 0.0;
                for ($ci = 0; $ci < $cnt - 1; $ci++) {
                    $sliced[$sNum][$ci]['credit'] = $perCourse;
                    $running += $perCourse;
                }
                $lastCr = round(($targetSemCr - $running) * 2) / 2;
                if ($lastCr >= 1.0 && $lastCr <= 8.0) {
                    $sliced[$sNum][$cnt - 1]['credit'] = $lastCr;
                }
            }
        }
    }

    return $sliced;
}

/**
 * Generate Estimated Academic Results for a student to mathematically match target CGPA.
 * Supports Bachelor / Honours (8 Semesters), B.Ed (2 or 3 Semesters), and Master's (2, 3, or 4 Semesters).
 * Safely replaces previous estimated results without duplicates and preserves any Verified official results.
 */
function generate_estimated_student_results(int $studentId, float $targetCgpa, bool $publishToPortal = true, int $adminId = 1, array $options = []): array {
    $db = getDb();

    // Validate target CGPA range
    $targetCgpa = max(2.00, min(4.00, round($targetCgpa, 2)));

    // Fetch student and program
    $stStmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.degree_level, p.degree_type AS prog_degree_type,
               p.duration AS prog_duration, p.duration_years AS prog_duration_years,
               p.total_semesters AS prog_total_semesters, p.slug AS program_slug
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $gate = can_manage_student_results($studentId);
    if (empty($gate['allowed'])) {
        return ['success' => false, 'error' => $gate['reason']];
    }

    $overrideSemesters = isset($options['total_semesters']) && (int)$options['total_semesters'] > 0
        ? (int)$options['total_semesters']
        : null;
    $struct = get_student_program_structure($student, $overrideSemesters);
    $programId = (int)($student['program_id'] ?? 1);
    $totalSemesters = (int)$struct['total_semesters'];

    $academicSession = trim((string)($options['academic_session'] ?? ''));
    if ($academicSession === '') {
        $academicSession = !empty($student['academic_session']) ? trim((string)$student['academic_session']) : (!empty($student['batch']) ? trim((string)$student['batch']) : get_current_academic_session_name('Fall 2026'));
    }

    $pubDateRaw = trim((string)($options['publication_date'] ?? ''));
    if ($pubDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $pubDateRaw)) {
        $publicationDate = $pubDateRaw;
    } elseif (!empty($student['result_publication_date'])) {
        $publicationDate = $student['result_publication_date'];
    } else {
        $publicationDate = date('Y-m-d');
    }

    // Load curriculum for this program and configured semester count
    $curriculum = get_program_semester_curriculum($programId, $totalSemesters);

    // Check existing verified results (is_estimated = 0) so we preserve them & include their points in target balance
    $evStmt = $db->prepare("SELECT semester, course_code, credit, grade_point FROM student_results WHERE student_id = ? AND is_estimated = 0");
    $evStmt->execute([$studentId]);
    $verifiedRows = $evStmt->fetchAll(PDO::FETCH_ASSOC);
    $verifiedKeys = [];
    $fixedCredits = 0.0;
    $fixedPoints = 0.0;
    foreach ($verifiedRows as $vr) {
        $key = trim((string)$vr['semester']) . ':' . trim((string)$vr['course_code']);
        $verifiedKeys[$key] = true;
        $cr = (float)$vr['credit'];
        $fixedCredits += $cr;
        $fixedPoints += ($cr * (float)$vr['grade_point']);
    }

    // Collect estimated courses to generate
    $allCourses = [];
    foreach ($curriculum as $semNum => $courses) {
        if ($semNum > $totalSemesters) continue;
        $semName = get_semester_ordinal_title((int)$semNum);
        foreach ($courses as $c) {
            $key = $semName . ':' . trim((string)$c['code']);
            if (isset($verifiedKeys[$key])) {
                continue;
            }
            $allCourses[] = [
                'semester_num'  => $semNum,
                'semester_name' => $semName,
                'course_code'   => $c['code'],
                'course_name'   => $c['name'],
                'credit'        => (float)($c['credit'] ?? 3.0),
                'full_marks'    => 100
            ];
        }
    }

    if (empty($allCourses)) {
        return ['success' => false, 'error' => 'No unverified curriculum courses remain to estimate (all subjects are already verified Official).'];
    }

    // UGC Scale valid Grade Points
    $validGps = [4.00, 3.75, 3.50, 3.25, 3.00, 2.75, 2.50, 2.25, 2.00];

    $estCredits = 0.0;
    foreach ($allCourses as $c) {
        $estCredits += $c['credit'];
    }
    $totalCredits = $fixedCredits + $estCredits;
    $targetPoints = $targetCgpa * $totalCredits;
    $neededEstGpa = $estCredits > 0 ? max(2.00, min(4.00, ($targetPoints - $fixedPoints) / $estCredits)) : $targetCgpa;

    // Closest baseline Grade Point
    $baseGp = 4.00;
    foreach ($validGps as $gp) {
        if ($neededEstGpa >= ($gp - 0.125)) {
            $baseGp = $gp;
            break;
        }
    }
    $baseIdx = array_search($baseGp, $validGps);
    if ($baseIdx === false) $baseIdx = 0;

    // 1. Seed plausible per-semester & per-subject distribution within grading ladder
    $semShiftPattern = [-1, 0, 1, 0, -1, 1, 0, 1];
    $coursePattern = [0, 1, 0, -1, 0, 1, -1, 0];

    foreach ($allCourses as $idx => &$c) {
        $semShift = $semShiftPattern[($c['semester_num'] - 1) % count($semShiftPattern)];
        $subShift = $coursePattern[$idx % count($coursePattern)];
        // Bias moderately so semester SGPAs show realistic variation while hovering near target
        $offset = (int)round(($semShift + $subShift) / 2);
        if ($offset === 0 && ($idx % 3 === 1)) {
            $offset = ($c['semester_num'] % 2 === 0) ? -1 : 1;
        }
        $chosenIdx = max(0, min(count($validGps) - 1, $baseIdx + $offset));
        $c['gp'] = $validGps[$chosenIdx];
    }
    unset($c);

    // 2. Iterative credit-weighted optimization across all estimated courses until cumulative CGPA matches target
    $courseCount = count($allCourses);
    $maxIter = max(600, $courseCount * 20);
    for ($iter = 0; $iter < $maxIter; $iter++) {
        $estPoints = 0.0;
        foreach ($allCourses as $c) {
            $estPoints += ($c['credit'] * $c['gp']);
        }
        $currentPoints = $fixedPoints + $estPoints;
        $diff = $targetPoints - $currentPoints;
        $currentCgpa = round($currentPoints / $totalCredits, 2);

        if (abs($currentCgpa - $targetCgpa) < 0.005) {
            break;
        }

        if ($diff > 0) {
            $candidates = [];
            foreach ($allCourses as $i => $c) {
                if ($c['gp'] < 4.00) $candidates[] = $i;
            }
            if (empty($candidates)) break;
            // Pick spread evenly across courses & semesters
            $pick = $candidates[($iter * 7) % count($candidates)];
            $curIdx = array_search($allCourses[$pick]['gp'], $validGps);
            if ($curIdx > 0) {
                $allCourses[$pick]['gp'] = $validGps[$curIdx - 1];
            }
        } else {
            $candidates = [];
            foreach ($allCourses as $i => $c) {
                if ($c['gp'] > 2.00) $candidates[] = $i;
            }
            if (empty($candidates)) break;
            $pick = $candidates[($iter * 7) % count($candidates)];
            $curIdx = array_search($allCourses[$pick]['gp'], $validGps);
            if ($curIdx < count($validGps) - 1) {
                $allCourses[$pick]['gp'] = $validGps[$curIdx + 1];
            }
        }
    }

    // 3. Assign plausible marks & letter grades strictly inside UGC grading scale
    foreach ($allCourses as &$c) {
        $mapped = grade_point_to_sample_marks((float)$c['gp']);
        $c['marks'] = $mapped['marks'];
        $c['letter_grade'] = $mapped['letter_grade'];
    }
    unset($c);

    // Compute actual realized CGPA & semester SGPAs
    $finalEstPoints = 0.0;
    foreach ($allCourses as $c) {
        $finalEstPoints += ($c['credit'] * $c['gp']);
    }
    $actualCgpa = ($totalCredits > 0) ? round(($fixedPoints + $finalEstPoints) / $totalCredits, 2) : 0.00;

    // 4. Persist atomically without duplicating rows
    $delStmt = $db->prepare("DELETE FROM student_results WHERE student_id = ? AND is_estimated = 1");
    $delStmt->execute([$studentId]);

    $pubVal = $publishToPortal ? 1 : 0;
    $pubStatus = $publishToPortal ? 'Published' : 'Draft';
    $pubTimestamp = $publishToPortal ? ($publicationDate . ' ' . date('H:i:s')) : null;
    $reason = "ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA " . number_format($targetCgpa, 2);

    $insStmt = $db->prepare("
        INSERT INTO student_results 
        (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, academic_session, is_published, is_estimated, result_type, publish_status, published_at, publication_date, published_by, correction_reason)
        VALUES (?, ?, ?, ?, ?, 100, ?, ?, ?, 'Semester Final Exam', ?, ?, 1, 'Estimated', ?, ?, ?, ?, ?)
    ");

    $insertedCount = 0;
    foreach ($allCourses as $c) {
        $insStmt->execute([
            $studentId,
            $c['semester_name'],
            $c['course_code'],
            $c['course_name'],
            $c['credit'],
            $c['marks'],
            $c['letter_grade'],
            $c['gp'],
            $academicSession,
            $pubVal,
            $pubStatus,
            $pubTimestamp,
            $publicationDate,
            $adminId,
            $reason
        ]);
        $insertedCount++;
    }

    // Update student's structural & result metadata (NEVER alter passing_year!)
    $expCalc = calculate_expected_passing_year($academicSession, (float)$struct['duration_years']);
    $expectedPassingYear = $expCalc ? (int)$expCalc['expected_year'] : (!empty($student['expected_passing_year']) ? (int)$student['expected_passing_year'] : null);

    $updSt = $db->prepare("
        UPDATE students
        SET degree_type = ?,
            academic_session = ?,
            duration_years = ?,
            program_duration = ?,
            total_semesters = ?,
            total_credits_required = ?,
            expected_passing_year = COALESCE(?, expected_passing_year),
            result_publication_date = ?
        WHERE id = ?
    ");
    $updSt->execute([
        $struct['degree_type'],
        $academicSession,
        (float)$struct['duration_years'],
        $struct['duration_label'],
        $totalSemesters,
        (int)round($totalCredits),
        $expectedPassingYear,
        $publicationDate,
        $studentId
    ]);

    // Recalculate official student CGPA + sync student_semester_results & result_status
    $metrics = recalculate_student_cgpa($studentId);

    // Keep exact realized CGPA when published, and preserve 0.00 published CGPA when kept in Draft
    if ($publishToPortal && abs((float)$metrics['cgpa'] - $targetCgpa) > 0.01 && abs($actualCgpa - $targetCgpa) <= 0.01) {
        $db->prepare("UPDATE students SET cgpa = ?, credits_completed = ? WHERE id = ?")
           ->execute([$actualCgpa, (int)round($totalCredits), $studentId]);
    }

    // Dispatch clear student in-app notification with mandatory disclaimer
    create_student_notification(
        $studentId,
        "Academic Results Projected (Estimated)",
        "Your {$totalSemesters}-semester estimated results ({$academicSession}) have been generated for cumulative CGPA " . number_format($actualCgpa, 2) . ". IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.",
        'result'
    );

    log_audit('ESTIMATED_RESULTS_GENERATED', "Generated {$totalSemesters}-semester estimated results for student #{$studentId} ({$student['full_name']}) [Session: {$academicSession}, PubDate: {$publicationDate}, Target CGPA: {$targetCgpa}, Realized CGPA: {$actualCgpa}]", $adminId);

    $summaryAfter = get_student_academic_summary($studentId, false);

    return [
        'success'            => true,
        'student_id'         => $student['student_id'],
        'student_name'       => $student['full_name'],
        'target_cgpa'        => $targetCgpa,
        'realized_cgpa'      => $actualCgpa,
        'degree_level'       => $struct['degree_level'],
        'degree_type'        => $struct['degree_type'],
        'academic_session'   => $academicSession,
        'publication_date'   => $publicationDate,
        'total_semesters'    => $totalSemesters,
        'total_credits'      => $totalCredits,
        'inserted_courses'   => $insertedCount,
        'semester_gpas'      => $summaryAfter['semester_gpas'] ?? [],
    ];
}

/**
 * Edit an individual semester's GPA target by rebalancing its course marks/grades cleanly
 * and recalculating cumulative CGPA without recreating other semesters.
 */
function update_semester_cgpa_target(int $studentId, int $semesterNumber, float $newSemesterGpa, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $gate = can_manage_student_results($studentId);
    if (empty($gate['allowed'])) {
        return ['success' => false, 'error' => $gate['reason']];
    }

    $stStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $struct = get_student_program_structure($student);
    $totalSemesters = (int)$struct['total_semesters'];
    if ($semesterNumber < 1 || $semesterNumber > $totalSemesters) {
        return ['success' => false, 'error' => "Invalid semester number {$semesterNumber} for {$totalSemesters}-semester program."];
    }

    $newSemesterGpa = max(2.00, min(4.00, round($newSemesterGpa, 2)));
    $semTitle = get_semester_ordinal_title($semesterNumber);

    // Fetch existing rows for this semester
    $rStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
    $rStmt->execute([$studentId]);
    $allRows = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    $semRows = [];
    foreach ($allRows as $r) {
        if (get_semester_number((string)$r['semester']) === $semesterNumber) {
            $semRows[] = $r;
        }
    }

    // If this semester had no rows yet, seed its standard curriculum courses first
    if (empty($semRows)) {
        $curr = get_program_semester_curriculum((int)$student['program_id'], $totalSemesters);
        $semCurr = $curr[$semesterNumber] ?? [];
        if (empty($semCurr)) {
            return ['success' => false, 'error' => "No curriculum found for {$semTitle}."];
        }
        $pubVal = (isset($student['result_status']) && $student['result_status'] === 'Published') ? 1 : 0;
        $pubStat = $pubVal ? 'Published' : 'Draft';
        $sess = !empty($student['academic_session']) ? $student['academic_session'] : (!empty($student['batch']) ? $student['batch'] : get_current_academic_session_name('Fall 2026'));
        $insStmt = $db->prepare("
            INSERT INTO student_results
            (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, academic_session, is_published, is_estimated, result_type, publish_status, published_at, publication_date, published_by, correction_reason)
            VALUES (?, ?, ?, ?, ?, 100, 70.0, 'A-', 3.50, 'Semester Final Exam', ?, ?, 1, 'Estimated', ?, ?, ?, ?, 'ESTIMATED / GENERATED')
        ");
        $pubAt = $pubVal ? date('Y-m-d H:i:s') : null;
        $pubDt = !empty($student['result_publication_date']) ? $student['result_publication_date'] : date('Y-m-d');
        foreach ($semCurr as $sc) {
            $insStmt->execute([$studentId, $semTitle, $sc['code'], $sc['name'], (float)$sc['credit'], $sess, $pubVal, $pubStat, $pubAt, $pubDt, $adminId]);
        }
        $rStmt->execute([$studentId]);
        foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (get_semester_number((string)$r['semester']) === $semesterNumber) {
                $semRows[] = $r;
            }
        }
    }

    $validGps = [4.00, 3.75, 3.50, 3.25, 3.00, 2.75, 2.50, 2.25, 2.00];
    $baseGp = 4.00;
    foreach ($validGps as $gp) {
        if ($newSemesterGpa >= ($gp - 0.125)) {
            $baseGp = $gp;
            break;
        }
    }
    $baseIdx = array_search($baseGp, $validGps);
    if ($baseIdx === false) $baseIdx = 0;

    $totalSemCredits = 0.0;
    foreach ($semRows as $idx => &$sr) {
        $totalSemCredits += (float)$sr['credit'];
        $offset = ($idx % 2 === 0) ? 0 : (($idx % 4 === 1) ? 1 : -1);
        $sr['new_gp'] = $validGps[max(0, min(count($validGps) - 1, $baseIdx + $offset))];
    }
    unset($sr);

    $targetSemPts = $newSemesterGpa * $totalSemCredits;
    for ($iter = 0; $iter < 300; $iter++) {
        $curPts = 0.0;
        foreach ($semRows as $sr) {
            $curPts += ((float)$sr['credit'] * $sr['new_gp']);
        }
        $curGpa = ($totalSemCredits > 0) ? round($curPts / $totalSemCredits, 2) : 0.00;
        if (abs($curGpa - $newSemesterGpa) < 0.005) {
            break;
        }
        $diff = $targetSemPts - $curPts;
        if ($diff > 0) {
            $cands = [];
            foreach ($semRows as $i => $sr) if ($sr['new_gp'] < 4.00) $cands[] = $i;
            if (empty($cands)) break;
            $p = $cands[$iter % count($cands)];
            $ci = array_search($semRows[$p]['new_gp'], $validGps);
            if ($ci > 0) $semRows[$p]['new_gp'] = $validGps[$ci - 1];
        } else {
            $cands = [];
            foreach ($semRows as $i => $sr) if ($sr['new_gp'] > 2.00) $cands[] = $i;
            if (empty($cands)) break;
            $p = $cands[$iter % count($cands)];
            $ci = array_search($semRows[$p]['new_gp'], $validGps);
            if ($ci < count($validGps) - 1) $semRows[$p]['new_gp'] = $validGps[$ci + 1];
        }
    }

    $note = trim($reason) !== '' ? trim($reason) : "Semester {$semesterNumber} GPA adjusted to " . number_format($newSemesterGpa, 2);
    $updRow = $db->prepare("
        UPDATE student_results
        SET marks = ?, letter_grade = ?, grade_point = ?, correction_reason = ?
        WHERE id = ? AND student_id = ?
    ");

    foreach ($semRows as $sr) {
        $mapped = grade_point_to_sample_marks((float)$sr['new_gp']);
        $oldVal = "{$sr['marks']} ({$sr['letter_grade']}, {$sr['grade_point']})";
        $newVal = "{$mapped['marks']} ({$mapped['letter_grade']}, {$sr['new_gp']})";
        $fullReason = (!empty($sr['is_estimated']) ? "ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. " : '') . $note;
        $updRow->execute([$mapped['marks'], $mapped['letter_grade'], $sr['new_gp'], $fullReason, (int)$sr['id'], $studentId]);
        log_result_modification((int)$sr['id'], $studentId, $adminId, (string)$sr['course_code'], (string)$sr['semester'], 'Semester GPA Adjustment', $oldVal, $newVal, $note);
    }

    $metrics = recalculate_student_cgpa($studentId);
    log_audit('SEMESTER_GPA_UPDATED', "Admin #{$adminId} adjusted Semester {$semesterNumber} GPA target to {$newSemesterGpa} for student #{$studentId} ({$student['student_id']}). New CGPA: {$metrics['cgpa']}", $adminId);

    return [
        'success' => true,
        'semester_number' => $semesterNumber,
        'semester_name' => $semTitle,
        'new_cgpa' => $metrics['cgpa'],
    ];
}

/**
 * Verify all estimated results across all semesters of a student as Official Examination Records
 */
function verify_all_student_results_official(int $studentId, int $adminId = 1): array {
    $db = getDb();
    $stmt = $db->prepare("
        UPDATE student_results
        SET is_estimated = 0,
            result_type = 'Verified',
            publish_status = 'Published',
            is_published = 1,
            published_by = ?,
            published_at = COALESCE(published_at, NOW()),
            correction_reason = 'Verified by administrator as official examination record'
        WHERE student_id = ? AND is_estimated = 1
    ");
    $stmt->execute([$adminId, $studentId]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ? AND title = 'Academic Results Projected (Estimated)'")->execute([$studentId]);
        log_audit('ALL_RESULTS_VERIFIED_OFFICIAL', "Admin #{$adminId} verified {$affected} estimated result(s) as official for student #{$studentId}", $adminId);
        create_student_notification(
            $studentId,
            "All Academic Results Officially Verified",
            "Your academic examination transcript has been verified as an official university record by the Office of the Controller of Examinations.",
            'result_published'
        );
    }
    $metrics = recalculate_student_cgpa($studentId);

    return ['success' => true, 'verified_count' => $affected, 'cgpa' => $metrics['cgpa']];
}

/**
 * Suspend a student's access to view results/transcript while keeping account login and Profile accessible
 */
function suspend_student_result_access(int $studentId, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $cleanReason = trim($reason) !== '' ? trim($reason) : 'Result access temporarily suspended by university administration';
    $db->prepare("
        UPDATE students
        SET result_access_status = 'Suspended',
            result_suspended_reason = ?,
            result_status = 'Suspended'
        WHERE id = ?
    ")->execute([$cleanReason, $studentId]);

    log_audit('STUDENT_RESULT_ACCESS_SUSPENDED', "Admin #{$adminId} suspended result access for student #{$studentId} ({$student['student_id']} - {$student['full_name']}). Reason: {$cleanReason}", $adminId);

    return ['success' => true, 'student_id' => $student['student_id'], 'full_name' => $student['full_name']];
}

/**
 * Restore a student's suspended result access cleanly
 */
function restore_student_result_access(int $studentId, int $adminId = 1): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $db->prepare("
        UPDATE students
        SET result_access_status = 'Active',
            result_suspended_reason = NULL
        WHERE id = ?
    ")->execute([$studentId]);

    sync_student_semester_results($studentId);

    log_audit('STUDENT_RESULT_ACCESS_RESTORED', "Admin #{$adminId} restored result access for student #{$studentId} ({$student['student_id']} - {$student['full_name']}).", $adminId);
    if ($student['status'] !== 'Deleted') {
        create_student_notification(
            $studentId,
            'Result Portal Access Restored',
            'Your access to view academic results and semester transcripts has been restored by university administration.',
            'account_status'
        );
    }

    return ['success' => true, 'student_id' => $student['student_id'], 'full_name' => $student['full_name']];
}

/**
 * Delete ALL academic results for a student while strictly keeping the student's account, login credentials, and profile intact
 */
function delete_student_all_results(int $studentId, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $cleanReason = trim($reason) !== '' ? trim($reason) : 'All academic results cleared by administrator (student account preserved)';

    try {
        $db->beginTransaction();

        $stStmt = $db->prepare("SELECT id, student_id, full_name, status, result_access_status FROM students WHERE id = ? LIMIT 1 FOR UPDATE");
        $stStmt->execute([$studentId]);
        $student = $stStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Student record not found.'];
        }

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM student_results WHERE student_id = ?");
        $cntStmt->execute([$studentId]);
        $deletedCount = (int)$cntStmt->fetchColumn();

        $db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$studentId]);
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$studentId]);

        $nextResultStatus = ($student['result_access_status'] === 'Suspended') ? 'Suspended' : 'Pending';
        $db->prepare("
            UPDATE students
            SET cgpa = 0.00,
                credits_completed = 0,
                result_publication_date = NULL,
                result_status = ?
            WHERE id = ?
        ")->execute([$nextResultStatus, $studentId]);

        $db->commit();

        log_result_modification(null, $studentId, $adminId, 'ALL', 'All Semesters', 'Delete All Results', "{$deletedCount} records", '0 records', $cleanReason);
        log_audit('STUDENT_ALL_RESULTS_DELETED', "Admin #{$adminId} deleted {$deletedCount} result record(s) for student #{$studentId} ({$student['student_id']} - {$student['full_name']}). Account kept intact. Reason: {$cleanReason}", $adminId);

        sync_student_semester_results($studentId);

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'student_id' => $student['student_id'],
            'full_name' => $student['full_name'],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[RMU delete_all_student_results] ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to delete results due to a database error. Please try again.'];
    }
}

/**
 * Administrator Verification Workflow: Verify an estimated semester or result as official
 */
function verify_semester_results_as_official(int $studentId, string $semester, int $adminId = 1): int {
    $db = getDb();
    $stmt = $db->prepare("
        UPDATE student_results 
        SET is_estimated = 0, 
            result_type = 'Verified', 
            publish_status = 'Published',
            is_published = 1,
            published_by = ?, 
            published_at = COALESCE(published_at, NOW()),
            correction_reason = 'Verified by administrator as official examination record'
        WHERE student_id = ? AND semester = ? AND is_estimated = 1
    ");
    $stmt->execute([$adminId, $studentId, $semester]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        $remEst = $db->prepare("SELECT COUNT(*) FROM student_results WHERE student_id = ? AND is_estimated = 1");
        $remEst->execute([$studentId]);
        if ((int)$remEst->fetchColumn() === 0) {
            $db->prepare("DELETE FROM student_notifications WHERE student_id = ? AND title = 'Academic Results Projected (Estimated)'")->execute([$studentId]);
        }
        log_audit('RESULTS_VERIFIED_OFFICIAL', "Admin verified {$affected} estimated course result(s) in {$semester} as official for student #{$studentId}", $adminId);
        create_student_notification(
            $studentId,
            "Official Results Verified",
            "Your academic results for {$semester} have been officially verified and confirmed by the Office of the Controller of Examinations.",
            'result'
        );
        recalculate_student_cgpa($studentId);
    }
    return $affected;
}

/**
 * ========================================================
 * STUDENT ACCOUNT CONTROL & RESULT VISIBILITY HELPERS
 * ========================================================
 */

/**
 * Build consistent badge descriptors for Student Account & Result Visibility statuses
 */
function get_student_status_badges(array $student, ?int $totalResultsCount = null, ?int $publishedResultsCount = null): array {
    $status = trim((string)($student['status'] ?? 'Active'));
    $resultsVisible = !isset($student['results_visible']) || (int)$student['results_visible'] === 1;
    $resultsSuspended = isset($student['result_access_status']) && $student['result_access_status'] === 'Suspended';

    $estimatedCount = 0;
    if ($totalResultsCount === null || $publishedResultsCount === null) {
        try {
            $db = getDb();
            $stId = (int)($student['id'] ?? 0);
            if ($stId > 0) {
                $q = $db->prepare("SELECT COUNT(*) AS total_cnt, SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS pub_cnt, SUM(CASE WHEN is_estimated = 1 THEN 1 ELSE 0 END) AS est_cnt FROM student_results WHERE student_id = ?");
                $q->execute([$stId]);
                $counts = $q->fetch(PDO::FETCH_ASSOC);
                $totalResultsCount = (int)($counts['total_cnt'] ?? 0);
                $publishedResultsCount = (int)($counts['pub_cnt'] ?? 0);
                $estimatedCount = (int)($counts['est_cnt'] ?? 0);
            } else {
                $totalResultsCount = 0;
                $publishedResultsCount = 0;
            }
        } catch (Throwable $e) {
            $totalResultsCount = 0;
            $publishedResultsCount = 0;
        }
    }

    $accountMap = [
        'Active'      => ['label' => 'Active',      'bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#86efac', 'icon' => 'fa-check-circle'],
        'Graduated'   => ['label' => 'Graduated',   'bg' => '#dbeafe', 'color' => '#1d4ed8', 'border' => '#93c5fd', 'icon' => 'fa-user-graduate'],
        'Deactivated' => ['label' => 'Deactivated', 'bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fcd34d', 'icon' => 'fa-user-slash'],
        'Suspended'   => ['label' => 'Suspended',   'bg' => '#ffedd5', 'color' => '#c2410c', 'border' => '#fdba74', 'icon' => 'fa-ban'],
        'Deleted'     => ['label' => 'Deleted',     'bg' => '#fee2e2', 'color' => '#b91c1c', 'border' => '#fca5a5', 'icon' => 'fa-trash-alt'],
    ];
    $accBadge = $accountMap[$status] ?? $accountMap['Active'];

    if ($resultsSuspended) {
        $resBadge = ['label' => 'Result Access Suspended', 'key' => 'suspended', 'bg' => '#ffedd5', 'color' => '#9a3412', 'border' => '#fdba74', 'icon' => 'fa-user-lock'];
    } elseif (!$resultsVisible) {
        $resBadge = ['label' => 'Result Hidden', 'key' => 'hidden', 'bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#fca5a5', 'icon' => 'fa-eye-slash'];
    } elseif ($publishedResultsCount > 0) {
        $resBadge = ['label' => 'Result Published', 'key' => 'published', 'bg' => '#dcfce7', 'color' => '#166534', 'border' => '#86efac', 'icon' => 'fa-globe'];
    } else {
        $resBadge = ['label' => 'Pending', 'key' => 'pending', 'bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#cbd5e1', 'icon' => 'fa-clock'];
    }

    return [
        'account' => $accBadge,
        'result' => $resBadge,
        'total_results' => (int)$totalResultsCount,
        'published_results' => (int)$publishedResultsCount,
        'estimated_results' => (int)$estimatedCount,
        'draft_results' => max(0, (int)$totalResultsCount - (int)$publishedResultsCount),
        'results_visible' => $resultsVisible,
        'results_suspended' => $resultsSuspended,
    ];
}

/**
 * Activate or Reactivate a student account cleanly
 */
function activate_student_account(int $studentId, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }
    if ($student['status'] === 'Deleted') {
        return ['success' => false, 'error' => 'This student account was permanently deleted and its credentials were revoked under university data retention policy.'];
    }

    $wasDeactivated = in_array($student['status'], ['Deactivated', 'Suspended'], true);
    $upd = $db->prepare("
        UPDATE students
        SET status = 'Active',
            session_version = session_version + 1,
            deactivated_at = NULL,
            deactivated_by = NULL,
            deactivation_reason = NULL
        WHERE id = ?
    ");
    $upd->execute([$studentId]);

    $note = $reason !== '' ? $reason : 'Account activated / reactivated by administrator';
    $actionName = $wasDeactivated ? 'STUDENT_ACCOUNT_REACTIVATED' : 'STUDENT_ACCOUNT_ACTIVATED';
    log_audit($actionName, "Admin #{$adminId} set student #{$studentId} ({$student['student_id']} - {$student['full_name']}) status to Active. Note: {$note}", $adminId);

    create_student_notification(
        $studentId,
        'Student Account Activated',
        'Your university portal account is active. You may log in to view your academic profile and published examination results.',
        'account_status'
    );

    return [
        'success' => true,
        'student_id' => $student['student_id'],
        'full_name' => $student['full_name'],
        'status' => 'Active',
    ];
}

/**
 * Deactivate a student account while preserving all academic records & invalidating sessions immediately
 */
function deactivate_student_account(int $studentId, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }
    if ($student['status'] === 'Deleted') {
        return ['success' => false, 'error' => 'Cannot deactivate an account that has already been permanently deleted.'];
    }

    $cleanReason = trim($reason) !== '' ? trim($reason) : 'Temporarily deactivated by university administration';
    $upd = $db->prepare("
        UPDATE students
        SET status = 'Deactivated',
            session_version = session_version + 1,
            deactivated_at = NOW(),
            deactivated_by = ?,
            deactivation_reason = ?
        WHERE id = ?
    ");
    $upd->execute([$adminId, $cleanReason, $studentId]);
    clear_admin_temp_student_password($studentId);

    log_audit('STUDENT_ACCOUNT_DEACTIVATED', "Admin #{$adminId} deactivated student #{$studentId} ({$student['student_id']} - {$student['full_name']}). Sessions invalidated & academic records preserved. Reason: {$cleanReason}", $adminId);

    return [
        'success' => true,
        'student_id' => $student['student_id'],
        'full_name' => $student['full_name'],
        'status' => 'Deactivated',
    ];
}

/**
 * Permanently Delete Student Account Safely:
 * - Preserves retention snapshot in student_retention_archives & preserves relational audit/academic rows
 * - Revokes student login password, linked admission password & active session version immediately
 * - Never affects any other student record
 */
function delete_student_account_safely(int $studentId, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $cleanReason = trim($reason) !== '' ? trim($reason) : 'Permanent account deletion authorized by administrator';

    try {
        $db->beginTransaction();

        $stStmt = $db->prepare("
            SELECT s.*, p.title_en AS program_title, d.name_en AS department_name
            FROM students s
            LEFT JOIN programs p ON s.program_id = p.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stStmt->execute([$studentId]);
        $student = $stStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Student account not found.'];
        }
        if ($student['status'] === 'Deleted') {
            $db->rollBack();
            return ['success' => false, 'error' => 'This student account has already been permanently deleted.'];
        }

        // Collect all student-scoped related academic & audit data prior to revocation
        $resStmt = $db->prepare("SELECT * FROM student_results WHERE student_id = ? ORDER BY id ASC");
        $resStmt->execute([$studentId]);
        $results = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        $histStmt = $db->prepare("SELECT * FROM result_modification_history WHERE student_id = ? ORDER BY id ASC");
        $histStmt->execute([$studentId]);
        $history = $histStmt->fetchAll(PDO::FETCH_ASSOC);

        $courseStmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ? ORDER BY id ASC");
        $courseStmt->execute([$studentId]);
        $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

        $feeStmt = $db->prepare("SELECT * FROM student_fees WHERE student_id = ? ORDER BY id ASC");
        $feeStmt->execute([$studentId]);
        $fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

        $reqStmt = $db->prepare("SELECT * FROM result_requests WHERE student_id = ? ORDER BY id ASC");
        $reqStmt->execute([$studentId]);
        $requests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

        $studentProfileCopy = $student;
        unset($studentProfileCopy['password_hash']);

        $snapshot = [
            'archived_at' => date('Y-m-d H:i:s'),
            'archived_by_admin_id' => $adminId,
            'deletion_reason' => $cleanReason,
            'student' => $studentProfileCopy,
            'results' => $results,
            'result_modification_history' => $history,
            'courses' => $courses,
            'fees' => $fees,
            'result_requests' => $requests,
        ];
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Store immutable retention snapshot
        $archStmt = $db->prepare("
            INSERT INTO student_retention_archives (
                student_pk_id, student_id, roll_number, application_number, username,
                full_name, email, phone, program_title, department_name, batch,
                final_cgpa, credits_completed, results_count, snapshot_json,
                deleted_by, deletion_reason, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $archStmt->execute([
            $studentId,
            $student['student_id'],
            $student['roll_number'] ?? null,
            $student['application_number'] ?? null,
            $student['username'] ?? $student['student_id'],
            $student['full_name'],
            $student['email'],
            $student['phone'],
            $student['program_title'] ?? null,
            $student['department_name'] ?? null,
            $student['batch'],
            (float)($student['cgpa'] ?? 0.00),
            (int)($student['credits_completed'] ?? 0),
            count($results),
            $snapshotJson ?: '{}',
            $adminId,
            $cleanReason
        ]);
        $archiveId = (int)$db->lastInsertId();

        // Revoke login credentials & access immediately while preserving relational history row
        ensure_student_password_schema($db);
        $revokedSecret = '!DELETED_REVOKED_' . bin2hex(random_bytes(8)) . '!';
        $delUpd = $db->prepare("
            UPDATE students
            SET status = 'Deleted',
                password_hash = ?,
                password_updated_at = NOW(),
                password_updated_by = ?,
                must_change_password = 0,
                results_visible = 0,
                results_hidden_reason = 'Account permanently deleted',
                session_version = session_version + 1,
                deleted_at = NOW(),
                deleted_by = ?,
                deletion_reason = ?
            WHERE id = ?
        ");
        $delUpd->execute([$revokedSecret, $adminId, $adminId, $cleanReason, $studentId]);
        clear_admin_temp_student_password($studentId);

        // Also revoke plain portal password on admissions record if linked
        if (!empty($student['student_id'])) {
            $admRev = $db->prepare("UPDATE admissions SET portal_password = NULL WHERE student_id = ?");
            $admRev->execute([$student['student_id']]);
        }

        // Clear unread alerts cleanly
        $db->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ?")->execute([$studentId]);

        $db->commit();

        log_audit(
            'STUDENT_ACCOUNT_DELETED',
            "Admin #{$adminId} permanently deleted student account #{$studentId} ({$student['student_id']} - {$student['full_name']}). Archive #{$archiveId} created with " . count($results) . " retained academic records. Reason: {$cleanReason}",
            $adminId
        );

        return [
            'success' => true,
            'archive_id' => $archiveId,
            'student_id' => $student['student_id'],
            'full_name' => $student['full_name'],
            'results_retained' => count($results),
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[RMU delete_student_account_safely] ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to delete student account due to a database error. Please try again.'];
    }
}

/**
 * Permanently Delete a Student Record and All Student-Scoped Child Records from the Database:
 * - Uses strict primary key ID ($studentId) inside an atomic transaction
 * - Safely removes child records & unlinks admissions referencing this student without affecting unrelated students
 * - Never leaves the row behind or fakes deletion in the UI
 */
function permanently_delete_student_record(int $studentId, int $adminId = 1, string $reason = ''): array {
    if ($studentId <= 0) {
        return ['success' => false, 'code' => 400, 'error' => 'Invalid student database ID.'];
    }

    $db = getDb();
    $cleanReason = trim($reason);

    try {
        $db->beginTransaction();

        $stStmt = $db->prepare("
            SELECT id, student_id, full_name, avatar
            FROM students
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stStmt->execute([$studentId]);
        $student = $stStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $db->rollBack();
            return ['success' => false, 'code' => 404, 'error' => 'Student record not found or already deleted.'];
        }

        // Remove all student-scoped child records cleanly before removing parent student row
        $childDeletes = [
            "DELETE FROM result_modification_history WHERE student_id = ?",
            "DELETE FROM result_requests WHERE student_id = ?",
            "DELETE FROM student_notifications WHERE student_id = ?",
            "DELETE FROM student_fees WHERE student_id = ?",
            "DELETE FROM student_courses WHERE student_id = ?",
            "DELETE FROM student_semester_results WHERE student_id = ?",
            "DELETE FROM student_results WHERE student_id = ?",
            "DELETE FROM academic_edit_history WHERE entity_type = 'student' AND entity_id = ?",
            "DELETE FROM student_retention_archives WHERE student_pk_id = ?",
        ];
        foreach ($childDeletes as $sql) {
            $stmtChild = $db->prepare($sql);
            $stmtChild->execute([$studentId]);
        }

        // Safely detach any linked admissions record without deleting admission application history
        $studentCode = trim((string)($student['student_id'] ?? ''));
        if ($studentCode !== '') {
            $unlinkAdm = $db->prepare("UPDATE admissions SET student_id = NULL, portal_password = NULL WHERE student_id = ?");
            $unlinkAdm->execute([$studentCode]);
        }

        $delStudent = $db->prepare("DELETE FROM students WHERE id = ? LIMIT 1");
        $delStudent->execute([$studentId]);

        if ($delStudent->rowCount() !== 1) {
            $db->rollBack();
            return ['success' => false, 'code' => 500, 'error' => 'Failed to delete student record from database.'];
        }

        $db->commit();

        if (function_exists('clear_admin_temp_student_password')) {
            clear_admin_temp_student_password($studentId);
        }

        // Safely unlink orphaned avatar image only if no student or admission record references it
        $avatarFile = basename(trim((string)($student['avatar'] ?? '')));
        if ($avatarFile !== '' && $avatarFile !== '.' && $avatarFile !== '..') {
            try {
                $chkAvSt = $db->prepare("SELECT COUNT(*) FROM students WHERE avatar = ?");
                $chkAvSt->execute([$avatarFile]);
                $chkAvAdm = $db->prepare("SELECT COUNT(*) FROM admissions WHERE photo = ?");
                $chkAvAdm->execute([$avatarFile]);
                if ((int)$chkAvSt->fetchColumn() === 0 && (int)$chkAvAdm->fetchColumn() === 0) {
                    $avatarPath = UPLOADS_PATH . '/students/' . $avatarFile;
                    if (is_file($avatarPath)) {
                        @unlink($avatarPath);
                    }
                }
            } catch (Throwable $ignore) {
                // Non-critical file cleanup error should never reverse DB deletion result
            }
        }

        $auditMsg = "Admin #{$adminId} permanently deleted student #{$studentId} ({$studentCode} - {$student['full_name']}) from database.";
        if ($cleanReason !== '') {
            $auditMsg .= " Reason: {$cleanReason}";
        }
        log_audit('STUDENT_PERMANENTLY_DELETED', $auditMsg, $adminId);

        return [
            'success'    => true,
            'code'       => 200,
            'deleted_id' => $studentId,
            'student_id' => $studentCode,
            'full_name'  => (string)$student['full_name'],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[RMU permanently_delete_student_record] ' . $e->getMessage());
        return ['success' => false, 'code' => 500, 'error' => 'Database error while deleting student record. Please try again.'];
    }
}

/**
 * Permanently Delete a Degree Program Record Safely:
 * - Detaches nullable program_id references from students & admissions without deleting any student records
 * - Cleans program-scoped academic edit history rows inside an atomic transaction
 */
function permanently_delete_program_record(int $programId, int $adminId = 1): array {
    if ($programId <= 0) {
        return ['success' => false, 'code' => 400, 'error' => 'Invalid program database ID.'];
    }

    $db = getDb();

    try {
        $db->beginTransaction();

        $pStmt = $db->prepare("SELECT id, title_en FROM programs WHERE id = ? LIMIT 1 FOR UPDATE");
        $pStmt->execute([$programId]);
        $prog = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prog) {
            $db->rollBack();
            return ['success' => false, 'code' => 404, 'error' => 'Degree program not found or already deleted.'];
        }

        // Safely detach program references without deleting unrelated student or admission data
        $db->prepare("UPDATE students SET program_id = NULL WHERE program_id = ?")->execute([$programId]);
        $db->prepare("UPDATE admissions SET program_id = NULL WHERE program_id = ?")->execute([$programId]);
        $db->prepare("DELETE FROM academic_edit_history WHERE entity_type = 'program' AND entity_id = ?")->execute([$programId]);

        $delProg = $db->prepare("DELETE FROM programs WHERE id = ? LIMIT 1");
        $delProg->execute([$programId]);

        if ($delProg->rowCount() !== 1) {
            $db->rollBack();
            return ['success' => false, 'code' => 500, 'error' => 'Failed to delete degree program from database.'];
        }

        $db->commit();

        log_audit('PROGRAM_DELETED', "Admin #{$adminId} permanently deleted program #{$programId}: {$prog['title_en']}", $adminId);

        return [
            'success'    => true,
            'code'       => 200,
            'deleted_id' => $programId,
            'title_en'   => (string)$prog['title_en'],
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[RMU permanently_delete_program_record] ' . $e->getMessage());
        return ['success' => false, 'code' => 500, 'error' => 'Database error while deleting degree program. Please try again.'];
    }
}

/**
 * Show or Hide published academic results from Student Portal view WITHOUT modifying underlying academic data
 */
function set_student_result_visibility(int $studentId, bool $visible, int $adminId = 1, string $reason = ''): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $visVal = $visible ? 1 : 0;
    $hiddenReason = $visible ? null : (trim($reason) !== '' ? trim($reason) : 'Results temporarily hidden by Controller of Examinations');

    $upd = $db->prepare("UPDATE students SET results_visible = ?, results_hidden_reason = ? WHERE id = ?");
    $upd->execute([$visVal, $hiddenReason, $studentId]);

    $action = $visible ? 'RESULTS_SHOWN' : 'RESULTS_HIDDEN';
    $detail = $visible
        ? "Admin #{$adminId} enabled Student Portal result visibility (Show Result) for student #{$studentId} ({$student['student_id']} - {$student['full_name']})."
        : "Admin #{$adminId} hid Student Portal results (Hide Result) for student #{$studentId} ({$student['student_id']} - {$student['full_name']}). Academic records preserved. Note: {$hiddenReason}";
    log_audit($action, $detail, $adminId);

    if ($visible && $student['status'] !== 'Deleted') {
        create_student_notification(
            $studentId,
            'Academic Results Visible',
            'Your academic results and grade transcripts are now visible in your Student Portal.',
            'result_published'
        );
    }

    return [
        'success' => true,
        'results_visible' => $visVal,
        'student_id' => $student['student_id'],
        'full_name' => $student['full_name'],
    ];
}

/**
 * Publish or Unpublish a student's results (all semesters or a specific semester)
 */
function set_student_results_publication(int $studentId, bool $publish, ?string $semesterName = null, int $adminId = 1): array {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, student_id, full_name, status, cgpa FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $trimmedSem = ($semesterName !== null) ? trim($semesterName) : '';
    $targetIds = [];

    if ($trimmedSem !== '') {
        $exactStmt = $db->prepare("SELECT id FROM student_results WHERE student_id = ? AND semester = ?");
        $exactStmt->execute([$studentId, $trimmedSem]);
        $targetIds = array_map('intval', $exactStmt->fetchAll(PDO::FETCH_COLUMN));

        if (empty($targetIds)) {
            $reqSemNum = get_semester_number($trimmedSem);
            if ($reqSemNum >= 1 && $reqSemNum <= 12) {
                $allSt = $db->prepare("SELECT id, semester FROM student_results WHERE student_id = ?");
                $allSt->execute([$studentId]);
                foreach ($allSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if (get_semester_number((string)$r['semester']) === $reqSemNum) {
                        $targetIds[] = (int)$r['id'];
                    }
                }
            }
        }

        if ($publish && empty($targetIds)) {
            return [
                'success' => false,
                'error' => "Cannot publish '{$trimmedSem}': No saved subject results exist for this semester yet.",
                'affected' => 0,
                'published' => false,
                'cgpa' => (float)($student['cgpa'] ?? 0.00),
            ];
        }
    }

    if ($trimmedSem !== '' && !empty($targetIds)) {
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        if ($publish) {
            $sql = "UPDATE student_results SET is_published = 1, publish_status = 'Published', published_at = NOW(), published_by = ? WHERE student_id = ? AND id IN ({$placeholders})";
            $params = array_merge([$adminId, $studentId], $targetIds);
        } else {
            $sql = "UPDATE student_results SET is_published = 0, publish_status = 'Draft', published_at = NULL WHERE student_id = ? AND id IN ({$placeholders})";
            $params = array_merge([$studentId], $targetIds);
        }
        $upd = $db->prepare($sql);
        $upd->execute($params);
        $affected = $upd->rowCount();
    } elseif ($trimmedSem !== '') {
        $affected = 0;
    } else {
        if ($publish) {
            $upd = $db->prepare("UPDATE student_results SET is_published = 1, publish_status = 'Published', published_at = NOW(), published_by = ? WHERE student_id = ?");
            $upd->execute([$adminId, $studentId]);
        } else {
            $upd = $db->prepare("UPDATE student_results SET is_published = 0, publish_status = 'Draft', published_at = NULL WHERE student_id = ?");
            $upd->execute([$studentId]);
        }
        $affected = $upd->rowCount();
    }

    $metrics = recalculate_student_cgpa($studentId);
    $scopeLabel = ($trimmedSem !== '') ? $trimmedSem : 'all semesters';

    if ($publish) {
        log_audit('RESULTS_PUBLISHED', "Admin #{$adminId} published {$affected} result record(s) ({$scopeLabel}) for student #{$studentId} ({$student['student_id']}). CGPA: {$metrics['cgpa']}", $adminId);
        if ($affected > 0 && $student['status'] !== 'Deleted') {
            create_student_notification(
                $studentId,
                'Academic Results Published',
                "Examination results for {$scopeLabel} have been published to your Student Portal. Current CGPA: " . number_format((float)$metrics['cgpa'], 2),
                'result_published'
            );
        }
    } else {
        log_audit('RESULTS_UNPUBLISHED', "Admin #{$adminId} unpublished {$affected} result record(s) ({$scopeLabel}) back to Draft for student #{$studentId} ({$student['student_id']}).", $adminId);
    }

    return [
        'success' => true,
        'affected' => $affected,
        'published' => $publish,
        'cgpa' => $metrics['cgpa'],
    ];
}

/**
 * Parse CSV/TSV semester subject results for importing missing or historical semesters.
 * Supports:
 *   7-col: Semester, Course Code, Course Name, Credit, Full Marks, Obtained Marks[, Exam Term]
 *   6-col: Course Code, Course Name, Credit, Full Marks, Obtained Marks[, Exam Term] (uses $defaultSemester)
 */
function parse_semester_results_import_text(string $rawText, int $totalSemesters = 8, ?string $defaultSemester = null): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($rawText));
    $rows = [];
    $errors = [];

    $defaultSemNum = ($defaultSemester !== null && trim($defaultSemester) !== '')
        ? get_semester_number(trim($defaultSemester))
        : 99;

    foreach ($lines as $idx => $rawLine) {
        $lineNum = $idx + 1;
        $line = trim($rawLine);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '//') === 0) {
            continue;
        }

        if (strpos($line, "\t") !== false && strpos($line, ',') === false) {
            $cols = array_map('trim', explode("\t", $line));
        } elseif (strpos($line, '|') !== false && strpos($line, ',') === false) {
            $cols = array_map('trim', explode('|', $line));
        } else {
            $cols = array_map('trim', str_getcsv($line, ',', '"', '\\'));
        }

        // Skip optional header row
        $firstLower = strtolower($cols[0] ?? '');
        $secondLower = strtolower($cols[1] ?? '');
        if ($idx === 0 && (in_array($firstLower, ['semester', 'course_code', 'course code', 'subject code', 'code'], true) || in_array($secondLower, ['course_code', 'course code', 'course_name', 'subject name'], true))) {
            continue;
        }

        $semRaw = '';
        $code = '';
        $name = '';
        $creditRaw = '';
        $fullRaw = '';
        $marksRaw = '';
        $examTerm = 'Semester Final Examination';

        // Decide 7-col (leading semester) vs 6-col/5-col (default semester)
        $col0SemNum = get_semester_number($cols[0] ?? '');
        $col0LooksNumericCredit = isset($cols[2]) && is_numeric($cols[2]) && isset($cols[3]) && is_numeric($cols[3]) && isset($cols[4]) && is_numeric($cols[4]);
        $hasLeadingSemester = (
            count($cols) >= 6
            && $col0SemNum >= 1
            && $col0SemNum <= max(12, $totalSemesters)
            && !preg_match('/^[A-Za-z]{2,}\s*[- ]?\d{2,}/', $cols[0])
            && !$col0LooksNumericCredit
        );

        if ($hasLeadingSemester) {
            $semRaw = $cols[0];
            $code = strtoupper(trim($cols[1] ?? ''));
            $name = trim($cols[2] ?? '');
            $creditRaw = $cols[3] ?? '';
            $fullRaw = $cols[4] ?? '';
            $marksRaw = $cols[5] ?? '';
            if (!empty($cols[6])) {
                $examTerm = trim($cols[6]);
            }
        } elseif ($defaultSemNum >= 1 && $defaultSemNum <= $totalSemesters && count($cols) >= 5) {
            $semRaw = trim((string)$defaultSemester);
            $code = strtoupper(trim($cols[0] ?? ''));
            $name = trim($cols[1] ?? '');
            $creditRaw = $cols[2] ?? '';
            $fullRaw = $cols[3] ?? '';
            $marksRaw = $cols[4] ?? '';
            if (!empty($cols[5])) {
                $examTerm = trim($cols[5]);
            }
        } else {
            $errors[] = "Line {$lineNum}: Expected 'Semester, Course Code, Course Name, Credit, Full Marks, Obtained Marks' (or select a Target Semester and provide 5+ columns).";
            continue;
        }

        $semNum = get_semester_number($semRaw);
        if ($semNum < 1 || $semNum > $totalSemesters) {
            $errors[] = "Line {$lineNum}: Semester '{$semRaw}' is outside configured range (1 to {$totalSemesters}).";
            continue;
        }
        if ($code === '' || strlen($code) > 30) {
            $errors[] = "Line {$lineNum}: Invalid or missing Course Code.";
            continue;
        }
        if ($name === '') {
            $errors[] = "Line {$lineNum}: Missing Subject Name for {$code}.";
            continue;
        }
        if (!is_numeric($creditRaw) || (float)$creditRaw < 0.5 || (float)$creditRaw > 9.5) {
            $errors[] = "Line {$lineNum}: Credit for {$code} must be between 0.5 and 9.5.";
            continue;
        }
        if (!is_numeric($fullRaw) || (int)$fullRaw < 1 || (int)$fullRaw > 500) {
            $errors[] = "Line {$lineNum}: Full Marks for {$code} must be between 1 and 500.";
            continue;
        }
        if (!is_numeric($marksRaw) || (float)$marksRaw < 0.0 || (float)$marksRaw > (int)$fullRaw) {
            $errors[] = "Line {$lineNum}: Obtained Marks for {$code} ({$marksRaw}) must be between 0 and Full Marks ({$fullRaw}).";
            continue;
        }

        $rows[] = [
            'line_number'     => $lineNum,
            'semester_number' => $semNum,
            'semester_raw'    => $semRaw,
            'course_code'     => $code,
            'course_name'     => mb_substr($name, 0, 150),
            'credit'          => round((float)$creditRaw, 1),
            'full_marks'      => (int)$fullRaw,
            'marks'           => round((float)$marksRaw, 2),
            'exam_term'       => mb_substr($examTerm !== '' ? $examTerm : 'Semester Final Examination', 0, 50),
        ];
    }

    return ['rows' => $rows, 'errors' => $errors];
}

/**
 * Import validated missing/previous semester subject results into student_results cleanly
 * using database transaction, UGC automatic grading, audit trail & CGPA recalculation.
 */
function import_student_semester_results(int $studentId, array $parsedRows, int $adminId = 1, array $options = []): array {
    $db = getDb();
    $gate = can_manage_student_results($studentId);
    if (empty($gate['allowed'])) {
        return ['success' => false, 'error' => $gate['reason'] ?? 'Student results locked.'];
    }

    if (empty($parsedRows)) {
        return ['success' => false, 'error' => 'No valid subject rows to import.'];
    }

    $stStmt = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $publishStatus = in_array($options['publish_status'] ?? '', ['Published', 'Draft', 'Under Review'], true)
        ? $options['publish_status']
        : 'Published';
    $isPublished = ($publishStatus === 'Published') ? 1 : 0;
    $publishedAt = $isPublished ? date('Y-m-d H:i:s') : null;
    $publishedBy = $isPublished ? $adminId : null;
    $updateExisting = !isset($options['update_existing']) || !empty($options['update_existing']);
    $reason = trim((string)($options['reason'] ?? 'Missing/Previous semester result import'));
    if ($reason === '') {
        $reason = 'Missing/Previous semester result import';
    }

    // Map existing semester labels and subject keys by semester_number so we avoid splitting labels
    $existingStmt = $db->prepare("SELECT id, semester, course_code, marks, letter_grade, grade_point, credit FROM student_results WHERE student_id = ?");
    $existingStmt->execute([$studentId]);
    $canonicalSemLabel = [];
    $existingIndex = [];
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $ex) {
        $sn = get_semester_number((string)$ex['semester']);
        if ($sn >= 1 && $sn <= 12 && !isset($canonicalSemLabel[$sn])) {
            $canonicalSemLabel[$sn] = trim((string)$ex['semester']);
        }
        $key = $sn . '|' . strtoupper(trim((string)$ex['course_code']));
        $existingIndex[$key] = $ex;
    }

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $touchedSemesters = [];

    try {
        $db->beginTransaction();

        $insStmt = $db->prepare("
            INSERT INTO student_results
            (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, academic_session, is_published, is_estimated, result_type, publish_status, published_at, published_by, correction_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Verified', ?, ?, ?, ?)
        ");

        $updStmt = $db->prepare("
            UPDATE student_results
            SET course_name = ?, credit = ?, full_marks = ?, marks = ?, letter_grade = ?, grade_point = ?, exam_term = ?,
                is_published = ?, is_estimated = 0, result_type = 'Verified', publish_status = ?, published_at = ?, published_by = ?, correction_reason = ?
            WHERE id = ? AND student_id = ?
        ");

        $defaultSession = !empty($student['academic_session']) ? $student['academic_session'] : ($student['batch'] ?? null);

        foreach ($parsedRows as $row) {
            $sNum = (int)$row['semester_number'];
            $semLabel = $canonicalSemLabel[$sNum] ?? get_semester_ordinal_title($sNum);
            $canonicalSemLabel[$sNum] = $semLabel;

            $gradeInfo = calculate_grade_from_marks((float)$row['marks'], (float)$row['full_marks']);
            $letterGrade = $gradeInfo['letter_grade'];
            $gradePoint = (float)$gradeInfo['grade_point'];
            $courseKey = $sNum . '|' . strtoupper($row['course_code']);

            if (isset($existingIndex[$courseKey])) {
                if (!$updateExisting) {
                    $skipped++;
                    continue;
                }
                $exRow = $existingIndex[$courseKey];
                $updStmt->execute([
                    $row['course_name'],
                    $row['credit'],
                    $row['full_marks'],
                    $row['marks'],
                    $letterGrade,
                    $gradePoint,
                    $row['exam_term'],
                    $isPublished,
                    $publishStatus,
                    $publishedAt,
                    $publishedBy,
                    $reason,
                    (int)$exRow['id'],
                    $studentId,
                ]);
                log_result_modification(
                    (int)$exRow['id'],
                    $studentId,
                    $adminId,
                    $row['course_code'],
                    $semLabel,
                    'Bulk Import Update',
                    "Marks: " . ($exRow['marks'] ?? 'N/A') . ", Grade: {$exRow['letter_grade']}, GP: {$exRow['grade_point']}",
                    "Marks: {$row['marks']}/{$row['full_marks']}, Grade: {$letterGrade}, GP: {$gradePoint}",
                    $reason
                );
                $updated++;
                $touchedSemesters[$semLabel] = true;
            } else {
                $insStmt->execute([
                    $studentId,
                    $semLabel,
                    $row['course_code'],
                    $row['course_name'],
                    $row['credit'],
                    $row['full_marks'],
                    $row['marks'],
                    $letterGrade,
                    $gradePoint,
                    $row['exam_term'],
                    $defaultSession,
                    $isPublished,
                    $publishStatus,
                    $publishedAt,
                    $publishedBy,
                    $reason,
                ]);
                $newId = (int)$db->lastInsertId();
                $existingIndex[$courseKey] = [
                    'id' => $newId,
                    'semester' => $semLabel,
                    'course_code' => $row['course_code'],
                    'marks' => $row['marks'],
                    'letter_grade' => $letterGrade,
                    'grade_point' => $gradePoint,
                    'credit' => $row['credit'],
                ];
                log_result_modification(
                    $newId,
                    $studentId,
                    $adminId,
                    $row['course_code'],
                    $semLabel,
                    'Bulk Import Entry',
                    null,
                    "Marks: {$row['marks']}/{$row['full_marks']} ({$letterGrade}, GP {$gradePoint})",
                    $reason
                );
                $inserted++;
                $touchedSemesters[$semLabel] = true;
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[RMU import_student_semester_results] ' . $e->getMessage());
        return ['success' => false, 'error' => 'Import failed due to a database error. Please verify the CSV rows and try again.'];
    }

    $metrics = recalculate_student_cgpa($studentId);
    $semListText = implode(', ', array_keys($touchedSemesters));
    log_audit(
        'SEMESTER_RESULTS_IMPORTED',
        "Admin #{$adminId} imported {$inserted} new, updated {$updated}, skipped {$skipped} subject result(s) ({$semListText}) for Student #{$studentId} ({$student['student_id']}). New CGPA: {$metrics['cgpa']}",
        $adminId
    );

    if ($isPublished && ($inserted + $updated) > 0 && ($student['status'] ?? '') !== 'Deleted') {
        create_student_notification(
            $studentId,
            'Academic Transcript Updated',
            "Semester examination results ({$semListText}) have been imported and published. Current CGPA: " . number_format((float)$metrics['cgpa'], 2),
            'result_published'
        );
    }

    return [
        'success'   => true,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'skipped'   => $skipped,
        'semesters' => array_keys($touchedSemesters),
        'cgpa'      => $metrics['cgpa'],
        'credits_completed' => $metrics['credits_completed'],
    ];
}

/**
 * ========================================================
 * STUDENT PASSWORD & CREDENTIAL MANAGEMENT HELPERS
 * ========================================================
 */

function ensure_student_password_schema(?PDO $db = null): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $pdo = $db ?: getDb();
        $cols = $pdo->query("SHOW COLUMNS FROM students")->fetchAll(PDO::FETCH_COLUMN);
        $colMap = array_flip($cols);
        $additions = [
            'password_updated_at'  => "ADD COLUMN password_updated_at DATETIME NULL AFTER password_hash",
            'password_updated_by'  => "ADD COLUMN password_updated_by INT UNSIGNED NULL AFTER password_updated_at",
            'password_source'      => "ADD COLUMN password_source ENUM('system_generated','admin_reset','student_updated','legacy_hash') NOT NULL DEFAULT 'legacy_hash' AFTER password_updated_by",
            'must_change_password' => "ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_source",
        ];
        foreach ($additions as $col => $ddl) {
            if (!isset($colMap[$col])) {
                $pdo->exec("ALTER TABLE students {$ddl}");
            }
        }
    } catch (Throwable $e) {
        // Non-fatal schema check fallback
    }
}

function compute_password_hash_sig(?string $passwordHash): string {
    return substr(hash('sha256', (string)$passwordHash), 0, 16);
}

function store_admin_temp_student_password(int $studentPk, string $plainPassword, ?int $adminId = null, ?string $passwordHash = null, int $ttlSeconds = 1800): void {
    if ($studentPk <= 0 || $plainPassword === '') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE || !is_admin_manager_role()) {
        return;
    }
    $uid = $adminId !== null && $adminId > 0 ? $adminId : (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }
    if (!isset($_SESSION['rmu_admin_temp_credentials']) || !is_array($_SESSION['rmu_admin_temp_credentials'])) {
        $_SESSION['rmu_admin_temp_credentials'] = [];
    }
    $now = time();
    $_SESSION['rmu_admin_temp_credentials'][$studentPk] = [
        'password'   => $plainPassword,
        'issued_at'  => $now,
        'expires_at' => $now + max(60, $ttlSeconds),
        'issued_by'  => $uid,
        'hash_sig'   => ($passwordHash !== null && $passwordHash !== '') ? compute_password_hash_sig($passwordHash) : '',
    ];
}

function get_admin_temp_student_password(int $studentPk, ?array $studentRow = null): ?array {
    if ($studentPk <= 0 || session_status() !== PHP_SESSION_ACTIVE || !is_admin_manager_role()) {
        return null;
    }
    if (empty($_SESSION['rmu_admin_temp_credentials'][$studentPk]) || !is_array($_SESSION['rmu_admin_temp_credentials'][$studentPk])) {
        return null;
    }
    $entry = $_SESSION['rmu_admin_temp_credentials'][$studentPk];
    $currentUid = (int)($_SESSION['user_id'] ?? 0);
    if ((int)($entry['issued_by'] ?? 0) !== $currentUid || (int)($entry['expires_at'] ?? 0) < time()) {
        unset($_SESSION['rmu_admin_temp_credentials'][$studentPk]);
        return null;
    }
    if ($studentRow !== null) {
        if (($studentRow['status'] ?? '') === 'Deleted') {
            unset($_SESSION['rmu_admin_temp_credentials'][$studentPk]);
            return null;
        }
        $storedSig = (string)($entry['hash_sig'] ?? '');
        $rowHash = (string)($studentRow['password_hash'] ?? '');
        if ($storedSig !== '' && $rowHash !== '' && !hash_equals($storedSig, compute_password_hash_sig($rowHash))) {
            unset($_SESSION['rmu_admin_temp_credentials'][$studentPk]);
            return null;
        }
    }
    $entry['seconds_remaining'] = max(0, (int)$entry['expires_at'] - time());
    return $entry;
}

function clear_admin_temp_student_password(int $studentPk): void {
    if ($studentPk > 0 && isset($_SESSION['rmu_admin_temp_credentials'][$studentPk])) {
        unset($_SESSION['rmu_admin_temp_credentials'][$studentPk]);
    }
}

function reset_student_login_password(int $studentPk, int $adminId, string $source = 'admin_reset'): array {
    if ((php_sapi_name() !== 'cli' || !empty($_SESSION['user_role'])) && !is_admin_manager_role()) {
        return ['success' => false, 'error' => 'Unauthorized: Only Administrator accounts can reset student passwords.'];
    }
    $db = getDb();
    ensure_student_password_schema($db);

    $stmt = $db->prepare("SELECT id, student_id, full_name, email, status FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$studentPk]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }
    if (($student['status'] ?? '') === 'Deleted') {
        clear_admin_temp_student_password($studentPk);
        return ['success' => false, 'error' => 'Cannot reset password: this student account has been permanently deleted and credentials were revoked.'];
    }

    $newPlainPassword = generate_secure_student_password(12);
    $newHash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
    $validSources = ['system_generated', 'admin_reset'];
    $sourceVal = in_array($source, $validSources, true) ? $source : 'admin_reset';

    $upd = $db->prepare("
        UPDATE students
        SET password_hash = ?,
            password_updated_at = NOW(),
            password_updated_by = ?,
            password_source = ?,
            must_change_password = 1,
            session_version = session_version + 1
        WHERE id = ?
    ");
    $upd->execute([$newHash, $adminId > 0 ? $adminId : null, $sourceVal, $studentPk]);

    // Clear stale plaintext admission credential so old initial passwords never reappear on status page
    if (!empty($student['student_id'])) {
        $db->prepare("UPDATE admissions SET portal_password = NULL WHERE student_id = ?")->execute([$student['student_id']]);
    }

    store_admin_temp_student_password($studentPk, $newPlainPassword, $adminId, $newHash);

    create_student_notification(
        $studentPk,
        'Student Portal Password Reset',
        'Your Student Portal login password was reset by university administration and active sessions were signed out. Obtain your temporary password from the Registrar Office and update it after signing in.',
        'account_status'
    );

    log_audit(
        'STUDENT_PASSWORD_RESET',
        "Admin #{$adminId} reset login password and invalidated active portal sessions for student {$student['student_id']} (#{$studentPk}).",
        $adminId > 0 ? $adminId : null
    );

    return [
        'success'            => true,
        'student_id'         => $student['student_id'],
        'full_name'          => $student['full_name'],
        'temporary_password' => $newPlainPassword,
    ];
}

function get_student_password_source_meta(?string $source): array {
    $map = [
        'system_generated' => [
            'label'  => 'System Generated (Initial)',
            'bg'     => '#dbeafe',
            'color'  => '#1d4ed8',
            'border' => '#93c5fd',
            'icon'   => 'fa-robot',
        ],
        'admin_reset' => [
            'label'  => 'Admin Reset (Temporary)',
            'bg'     => '#fef3c7',
            'color'  => '#92400e',
            'border' => '#fcd34d',
            'icon'   => 'fa-key',
        ],
        'student_updated' => [
            'label'  => 'Student Self-Updated',
            'bg'     => '#dcfce7',
            'color'  => '#15803d',
            'border' => '#86efac',
            'icon'   => 'fa-user-check',
        ],
        'legacy_hash' => [
            'label'  => 'One-Way Encrypted Hash',
            'bg'     => '#f1f5f9',
            'color'  => '#475569',
            'border' => '#cbd5e1',
            'icon'   => 'fa-shield-alt',
        ],
    ];
    return $map[$source ?? 'legacy_hash'] ?? $map['legacy_hash'];
}

function render_student_login_credentials_section(array $student, bool $isManager, string $postActionUrl, string $returnTab = ''): void {
    $canManage = $isManager && is_admin_manager_role();
    $studentPk = (int)($student['id'] ?? 0);
    $studentCode = (string)($student['student_id'] ?? '');
    $portalUsername = !empty($student['username']) ? (string)$student['username'] : $studentCode;
    $status = (string)($student['status'] ?? 'Active');
    $isDeleted = ($status === 'Deleted');
    $srcMeta = get_student_password_source_meta($student['password_source'] ?? 'legacy_hash');
    $tempCred = $canManage ? get_admin_temp_student_password($studentPk, $student) : null;
    $hasTemp = ($tempCred !== null && !empty($tempCred['password']));
    $minsLeft = $hasTemp ? max(1, (int)ceil(((int)($tempCred['seconds_remaining'] ?? 0)) / 60)) : 0;
    $pwdUpdatedAt = !empty($student['password_updated_at']) ? (string)$student['password_updated_at'] : '';
    $mustChange = !empty($student['must_change_password']);

    $updatedByLabel = '';
    if (!empty($student['password_updated_by'])) {
        try {
            $uStmt = getDb()->prepare("SELECT username, full_name FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([(int)$student['password_updated_by']]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $updatedByLabel = trim((string)($uRow['full_name'] ?: $uRow['username']));
            }
        } catch (Throwable $e) {
            // ignore lookup error
        }
    }

    $actionUrlWithAnchor = $postActionUrl . (strpos($postActionUrl, '#') === false ? '#studentLoginCredentialsSection' : '');
    ?>
    <div id="studentLoginCredentialsSection" style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.75rem; box-shadow:0 2px 8px rgba(15,23,42,0.05);">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:1px solid #e2e8f0;">
        <div>
          <h3 style="margin:0; font-size:1.08rem; font-weight:800; color:#0f2c59; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-key" style="color:#0284c7;"></i> Student Login Credentials
          </h3>
          <div style="font-size:0.78rem; color:#64748b; margin-top:2px;">
            Student Portal Authentication (<code style="background:#f1f5f9; padding:1px 5px; border-radius:4px;">student/login.php</code>) &bull; Passwords stored using one-way <code style="background:#f1f5f9; padding:1px 5px; border-radius:4px;">password_hash()</code>
          </div>
        </div>
        <div style="display:flex; gap:0.45rem; flex-wrap:wrap; align-items:center;">
          <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:99px; font-size:0.74rem; font-weight:800; background:<?= e($srcMeta['bg']) ?>; color:<?= e($srcMeta['color']) ?>; border:1px solid <?= e($srcMeta['border']) ?>;">
            <i class="fas <?= e($srcMeta['icon']) ?>"></i> <?= e($srcMeta['label']) ?>
          </span>
          <?php if ($mustChange && !$isDeleted): ?>
            <span style="display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:99px; font-size:0.74rem; font-weight:800; background:#fffbeb; color:#b45309; border:1px solid #fde68a;">
              <i class="fas fa-clock"></i> Temporary / Reset Active
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap:1rem; align-items:stretch;">
        <!-- Box 1: Student ID & Copy Student ID -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:0.95rem 1.05rem; display:flex; flex-direction:column; justify-content:space-between; gap:0.65rem; min-width:0;">
          <div>
            <div style="font-size:0.73rem; font-weight:800; text-transform:uppercase; color:#475569; margin-bottom:0.45rem;">
              <i class="fas fa-id-badge"></i> Student ID &amp; Portal Username
            </div>
            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
              <input type="text" id="credStudentIdInput" value="<?= e($studentCode) ?>" readonly style="flex:1; min-width:min(100%, 160px); font-family:monospace; font-weight:800; font-size:0.96rem; color:#0f2c59; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:0.5rem 0.7rem;" aria-label="Student ID">
              <button type="button" id="copyStudentIdBtn" onclick="rmuCopyCredText('credStudentIdInput', this, 'Copy Student ID')" style="background:#0f2c59; color:#ffffff; border:none; border-radius:6px; padding:0.52rem 0.85rem; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;">
                <i class="fas fa-copy"></i> <span>Copy Student ID</span>
              </button>
            </div>
          </div>
          <div style="font-size:0.77rem; color:#475569; display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.4rem;">
            <span>Portal Username: <strong style="font-family:monospace; color:#0f172a;"><?= e($portalUsername) ?></strong></span>
            <?php if ($pwdUpdatedAt !== ''): ?>
              <span>Updated: <strong><?= e(format_date($pwdUpdatedAt, 'M d, Y h:i A')) ?></strong><?= $updatedByLabel !== '' ? ' by ' . e($updatedByLabel) : '' ?></span>
            <?php else: ?>
              <span>Status: <strong><?= e($status) ?></strong></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Box 2: Admin-Only Password Controls -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:0.95rem 1.05rem; display:flex; flex-direction:column; justify-content:space-between; gap:0.65rem; min-width:0;">
          <?php if (!$canManage): ?>
            <div>
              <div style="font-size:0.73rem; font-weight:800; text-transform:uppercase; color:#991b1b; margin-bottom:0.4rem;">
                <i class="fas fa-lock"></i> Portal Password Security
              </div>
              <div style="background:#fff1f2; border:1px solid #fecdd3; color:#881337; border-radius:6px; padding:0.65rem 0.8rem; font-size:0.8rem; line-height:1.45;">
                <strong>Restricted Access:</strong> Only Administrator (<code style="background:rgba(255,255,255,0.6); padding:1px 4px; border-radius:3px;">Admin</code> / <code style="background:rgba(255,255,255,0.6); padding:1px 4px; border-radius:3px;">Superadmin</code>) accounts may view temporary credentials or reset student login passwords.
              </div>
            </div>
          <?php elseif ($isDeleted): ?>
            <div>
              <div style="font-size:0.73rem; font-weight:800; text-transform:uppercase; color:#991b1b; margin-bottom:0.4rem;">
                <i class="fas fa-ban"></i> Credentials Permanently Revoked
              </div>
              <div style="background:#fef2f2; border:1px solid #fecaca; color:#7f1d1d; border-radius:6px; padding:0.65rem 0.8rem; font-size:0.8rem; line-height:1.45;">
                This student account has been permanently deleted under University Data-Retention Policy. Login access and password resets are permanently disabled.
              </div>
            </div>
          <?php else: ?>
            <div>
              <div style="font-size:0.73rem; font-weight:800; text-transform:uppercase; color:#047857; margin-bottom:0.45rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem;">
                <span><i class="fas fa-shield-alt"></i> Portal Password (Admin Only)</span>
                <?php if ($hasTemp): ?>
                  <span style="background:#dcfce7; color:#15803d; padding:1px 7px; border-radius:99px; font-size:0.68rem; font-weight:800; text-transform:none;">
                    Temporary password active (~<?= $minsLeft ?> min)
                  </span>
                <?php endif; ?>
              </div>

              <div style="display:flex; gap:0.45rem; align-items:center; flex-wrap:wrap;">
                <?php if ($hasTemp): ?>
                  <input type="password" id="credPasswordInput" value="<?= e($tempCred['password']) ?>" readonly autocomplete="new-password" style="flex:1; min-width:min(100%, 150px); font-family:monospace; font-weight:800; font-size:0.95rem; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:0.5rem 0.7rem;" aria-label="Temporary Student Password">
                  <button type="button" id="toggleCredPasswordBtn" onclick="rmuToggleCredPassword()" aria-pressed="false" style="background:#0284c7; color:#ffffff; border:none; border-radius:6px; padding:0.52rem 0.8rem; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;">
                    <i class="fas fa-eye"></i> <span>Show Password</span>
                  </button>
                  <button type="button" id="copyCredPasswordBtn" onclick="rmuCopyCredText('credPasswordInput', this, 'Copy Password')" style="background:#0f766e; color:#ffffff; border:none; border-radius:6px; padding:0.52rem 0.8rem; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;">
                    <i class="fas fa-copy"></i> <span>Copy Password</span>
                  </button>
                <?php else: ?>
                  <input type="password" id="credPasswordInput" value="••••••••••••" readonly disabled style="flex:1; min-width:min(100%, 150px); font-family:monospace; font-size:0.95rem; color:#64748b; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:0.5rem 0.7rem;" aria-label="Hashed Password Placeholder">
                  <button type="button" id="toggleCredPasswordBtn" disabled title="One-way cryptographic hashes cannot be decrypted. Click Reset Password to issue a new temporary password." style="background:#94a3b8; color:#ffffff; border:none; border-radius:6px; padding:0.52rem 0.8rem; font-size:0.78rem; font-weight:700; cursor:not-allowed; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; opacity:0.7;">
                    <i class="fas fa-eye"></i> <span>Show Password</span>
                  </button>
                  <button type="button" id="copyCredPasswordBtn" disabled title="Click Reset Password first to generate a copyable temporary password." style="background:#94a3b8; color:#ffffff; border:none; border-radius:6px; padding:0.52rem 0.8rem; font-size:0.78rem; font-weight:700; cursor:not-allowed; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; opacity:0.7;">
                    <i class="fas fa-copy"></i> <span>Copy Password</span>
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
              <div style="font-size:0.75rem; color:#475569; flex:1; min-width:min(100%, 210px);">
                <?php if ($hasTemp): ?>
                  <i class="fas fa-info-circle" style="color:#0284c7;"></i> Share securely with the student; visibility expires automatically when session ends or clears.
                <?php else: ?>
                  <i class="fas fa-lock" style="color:#64748b;"></i> Encrypted hash cannot be retrieved. Click <strong>Reset Password</strong> to generate a new secure password.
                <?php endif; ?>
              </div>

              <div style="display:flex; gap:0.45rem; align-items:center; flex-wrap:wrap;">
                <?php if ($hasTemp): ?>
                  <form action="<?= e($actionUrlWithAnchor) ?>" method="POST" style="margin:0; display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_temp_credential">
                    <input type="hidden" name="student_id_pk" value="<?= $studentPk ?>">
                    <?php if ($returnTab !== ''): ?>
                      <input type="hidden" name="return_tab" value="<?= e($returnTab) ?>">
                    <?php endif; ?>
                    <button type="submit" id="clearTempCredBtn" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; border-radius:6px; padding:0.46rem 0.75rem; font-size:0.76rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">
                      <i class="fas fa-eye-slash"></i> Hide / Clear
                    </button>
                  </form>
                <?php endif; ?>

                <form action="<?= e($actionUrlWithAnchor) ?>" method="POST" style="margin:0; display:inline;" onsubmit="return confirm('Generate a new secure random password for <?= e($studentCode) ?>? Any active Student Portal session for this student will be signed out immediately.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reset_student_password">
                  <input type="hidden" name="student_id_pk" value="<?= $studentPk ?>">
                  <?php if ($returnTab !== ''): ?>
                    <input type="hidden" name="return_tab" value="<?= e($returnTab) ?>">
                  <?php endif; ?>
                  <button type="submit" id="resetStudentPasswordBtn" style="background:#d97706; color:#ffffff; border:none; border-radius:6px; padding:0.48rem 0.9rem; font-size:0.78rem; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; gap:5px; white-space:nowrap;">
                    <i class="fas fa-sync-alt"></i> Reset Password
                  </button>
                </form>
              </div>
            </div>

            <?php if (in_array($status, ['Deactivated', 'Suspended'], true)): ?>
              <div style="font-size:0.73rem; color:#b45309; font-weight:700;">
                <i class="fas fa-exclamation-triangle"></i> Note: Account is currently <?= e($status) ?> — portal login remains blocked until reactivated.
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php
    static $credJsRendered = false;
    if (!$credJsRendered):
        $credJsRendered = true;
    ?>
    <script>
      <?php if ($canManage): ?>
      function rmuToggleCredPassword() {
        var inp = document.getElementById('credPasswordInput');
        var btn = document.getElementById('toggleCredPasswordBtn');
        if (!inp || inp.disabled || !btn) return;
        var showing = inp.type === 'text';
        inp.type = showing ? 'password' : 'text';
        btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
        btn.innerHTML = showing
          ? '<i class="fas fa-eye"></i> <span>Show Password</span>'
          : '<i class="fas fa-eye-slash"></i> <span>Hide Password</span>';
      }
      <?php endif; ?>

      function rmuCopyCredText(inputId, btn, defaultLabel) {
        var inp = document.getElementById(inputId);
        if (!inp || inp.disabled || !inp.value) return;
        var val = inp.value;

        var markCopied = function() {
          if (!btn) return;
          btn.innerHTML = '<i class="fas fa-check"></i> <span>Copied!</span>';
          setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-copy"></i> <span>' + defaultLabel + '</span>';
          }, 1800);
        };

        var fallbackCopy = function(text) {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.setAttribute('readonly', '');
          ta.style.position = 'fixed';
          ta.style.left = '-9999px';
          ta.style.top = '0';
          document.body.appendChild(ta);
          ta.select();
          try {
            document.execCommand('copy');
            markCopied();
          } catch (err) {}
          document.body.removeChild(ta);
        };

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(val).then(markCopied).catch(function() {
            fallbackCopy(val);
          });
        } else {
          fallbackCopy(val);
        }
      }
    </script>
    <?php endif;
}

/**
 * ========================================================
 * SEQUENTIAL APPLICATION ID & ADMISSION ROLL ENGINE
 * Formats:
 *   - Application ID: RMU-2026-0001
 *   - Admission / Exam Roll: 2026-0001
 * Concurrency-safe via row locks (SELECT ... FOR UPDATE) + UNIQUE indexes.
 * ========================================================
 */

function is_retryable_db_concurrency_exception(PDOException $e): bool {
    $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());
    $driverCode = (int)($e->errorInfo[1] ?? 0);
    return in_array($sqlState, ['23000', '40001'], true) || in_array($driverCode, [1062, 1205, 1213], true);
}

function format_sequential_application_id(int $year, int $seq): string {
    return sprintf('RMU-%04d-%s', max(2015, $year), str_pad((string)max(1, $seq), 4, '0', STR_PAD_LEFT));
}

function format_sequential_roll_number(int $year, int $seq): string {
    return sprintf('%04d-%s', max(2015, $year), str_pad((string)max(1, $seq), 4, '0', STR_PAD_LEFT));
}

function ensure_admission_id_generation_schema(?PDO $db = null): void {
    static $schemaEnsured = false;
    if ($schemaEnsured) {
        return;
    }
    $pdo = $db ?: getDb();
    try {
        $pdo->query("SELECT 1 FROM admission_sequences LIMIT 1");
        $pdo->query("SELECT 1 FROM admission_id_reservations LIMIT 1");
        $schemaEnsured = true;
        return;
    } catch (PDOException $ignored) {
        // Fall through to table creation when tables do not yet exist
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admission_sequences (
            seq_year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
            last_app_seq INT UNSIGNED NOT NULL DEFAULT 0,
            last_roll_seq INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admission_id_reservations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            draft_token VARCHAR(64) NOT NULL UNIQUE,
            session_key VARCHAR(128) NOT NULL,
            seq_year SMALLINT UNSIGNED NOT NULL,
            application_id VARCHAR(30) NULL UNIQUE,
            roll_number VARCHAR(30) NULL UNIQUE,
            status ENUM('reserved','committed','expired') NOT NULL DEFAULT 'reserved',
            committed_admission_id INT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_res_session_status (session_key, status),
            INDEX idx_res_expires (status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $schemaEnsured = true;
}

function cleanup_expired_admission_reservations(PDO $db): void {
    $db->exec("
        UPDATE admission_id_reservations
        SET status = 'expired',
            application_id = NULL,
            roll_number = NULL,
            updated_at = NOW()
        WHERE status = 'reserved' AND expires_at < NOW()
    ");
    $db->exec("
        DELETE FROM admission_id_reservations
        WHERE status = 'expired' AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
}

function admission_app_id_exists(PDO $db, string $appId, int $excludeAdmId = 0, int $excludeResId = 0): bool {
    $stmt1 = $db->prepare("SELECT 1 FROM admissions WHERE (application_ref = ? OR application_id = ?) AND id != ? LIMIT 1");
    $stmt1->execute([$appId, $appId, $excludeAdmId]);
    if ($stmt1->fetchColumn()) {
        return true;
    }

    $stmt2 = $db->prepare("SELECT 1 FROM students WHERE application_number = ? LIMIT 1");
    $stmt2->execute([$appId]);
    if ($stmt2->fetchColumn()) {
        return true;
    }

    $stmt3 = $db->prepare("
        SELECT 1 FROM admission_id_reservations
        WHERE application_id = ?
          AND status = 'reserved'
          AND expires_at >= NOW()
          AND id != ?
        LIMIT 1
    ");
    $stmt3->execute([$appId, $excludeResId]);
    return (bool)$stmt3->fetchColumn();
}

function admission_roll_number_exists(PDO $db, string $rollNumber, int $excludeAdmId = 0, int $excludeResId = 0): bool {
    $stmt1 = $db->prepare("SELECT 1 FROM admissions WHERE (roll_number = ? OR exam_roll = ?) AND id != ? LIMIT 1");
    $stmt1->execute([$rollNumber, $rollNumber, $excludeAdmId]);
    if ($stmt1->fetchColumn()) {
        return true;
    }

    $stmt2 = $db->prepare("SELECT 1 FROM students WHERE roll_number = ? LIMIT 1");
    $stmt2->execute([$rollNumber]);
    if ($stmt2->fetchColumn()) {
        return true;
    }

    $stmt3 = $db->prepare("
        SELECT 1 FROM admission_id_reservations
        WHERE roll_number = ?
          AND status = 'reserved'
          AND expires_at >= NOW()
          AND id != ?
        LIMIT 1
    ");
    $stmt3->execute([$rollNumber, $excludeResId]);
    return (bool)$stmt3->fetchColumn();
}

/**
 * Caller MUST hold an active DB transaction.
 */
function allocate_next_application_ref_locked(PDO $db, int $year): string {
    $cleanYear = max(2015, $year);
    $lockStmt = $db->prepare("SELECT last_app_seq FROM admission_sequences WHERE seq_year = ? FOR UPDATE");
    $lockStmt->execute([$cleanYear]);
    $rawSeq = $lockStmt->fetchColumn();
    if ($rawSeq === false) {
        $db->prepare("INSERT IGNORE INTO admission_sequences (seq_year, last_app_seq, last_roll_seq, updated_at) VALUES (?, 0, 0, NOW())")
           ->execute([$cleanYear]);
        $lockStmt->execute([$cleanYear]);
        $rawSeq = $lockStmt->fetchColumn();
    }
    $lastAppSeq = (int)($rawSeq ?: 0);

    $appSeq = $lastAppSeq + 1;
    while (true) {
        $candidate = format_sequential_application_id($cleanYear, $appSeq);
        if (!admission_app_id_exists($db, $candidate)) {
            break;
        }
        $appSeq++;
    }

    $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, updated_at = NOW() WHERE seq_year = ?")
       ->execute([$appSeq, $cleanYear]);

    return format_sequential_application_id($cleanYear, $appSeq);
}

/**
 * Caller MUST hold an active DB transaction.
 */
function allocate_next_roll_number_locked(PDO $db, int $year): string {
    $cleanYear = max(2015, $year);
    $lockStmt = $db->prepare("SELECT last_roll_seq FROM admission_sequences WHERE seq_year = ? FOR UPDATE");
    $lockStmt->execute([$cleanYear]);
    $rawSeq = $lockStmt->fetchColumn();
    if ($rawSeq === false) {
        $db->prepare("INSERT IGNORE INTO admission_sequences (seq_year, last_app_seq, last_roll_seq, updated_at) VALUES (?, 0, 0, NOW())")
           ->execute([$cleanYear]);
        $lockStmt->execute([$cleanYear]);
        $rawSeq = $lockStmt->fetchColumn();
    }
    $lastRollSeq = (int)($rawSeq ?: 0);

    $rollSeq = $lastRollSeq + 1;
    while (true) {
        $candidate = format_sequential_roll_number($cleanYear, $rollSeq);
        if (!admission_roll_number_exists($db, $candidate)) {
            break;
        }
        $rollSeq++;
    }

    $db->prepare("UPDATE admission_sequences SET last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
       ->execute([$rollSeq, $cleanYear]);

    return format_sequential_roll_number($cleanYear, $rollSeq);
}

function generate_next_admission_roll_number(PDO $db, ?int $year = null): string {
    ensure_admission_id_generation_schema($db);
    $cleanYear = max(2015, $year ?: (int)date('Y'));
    if ($db->inTransaction()) {
        return allocate_next_roll_number_locked($db, $cleanYear);
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $db->beginTransaction();
            $roll = allocate_next_roll_number_locked($db, $cleanYear);
            $db->commit();
            return $roll;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!is_retryable_db_concurrency_exception($e) || $attempt === 4) {
                throw $e;
            }
            usleep(25000);
        }
    }
    return format_sequential_roll_number($cleanYear, 1);
}

/**
 * Caller MUST hold an active DB transaction.
 */
function allocate_next_sequential_pair_locked(PDO $db, int $year): array {
    $cleanYear = max(2015, $year);
    $lockStmt = $db->prepare("SELECT last_app_seq, last_roll_seq FROM admission_sequences WHERE seq_year = ? FOR UPDATE");
    $lockStmt->execute([$cleanYear]);
    $row = $lockStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $db->prepare("INSERT IGNORE INTO admission_sequences (seq_year, last_app_seq, last_roll_seq, updated_at) VALUES (?, 0, 0, NOW())")
           ->execute([$cleanYear]);
        $lockStmt->execute([$cleanYear]);
        $row = $lockStmt->fetch(PDO::FETCH_ASSOC) ?: ['last_app_seq' => 0, 'last_roll_seq' => 0];
    }

    $appSeq  = (int)($row['last_app_seq'] ?? 0) + 1;
    $rollSeq = (int)($row['last_roll_seq'] ?? 0) + 1;

    while (admission_app_id_exists($db, format_sequential_application_id($cleanYear, $appSeq))) {
        $appSeq++;
    }
    while (admission_roll_number_exists($db, format_sequential_roll_number($cleanYear, $rollSeq))) {
        $rollSeq++;
    }

    $appId  = format_sequential_application_id($cleanYear, $appSeq);
    $rollNo = format_sequential_roll_number($cleanYear, $rollSeq);

    $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
       ->execute([$appSeq, $rollSeq, $cleanYear]);

    return [
        'seq_year'       => $cleanYear,
        'app_seq'        => $appSeq,
        'roll_seq'       => $rollSeq,
        'application_id' => $appId,
        'roll_number'    => $rollNo,
    ];
}

/**
 * Get or atomically reserve sequential (Application ID, Roll Number) for the active form session.
 * Idempotent across page refreshes and revisits until the application is submitted.
 */
function get_or_reserve_admission_identifiers(PDO $db, ?int $year = null): array {
    ensure_admission_id_generation_schema($db);
    if (session_status() === PHP_SESSION_NONE && !headers_sent() && function_exists('start_secure_session')) {
        start_secure_session();
    }

    $cleanYear = max(2015, $year ?: (int)date('Y'));
    $sessKey = session_id() ?: ('cli_' . getmypid());

    // 1. Reuse existing active reserved draft in the same session (no extra serials on refresh)
    $existing = $_SESSION['rmu_admission_draft'] ?? null;
    if (is_array($existing) && !empty($existing['draft_token']) && (int)($existing['seq_year'] ?? 0) === $cleanYear) {
        $resStmt = $db->prepare("
            SELECT * FROM admission_id_reservations
            WHERE draft_token = ?
              AND seq_year = ?
              AND status = 'reserved'
              AND expires_at > NOW()
            LIMIT 1
        ");
        $resStmt->execute([(string)$existing['draft_token'], $cleanYear]);
        $resRow = $resStmt->fetch(PDO::FETCH_ASSOC);

        if ($resRow && !empty($resRow['application_id']) && !empty($resRow['roll_number'])) {
            $appTaken = admission_app_id_exists($db, (string)$resRow['application_id'], 0, (int)$resRow['id']);
            $rollTaken = admission_roll_number_exists($db, (string)$resRow['roll_number'], 0, (int)$resRow['id']);
            if (!$appTaken && !$rollTaken) {
                $db->prepare("UPDATE admission_id_reservations SET expires_at = DATE_ADD(NOW(), INTERVAL 12 HOUR), updated_at = NOW() WHERE id = ?")
                   ->execute([(int)$resRow['id']]);
                $draftData = [
                    'reservation_id' => (int)$resRow['id'],
                    'draft_token'    => (string)$resRow['draft_token'],
                    'seq_year'       => $cleanYear,
                    'application_id' => (string)$resRow['application_id'],
                    'roll_number'    => (string)$resRow['roll_number'],
                ];
                $_SESSION['rmu_admission_draft'] = $draftData;
                return $draftData;
            }
        }
        unset($_SESSION['rmu_admission_draft']);
    }

    // 2. Clean expired reservations before opening lock transaction, then reserve atomically
    if (!$db->inTransaction()) {
        cleanup_expired_admission_reservations($db);
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $startedTx = false;
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTx = true;
            }

            $pair = allocate_next_sequential_pair_locked($db, $cleanYear);
            $draftToken = bin2hex(random_bytes(24));

            $insRes = $db->prepare("
                INSERT INTO admission_id_reservations
                (draft_token, session_key, seq_year, application_id, roll_number, status, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'reserved', DATE_ADD(NOW(), INTERVAL 12 HOUR), NOW(), NOW())
            ");
            $insRes->execute([
                $draftToken,
                $sessKey,
                $cleanYear,
                $pair['application_id'],
                $pair['roll_number'],
            ]);
            $resId = (int)$db->lastInsertId();

            if ($startedTx && $db->inTransaction()) {
                $db->commit();
            }

            $draftData = [
                'reservation_id' => $resId,
                'draft_token'    => $draftToken,
                'seq_year'       => $cleanYear,
                'application_id' => $pair['application_id'],
                'roll_number'    => $pair['roll_number'],
            ];
            $_SESSION['rmu_admission_draft'] = $draftData;
            return $draftData;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (!is_retryable_db_concurrency_exception($e) || $attempt === 4) {
                throw $e;
            }
            usleep(25000);
        }
    }

    throw new RuntimeException('Unable to allocate unique Admission Application ID and Roll Number.');
}

/**
 * Resolve or allocate final sequential Application ID + Roll Number inside an active submit transaction.
 * Caller MUST hold an active DB transaction.
 */
function finalize_admission_identifiers_in_tx(PDO $db, ?string $draftToken = null, ?int $year = null): array {
    $cleanYear = max(2015, $year ?: (int)date('Y'));
    $token = trim((string)$draftToken);
    $sessId = session_id();

    if ($token !== '') {
        $stmt = $db->prepare("
            SELECT * FROM admission_id_reservations
            WHERE draft_token = ? AND seq_year = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$token, $cleanYear]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $res
            && $res['status'] === 'reserved'
            && strtotime((string)$res['expires_at']) >= time()
            && ($sessId === '' || (string)$res['session_key'] === (string)$sessId)
            && !empty($res['application_id'])
            && !empty($res['roll_number'])
        ) {
            $appTaken  = admission_app_id_exists($db, (string)$res['application_id'], 0, (int)$res['id']);
            $rollTaken = admission_roll_number_exists($db, (string)$res['roll_number'], 0, (int)$res['id']);
            if (!$appTaken && !$rollTaken) {
                return [
                    'reservation_id' => (int)$res['id'],
                    'application_id' => (string)$res['application_id'],
                    'roll_number'    => (string)$res['roll_number'],
                    'source'         => 'reserved_draft',
                ];
            }
        }
    }

    $pair = allocate_next_sequential_pair_locked($db, $cleanYear);
    return [
        'reservation_id' => null,
        'application_id' => $pair['application_id'],
        'roll_number'    => $pair['roll_number'],
        'source'         => 'fresh_sequence',
    ];
}

function mark_admission_reservation_committed(PDO $db, ?int $reservationId, int $admissionId): void {
    if ($reservationId === null || $reservationId <= 0 || $admissionId <= 0) {
        return;
    }
    $db->prepare("
        UPDATE admission_id_reservations
        SET status = 'committed',
            committed_admission_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$admissionId, $reservationId]);
}

/**
 * Validate Student / Applicant Identifiers against duplicates across both `students` and `admissions`.
 * Returns a human-readable error message when duplicate found, or null when unique.
 */
function validate_unique_student_identifiers(
    PDO $db,
    ?string $studentCode,
    ?string $rollNumber,
    ?string $appNumber,
    ?string $email = null,
    int $excludeStudentPk = 0
): ?string {
    $cleanCode  = trim((string)$studentCode);
    $cleanRoll  = trim((string)$rollNumber);
    $cleanAppNo = trim((string)$appNumber);
    $cleanEmail = trim((string)$email);

    $linkedStudentCode = '';
    if ($excludeStudentPk > 0) {
        $stCodeStmt = $db->prepare("SELECT student_id FROM students WHERE id = ? LIMIT 1");
        $stCodeStmt->execute([$excludeStudentPk]);
        $linkedStudentCode = trim((string)$stCodeStmt->fetchColumn());
    }

    if ($cleanCode !== '') {
        $chkCode = $db->prepare("SELECT id, full_name FROM students WHERE student_id = ? AND id != ? LIMIT 1");
        $chkCode->execute([$cleanCode, $excludeStudentPk]);
        if ($r = $chkCode->fetch(PDO::FETCH_ASSOC)) {
            return "Student ID '{$cleanCode}' is already assigned to {$r['full_name']}. Duplicate Student IDs are not allowed.";
        }
    }

    if ($cleanRoll !== '') {
        $chkRollStu = $db->prepare("SELECT id, student_id, full_name FROM students WHERE roll_number = ? AND id != ? LIMIT 1");
        $chkRollStu->execute([$cleanRoll, $excludeStudentPk]);
        if ($r = $chkRollStu->fetch(PDO::FETCH_ASSOC)) {
            return "Admission / Examination Roll Number '{$cleanRoll}' is already assigned to student {$r['full_name']} ({$r['student_id']}).";
        }

        $admSql = "SELECT id, application_ref, full_name FROM admissions WHERE (roll_number = ? OR exam_roll = ?)";
        $admParams = [$cleanRoll, $cleanRoll];
        if ($linkedStudentCode !== '') {
            $admSql .= " AND COALESCE(student_id, '') != ?";
            $admParams[] = $linkedStudentCode;
        }
        $admSql .= " LIMIT 1";
        $chkRollAdm = $db->prepare($admSql);
        $chkRollAdm->execute($admParams);
        if ($a = $chkRollAdm->fetch(PDO::FETCH_ASSOC)) {
            return "Admission / Examination Roll Number '{$cleanRoll}' is already assigned to admission record {$a['application_ref']} ({$a['full_name']}).";
        }
    }

    if ($cleanAppNo !== '') {
        $chkAppStu = $db->prepare("SELECT id, student_id, full_name FROM students WHERE application_number = ? AND id != ? LIMIT 1");
        $chkAppStu->execute([$cleanAppNo, $excludeStudentPk]);
        if ($r = $chkAppStu->fetch(PDO::FETCH_ASSOC)) {
            return "Application ID '{$cleanAppNo}' is already assigned to student {$r['full_name']} ({$r['student_id']}).";
        }

        $admAppSql = "SELECT id, application_ref, full_name FROM admissions WHERE (application_id = ? OR application_ref = ?)";
        $admAppParams = [$cleanAppNo, $cleanAppNo];
        if ($linkedStudentCode !== '') {
            $admAppSql .= " AND COALESCE(student_id, '') != ?";
            $admAppParams[] = $linkedStudentCode;
        }
        $admAppSql .= " LIMIT 1";
        $chkAppAdm = $db->prepare($admAppSql);
        $chkAppAdm->execute($admAppParams);
        if ($a = $chkAppAdm->fetch(PDO::FETCH_ASSOC)) {
            return "Application ID '{$cleanAppNo}' is already assigned to admission record {$a['application_ref']} ({$a['full_name']}).";
        }
    }

    if ($cleanEmail !== '') {
        $chkEmail = $db->prepare("SELECT id, student_id FROM students WHERE email = ? AND id != ? LIMIT 1");
        $chkEmail->execute([$cleanEmail, $excludeStudentPk]);
        if ($r = $chkEmail->fetch(PDO::FETCH_ASSOC)) {
            return "Email address '{$cleanEmail}' is already registered to student {$r['student_id']}.";
        }
    }

    return null;
}

require_once __DIR__ . '/admission_documents.php';
require_once __DIR__ . '/sandbox_gateway.php';







