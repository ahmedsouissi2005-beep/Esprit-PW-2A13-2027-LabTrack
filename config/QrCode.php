<?php
/**
 * config/QrCode.php
 * Générateur de QR code sans bibliothèque externe (norme ISO/IEC 18004),
 * utilisé par la carte d'urgence de l'Espace famille.
 *
 * - mode octet (UTF-8), niveau de correction M (15 % du code peut être abîmé),
 *   versions 1 à 10 (jusqu'à 213 caractères : largement assez pour une adresse) ;
 * - correction d'erreurs Reed-Solomon, choix automatique du meilleur masque ;
 * - rendu en SVG (pas d'image ni de script : compatible avec la CSP du site).
 */
class QrCode
{
    /** Par version (niveau M) : [codes de correction par bloc, blocs groupe 1, octets par bloc g1, blocs groupe 2, octets par bloc g2] */
    private const BLOCKS_M = [
        1 => [10, 1, 16, 0, 0], 2 => [16, 1, 28, 0, 0], 3 => [26, 1, 44, 0, 0],
        4 => [18, 2, 32, 0, 0], 5 => [24, 2, 43, 0, 0], 6 => [16, 4, 27, 0, 0],
        7 => [18, 4, 31, 0, 0], 8 => [22, 2, 38, 2, 39], 9 => [22, 3, 36, 2, 37],
        10 => [26, 4, 43, 1, 44],
    ];

    /** Centres des motifs d'alignement par version */
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $size;
    /** @var array<int, array<int, bool>> modules[y][x] : true = noir */
    private array $modules = [];
    /** @var array<int, array<int, bool>> modules réservés (motifs fixes, non masqués) */
    private array $reserved = [];

    /** Matrice du QR code (lignes de booléens) pour un texte donné */
    public static function matrix(string $text): array
    {
        $qr = new self();
        return $qr->encode($text);
    }

    /** QR code en SVG, avec la marge blanche de 4 modules exigée par la norme */
    public static function svg(string $text, int $pixels = 220, string $label = 'QR code'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $full = $n + 8;
        $path = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $full . ' ' . $full . '" width="' . $pixels
            . '" height="' . $pixels . '" shape-rendering="crispEdges" role="img" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"><rect width="100%" height="100%" fill="#fff"/>'
            . '<path d="' . $path . '" fill="#000"/></svg>';
    }

    private function encode(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = 0;
        foreach (self::BLOCKS_M as $v => $b) {
            $capacityBits = ($b[1] * $b[2] + $b[3] * $b[4]) * 8;
            $needed = 4 + ($v < 10 ? 8 : 16) + 8 * count($bytes);
            if ($needed <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new InvalidArgumentException('Texte trop long pour le QR code');
        }

        $codewords = $this->addErrorCorrection($this->dataCodewords($bytes, $version), $version);

        $this->size = 17 + 4 * $version;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->reserved = $this->modules;
        $this->drawFunctionPatterns($version);
        $this->placeData($codewords);

        // Choix du masque qui donne le moins de motifs gênants pour les lecteurs
        $best = 0;
        $bestScore = PHP_INT_MAX;
        $base = $this->modules;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->modules = $base;
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $mask;
            }
        }
        $this->modules = $base;
        $this->applyMask($best);
        $this->drawFormatBits($best);
        return $this->modules;
    }

    /** Données : mode octet (0100), longueur, octets, terminaison et octets de bourrage */
    private function dataCodewords(array $bytes, int $version): array
    {
        $b = self::BLOCKS_M[$version];
        $capacity = $b[1] * $b[2] + $b[3] * $b[4];
        $bits = [];
        $push = function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push(count($bytes), $version < 10 ? 8 : 16);
        foreach ($bytes as $byte) {
            $push($byte, 8);
        }
        $push(0, min(4, $capacity * 8 - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $out = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $out[] = bindec(implode('', $chunk));
        }
        for ($pad = 0xEC; count($out) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $out[] = $pad;
        }
        return $out;
    }

    /** Découpe en blocs, ajoute la correction Reed-Solomon de chaque bloc, puis entrelace */
    private function addErrorCorrection(array $data, int $version): array
    {
        [$ecLen, $g1, $len1, $g2, $len2] = self::BLOCKS_M[$version];
        $divisor = $this->rsDivisor($ecLen);
        $blocks = [];
        $eccs = [];
        $pos = 0;
        foreach ([[$g1, $len1], [$g2, $len2]] as [$count, $len]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $pos, $len);
                $pos += $len;
                $blocks[] = $block;
                $eccs[] = $this->rsRemainder($block, $divisor);
            }
        }
        $result = [];
        $maxLen = max($len1, $len2);
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($eccs as $ecc) {
                $result[] = $ecc[$i];
            }
        }
        return $result;
    }

    /** Multiplication dans GF(256), polynôme 0x11D */
    private function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMul($root, 0x02);
        }
        return $result;
    }

    private function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= $this->gfMul($coef, $factor);
            }
        }
        return $result;
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    /** Motifs fixes : repères des coins, lignes de synchronisation, alignement, informations */
    private function drawFunctionPatterns(int $version): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$this->size - 4, 3], [3, $this->size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                        $dist = max(abs($dx), abs($dy));
                        $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
                    }
                }
            }
        }
        $align = self::ALIGN[$version];
        $n = count($align);
        foreach ($align as $i => $ax) {
            foreach ($align as $j => $ay) {
                // pas d'alignement sur les trois repères des coins
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFunction($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // réserve les emplacements (valeurs réelles écrites après le masque)
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $this->size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->setFunction($a, $b, $bit);
                $this->setFunction($b, $a, $bit);
            }
        }
    }

    /** Niveau de correction (M = 00) + numéro du masque, protégés par un code BCH */
    private function drawFormatBits(int $mask): void
    {
        $data = (0 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn(int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true); // module toujours noir
    }

    /** Place les bits en zigzag, deux colonnes à la fois, de bas en haut puis de haut en bas */
    private function placeData(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->reserved[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $invert = ($x * $y) % 2 + ($x * $y) % 3 === 0; break;
                    case 6: $invert = (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0; break;
                    default: $invert = (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0;
                }
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Pénalités de la norme : longues suites, carrés 2x2, faux repères, déséquilibre noir/blanc */
    private function penalty(): int
    {
        $n = $this->size;
        $score = 0;
        $dark = 0;
        $lines = [];
        for ($y = 0; $y < $n; $y++) {
            $lines[] = $this->modules[$y];
        }
        for ($x = 0; $x < $n; $x++) {
            $lines[] = array_column($this->modules, $x);
        }
        foreach ($lines as $line) {
            $run = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $line[$i] === $line[$i - 1]) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += $run - 2;
                }
                $run = 1;
            }
            $s = implode('', array_map('intval', $line));
            $score += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
        }
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $c = $this->modules[$y][$x];
                $dark += $c ? 1 : 0;
                if ($x < $n - 1 && $y < $n - 1 && $c === $this->modules[$y][$x + 1]
                    && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $score += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;
        return $score;
    }
}
