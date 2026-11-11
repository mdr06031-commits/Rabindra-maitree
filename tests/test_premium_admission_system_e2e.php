<?php
/**
 * Comprehensive End-to-End Integration Test:
 * FINAL MASTER PROMPT – PREMIUM UNIVERSITY ADMISSION APPLICATION SYSTEM
 *
 * Covers:
 *  1. Bilingual (English + বাংলা) admission submission via admission-form.php -> actions/submit-admission.php
 *     with SSC/HSC Registration Numbers, photo upload, and automatic individual A4 Portrait PDF creation.
 *  2. PDF binary inspection: FlateDecode decompression + ToUnicode CMap decoding verifying selectable
 *     Unicode English + Bengali text, Page X of Y numbering, university branding, QR & photo assets,
 *     and complete absence of removed noise (Expected Completion (Advisory), Application Record Year,
 *     Sender & Gateway, Md. Rafiqul Islam, Photo Verified, Change Photo, duplicate payment block).
 *  3. Separate Official Payment Receipt: unpaid blocking -> ৳150 payment -> Receipt HTML & PDF download
 *     (Institution EN+BN, Student Name, App ID, Amount, TrxID, Method, Sender Number, Date, Paid/Verified)
 *     + automatic refresh of Application PDF & SVG slip compatibility.
 *  4. QR Code Verification (`verify-application.php`): valid cryptographic token verification + live DB status +
 *     strict rejection of tampered/empty token + strict privacy check hiding PII/passwords/scores.
 *  5. Security / IDOR guards: unauthenticated & cross-student (`RMU2601001`) requests to
 *     `admission-document.php` blocked with HTTP 403.
 *  6. Admin Panel (`admin/admissions.php`): Department filter, Academic Session filter, View Application,
 *     View PDF, Download PDF, Print Application, Payment Receipt, Accept -> automatic status sync (`Approved`,
 *     Student ID `Issued`) in DB, Application Card, QR Verification, and regenerated PDF.
 *  7. Student Panel (`student/dashboard.php`): Student logs in -> sees `My Applications` with all 5 real actions
 *     (View Application, View PDF, Download PDF, Print Application, Payment Receipt) + HTTP 200 & valid PDFs.
 *  8. Responsive CSS viewport coverage checks + clean restoration of DB, sequences, and temp artifacts.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$baseUrl = 'http://127.0.0.1:8000';
$db = getDb();

$passed = 0;
$failed = 0;

function pas_assert(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

function pas_http(string $url, string $cookieFile, ?array $postData = null, array $files = []): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_COOKIEJAR      => $cookieFile,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'RMU-PremiumAdmission-E2E/1.0',
    ];
    if ($postData !== null) {
        $opts[CURLOPT_POST] = true;
        if (!empty($files)) {
            foreach ($files as $field => $filePath) {
                $mime = str_ends_with(strtolower($filePath), '.png') ? 'image/png' : 'image/jpeg';
                $postData[$field] = new CURLFile($filePath, $mime, basename($filePath));
            }
            $opts[CURLOPT_POSTFIELDS] = $postData;
        } else {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($postData);
        }
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $eff  = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $ct   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    return ['code' => $code, 'url' => $eff, 'type' => $ct, 'body' => (string)$body, 'error' => $err];
}

function pas_csrf(string $html): string {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Parse ToUnicode CMap stream into CID -> UTF-8 lookup map.
 */
function pas_decode_tounicode_cmap(string $cmapStr): array {
    $map = [];
    if (preg_match_all('/beginbfchar\s*(.*?)\s*endbfchar/s', $cmapStr, $bfBlocks)) {
        foreach ($bfBlocks[1] as $blk) {
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $blk, $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $p) {
                    $src = strtoupper(str_pad($p[1], 4, '0', STR_PAD_LEFT));
                    $hexDst = (strlen($p[2]) % 2 === 1) ? ('0' . $p[2]) : $p[2];
                    $dstBin = hex2bin($hexDst);
                    $map[$src] = $dstBin !== false ? mb_convert_encoding($dstBin, 'UTF-8', 'UTF-16BE') : '';
                }
            }
        }
    }
    if (preg_match_all('/beginbfrange\s*(.*?)\s*endbfrange/s', $cmapStr, $rngBlocks)) {
        foreach ($rngBlocks[1] as $blk) {
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $blk, $ranges, PREG_SET_ORDER)) {
                foreach ($ranges as $r) {
                    $start = hexdec($r[1]);
                    $end = hexdec($r[2]);
                    $dst = hexdec($r[3]);
                    for ($k = 0; ($start + $k) <= $end && $k < 512; $k++) {
                        $srcHex = sprintf('%04X', $start + $k);
                        $dstHex = sprintf('%04X', $dst + $k);
                        $map[$srcHex] = mb_convert_encoding((string)hex2bin($dstHex), 'UTF-8', 'UTF-16BE');
                    }
                }
            }
        }
    }
    return $map;
}

/**
 * Decode compressed Chromium/Edge PDF streams using per-font ToUnicode CMaps + ISO 32000 ActualText spans.
 */
function pas_extract_pdf_text(string $pdfBytes): array {
    preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)endobj/s', $pdfBytes, $objMatches, PREG_SET_ORDER);
    $objs = [];
    foreach ($objMatches as $m) {
        $objs[(int)$m[1]] = $m[2];
    }

    $streamOf = static function (int $objId) use ($objs): string {
        $body = $objs[$objId] ?? '';
        if (preg_match('/stream[\r\n]+(.*?)[\r\n]*endstream/s', $body, $sm)) {
            $un = @gzuncompress($sm[1]);
            if ($un === false) {
                $un = @gzuncompress(trim($sm[1]));
            }
            return ($un !== false) ? $un : $sm[1];
        }
        return '';
    };

    $fontObjToCmap = [];
    foreach ($objs as $id => $body) {
        if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $body, $tm)) {
            $fontObjToCmap[$id] = pas_decode_tounicode_cmap($streamOf((int)$tm[1]));
        }
    }

    $pageTexts = [];
    foreach ($objs as $id => $body) {
        if (!preg_match('/\/Type\s*\/Page\b/', $body) || preg_match('/\/Type\s*\/Pages\b/', $body)) {
            continue;
        }
        $resDict = $body;
        if (preg_match('/\/Resources\s+(\d+)\s+\d+\s+R/', $body, $rm)) {
            $resDict = $objs[(int)$rm[1]] ?? $body;
        }
        $localFontCmaps = [];
        if (preg_match('/\/Font\s*<<(.*?)>>/s', $resDict, $fm)) {
            if (preg_match_all('/\/([A-Za-z0-9_]+)\s+(\d+)\s+\d+\s+R/', $fm[1], $fRefs, PREG_SET_ORDER)) {
                foreach ($fRefs as $fr) {
                    $localFontCmaps[$fr[1]] = $fontObjToCmap[(int)$fr[2]] ?? [];
                }
            }
        }
        if (preg_match('/\/Font\s+(\d+)\s+\d+\s+R/', $resDict, $fObjRef)) {
            $fontBlock = $objs[(int)$fObjRef[1]] ?? '';
            if (preg_match_all('/\/([A-Za-z0-9_]+)\s+(\d+)\s+\d+\s+R/', $fontBlock, $fRefs, PREG_SET_ORDER)) {
                foreach ($fRefs as $fr) {
                    $localFontCmaps[$fr[1]] = $fontObjToCmap[(int)$fr[2]] ?? [];
                }
            }
        }

        $contentIds = [];
        if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R/', $body, $cm)) {
            $contentIds[] = (int)$cm[1];
        } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $body, $cm)) {
            if (preg_match_all('/(\d+)\s+\d+\s+R/', $cm[1], $cList)) {
                foreach ($cList[1] as $cid) {
                    $contentIds[] = (int)$cid;
                }
            }
        }

        $pageText = '';
        $currentFont = '';
        $bdcStack = []; // Tracks nested BDC/EMC and whether inside ActualText replacement span
        foreach ($contentIds as $cId) {
            $cs = $streamOf($cId);
            $pattern = '/\/Span\s*<<\s*\/ActualText\s*(?:<([0-9A-Fa-f]+)>|\(([^()]*)\))\s*>>\s*BDC'
                . '|\/[A-Za-z0-9_]+\s*(?:<<.*?>>)?\s*BDC'
                . '|\bEMC\b'
                . '|\/([A-Za-z0-9_]+)\s+[\d.]+\s+Tf'
                . '|\[(.*?)\]\s*TJ'
                . '|<([0-9A-Fa-f]+)>\s*Tj'
                . '|\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)\s*T[jJ]/s';

            if (preg_match_all($pattern, $cs, $ops, PREG_SET_ORDER)) {
                foreach ($ops as $op) {
                    $rawOp = $op[0];
                    if (strpos($rawOp, '/ActualText') !== false) {
                        if (!empty($op[1])) {
                            $hx = $op[1];
                            if (stripos($hx, 'FEFF') === 0) {
                                $hx = substr($hx, 4);
                            }
                            if (strlen($hx) % 2 === 1) {
                                $hx = '0' . $hx;
                            }
                            $bin = hex2bin($hx);
                            if ($bin !== false) {
                                $pageText .= mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
                            }
                        } elseif (isset($op[2]) && $op[2] !== '') {
                            $pageText .= stripcslashes($op[2]);
                        }
                        $bdcStack[] = true; // Suppress raw glyph fallback inside ActualText span
                    } elseif (preg_match('/BDC$/', $rawOp)) {
                        $bdcStack[] = false;
                    } elseif ($rawOp === 'EMC') {
                        array_pop($bdcStack);
                    } elseif (!empty($op[3])) {
                        $currentFont = $op[3];
                    } elseif (in_array(true, $bdcStack, true)) {
                        continue; // Glyphs inside ActualText BDC...EMC already represented by ActualText
                    } elseif (!empty($op[4])) {
                        $cmap = $localFontCmaps[$currentFont] ?? [];
                        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $op[4], $hxArr)) {
                            foreach ($hxArr[1] as $hx) {
                                for ($i = 0; $i + 3 < strlen($hx); $i += 4) {
                                    $cid = strtoupper(substr($hx, $i, 4));
                                    $pageText .= $cmap[$cid] ?? '';
                                }
                            }
                        }
                    } elseif (!empty($op[5])) {
                        $cmap = $localFontCmaps[$currentFont] ?? [];
                        $hx = $op[5];
                        for ($i = 0; $i + 3 < strlen($hx); $i += 4) {
                            $cid = strtoupper(substr($hx, $i, 4));
                            $pageText .= $cmap[$cid] ?? '';
                        }
                    } elseif (!empty($op[6])) {
                        $pageText .= stripcslashes($op[6]);
                    }
                }
            }
        }
        $pageTexts[] = $pageText;
    }

    preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes, $pageMatches);
    preg_match_all('/\/Subtype\s*\/Image\b/', $pdfBytes, $imgMatches);

    return [
        'text'       => implode("\n", $pageTexts),
        'pages'      => $pageTexts,
        'page_count' => max(count($pageTexts), count($pageMatches[0])),
        'image_count'=> count($imgMatches[0]),
        'cmap_size'  => count($fontObjToCmap),
    ];
}

// Track baseline DB/files cleanly
$cookieDir = sys_get_temp_dir();
$anonCookie      = $cookieDir . '/rmu_pas_anon_' . getmypid() . '.txt';
$attackerCookie  = $cookieDir . '/rmu_pas_atk_' . getmypid() . '.txt';
$otherStuCookie  = $cookieDir . '/rmu_pas_stu_other_' . getmypid() . '.txt';
$adminCookie     = $cookieDir . '/rmu_pas_admin_' . getmypid() . '.txt';
$studentCookie   = $cookieDir . '/rmu_pas_student_' . getmypid() . '.txt';
$tmpPhotoPath    = $cookieDir . '/rmu_pas_photo_' . getmypid() . '.png';

foreach ([$anonCookie, $attackerCookie, $otherStuCookie, $adminCookie, $studentCookie, $tmpPhotoPath] as $f) {
    @unlink($f);
}

// Create minimal valid 2x2 PNG file for multipart photo upload (no GD required)
$tinyPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEklEQVR42mNkYPhfzwAEjDAGAAiAAf+9rQ0ZAAAAAElFTkSuQmCC';
file_put_contents($tmpPhotoPath, base64_decode($tinyPngBase64));

ensure_admission_id_generation_schema($db);
ensure_admission_application_system_schema($db);
$seqSnapshot = $db->query("SELECT seq_year, last_app_seq, last_roll_seq FROM admission_sequences")->fetchAll(PDO::FETCH_ASSOC);
$maxResIdBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM admission_id_reservations")->fetchColumn();
$maxAuditIdBefore = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM audit_logs")->fetchColumn();

$createdAdmId = 0;
$createdStudentIdPk = 0;
$idorStudentIdPk = 0;
$filesToDelete = [];

try {
    echo "\n=== RMU Premium University Admission Application System E2E Test ===\n\n";

    // Locate an active Bachelor program & department
    $prog = $db->query("
        SELECT p.*, d.name_en AS dept_name
        FROM programs p
        JOIN departments d ON p.department_id = d.id
        WHERE p.is_active = 1 AND p.degree_type = 'Bachelor / Honours'
        ORDER BY p.id ASC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    pas_assert(!empty($prog), "Active Bachelor program + department found in DB");

    $branding = get_university_official_branding($db);
    pas_assert(
        $branding['name_en_upper'] === 'RABINDRA MAITREE UNIVERSITY'
        && $branding['name_bn'] === 'রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়'
        && strpos($branding['address'], 'Kushtia') !== false
        && $branding['phone'] !== ''
        && $branding['email'] !== '',
        "University official branding resolves real DB details + English & Bengali names"
    );

    // -------------------------------------------------------------------------
    // 1. Bilingual Online Application Submission + Photo + SSC/HSC Reg No
    // -------------------------------------------------------------------------
    echo "1. Testing Bilingual Intake Form Submission & Auto-Generated Individual PDF:\n";
    $formGet = pas_http($baseUrl . '/admission-form.php', $anonCookie);
    pas_assert($formGet['code'] === 200, "admission-form.php returns HTTP 200");
    pas_assert(
        strpos($formGet['body'], 'name="ssc_reg_no"') !== false
        && strpos($formGet['body'], 'name="hsc_reg_no"') !== false,
        "admission-form.php includes SSC & HSC Registration Number fields"
    );

    $formCsrf = pas_csrf($formGet['body']);
    preg_match('/id="draftTokenInput"\s+value="([^"]+)"/', $formGet['body'], $dtMatch);
    $draftToken = $dtMatch[1] ?? '';
    pas_assert($formCsrf !== '' && $draftToken !== '', "CSRF token and server reservation draft_token retrieved");

    $uniqueNum = substr((string)time(), -6);
    $testMob = '017' . sprintf('%08d', (int)$uniqueNum + 100000);
    $testEmail = "bilingual.e2e.{$uniqueNum}@rmu.test";
    $bilingualName = "Saiful Islam (সাইফুল ইসলাম)";
    $testSscReg = '1122334455';
    $testHscReg = '6677889900';

    $submitRes = pas_http($baseUrl . '/actions/submit-admission.php', $anonCookie, [
        'csrf_token'         => $formCsrf,
        'draft_token'        => $draftToken,
        'application_year'   => '2026',
        'full_name'          => $bilingualName,
        'father_name'        => 'Md. Abdul Karim (মোঃ আব্দুল করিম)',
        'mother_name'        => 'Mst. Halima Begum (হালিমা বেগম)',
        'date_of_birth'      => '2005-03-15',
        'gender'             => 'Male',
        'mobile'             => $testMob,
        'email'              => $testEmail,
        'present_address'    => 'Courtpara, Kushtia 7000 (কোর্টপাড়া, কুষ্টিয়া)',
        'permanent_address'  => 'Bheramara, Kushtia (ভেড়ামারা, কুষ্টিয়া)',
        'last_college'       => 'Kushtia Govt. College',
        'home_district'      => 'Kushtia',
        'ssc_exam'           => 'S.S.C',
        'ssc_roll'           => '102030',
        'ssc_reg_no'         => $testSscReg,
        'ssc_board'          => 'Jashore',
        'ssc_result_type'    => 'GPA(out of 5)',
        'ssc_gpa'            => '5.00',
        'ssc_group'          => 'Science',
        'ssc_year'           => '2021',
        'hsc_exam'           => 'H.S.C',
        'hsc_roll'           => '405060',
        'hsc_reg_no'         => $testHscReg,
        'hsc_board'          => 'Jashore',
        'hsc_result_type'    => 'GPA(out of 5)',
        'hsc_gpa'            => '4.92',
        'hsc_group'          => 'Science',
        'hsc_year'           => '2023',
        'degree_type'        => 'Bachelor / Honours',
        'department_id'      => (string)$prog['department_id'],
        'program_id'         => (string)$prog['id'],
        'interested_subject' => (string)$prog['title_en'],
        'academic_session'   => 'Fall 2026',
        'passing_year'       => '2026',
        'quota'              => 'NO',
    ], ['applicant_photo' => $tmpPhotoPath]);

    pas_assert($submitRes['code'] === 200 && strpos($submitRes['url'], 'admission-payment.php?ref=') !== false, "Submission redirects to ৳150 payment page");

    $stmtAdm = $db->prepare("SELECT * FROM admissions WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $stmtAdm->execute([$testMob]);
    $adm = $stmtAdm->fetch(PDO::FETCH_ASSOC);
    pas_assert(!empty($adm), "Admission row persisted in DB");
    $createdAdmId = (int)$adm['id'];

    if (!empty($adm['photo'])) {
        $filesToDelete[] = BASE_PATH . '/storage/uploads/students/' . $adm['photo'];
    }

    pas_assert(
        $adm['ssc_reg_no'] === $testSscReg && $adm['hsc_reg_no'] === $testHscReg,
        "SSC & HSC Registration Numbers saved cleanly in DB"
    );
    pas_assert(strlen((string)$adm['verification_token']) >= 32, "Unique verification_token generated and saved");
    pas_assert(
        empty($adm['pdf_path']) && ($adm['payment_status'] ?? 'Pending') !== 'Paid',
        "Pre-payment Paid Application Card PDF is strictly NOT generated prior to genuine payment verification"
    );

    // -------------------------------------------------------------------------
    // 2. Application Status Page (Unpaid State) & Unpaid Document Guards
    // -------------------------------------------------------------------------
    echo "\n2. Testing Application Status (Pre-Payment Lock) & Unpaid Card/Receipt Guards:\n";
    $statusUnpaid = pas_http($baseUrl . '/admission-status.php?ref=' . urlencode($adm['application_ref']), $anonCookie);
    pas_assert(
        $statusUnpaid['code'] === 200
        && strpos($statusUnpaid['body'], 'pendingApplicationSummary') !== false
        && strpos($statusUnpaid['body'], 'admitCardPrint') === false,
        "Unpaid admission-status.php shows summary cleanly while locking Official Paid Application Card"
    );
    pas_assert(
        strpos($statusUnpaid['body'], 'Pay ৳150 &amp; Generate Application Card') !== false
        && strpos($statusUnpaid['body'], 'Unpaid') !== false
        && strpos($statusUnpaid['body'], 'Pending Admin Acceptance') !== false,
        "Unpaid application shows Unpaid payment status, Pending Admin Acceptance Student ID status, and Pay CTA"
    );

    $unpaidReceiptAttempt = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=view', $anonCookie);
    pas_assert($unpaidReceiptAttempt['code'] === 403 && strpos($unpaidReceiptAttempt['body'], 'Payment Receipt Unavailable') !== false, "Unpaid Payment Receipt access is strictly blocked (HTTP 403)");

    $unpaidCardAttempt = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=pdf', $anonCookie);
    pas_assert($unpaidCardAttempt['code'] === 403 && strpos($unpaidCardAttempt['body'], '%PDF-') === false, "Unpaid Paid Application Card PDF access is strictly blocked (HTTP 403)");

    // -------------------------------------------------------------------------
    // 3. Complete ৳150 Payment via Sandbox Gateway & Verify Generated Card + PDF + Receipt
    // -------------------------------------------------------------------------
    echo "\n3. Testing ৳150 Sandbox Payment Gateway, Immediate Application Card & Bilingual PDF:\n";
    $payPg = pas_http($baseUrl . '/admission-payment.php?ref=' . urlencode($adm['application_ref']), $anonCookie);
    pas_assert($payPg['code'] === 200 && strpos($payPg['body'], 'name="transaction_id"') === false, "Payment checkout page loads without manual Transaction ID prompt");
    $payCsrf = pas_csrf($payPg['body']);
    preg_match('/name="idempotency_key"\s+value="([^"]+)"/', $payPg['body'], $pasIdemMatch);

    $gwRes = pas_http($baseUrl . '/actions/process-admission-payment.php', $anonCookie, [
        'csrf_token'      => $payCsrf,
        'action'          => 'initiate_sandbox_payment',
        'ref'             => $adm['application_ref'],
        'idempotency_key' => $pasIdemMatch[1] ?? '',
        'payment_method'  => 'bKash',
        'sender_number'   => $testMob,
    ]);
    pas_assert($gwRes['code'] === 200 && strpos($gwRes['url'], 'sandbox-gateway.php?session=') !== false, "Checkout launched Sandbox Payment Gateway session");
    preg_match('/session=([A-Za-z0-9_-]+)/', $gwRes['url'], $pasSessMatch);
    preg_match('/id="sandboxTestOtpBadge"[^>]*>\s*([0-9]{6})\s*</', $gwRes['body'], $pasOtpMatch);

    $payPost = pas_http($baseUrl . '/sandbox-gateway.php?session=' . urlencode($pasSessMatch[1] ?? ''), $anonCookie, [
        'csrf_token'  => pas_csrf($gwRes['body']),
        'session'     => $pasSessMatch[1] ?? '',
        'gw_action'   => 'authorize',
        'sandbox_otp' => $pasOtpMatch[1] ?? '',
    ]);
    pas_assert($payPost['code'] === 200 && strpos($payPost['url'], 'admission-status.php?ref=') !== false, "Sandbox payment processed and redirected to status page");
    pas_assert(strpos($payPost['body'], 'paymentVerifiedCardBanner') !== false && strpos($payPost['body'], 'admitCardPrint') !== false, "Generated Application Card & Verified Banner appear immediately upon payment");

    foreach (['Expected Completion', 'Application Record Year', 'Sender &amp; Gateway', 'Sender & Gateway', 'Md. Rafiqul Islam', 'Photo Verified', 'Change Photo', 'printableVoucher'] as $bannedHtml) {
        pas_assert(stripos($payPost['body'], $bannedHtml) === false, "Generated Application Card HTML omits removed element '{$bannedHtml}'");
    }

    $admAfterPay = $db->query("SELECT * FROM admissions WHERE id = {$createdAdmId}")->fetch(PDO::FETCH_ASSOC);
    $trxId = (string)($admAfterPay['transaction_id'] ?? '');
    if (!empty($admAfterPay['payment_screenshot'])) {
        $filesToDelete[] = BASE_PATH . '/storage/uploads/payments/' . $admAfterPay['payment_screenshot'];
    }
    if (!empty($admAfterPay['pdf_path'])) {
        $filesToDelete[] = BASE_PATH . '/' . $admAfterPay['pdf_path'];
    }
    pas_assert($admAfterPay['payment_status'] === 'Paid' && $trxId !== '', "Payment verified as Paid in DB with Sandbox Gateway TrxID ({$trxId})");
    pas_assert(!empty($admAfterPay['payment_screenshot']) && is_file(BASE_PATH . '/storage/uploads/payments/' . $admAfterPay['payment_screenshot']), "SVG payment slip generated cleanly");
    pas_assert(
        !empty($admAfterPay['pdf_path']) && is_file(BASE_PATH . '/' . $admAfterPay['pdf_path']) && filesize(BASE_PATH . '/' . $admAfterPay['pdf_path']) > 5000,
        "Individual Application Card PDF synchronously generated and stored on disk after payment verification ({$admAfterPay['pdf_path']})"
    );

    // Inspect generated PDF binary for selectable English + Bengali text & page numbers
    $pdfOnDiskBytes = (string)file_get_contents(BASE_PATH . '/' . $admAfterPay['pdf_path']);
    pas_assert(strpos($pdfOnDiskBytes, '%PDF-1.') === 0 && strpos($pdfOnDiskBytes, '%%EOF') !== false, "Stored PDF has valid %PDF-1.x header & %%EOF trailer");
    $pdfExtracted = pas_extract_pdf_text($pdfOnDiskBytes);
    pas_assert($pdfExtracted['page_count'] >= 1, "PDF renders valid A4 page count ({$pdfExtracted['page_count']} page)");
    pas_assert($pdfExtracted['image_count'] >= 1, "PDF embeds raster/image XObjects cleanly");

    $flatText = preg_replace('/\s+/u', ' ', $pdfExtracted['text']);
    foreach ([
        'RABINDRA MAITREE UNIVERSITY',
        'রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়',
        'ADMISSION APPLICATION CARD',
        $admAfterPay['application_id'],
        'সাইফুল ইসলাম',
        'Saiful Islam',
        $testSscReg,
        $testHscReg,
        'Authorized Admission Officer',
        'Page 1 of',
    ] as $pdfNeedle) {
        pas_assert(mb_strpos($flatText, $pdfNeedle, 0, 'UTF-8') !== false, "Generated Application PDF contains selectable text: '{$pdfNeedle}'");
    }

    foreach ([
        'Expected Completion',
        'Application Record Year',
        'Sender & Gateway',
        'Md. Rafiqul Islam',
        'Photo Verified',
        'Change Photo',
    ] as $bannedNeedle) {
        pas_assert(stripos($flatText, $bannedNeedle) === false, "Main Application PDF omits banned text: '{$bannedNeedle}'");
    }

    $receiptView = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=view', $anonCookie);
    pas_assert($receiptView['code'] === 200 && strpos($receiptView['body'], 'OFFICIAL PAYMENT RECEIPT') !== false, "Separate Payment Receipt HTML page loads");
    foreach ([$branding['name_bn'], $adm['application_id'], '150.00 BDT', $trxId, 'bKash', $testMob, 'Paid / Verified'] as $rcNeedle) {
        pas_assert(mb_strpos($receiptView['body'], $rcNeedle, 0, 'UTF-8') !== false, "Payment Receipt displays '{$rcNeedle}'");
    }

    $receiptPdf = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=download', $anonCookie);
    pas_assert(
        $receiptPdf['code'] === 200
        && stripos($receiptPdf['type'], 'application/pdf') !== false
        && strpos($receiptPdf['body'], '%PDF-1.') === 0,
        "Separate Payment Receipt PDF downloads with valid %PDF binary & application/pdf header"
    );
    $receiptPdfText = pas_extract_pdf_text($receiptPdf['body'])['text'];
    pas_assert(
        mb_strpos($receiptPdfText, $trxId, 0, 'UTF-8') !== false
        && mb_strpos($receiptPdfText, 'রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়', 0, 'UTF-8') !== false,
        "Payment Receipt PDF contains selectable TrxID & Bengali University Name"
    );

    // -------------------------------------------------------------------------
    // 4. QR Code Verification Portal & Strict Privacy Protection
    // -------------------------------------------------------------------------
    echo "\n4. Testing Public QR Verification Portal & Strict Privacy Guards:\n";
    $validQrUrl = $baseUrl . '/verify-application.php?ref=' . urlencode($admAfterPay['application_id']) . '&token=' . urlencode($admAfterPay['verification_token']);
    $qrOk = pas_http($validQrUrl, $attackerCookie); // completely fresh anonymous session!
    pas_assert($qrOk['code'] === 200 && strpos($qrOk['body'], 'Authentic Application Record Verified') !== false, "Public QR link verifies authentic record from fresh anonymous client");
    foreach ([$admAfterPay['application_id'], 'Saiful Islam', 'সাইফুল ইসলাম', 'Pending', 'Paid', $prog['title_en']] as $pubOk) {
        pas_assert(mb_strpos($qrOk['body'], $pubOk, 0, 'UTF-8') !== false, "Public QR verification shows safe non-private field '{$pubOk}'");
    }
    // Strictly forbid private PII on public QR page
    foreach ([$testMob, $testEmail, 'Md. Abdul Karim', 'Halima Begum', 'Courtpara', 'Bheramara', '102030', '405060', $testSscReg, $testHscReg, $trxId] as $privatePii) {
        pas_assert(mb_strpos($qrOk['body'], $privatePii, 0, 'UTF-8') === false, "Public QR verification strictly hides private PII '{$privatePii}'");
    }

    $tamperedQr = pas_http($baseUrl . '/verify-application.php?ref=' . urlencode($admAfterPay['application_id']) . '&token=tampered_invalid_token_123456', $attackerCookie);
    pas_assert($tamperedQr['code'] === 200 && strpos($tamperedQr['body'], 'Verification Failed') !== false && strpos($tamperedQr['body'], 'Authentic Application Record Verified') === false, "Tampered QR token fails verification cleanly");

    // -------------------------------------------------------------------------
    // 5. IDOR & Unauthorized Access Protection on PDF & Document Routes
    // -------------------------------------------------------------------------
    echo "\n5. Testing Unauthenticated & Cross-Student IDOR Blocking:\n";
    $unauthPdf = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=pdf', $attackerCookie);
    pas_assert($unauthPdf['code'] === 403 && strpos($unauthPdf['body'], '%PDF-') === false, "Unauthenticated direct request to Application PDF blocked with HTTP 403");

    $unauthReceipt = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=download', $attackerCookie);
    pas_assert($unauthReceipt['code'] === 403 && strpos($unauthReceipt['body'], '%PDF-') === false, "Unauthenticated direct request to Payment Receipt PDF blocked with HTTP 403");

    // Create an authenticated disposable second student to verify real cross-student IDOR blocking (HTTP 403)
    $idorCode = 'RMUIDOR' . $uniqueNum;
    $idorPass = 'IdorPass@2026!';
    $idorHash = password_hash($idorPass, PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO students (student_id, username, full_name, email, phone, password_hash, program_id, department_id, academic_session, status) VALUES (?, ?, 'IDOR Peer Student', ?, '01811223344', ?, ?, ?, 'Fall 2026', 'Active')")
       ->execute([$idorCode, $idorCode, "idor.{$uniqueNum}@rmu.test", $idorHash, (int)$prog['id'], (int)$prog['department_id']]);
    $idorStudentIdPk = (int)$db->lastInsertId();

    $otherLogin = pas_http($baseUrl . '/student/login.php', $otherStuCookie);
    $otherLoginPost = pas_http($baseUrl . '/student/login.php', $otherStuCookie, [
        'csrf_token' => pas_csrf($otherLogin['body']),
        'student_id' => $idorCode,
        'password'   => $idorPass,
    ]);
    pas_assert($otherLoginPost['code'] === 200 && strpos($otherLoginPost['url'], '/student/dashboard.php') !== false, "Cross-student test account authenticated cleanly");
    $idorRes = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=pdf', $otherStuCookie);
    pas_assert($idorRes['code'] === 403 && strpos($idorRes['body'], '%PDF-') === false, "Cross-student IDOR access to another student's Application PDF blocked with HTTP 403");
    $idorRecRes = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=view', $otherStuCookie);
    pas_assert($idorRecRes['code'] === 403 && strpos($idorRecRes['body'], $trxId) === false, "Cross-student IDOR access to another student's Payment Receipt blocked with HTTP 403");

    // -------------------------------------------------------------------------
    // 6. Admin Panel: Dept + Session Filters, Document Actions, Accept -> Auto Update
    // -------------------------------------------------------------------------
    echo "\n6. Testing Admin Panel Department & Session Filters + Accept Synchronization:\n";
    $admLoginGet = pas_http($baseUrl . '/admin/login.php', $adminCookie);
    $admLoginPost = pas_http($baseUrl . '/admin/login.php', $adminCookie, [
        'csrf_token' => pas_csrf($admLoginGet['body']),
        'username'   => 'admin',
        'password'   => 'Admin@RMU2026!',
    ]);
    pas_assert($admLoginPost['code'] === 200 && strpos($admLoginPost['url'], '/admin/dashboard.php') !== false, "Admin authentication succeeded");

    $filteredAdmin = pas_http(
        $baseUrl . '/admin/admissions.php?department_id=' . (int)$prog['department_id'] . '&academic_session=' . urlencode('Fall 2026') . '&search=' . urlencode($admAfterPay['application_id']),
        $adminCookie
    );
    pas_assert($filteredAdmin['code'] === 200 && strpos($filteredAdmin['body'], $admAfterPay['application_id']) !== false, "Admin Department + Academic Session filter locates target application");
    pas_assert(
        strpos($filteredAdmin['body'], 'admission-document.php?id=' . $createdAdmId . '&amp;doc=application&amp;action=view') !== false
        && strpos($filteredAdmin['body'], 'admission-document.php?id=' . $createdAdmId . '&amp;doc=application&amp;action=pdf') !== false
        && strpos($filteredAdmin['body'], 'admission-document.php?id=' . $createdAdmId . '&amp;doc=application&amp;action=download') !== false
        && strpos($filteredAdmin['body'], 'admission-document.php?id=' . $createdAdmId . '&amp;doc=application&amp;action=print') !== false
        && strpos($filteredAdmin['body'], 'admission-document.php?id=' . $createdAdmId . '&amp;doc=receipt&amp;action=view') !== false,
        "Admin row exposes all 5 real document action links (View, PDF, Download, Print, Receipt)"
    );

    // Accept the application cleanly via POST
    $accRes = pas_http($baseUrl . '/admin/admissions.php', $adminCookie, [
        'csrf_token'  => pas_csrf($filteredAdmin['body']),
        'action'      => 'accept_application',
        'id'          => (string)$createdAdmId,
        'admin_notes' => 'E2E Premium Approved',
    ]);
    pas_assert($accRes['code'] === 200, "Admin Accept action completes");

    $admAccepted = $db->query("SELECT * FROM admissions WHERE id = {$createdAdmId}")->fetch(PDO::FETCH_ASSOC);
    pas_assert($admAccepted['status'] === 'Accepted' && !empty($admAccepted['student_id']) && !empty($admAccepted['portal_password']), "Accepted application provisioned Student ID ({$admAccepted['student_id']})");

    $stRow = $db->prepare("SELECT id FROM students WHERE student_id = ? LIMIT 1");
    $stRow->execute([$admAccepted['student_id']]);
    $createdStudentIdPk = (int)$stRow->fetchColumn();
    pas_assert($createdStudentIdPk > 0, "Student row created in students table");

    // Verify Admin status change updated QR verification + regenerated PDF + View/Print endpoints
    $qrAfterAccept = pas_http($validQrUrl, $attackerCookie);
    pas_assert(strpos($qrAfterAccept['body'], 'Approved') !== false && strpos($qrAfterAccept['body'], 'Issued') !== false, "QR verification automatically shows Approved & Student ID Issued after admin acceptance");

    $pdfAfterAccept = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=pdf', $adminCookie);
    pas_assert($pdfAfterAccept['code'] === 200 && strpos($pdfAfterAccept['body'], '%PDF-1.') === 0, "Admin View PDF returns valid %PDF binary");
    $accPdfText = pas_extract_pdf_text($pdfAfterAccept['body'])['text'];
    pas_assert(strpos($accPdfText, 'Approved') !== false && strpos($accPdfText, $admAccepted['student_id']) !== false, "Auto-refreshed Application PDF displays live Approved status and issued Student ID");

    $printView = pas_http($baseUrl . '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=print', $adminCookie);
    pas_assert($printView['code'] === 200 && strpos($printView['body'], 'window.print()') !== false, "Print Application wrapper loads cleanly and triggers browser print");

    // -------------------------------------------------------------------------
    // 7. Student Panel: My Applications Section + All 5 Document Actions
    // -------------------------------------------------------------------------
    echo "\n7. Testing Student Portal My Applications & All 5 Document Endpoints:\n";
    $stLoginGet = pas_http($baseUrl . '/student/login.php', $studentCookie);
    $stLoginPost = pas_http($baseUrl . '/student/login.php', $studentCookie, [
        'csrf_token' => pas_csrf($stLoginGet['body']),
        'student_id' => $admAccepted['student_id'],
        'password'   => $admAccepted['portal_password'],
    ]);
    pas_assert($stLoginPost['code'] === 200 && strpos($stLoginPost['url'], '/student/dashboard.php') !== false, "Admitted student logs into Student Portal cleanly");

    $dashBody = $stLoginPost['body'];
    pas_assert(strpos($dashBody, 'My Applications') !== false && strpos($dashBody, $admAccepted['application_id']) !== false, "Student Dashboard renders 'My Applications' with student's real Application ID");
    foreach (['View Application', 'View PDF', 'Download PDF', 'Print Application', 'Payment Receipt'] as $btnLabel) {
        pas_assert(strpos($dashBody, $btnLabel) !== false, "Student My Applications card exposes '{$btnLabel}' button");
    }

    foreach ([
        'View Application' => '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=view',
        'Print Application'=> '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=print',
        'Payment Receipt'  => '/admission-document.php?id=' . $createdAdmId . '&doc=receipt&action=view',
    ] as $lbl => $path) {
        $r = pas_http($baseUrl . $path, $studentCookie);
        pas_assert($r['code'] === 200 && strpos($r['body'], $admAccepted['application_id']) !== false, "Student button '{$lbl}' returns HTTP 200 with real record data");
    }

    foreach ([
        'View PDF'     => '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=pdf',
        'Download PDF' => '/admission-document.php?id=' . $createdAdmId . '&doc=application&action=download',
    ] as $lbl => $path) {
        $r = pas_http($baseUrl . $path, $studentCookie);
        pas_assert($r['code'] === 200 && stripos($r['type'], 'application/pdf') !== false && strpos($r['body'], '%PDF-1.') === 0, "Student button '{$lbl}' streams valid %PDF document");
    }

    // -------------------------------------------------------------------------
    // 8. Responsive CSS Rules Check
    // -------------------------------------------------------------------------
    echo "\n8. Verifying Responsive Media Queries for Mobile / Tablet / Desktop:\n";
    pas_assert(
        strpos($receiptView['body'], '@media (max-width: 767px)') !== false
        && strpos($printView['body'], 'grid-template-columns: 1fr') !== false
        && strpos($qrOk['body'], '@media (max-width: 640px)') !== false,
        "Document views and QR verification pages contain responsive mobile/tablet media rules"
    );

} finally {
    // Full clean rollback so live baseline data stays spotless
    foreach ([$anonCookie, $attackerCookie, $otherStuCookie, $adminCookie, $studentCookie, $tmpPhotoPath] as $f) {
        @unlink($f);
    }
    foreach ($filesToDelete as $fp) {
        if ($fp !== '' && is_file($fp)) {
            @unlink($fp);
        }
    }
    if ($idorStudentIdPk > 0) {
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$idorStudentIdPk]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$idorStudentIdPk]);
    }
    if ($createdStudentIdPk > 0) {
        $db->prepare("DELETE FROM student_notifications WHERE student_id = ?")->execute([$createdStudentIdPk]);
        $db->prepare("DELETE FROM student_courses WHERE student_id = ?")->execute([$createdStudentIdPk]);
        $db->prepare("DELETE FROM student_semester_results WHERE student_id = ?")->execute([$createdStudentIdPk]);
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$createdStudentIdPk]);
    }
    if ($createdAdmId > 0) {
        foreach (glob(BASE_PATH . '/storage/admissions/pdf/*_' . $createdAdmId . '.pdf') ?: [] as $pdfGlob) {
            if (is_file($pdfGlob)) {
                @unlink($pdfGlob);
            }
        }
        $db->prepare("DELETE FROM admission_payment_transactions WHERE admission_id = ?")->execute([$createdAdmId]);
        $db->prepare("DELETE FROM admissions WHERE id = ?")->execute([$createdAdmId]);
    }
    $db->prepare("DELETE FROM admission_id_reservations WHERE id > ?")->execute([$maxResIdBefore]);
    $db->prepare("DELETE FROM audit_logs WHERE id > ?")->execute([$maxAuditIdBefore]);
    foreach ($seqSnapshot as $sr) {
        $db->prepare("UPDATE admission_sequences SET last_app_seq = ?, last_roll_seq = ?, updated_at = NOW() WHERE seq_year = ?")
           ->execute([(int)$sr['last_app_seq'], (int)$sr['last_roll_seq'], (int)$sr['seq_year']]);
    }
}

echo "\n-------------------------------------------------------\n";
echo "PREMIUM ADMISSION E2E SUMMARY: PASSED = {$passed} | FAILED = {$failed}\n";
echo "-------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
