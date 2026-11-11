<?php
/**
 * Pure-PHP PDF 1.4 Academic Transcript Generator
 * Rabindra Maitree University (RMU)
 * Renders complete consolidated (1st-Nth) or single-semester transcripts with
 * genuine vector tables, wrapped course titles, multi-page pagination, credits,
 * SGPA, CGPA, UGC grading scale, and official Controller/Registrar verification block.
 */

class RmuTranscriptPdfBuilder {
    private float $pageW = 595.28; // A4 portrait width (pt)
    private float $pageH = 841.89; // A4 portrait height (pt)
    private float $marginL = 32.0;
    private float $marginR = 32.0;
    private float $marginT = 28.0;
    private float $maxY = 792.0;   // Footer trigger threshold

    private float $usableW;
    private float $y = 28.0;

    /** @var array<int, string> */
    private array $pages = [];
    private string $currentStream = '';
    private string $contHeaderTitle = '';
    private string $contHeaderMeta = '';
    private string $footerLeft = '';

    public function __construct(string $contTitle = '', string $contMeta = '', string $footerLeft = '') {
        $this->usableW = $this->pageW - $this->marginL - $this->marginR;
        $this->contHeaderTitle = $contTitle;
        $this->contHeaderMeta = $contMeta;
        $this->footerLeft = $footerLeft;
        $this->startNewPage(false);
    }

    public function getMarginL(): float {
        return $this->marginL;
    }

    public function getUsableW(): float {
        return $this->usableW;
    }

    public function getY(): float {
        return $this->y;
    }

    public function setY(float $y): void {
        $this->y = $y;
    }

    public function advanceY(float $dy): void {
        $this->y += $dy;
    }

    private function startNewPage(bool $withContinuationHeader = true): void {
        if ($this->currentStream !== '') {
            $this->pages[] = $this->currentStream;
        }
        $this->currentStream = '';
        $this->y = $this->marginT;

        if ($withContinuationHeader && $this->contHeaderTitle !== '') {
            $this->drawRect($this->marginL, $this->y, $this->usableW, 24.0, [0.06, 0.17, 0.35], null);
            $this->drawText($this->marginL + 8, $this->y + 15.5, $this->contHeaderTitle, 'F2', 8.5, [1, 1, 1]);
            $this->drawTextBox($this->marginL + 220, $this->y + 15.5, $this->usableW - 228, $this->contHeaderMeta, 'F1', 7.5, [0.85, 0.91, 1.0], 'R');
            $this->y += 32.0;
        }
    }

    public function ensureSpace(float $neededH): bool {
        if (($this->y + $neededH) > $this->maxY) {
            $this->startNewPage(true);
            return true;
        }
        return false;
    }

    public static function sanitizeText(string $text): string {
        $map = [
            "\r\n" => ' ',
            "\n"   => ' ',
            "\r"   => ' ',
            "\t"   => ' ',
            '—'    => '-',
            '–'    => '-',
            '−'    => '-',
            '•'    => '*',
            '·'    => '*',
            '×'    => 'x',
            '÷'    => '/',
            '∑'    => 'Sum',
            '৳'    => 'BDT ',
            '’'    => "'",
            '‘'    => "'",
            '“'    => '"',
            '”'    => '"',
            '→'    => '->',
            '←'    => '<-',
            '✓'    => 'OK',
            '&amp;'  => '&',
            '&bull;' => '*',
            '&mdash;' => '-',
            '&ndash;' => '-',
            '&rarr;' => '->',
            '&lt;'   => '<',
            '&gt;'   => '>',
            '&quot;' => '"',
        ];
        $clean = strtr($text, $map);
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? '';
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $clean);
            if ($conv !== false) {
                $clean = $conv;
            }
        }
        // Strip non-printable ASCII/control bytes cleanly
        $out = '';
        $len = strlen($clean);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($clean[$i]);
            if ($ord >= 32 && $ord <= 255 && $ord !== 127) {
                $out .= $clean[$i];
            }
        }
        return $out;
    }

    private static function escapePdfString(string $text): string {
        $safe = self::sanitizeText($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $safe);
    }

    public static function estimateCharWidth(string $ch, string $font, float $size): float {
        if ($font === 'F4') {
            return $size * 0.60;
        }
        $boldScale = ($font === 'F2') ? 1.07 : 1.0;
        if ($ch === ' ') return $size * 0.28 * $boldScale;
        if (strpos('ilIjtf.,:;!|\'"`', $ch) !== false) return $size * 0.27 * $boldScale;
        if (strpos('r()-[]/', $ch) !== false) return $size * 0.36 * $boldScale;
        if (strpos('mwMW@%#', $ch) !== false) return $size * 0.84 * $boldScale;
        if ($ch >= 'A' && $ch <= 'Z') return $size * 0.69 * $boldScale;
        if ($ch >= '0' && $ch <= '9') return $size * 0.56 * $boldScale;
        return $size * 0.52 * $boldScale;
    }

    public static function measureTextWidth(string $text, string $font, float $size): float {
        $safe = self::sanitizeText($text);
        $w = 0.0;
        $len = strlen($safe);
        for ($i = 0; $i < $len; $i++) {
            $w += self::estimateCharWidth($safe[$i], $font, $size);
        }
        return $w;
    }

    /**
     * @return array<int, string>
     */
    public static function wrapText(string $text, string $font, float $size, float $maxWidth): array {
        $safe = self::sanitizeText($text);
        if ($safe === '') {
            return [''];
        }
        $words = explode(' ', $safe);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = ($current === '') ? $word : ($current . ' ' . $word);
            if (self::measureTextWidth($candidate, $font, $size) <= $maxWidth) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                // Break overly long single tokens
                if (self::measureTextWidth($word, $font, $size) > $maxWidth) {
                    $chunk = '';
                    $wLen = strlen($word);
                    for ($i = 0; $i < $wLen; $i++) {
                        $testChunk = $chunk . $word[$i];
                        if (self::measureTextWidth($testChunk, $font, $size) <= $maxWidth) {
                            $chunk = $testChunk;
                        } else {
                            if ($chunk !== '') {
                                $lines[] = $chunk;
                            }
                            $chunk = $word[$i];
                        }
                    }
                    $current = $chunk;
                } else {
                    $current = $word;
                }
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return !empty($lines) ? $lines : [''];
    }

    private function pdfY(float $topY): float {
        return $this->pageH - $topY;
    }

    public function drawRect(float $x, float $yTop, float $w, float $h, ?array $fillRgb = null, ?array $strokeRgb = [0.8, 0.84, 0.89], float $lineWidth = 0.5): void {
        $yBottom = $this->pageH - ($yTop + $h);
        $cmds = "q\n";
        if ($fillRgb !== null) {
            $cmds .= sprintf("%.3F %.3F %.3F rg\n", $fillRgb[0], $fillRgb[1], $fillRgb[2]);
        }
        if ($strokeRgb !== null) {
            $cmds .= sprintf("%.2F w %.3F %.3F %.3F RG\n", $lineWidth, $strokeRgb[0], $strokeRgb[1], $strokeRgb[2]);
        }
        $cmds .= sprintf("%.2F %.2F %.2F %.2F re\n", $x, $yBottom, $w, $h);
        if ($fillRgb !== null && $strokeRgb !== null) {
            $cmds .= "B\n";
        } elseif ($fillRgb !== null) {
            $cmds .= "f\n";
        } else {
            $cmds .= "S\n";
        }
        $cmds .= "Q\n";
        $this->currentStream .= $cmds;
    }

    public function drawLine(float $x1, float $y1Top, float $x2, float $y2Top, array $rgb = [0.8, 0.84, 0.89], float $lineWidth = 0.6, bool $dashed = false): void {
        $cmds = "q\n";
        if ($dashed) {
            $cmds .= "[3 2] 0 d\n";
        }
        $cmds .= sprintf("%.2F w %.3F %.3F %.3F RG\n", $lineWidth, $rgb[0], $rgb[1], $rgb[2]);
        $cmds .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $this->pdfY($y1Top), $x2, $this->pdfY($y2Top));
        $cmds .= "Q\n";
        $this->currentStream .= $cmds;
    }

    public function drawText(float $x, float $baselineYTop, string $text, string $font = 'F1', float $size = 9.0, array $rgb = [0.12, 0.16, 0.23]): void {
        $esc = self::escapePdfString($text);
        if ($esc === '') {
            return;
        }
        $cmds = sprintf(
            "BT\n/%s %.2F Tf\n%.3F %.3F %.3F rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $this->pdfY($baselineYTop),
            $esc
        );
        $this->currentStream .= $cmds;
    }

    public function drawTextBox(float $x, float $baselineYTop, float $boxW, string $text, string $font = 'F1', float $size = 9.0, array $rgb = [0.12, 0.16, 0.23], string $align = 'L'): void {
        $safe = self::sanitizeText($text);
        if ($safe === '') {
            return;
        }
        $w = self::measureTextWidth($safe, $font, $size);
        $drawX = $x;
        if ($align === 'C') {
            $drawX = $x + max(0.0, ($boxW - $w) / 2.0);
        } elseif ($align === 'R') {
            $drawX = $x + max(0.0, $boxW - $w);
        }
        $this->drawText($drawX, $baselineYTop, $safe, $font, $size, $rgb);
    }

    public function buildPdf(): string {
        $allPages = $this->pages;
        if ($this->currentStream !== '') {
            $allPages[] = $this->currentStream;
        }
        if (empty($allPages)) {
            $allPages[] = '';
        }

        $totalPages = count($allPages);
        $stampedPages = [];
        foreach ($allPages as $idx => $stream) {
            $pNum = $idx + 1;
            $footerStream = $stream;
            // Draw page footer line + text directly at bottom
            $fy = 816.0;
            $footerStream .= sprintf("q\n0.5 w 0.80 0.84 0.89 RG\n%.2F %.2F m %.2F %.2F l S\nQ\n",
                $this->marginL, $this->pageH - ($fy - 10), $this->marginL + $this->usableW, $this->pageH - ($fy - 10)
            );
            $leftEsc = self::escapePdfString($this->footerLeft);
            $rightEsc = self::escapePdfString("Page {$pNum} of {$totalPages}");
            $footerStream .= sprintf("BT\n/F1 7.50 Tf\n0.39 0.45 0.55 rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
                $this->marginL, $this->pageH - $fy, $leftEsc
            );
            $rw = self::measureTextWidth("Page {$pNum} of {$totalPages}", 'F1', 7.5);
            $footerStream .= sprintf("BT\n/F1 7.50 Tf\n0.39 0.45 0.55 rg\n1 0 0 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
                $this->marginL + $this->usableW - $rw, $this->pageH - $fy, $rightEsc
            );
            $stampedPages[] = $footerStream;
        }

        // Assemble PDF objects cleanly
        // Object layout:
        // 1: Catalog
        // 2: Pages
        // 3: Font F1 (Helvetica)
        // 4: Font F2 (Helvetica-Bold)
        // 5: Font F3 (Helvetica-Oblique)
        // 6: Font F4 (Courier-Bold)
        // For each page i (0..N-1):
        //   Page obj = 7 + i*2
        //   Content obj = 8 + i*2
        $objects = [];
        $pageObjIds = [];
        $firstContentObj = 7;

        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjIds[] = $firstContentObj + ($i * 2);
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $objects[2] = "<< /Type /Pages /Kids [ {$kidsStr} ] /Count {$totalPages} >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>";

        $useFlate = function_exists('gzcompress');
        for ($i = 0; $i < $totalPages; $i++) {
            $pObjId = $firstContentObj + ($i * 2);
            $cObjId = $pObjId + 1;
            $raw = $stampedPages[$i];

            $objects[$pObjId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >> >> /Contents %d 0 R >>",
                $this->pageW, $this->pageH, $cObjId
            );

            if ($useFlate) {
                $compressed = gzcompress($raw, 6);
                if ($compressed !== false) {
                    $len = strlen($compressed);
                    $objects[$cObjId] = "<< /Length {$len} /Filter /FlateDecode >>\nstream\n{$compressed}\nendstream";
                    continue;
                }
            }
            $len = strlen($raw);
            $objects[$cObjId] = "<< /Length {$len} >>\nstream\n{$raw}\nendstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjs = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}

/**
 * Render complete multi-semester or single-semester RMU transcript into PDF 1.4 binary string.
 */
function render_student_transcript_pdf(
    array $student,
    array $academicSummary,
    string $requestedSemester = 'all',
    string $transcriptSerial = '',
    string $issueDate = '',
    bool $isAdminPreview = false
): string {
    $progStruct = $academicSummary['program_structure'] ?? [
        'degree_type' => $student['degree_type'] ?? 'Bachelor / Honours',
        'duration_label' => $student['program_duration'] ?? '4 Years (8 Semesters)',
        'total_semesters' => (int)($student['total_semesters'] ?? 8),
    ];
    $totalSemesters = max(1, (int)($progStruct['total_semesters'] ?? 8));
    $allSemesters = $academicSummary['all_semesters'] ?? [];
    $groupedSemesters = $academicSummary['semesters'] ?? [];
    $semesterGpas = $academicSummary['semester_gpas'] ?? [];
    $hasResults = !empty($academicSummary['has_results']);
    $hasEstimated = !empty($academicSummary['has_estimated_results']);
    $finalCgpa = (float)($academicSummary['final_cgpa'] ?? 0.00);
    $totalRegCredits = (float)($academicSummary['total_registered_credits'] ?? 0.0);
    $totalEarnedCredits = (float)($academicSummary['total_earned_credits'] ?? 0.0);
    $totalReqCredits = (int)($academicSummary['total_required_credits'] ?? ($student['total_credits_required'] ?? 140));
    $publishedSemCount = (int)($academicSummary['published_semesters_count'] ?? count($groupedSemesters));
    $overallStatus = $academicSummary['overall_academic_status'] ?? ($student['status'] ?? 'Active');
    $isConsolidated = ($requestedSemester === '' || $requestedSemester === 'all');

    if ($issueDate === '') {
        $issueDate = date('d F Y');
    }
    if ($transcriptSerial === '') {
        $transcriptSerial = 'RMU-TR-' . date('Y') . '-' . strtoupper(substr(md5(($student['student_id'] ?? '') . $requestedSemester), 0, 8));
    }

    $stSession = !empty($student['academic_session']) ? $student['academic_session'] : ($student['batch'] ?? 'N/A');
    $contTitle = 'RABINDRA MAITREE UNIVERSITY - ACADEMIC TRANSCRIPT (CONT.)';
    $contMeta = ($student['full_name'] ?? '') . ' (' . ($student['student_id'] ?? '') . ') | CGPA: ' . number_format($finalCgpa, 2);
    $footerLeft = "Rabindra Maitree University, Kushtia | Official Record | Serial: {$transcriptSerial} | Issued: {$issueDate}";

    $pdf = new RmuTranscriptPdfBuilder($contTitle, $contMeta, $footerLeft);
    $x0 = $pdf->getMarginL();
    $w = $pdf->getUsableW();

    $navy = [0.06, 0.17, 0.35];
    $blue = [0.12, 0.45, 0.75];
    $dark = [0.10, 0.14, 0.22];
    $muted = [0.35, 0.42, 0.52];
    $green = [0.08, 0.50, 0.24];
    $red = [0.78, 0.14, 0.14];
    $amberBg = [1.0, 0.96, 0.90];
    $amberTxt = [0.65, 0.30, 0.04];

    // 1. Letterhead Banner
    $y = $pdf->getY();
    $pdf->drawRect($x0, $y, $w, 4.0, $navy, null);
    $y += 10.0;
    $pdf->drawTextBox($x0, $y + 12, $w, 'RABINDRA MAITREE UNIVERSITY', 'F2', 15.5, $navy, 'C');
    $y += 19.0;
    $pdf->drawTextBox($x0, $y + 8, $w, 'Kushtia, Bangladesh  |  Approved by Govt. of Bangladesh & University Grants Commission (UGC)', 'F1', 8.0, $muted, 'C');
    $y += 13.0;
    $pdf->drawTextBox($x0, $y + 8, $w, 'OFFICE OF THE CONTROLLER OF EXAMINATIONS', 'F2', 9.0, $dark, 'C');
    $y += 14.0;
    $pdf->drawLine($x0, $y, $x0 + $w, $y, $navy, 1.4);
    $y += 8.0;

    if ($isAdminPreview) {
        $pdf->drawRect($x0, $y, $w, 16.0, [0.99, 0.95, 0.85], [0.95, 0.65, 0.15]);
        $pdf->drawTextBox($x0, $y + 11.0, $w, 'ADMIN PREVIEW MODE - INTERNAL REVIEW TRANSCRIPT COPY', 'F2', 7.5, $amberTxt, 'C');
        $y += 20.0;
    }

    if ($hasEstimated) {
        $pdf->drawRect($x0, $y, $w, 18.0, [0.99, 0.92, 0.92], [0.86, 0.18, 0.18]);
        $pdf->drawTextBox($x0, $y + 11.5, $w, 'ESTIMATED / GENERATED - ESTIMATED / AUTO-GENERATED RESULT - NOT AN OFFICIAL UNIVERSITY RESULT', 'F2', 7.2, $red, 'C');
        $y += 22.0;
    }

    // Title Pill + Serial Strip
    $docTitle = $isConsolidated
        ? "CONSOLIDATED ACADEMIC TRANSCRIPT (1ST TO " . strtoupper(preg_replace('/\s+Semester$/', '', get_semester_ordinal_title($totalSemesters))) . " SEMESTER)"
        : "OFFICIAL SEMESTER GRADE SHEET & TRANSCRIPT";
    $pdf->drawRect($x0 + 60, $y, $w - 120, 20.0, $hasEstimated ? [0.70, 0.35, 0.04] : $navy, null);
    $pdf->drawTextBox($x0 + 60, $y + 13.5, $w - 120, $docTitle, 'F2', 8.8, [1, 1, 1], 'C');
    $y += 26.0;

    $pdf->drawTextBox(
        $x0,
        $y + 8,
        $w,
        "Transcript Serial: {$transcriptSerial}   |   Academic Session: {$stSession}   |   Date of Issue: {$issueDate}",
        'F1',
        7.5,
        $muted,
        'C'
    );
    $y += 14.0;

    // 2. Student Information Matrix (2 columns bordered box)
    $stName = (string)($student['full_name'] ?? 'N/A');
    $stCode = (string)($student['student_id'] ?? 'N/A');
    $stRoll = !empty($student['roll_number']) ? (string)$student['roll_number'] : 'N/A';
    $stAppId = !empty($student['application_number']) ? (string)$student['application_number'] : 'N/A';
    $stProg = (string)($student['program_name'] ?? 'Degree Program');
    $stDept = (string)($student['department_name'] ?? 'N/A');
    $stFac  = (string)($student['faculty_name'] ?? 'Faculty of Science & Engineering');
    $stDeg  = (string)($progStruct['degree_type'] ?? 'Bachelor / Honours') . ' (' . ($progStruct['duration_label'] ?? "{$totalSemesters} Semesters") . ')';
    $stTermLabel = $isConsolidated
        ? "Full {$totalSemesters} Semesters (1st to " . get_semester_ordinal_title($totalSemesters) . ")"
        : $requestedSemester;
    $stPassYr = !empty($student['passing_year']) ? (string)$student['passing_year'] : 'In Progress';
    $stPubDt = !empty($student['result_publication_date']) ? date('d M Y', strtotime($student['result_publication_date'])) : $issueDate;

    $infoRowsLeft = [
        ['Student Name', $stName],
        ['Student ID / Roll', "{$stCode} / {$stRoll}"],
        ['Application ID', $stAppId],
        ['Degree Type', $stDeg],
        ['Program', $stProg],
        ['Department', $stDept],
    ];
    $infoRowsRight = [
        ['Faculty', $stFac],
        ['Academic Session', $stSession],
        ['Passing Year', $stPassYr],
        ['Transcript Scope', $stTermLabel],
        ['Medium / Publish Date', "English | {$stPubDt}"],
        ['Overall CGPA', $hasResults ? number_format($finalCgpa, 2) . ' / 4.00' : 'N/A'],
    ];

    $boxH = (count($infoRowsLeft) * 14.0) + 10.0;
    $pdf->drawRect($x0, $y, $w, $boxH, [0.97, 0.98, 1.0], [0.78, 0.82, 0.88]);
    $halfW = $w / 2.0;
    $pdf->drawLine($x0 + $halfW, $y + 4, $x0 + $halfW, $y + $boxH - 4, [0.85, 0.88, 0.92], 0.5);

    for ($r = 0; $r < count($infoRowsLeft); $r++) {
        $ry = $y + 15.0 + ($r * 14.0);
        // Left pair
        $pdf->drawText($x0 + 8, $ry, $infoRowsLeft[$r][0] . ':', 'F2', 7.6, $muted);
        $lValLines = RmuTranscriptPdfBuilder::wrapText($infoRowsLeft[$r][1], 'F2', 7.6, $halfW - 96);
        $pdf->drawText($x0 + 86, $ry, $lValLines[0], ($r === 1 ? 'F4' : 'F2'), 7.6, $dark);
        // Right pair
        $pdf->drawText($x0 + $halfW + 8, $ry, $infoRowsRight[$r][0] . ':', 'F2', 7.6, $muted);
        $rValLines = RmuTranscriptPdfBuilder::wrapText($infoRowsRight[$r][1], 'F2', 7.6, $halfW - 104);
        $pdf->drawText($x0 + $halfW + 96, $ry, $rValLines[0], 'F2', 7.6, ($r === 5 && $hasResults ? $green : $dark));
    }
    $y += $boxH + 10.0;
    $pdf->setY($y);

    if (!$hasResults) {
        $pdf->ensureSpace(90.0);
        $cy = $pdf->getY();
        $pdf->drawRect($x0, $cy, $w, 60.0, [0.97, 0.98, 0.99], [0.78, 0.82, 0.88]);
        $pdf->drawTextBox($x0, $cy + 24.0, $w, 'NO ACADEMIC RESULTS PUBLISHED YET', 'F2', 11.0, $navy, 'C');
        $pdf->drawTextBox($x0, $cy + 41.0, $w, 'Official examination results will appear automatically once published by the Controller of Examinations.', 'F1', 8.2, $muted, 'C');
        $pdf->setY($cy + 74.0);
    } else {
        // 3. Top Overall Cumulative Academic Summary (5 KPI Cells)
        $pdf->ensureSpace(88.0);
        $cy = $pdf->getY();
        $pdf->drawRect($x0, $cy, $w, 40.0, [0.94, 0.97, 1.0], [0.70, 0.80, 0.92]);
        $kpis = [
            ['OVERALL CGPA', number_format($finalCgpa, 2) . ' / 4.00', $green],
            ['REGISTERED CREDITS', number_format($totalRegCredits, 1) . ' Cr', $navy],
            ['EARNED CREDITS', number_format($totalEarnedCredits, 1) . ' Cr', $navy],
            ['COMPLETED CREDITS', (int)$totalEarnedCredits . ' / ' . $totalReqCredits . ' Cr', $dark],
            ['FINAL ACADEMIC STATUS', $overallStatus, $blue],
        ];
        $cellW = $w / 5.0;
        foreach ($kpis as $ki => $kv) {
            $kx = $x0 + ($ki * $cellW);
            if ($ki > 0) {
                $pdf->drawLine($kx, $cy + 5, $kx, $cy + 35, [0.78, 0.84, 0.92], 0.5);
            }
            $pdf->drawTextBox($kx + 3, $cy + 13.0, $cellW - 6, $kv[0], 'F2', 6.3, $muted, 'C');
            $vLines = RmuTranscriptPdfBuilder::wrapText($kv[1], 'F2', 7.8, $cellW - 8);
            if (count($vLines) > 1) {
                $pdf->drawTextBox($kx + 3, $cy + 24.0, $cellW - 6, $vLines[0], 'F2', 7.5, $kv[2], 'C');
                $pdf->drawTextBox($kx + 3, $cy + 33.5, $cellW - 6, $vLines[1], 'F2', 7.5, $kv[2], 'C');
            } else {
                $pdf->drawTextBox($kx + 3, $cy + 28.5, $cellW - 6, $vLines[0], 'F2', 8.5, $kv[2], 'C');
            }
        }
        $cy += 46.0;

        // N-Semester GPA Overview Strip
        $colsPerRow = min(8, $totalSemesters);
        $stripRows = (int)ceil($totalSemesters / $colsPerRow);
        $stripW = $w / $colsPerRow;
        $pdf->drawText($x0, $cy + 9.0, strtoupper(($totalSemesters === 8 ? 'Eight-Semester' : "{$totalSemesters}-Semester") . " SGPA Progression Overview ({$publishedSemCount} of {$totalSemesters} Semesters Published):"), 'F2', 7.4, $navy);
        $cy += 13.0;
        for ($s = 1; $s <= $totalSemesters; $s++) {
            $rIdx = (int)floor(($s - 1) / $colsPerRow);
            $cIdx = ($s - 1) % $colsPerRow;
            $bx = $x0 + ($cIdx * $stripW);
            $by = $cy + ($rIdx * 28.0);
            $hasSem = isset($semesterGpas[$s]);
            $pdf->drawRect($bx, $by, $stripW - 2.0, 25.0, $hasSem ? [0.95, 0.99, 0.96] : [0.97, 0.98, 0.99], $hasSem ? [0.55, 0.82, 0.64] : [0.82, 0.85, 0.90]);
            $ordShort = preg_replace('/\s+Semester$/', '', get_semester_ordinal_title($s)) . ' Sem';
            $pdf->drawTextBox($bx, $by + 9.5, $stripW - 2.0, $ordShort, 'F2', 6.6, $muted, 'C');
            $pdf->drawTextBox($bx, $by + 20.5, $stripW - 2.0, $hasSem ? number_format((float)$semesterGpas[$s], 2) : 'Pending', 'F2', 8.0, $hasSem ? $green : [0.55, 0.60, 0.68], 'C');
        }
        $cy += ($stripRows * 28.0) + 10.0;
        $pdf->setY($cy);

        // 4. Semester Tables
        // Column geometry summing exactly to usable width 531.28pt
        $colW = [
            'sl'     => 22.0,
            'code'   => 62.0,
            'name'   => $w - 331.0, // ~200.28pt
            'credit' => 36.0,
            'full'   => 36.0,
            'obt'    => 38.0,
            'grade'  => 36.0,
            'gp'     => 40.0,
            'status' => 61.0,
        ];

        $drawTableHeader = function() use ($pdf, $x0, $w, $colW, $dark) {
            $ty = $pdf->getY();
            $pdf->drawRect($x0, $ty, $w, 17.0, [0.91, 0.94, 0.97], [0.72, 0.78, 0.86]);
            $cx = $x0;
            $headers = [
                ['sl', 'SL', 'C'],
                ['code', 'Code', 'L'],
                ['name', 'Subject Name', 'L'],
                ['credit', 'Credit', 'C'],
                ['full', 'Full', 'C'],
                ['obt', 'Obtained', 'C'],
                ['grade', 'Grade', 'C'],
                ['gp', 'GP', 'C'],
                ['status', 'Pass/Fail', 'C'],
            ];
            foreach ($headers as $hi => $h) {
                $cw = $colW[$h[0]];
                if ($hi > 0) {
                    $pdf->drawLine($cx, $ty, $cx, $ty + 17.0, [0.78, 0.82, 0.88], 0.4);
                }
                $padX = ($h[2] === 'L') ? $cx + 4 : $cx;
                $padW = ($h[2] === 'L') ? $cw - 6 : $cw;
                $pdf->drawTextBox($padX, $ty + 11.5, $padW, $h[1], 'F2', 7.3, $dark, $h[2]);
                $cx += $cw;
            }
            $pdf->setY($ty + 17.0);
        };

        $renderSemesterSection = function(int $semNum, string $semTitle, ?array $semData) use ($pdf, $x0, $w, $colW, $navy, $blue, $dark, $muted, $green, $red, $drawTableHeader) {
            $hasCourses = ($semData !== null && !empty($semData['courses']));
            $neededHeaderH = $hasCourses ? 58.0 : 44.0;
            $pdf->ensureSpace($neededHeaderH);

            $sy = $pdf->getY();
            if ($hasCourses) {
                $pdf->drawRect($x0, $sy, $w, 20.0, $navy, $navy);
                $leftLbl = "Semester {$semNum}: {$semTitle}" . (!empty($semData['is_estimated']) ? ' [ESTIMATED]' : '');
                $rightLbl = "SGPA: " . number_format((float)$semData['sgpa'], 2)
                    . "  |  Reg: " . number_format((float)$semData['registered_credits'], 1) . " Cr"
                    . "  |  Earned: " . number_format((float)$semData['earned_credits'], 1) . " Cr"
                    . "  |  Cum. CGPA: " . number_format((float)$semData['cumulative_cgpa'], 2);
                $pdf->drawText($x0 + 7, $sy + 13.2, $leftLbl, 'F2', 8.2, [1, 1, 1]);
                $pdf->drawTextBox($x0 + 200, $sy + 13.0, $w - 207, $rightLbl, 'F2', 7.4, [0.85, 0.96, 0.88], 'R');
                $pdf->setY($sy + 20.0);

                $drawTableHeader();

                $sl = 1;
                foreach ($semData['courses'] as $c) {
                    $nameLines = RmuTranscriptPdfBuilder::wrapText((string)$c['course_name'], 'F1', 7.5, $colW['name'] - 8);
                    $rowH = max(15.5, (count($nameLines) * 9.5) + 6.0);

                    if ($pdf->ensureSpace($rowH + 18.0)) {
                        $subY = $pdf->getY();
                        $pdf->drawRect($x0, $subY, $w, 16.0, $navy, $navy);
                        $pdf->drawText($x0 + 7, $subY + 11.2, "Semester {$semNum}: {$semTitle} (Continued)", 'F2', 7.8, [1, 1, 1]);
                        $pdf->setY($subY + 16.0);
                        $drawTableHeader();
                    }

                    $ry = $pdf->getY();
                    $rowBg = ($sl % 2 === 0) ? [0.98, 0.99, 1.0] : [1.0, 1.0, 1.0];
                    $pdf->drawRect($x0, $ry, $w, $rowH, $rowBg, [0.82, 0.85, 0.90], 0.4);

                    $cx = $x0;
                    $cols = ['sl', 'code', 'name', 'credit', 'full', 'obt', 'grade', 'gp', 'status'];
                    foreach ($cols as $ci => $ck) {
                        if ($ci > 0) {
                            $pdf->drawLine($cx, $ry, $cx, $ry + $rowH, [0.85, 0.88, 0.92], 0.35);
                        }
                        $cx += $colW[$ck];
                    }

                    $baseY = $ry + 10.5;
                    $cx = $x0;
                    $pdf->drawTextBox($cx, $baseY, $colW['sl'], (string)$sl, 'F1', 7.4, $muted, 'C');
                    $cx += $colW['sl'];
                    $pdf->drawTextBox($cx + 4, $baseY, $colW['code'] - 6, (string)$c['course_code'], 'F4', 7.3, $navy, 'L');
                    $cx += $colW['code'];

                    foreach ($nameLines as $li => $nLine) {
                        $pdf->drawText($cx + 4, $ry + 10.2 + ($li * 9.5), $nLine, 'F1', 7.5, $dark);
                    }
                    $cx += $colW['name'];

                    $pdf->drawTextBox($cx, $baseY, $colW['credit'], number_format((float)$c['credit'], 1), 'F1', 7.4, $dark, 'C');
                    $cx += $colW['credit'];
                    $pdf->drawTextBox($cx, $baseY, $colW['full'], (string)(int)$c['full_marks'], 'F1', 7.4, $muted, 'C');
                    $cx += $colW['full'];
                    $obtStr = ($c['marks'] !== null) ? number_format((float)$c['marks'], 1) : '-';
                    $pdf->drawTextBox($cx, $baseY, $colW['obt'], $obtStr, 'F2', 7.5, $navy, 'C');
                    $cx += $colW['obt'];
                    $pdf->drawTextBox($cx, $baseY, $colW['grade'], (string)$c['letter_grade'], 'F2', 7.6, $blue, 'C');
                    $cx += $colW['grade'];
                    $pdf->drawTextBox($cx, $baseY, $colW['gp'], number_format((float)$c['grade_point'], 2), 'F2', 7.4, $dark, 'C');
                    $cx += $colW['gp'];

                    $isPass = (($c['status'] ?? 'Fail') === 'Pass');
                    $pdf->drawTextBox($cx, $baseY, $colW['status'], strtoupper((string)$c['status']), 'F2', 7.0, $isPass ? $green : $red, 'C');

                    $pdf->setY($ry + $rowH);
                    $sl++;
                }

                // Semester footer summary row
                $pdf->ensureSpace(20.0);
                $fy = $pdf->getY();
                $pdf->drawRect($x0, $fy, $w, 17.5, [0.95, 0.97, 0.99], [0.75, 0.80, 0.87]);
                $leftFootW = $colW['sl'] + $colW['code'] + $colW['name'];
                $pdf->drawTextBox($x0 + 4, $fy + 11.5, $leftFootW - 10, "Semester {$semNum} Summary ({$semData['status']}):", 'F2', 7.4, $muted, 'R');
                $pdf->drawTextBox($x0 + $leftFootW, $fy + 11.5, $colW['credit'], number_format((float)$semData['registered_credits'], 1) . ' Cr', 'F2', 7.4, $navy, 'C');
                $earnedPos = $leftFootW + $colW['credit'];
                $pdf->drawTextBox($x0 + $earnedPos, $fy + 11.5, $colW['full'] + $colW['obt'], 'Earned: ' . number_format((float)$semData['earned_credits'], 1), 'F2', 7.2, $dark, 'C');
                $sgpaPos = $earnedPos + $colW['full'] + $colW['obt'];
                $pdf->drawTextBox($x0 + $sgpaPos, $fy + 11.5, $colW['grade'] + $colW['gp'], 'SGPA: ' . number_format((float)$semData['sgpa'], 2), 'F2', 7.5, $navy, 'C');
                $cgpaPos = $sgpaPos + $colW['grade'] + $colW['gp'];
                $pdf->drawTextBox($x0 + $cgpaPos, $fy + 11.5, $colW['status'], 'CGPA: ' . number_format((float)$semData['cumulative_cgpa'], 2), 'F2', 7.4, $green, 'C');
                $pdf->setY($fy + 25.0);
            } else {
                // Clean empty / unpublished semester section (NO fake marks, NO copied GPA)
                $pdf->drawRect($x0, $sy, $w, 18.0, [0.92, 0.94, 0.96], [0.78, 0.82, 0.88]);
                $pdf->drawText($x0 + 7, $sy + 12.0, "Semester {$semNum}: {$semTitle}", 'F2', 8.0, $navy);
                $pdf->drawTextBox($x0 + 250, $sy + 11.8, $w - 257, "Status: Not Published Yet", 'F2', 7.3, $muted, 'R');
                $pdf->drawRect($x0, $sy + 18.0, $w, 20.0, [0.99, 0.99, 1.0], [0.82, 0.85, 0.90]);
                $pdf->drawText($x0 + 8, $sy + 31.0, "No published subject results recorded for this semester yet. (SGPA: N/A | Reg: 0.0 Cr | Earned: 0.0 Cr)", 'F3', 7.4, $muted);
                $pdf->setY($sy + 45.0);
            }
        };

        if ($isConsolidated) {
            for ($sn = 1; $sn <= $totalSemesters; $sn++) {
                $slot = $allSemesters[$sn] ?? null;
                $sTitle = $slot['semester_name'] ?? get_semester_ordinal_title($sn);
                $renderSemesterSection($sn, $sTitle, $slot);
            }
            // Also include any extra non-standard semesters if present in DB
            foreach ($groupedSemesters as $extraName => $extraData) {
                $exNum = (int)($extraData['semester_number'] ?? 99);
                if ($exNum < 1 || $exNum > $totalSemesters) {
                    $renderSemesterSection($exNum, $extraName, $extraData);
                }
            }
        } else {
            $matchKey = null;
            $targetNum = get_semester_number($requestedSemester);
            if (isset($groupedSemesters[$requestedSemester])) {
                $matchKey = $requestedSemester;
            } elseif ($targetNum >= 1 && $targetNum <= $totalSemesters && isset($allSemesters[$targetNum])) {
                $sTitle = $allSemesters[$targetNum]['semester_name'];
                $renderSemesterSection($targetNum, $sTitle, $allSemesters[$targetNum]);
            } else {
                $renderSemesterSection(max(1, $targetNum), $requestedSemester, null);
            }
            if ($matchKey !== null) {
                $renderSemesterSection((int)$groupedSemesters[$matchKey]['semester_number'], $matchKey, $groupedSemesters[$matchKey]);
            }
        }

        // 5. UGC Grading Scale Reference + Official Transcript Summary + Verification Signatures
        $pdf->ensureSpace(155.0);
        $sy = $pdf->getY();

        // Bottom Official Summary Box
        $pdf->drawRect($x0, $sy, $w, 44.0, [0.97, 0.98, 1.0], [0.72, 0.78, 0.86]);
        $pdf->drawText($x0 + 8, $sy + 12.5, 'OFFICIAL ACADEMIC TRANSCRIPT SUMMARY & CREDIT-WEIGHTED FORMULA', 'F2', 7.8, $navy);
        $pdf->drawLine($x0 + 6, $sy + 16.0, $x0 + $w - 6, $sy + 16.0, [0.85, 0.88, 0.92], 0.5);
        $pdf->drawText(
            $x0 + 8,
            $sy + 28.0,
            sprintf(
                "Registered Credits: %.1f Cr   |   Earned Credits: %.1f Cr   |   Completed Credits: %d / %d   |   Overall CGPA: %.2f / 4.00   |   Status: %s",
                $totalRegCredits, $totalEarnedCredits, (int)$totalEarnedCredits, $totalReqCredits, $finalCgpa, $overallStatus
            ),
            'F2',
            7.4,
            $dark
        );
        $pdf->drawText($x0 + 8, $sy + 39.5, 'Formula: CGPA = Sum of (Subject Credit x Subject Grade Point) / Total Registered Credits   |   Grading Scale: UGC Uniform 4.00', 'F1', 7.0, $muted);
        $sy += 50.0;

        // Compact UGC Scale Strip
        $pdf->drawRect($x0, $sy, $w, 22.0, [0.99, 0.99, 1.0], [0.82, 0.85, 0.90]);
        $pdf->drawTextBox($x0, $sy + 9.5, $w, 'UGC Grading Scale:  80%+=A+(4.00)  75-79%=A(3.75)  70-74%=A-(3.50)  65-69%=B+(3.25)  60-64%=B(3.00)  55-59%=B-(2.75)  50-54%=C+(2.50)  45-49%=C(2.25)  40-44%=D(2.00)  <40%=F(0.00)', 'F1', 6.4, $muted, 'C');
        $pdf->drawTextBox($x0, $sy + 18.5, $w, 'Prepared & Tabulated Electronically by Rabindra Maitree University Examination Result System', 'F3', 6.4, $muted, 'C');
        $sy += 34.0;

        // Signatures & University Seal
        $sigY = $sy;
        $pdf->drawTextBox($x0, $sigY + 14.0, 165.0, 'A. H. Tanvir', 'F3', 10.5, $navy, 'C');
        $pdf->drawLine($x0 + 15.0, $sigY + 18.0, $x0 + 150.0, $sigY + 18.0, $muted, 0.6);
        $pdf->drawTextBox($x0, $sigY + 28.0, 165.0, 'Prepared & Tabulated by', 'F2', 7.5, $dark, 'C');
        $pdf->drawTextBox($x0, $sigY + 38.0, 165.0, 'Office of the Registrar', 'F1', 7.0, $muted, 'C');

        // University Seal Box
        $sealX = $x0 + ($w / 2.0) - 48.0;
        $pdf->drawRect($sealX, $sigY, 96.0, 42.0, [0.95, 0.97, 1.0], $navy, 1.0);
        $pdf->drawTextBox($sealX, $sigY + 14.0, 96.0, 'RABINDRA MAITREE', 'F2', 6.8, $navy, 'C');
        $pdf->drawTextBox($sealX, $sigY + 24.0, 96.0, 'UNIVERSITY', 'F2', 6.8, $navy, 'C');
        $pdf->drawTextBox($sealX, $sigY + 34.0, 96.0, 'KUSHTIA * SEAL', 'F1', 6.0, $muted, 'C');

        $rightSigX = $x0 + $w - 165.0;
        $pdf->drawTextBox($rightSigX, $sigY + 14.0, 165.0, 'Prof. M. Rahman', 'F3', 10.5, $navy, 'C');
        $pdf->drawLine($rightSigX + 15.0, $sigY + 18.0, $rightSigX + 150.0, $sigY + 18.0, $muted, 0.6);
        $pdf->drawTextBox($rightSigX, $sigY + 28.0, 165.0, 'Controller of Examinations', 'F2', 7.5, $dark, 'C');
        $pdf->drawTextBox($rightSigX, $sigY + 38.0, 165.0, 'Rabindra Maitree University', 'F1', 7.0, $muted, 'C');
    }

    return $pdf->buildPdf();
}

/**
 * Send transcript PDF binary download cleanly with proper HTTP headers.
 */
function output_student_transcript_pdf(
    array $student,
    array $academicSummary,
    string $requestedSemester = 'all',
    string $transcriptSerial = '',
    string $issueDate = '',
    bool $isAdminPreview = false
): void {
    $pdfBinary = render_student_transcript_pdf(
        $student,
        $academicSummary,
        $requestedSemester,
        $transcriptSerial,
        $issueDate,
        $isAdminPreview
    );

    $cleanStId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($student['student_id'] ?? 'STUDENT'));
    $scopeTag = ($requestedSemester === '' || $requestedSemester === 'all')
        ? 'Consolidated_Transcript'
        : preg_replace('/[^A-Za-z0-9_-]/', '_', $requestedSemester);
    $filename = "RMU_{$cleanStId}_{$scopeTag}.pdf";

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    echo $pdfBinary;
    exit;
}
