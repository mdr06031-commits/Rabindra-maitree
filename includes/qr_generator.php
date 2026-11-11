<?php
/**
 * Pure-PHP ISO/IEC 18004 QR Code Generator (SVG Vector Output)
 * Supports UTF-8 Byte Mode, Versions 1..15 (up to 412 bytes at EC Level M),
 * Reed-Solomon GF(256) ECC with Multi-Block Interleaving, 8-Mask Penalty Scoring,
 * BCH Format Info, and Golay Version Info (V7-V15).
 * Zero external network calls or PHP GD dependencies.
 */

class RmuQrCodeGenerator {
    private const GF_POLY = 0x11D;

    /**
     * EC Level M Capacity & Block Spec for Versions 1..15:
     * [totalCodewords, ecPerBlock, g1Blocks, g1DataCw, g2Blocks, g2DataCw]
     */
    private const SPEC_M = [
        1  => [26,  10, 1, 16, 0, 0],
        2  => [44,  16, 1, 28, 0, 0],
        3  => [70,  26, 1, 44, 0, 0],
        4  => [100, 18, 2, 32, 0, 0],
        5  => [134, 24, 2, 43, 0, 0],
        6  => [172, 16, 4, 27, 0, 0],
        7  => [196, 18, 4, 31, 0, 0],
        8  => [242, 22, 2, 38, 2, 39],
        9  => [292, 22, 3, 36, 2, 37],
        10 => [346, 26, 4, 43, 1, 44],
        11 => [404, 30, 1, 50, 4, 51],
        12 => [466, 22, 6, 36, 2, 37],
        13 => [532, 22, 8, 37, 1, 38],
        14 => [581, 24, 4, 40, 5, 41],
        15 => [655, 24, 5, 41, 5, 42],
    ];

    /** Alignment pattern coordinates per version (ISO 18004 Annex E) */
    private const ALIGN_POS = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
        11 => [6, 30, 54],
        12 => [6, 32, 58],
        13 => [6, 34, 62],
        14 => [6, 26, 46, 66],
        15 => [6, 26, 48, 70],
    ];

    /** Remainder bits after interleaved codewords for V1..15 */
    private const REMAINDER_BITS = [
        1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7,
        7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 0, 13 => 0,
        14 => 3, 15 => 3,
    ];

    /** Precomputed GF(256) exp and log tables */
    private static array $gfExp = [];
    private static array $gfLog = [];

    private static function initGf(): void {
        if (!empty(self::$gfExp)) {
            return;
        }
        self::$gfExp = array_fill(0, 512, 0);
        self::$gfLog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $x;
            self::$gfLog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= self::GF_POLY;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$gfExp[self::$gfLog[$a] + self::$gfLog[$b]];
    }

    private static function rsGeneratorPoly(int $degree): array {
        self::initGf();
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $factor = [1, self::$gfExp[$i]];
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $pIdx => $pVal) {
                foreach ($factor as $fIdx => $fVal) {
                    $next[$pIdx + $fIdx] ^= self::gfMul($pVal, $fVal);
                }
            }
            $poly = $next;
        }
        return $poly;
    }

    private static function rsComputeRemainder(array $data, int $ecCount): array {
        $gen = self::rsGeneratorPoly($ecCount);
        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        $dataLen = count($data);
        for ($i = 0; $i < $dataLen; $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                for ($j = 0; $j <= $ecCount; $j++) {
                    $msg[$i + $j] ^= self::gfMul($gen[$j], $coef);
                }
            }
        }
        return array_slice($msg, $dataLen);
    }

    private static function selectVersion(int $byteLen): int {
        for ($v = 1; $v <= 15; $v++) {
            [$tot, $ec, $g1b, $g1d, $g2b, $g2d] = self::SPEC_M[$v];
            $totalDataCw = ($g1b * $g1d) + ($g2b * $g2d);
            $charCountBits = ($v <= 9) ? 8 : 16;
            $requiredBits = 4 + $charCountBits + ($byteLen * 8);
            if ($requiredBits <= ($totalDataCw * 8)) {
                return $v;
            }
        }
        throw new InvalidArgumentException('QR payload too large for Versions 1..15 Level M (' . $byteLen . ' bytes).');
    }

    private static function appendBits(array &$bits, int $value, int $length): void {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function buildCodewords(string $text, int $version): array {
        [$totCw, $ecPerBlock, $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw] = self::SPEC_M[$version];
        $totalDataCw = ($g1Blocks * $g1DataCw) + ($g2Blocks * $g2DataCw);
        $capacityBits = $totalDataCw * 8;

        $bytes = unpack('C*', $text) ?: [];
        $bytes = array_values($bytes);
        $byteLen = count($bytes);

        $bits = [];
        // Byte mode = 0100
        self::appendBits($bits, 0b0100, 4);
        // Character count indicator
        self::appendBits($bits, $byteLen, ($version <= 9) ? 8 : 16);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }

        // Terminator up to 4 zero bits
        $termLen = min(4, $capacityBits - count($bits));
        for ($i = 0; $i < $termLen; $i++) {
            $bits[] = 0;
        }
        // Pad to byte boundary
        while ((count($bits) % 8) !== 0) {
            $bits[] = 0;
        }

        // Convert to codewords
        $dataCodewords = [];
        $bitCount = count($bits);
        for ($i = 0; $i < $bitCount; $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | ($bits[$i + $j] ?? 0);
            }
            $dataCodewords[] = $val;
        }

        // Pad codewords alternating 0xEC, 0x11
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($dataCodewords) < $totalDataCw) {
            $dataCodewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        // Split into blocks & generate RS ECC per block
        $blocksData = [];
        $blocksEc = [];
        $offset = 0;
        for ($b = 0; $b < $g1Blocks; $b++) {
            $slice = array_slice($dataCodewords, $offset, $g1DataCw);
            $offset += $g1DataCw;
            $blocksData[] = $slice;
            $blocksEc[] = self::rsComputeRemainder($slice, $ecPerBlock);
        }
        for ($b = 0; $b < $g2Blocks; $b++) {
            $slice = array_slice($dataCodewords, $offset, $g2DataCw);
            $offset += $g2DataCw;
            $blocksData[] = $slice;
            $blocksEc[] = self::rsComputeRemainder($slice, $ecPerBlock);
        }

        // Interleave data codewords
        $finalCodewords = [];
        $maxDataCw = max($g1DataCw, $g2DataCw);
        for ($i = 0; $i < $maxDataCw; $i++) {
            foreach ($blocksData as $blk) {
                if (isset($blk[$i])) {
                    $finalCodewords[] = $blk[$i];
                }
            }
        }
        // Interleave EC codewords
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($blocksEc as $ecBlk) {
                if (isset($ecBlk[$i])) {
                    $finalCodewords[] = $ecBlk[$i];
                }
            }
        }

        $streamBits = [];
        foreach ($finalCodewords as $cw) {
            self::appendBits($streamBits, $cw, 8);
        }
        $remBits = self::REMAINDER_BITS[$version] ?? 0;
        for ($i = 0; $i < $remBits; $i++) {
            $streamBits[] = 0;
        }

        return $streamBits;
    }

    private static function setFinder(array &$matrix, array &$isFunc, int $size, int $r0, int $c0): void {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $r0 + $dr;
                $c = $c0 + $dc;
                if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                    continue;
                }
                $isBorderSep = ($dr === -1 || $dr === 7 || $dc === -1 || $dc === 7);
                $isDark = false;
                if (!$isBorderSep) {
                    $isOuter = ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6);
                    $isInner = ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4);
                    $isDark = ($isOuter || $isInner);
                }
                $matrix[$r][$c] = $isDark ? 1 : 0;
                $isFunc[$r][$c] = true;
            }
        }
    }

    private static function setAlignment(array &$matrix, array &$isFunc, int $r0, int $c0): void {
        for ($dr = -2; $dr <= 2; $dr++) {
            for ($dc = -2; $dc <= 2; $dc++) {
                $isDark = (max(abs($dr), abs($dc)) !== 1);
                $matrix[$r0 + $dr][$c0 + $dc] = $isDark ? 1 : 0;
                $isFunc[$r0 + $dr][$c0 + $dc] = true;
            }
        }
    }

    private static function reserveFunctionAreas(int $version, array &$matrix, array &$isFunc): int {
        $size = $version * 4 + 17;
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $isFunc = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder Patterns
        self::setFinder($matrix, $isFunc, $size, 0, 0);
        self::setFinder($matrix, $isFunc, $size, 0, $size - 7);
        self::setFinder($matrix, $isFunc, $size, $size - 7, 0);

        // 2. Alignment Patterns
        $coords = self::ALIGN_POS[$version] ?? [];
        $n = count($coords);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                // Skip corners intersecting finder patterns
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                self::setAlignment($matrix, $isFunc, $coords[$i], $coords[$j]);
            }
        }

        // 3. Timing Patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (!$isFunc[6][$i]) {
                $matrix[6][$i] = $bit;
                $isFunc[6][$i] = true;
            }
            if (!$isFunc[$i][6]) {
                $matrix[$i][6] = $bit;
                $isFunc[$i][6] = true;
            }
        }

        // 4. Dark Module
        $matrix[4 * $version + 9][8] = 1;
        $isFunc[4 * $version + 9][8] = true;

        // 5. Reserve Format Info Strips (set actual values after mask selection)
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $isFunc[8][$i] = true;
                $isFunc[$i][8] = true;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $isFunc[8][$i] = true;
        }
        for ($i = $size - 7; $i < $size; $i++) {
            $isFunc[$i][8] = true;
        }

        // 6. Reserve & Write Version Info if Version >= 7
        if ($version >= 7) {
            $verBits = self::computeVersionInfoBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($verBits >> $i) & 1;
                $r = intdiv($i, 3);
                $c = $size - 11 + ($i % 3);
                $matrix[$r][$c] = $bit;
                $isFunc[$r][$c] = true;
                $matrix[$c][$r] = $bit;
                $isFunc[$c][$r] = true;
            }
        }

        return $size;
    }

    private static function computeFormatInfoBits(int $mask): int {
        // EC Level M = 00 (2 bits), followed by 3-bit mask
        $data = (0b00 << 3) | ($mask & 0b111);
        $rem = $data << 10;
        $poly = 0x537; // x^10 + x^8 + x^5 + x^4 + x^2 + x + 1
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= ($poly << ($i - 10));
            }
        }
        return (($data << 10) | $rem) ^ 0x5412;
    }

    private static function computeVersionInfoBits(int $version): int {
        $rem = $version << 12;
        $poly = 0x1F25;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= ($poly << ($i - 12));
            }
        }
        return ($version << 12) | $rem;
    }

    private static function writeFormatInfo(array &$matrix, int $size, int $mask): void {
        $fmt = self::computeFormatInfoBits($mask);

        // Top-left & bottom-left vertical / horizontal mapping per ISO 18004
        for ($i = 0; $i <= 5; $i++) {
            $bit = ($fmt >> $i) & 1;
            $matrix[$i][8] = $bit;
        }
        $matrix[7][8] = ($fmt >> 6) & 1;
        $matrix[8][8] = ($fmt >> 7) & 1;
        $matrix[8][7] = ($fmt >> 8) & 1;
        for ($i = 9; $i <= 14; $i++) {
            $matrix[8][14 - $i] = ($fmt >> $i) & 1;
        }

        // Bottom-left (bits 0..6) & top-right (bits 7..14)
        for ($i = 0; $i <= 6; $i++) {
            $matrix[$size - 1 - $i][8] = ($fmt >> $i) & 1;
        }
        for ($i = 7; $i <= 14; $i++) {
            $matrix[8][$size - 15 + $i] = ($fmt >> $i) & 1;
        }
    }

    private static function placeDataBits(array &$matrix, array $isFunc, int $size, array $streamBits): void {
        $bitIdx = 0;
        $totalBits = count($streamBits);
        $upward = true;

        for ($col = $size - 1; $col >= 1; $col -= 2) {
            if ($col === 6) {
                $col--; // Skip timing column 6
            }
            for ($step = 0; $step < $size; $step++) {
                $r = $upward ? ($size - 1 - $step) : $step;
                for ($dx = 0; $dx <= 1; $dx++) {
                    $c = $col - $dx;
                    if (!$isFunc[$r][$c]) {
                        $matrix[$r][$c] = ($bitIdx < $totalBits) ? $streamBits[$bitIdx] : 0;
                        $bitIdx++;
                    }
                }
            }
            $upward = !$upward;
        }
    }

    private static function maskCondition(int $mask, int $r, int $c): bool {
        return match ($mask) {
            0 => (($r + $c) % 2) === 0,
            1 => ($r % 2) === 0,
            2 => ($c % 3) === 0,
            3 => (($r + $c) % 3) === 0,
            4 => ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0,
            5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
            6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
            7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
            default => false,
        };
    }

    private static function applyMaskAndFormat(array $baseMatrix, array $isFunc, int $size, int $mask): array {
        $m = $baseMatrix;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$isFunc[$r][$c] && self::maskCondition($mask, $r, $c)) {
                    $m[$r][$c] ^= 1;
                }
            }
        }
        self::writeFormatInfo($m, $size, $mask);
        return $m;
    }

    private static function penaltyLineRun(array $line): int {
        $pen = 0;
        $runColor = -1;
        $runLen = 0;
        foreach ($line as $cell) {
            if ($cell === $runColor) {
                $runLen++;
            } else {
                if ($runLen >= 5) {
                    $pen += 3 + ($runLen - 5);
                }
                $runColor = $cell;
                $runLen = 1;
            }
        }
        if ($runLen >= 5) {
            $pen += 3 + ($runLen - 5);
        }
        return $pen;
    }

    private static function evaluatePenalty(array $m, int $size): int {
        $penalty = 0;

        // N1: 5+ same-color adjacent modules
        for ($r = 0; $r < $size; $r++) {
            $penalty += self::penaltyLineRun($m[$r]);
        }
        for ($c = 0; $c < $size; $c++) {
            $col = [];
            for ($r = 0; $r < $size; $r++) {
                $col[] = $m[$r][$c];
            }
            $penalty += self::penaltyLineRun($col);
        }

        // N2: 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($m[$r][$c + 1] === $v && $m[$r + 1][$c] === $v && $m[$r + 1][$c + 1] === $v) {
                    $penalty += 3;
                }
            }
        }

        // N3: 1:1:3:1:1 finder-like patterns in rows & cols
        $checkLineN3 = static function (array $line) use ($size): int {
            $score = 0;
            for ($i = 0; $i <= $size - 11; $i++) {
                if (
                    $line[$i] === 1 && $line[$i+1] === 0 && $line[$i+2] === 1 && $line[$i+3] === 1 && $line[$i+4] === 1 &&
                    $line[$i+5] === 0 && $line[$i+6] === 1 &&
                    $line[$i+7] === 0 && $line[$i+8] === 0 && $line[$i+9] === 0 && $line[$i+10] === 0
                ) {
                    $score += 40;
                }
                if (
                    $line[$i] === 0 && $line[$i+1] === 0 && $line[$i+2] === 0 && $line[$i+3] === 0 &&
                    $line[$i+4] === 1 && $line[$i+5] === 0 && $line[$i+6] === 1 && $line[$i+7] === 1 && $line[$i+8] === 1 &&
                    $line[$i+9] === 0 && $line[$i+10] === 1
                ) {
                    $score += 40;
                }
            }
            return $score;
        };
        for ($r = 0; $r < $size; $r++) {
            $penalty += $checkLineN3($m[$r]);
        }
        for ($c = 0; $c < $size; $c++) {
            $col = [];
            for ($r = 0; $r < $size; $r++) {
                $col[] = $m[$r][$c];
            }
            $penalty += $checkLineN3($col);
        }

        // N4: dark module percentage balance
        $dark = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $dark += $m[$r][$c];
            }
        }
        $total = $size * $size;
        $percent = ($dark * 100) / $total;
        $prev5 = abs((int)(floor($percent / 5) * 5) - 50) / 5;
        $next5 = abs((int)(ceil($percent / 5) * 5) - 50) / 5;
        $penalty += (int)(min($prev5, $next5) * 10);

        return $penalty;
    }

    /**
     * Encode text into a 2D QR module matrix [row][col] of 0/1.
     */
    public static function encode(string $text): array {
        $version = self::selectVersion(strlen($text));
        $streamBits = self::buildCodewords($text, $version);

        $matrix = [];
        $isFunc = [];
        $size = self::reserveFunctionAreas($version, $matrix, $isFunc);
        self::placeDataBits($matrix, $isFunc, $size, $streamBits);

        $bestMask = 0;
        $bestMatrix = null;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMaskAndFormat($matrix, $isFunc, $size, $mask);
            $penalty = self::evaluatePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        return $bestMatrix ?? self::applyMaskAndFormat($matrix, $isFunc, $size, $bestMask);
    }

    /**
     * Generate crisp vector SVG markup using compact SVG path per row.
     */
    public static function toSvg(string $text, int $pxSize = 112, string $fgColor = '#0f2c59', string $bgColor = '#ffffff', int $quietZone = 3): string {
        $matrix = self::encode($text);
        $n = count($matrix);
        $fullSize = $n + ($quietZone * 2);

        $d = '';
        for ($r = 0; $r < $n; $r++) {
            $c = 0;
            while ($c < $n) {
                if ($matrix[$r][$c] === 1) {
                    $runStart = $c;
                    while ($c < $n && $matrix[$r][$c] === 1) {
                        $c++;
                    }
                    $len = $c - $runStart;
                    $x = $runStart + $quietZone;
                    $y = $r + $quietZone;
                    $d .= "M{$x} {$y}h{$len}v1h-{$len}z";
                } else {
                    $c++;
                }
            }
        }

        $safeFg = htmlspecialchars($fgColor, ENT_QUOTES, 'UTF-8');
        $safeBg = htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $pxSize . '" height="' . $pxSize . '" viewBox="0 0 ' . $fullSize . ' ' . $fullSize . '" shape-rendering="crispEdges" role="img" aria-label="Application Verification QR Code">'
            . '<rect width="' . $fullSize . '" height="' . $fullSize . '" fill="' . $safeBg . '"/>'
            . '<path d="' . $d . '" fill="' . $safeFg . '"/>'
            . '</svg>';
    }
}

if (!function_exists('rmu_generate_qr_svg')) {
    function rmu_generate_qr_svg(string $text, int $pxSize = 112, string $fgColor = '#0f2c59', string $bgColor = '#ffffff'): string {
        return RmuQrCodeGenerator::toSvg($text, $pxSize, $fgColor, $bgColor);
    }
}

if (!function_exists('rmu_generate_qr_data_uri')) {
    function rmu_generate_qr_data_uri(string $text, int $pxSize = 112, string $fgColor = '#0f2c59', string $bgColor = '#ffffff'): string {
        $svg = rmu_generate_qr_svg($text, $pxSize, $fgColor, $bgColor);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
