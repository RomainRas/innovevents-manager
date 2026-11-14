<?php
declare(strict_types=1);
// Illustrations de démonstration générées par programme (aucune photo tierce, aucun droit d'auteur externe).
// Usage : php bin/demo-images.php <dossier> ; chaque fichier est nommé par son empreinte SHA-256.
$out = $argv[1] ?? '/app/demo-images';
if (!is_dir($out) && !mkdir($out, 0755, true)) exit(1);
const W = 1200, H = 750;

function canvas(array $top, array $bottom): GdImage {
    $im = imagecreatetruecolor(W, H);
    for ($y = 0; $y < H; $y++) {
        $t = $y / (H - 1);
        $c = imagecolorallocate($im, ...array_map(fn($a, $b) => (int)round($a + ($b - $a) * $t), $top, $bottom));
        imageline($im, 0, $y, W, $y, $c);
    }
    imagealphablending($im, true);
    return $im;
}
function rgba(GdImage $im, array $c, int $alpha = 0): int { return imagecolorallocatealpha($im, $c[0], $c[1], $c[2], $alpha); }
function bokeh(GdImage $im, int $seed, int $count, array $colors, int $yMax): void {
    mt_srand($seed);
    for ($i = 0; $i < $count; $i++) {
        $r = mt_rand(12, 70); $x = mt_rand(0, W); $y = mt_rand(0, $yMax);
        $c = $colors[$i % count($colors)];
        for ($k = $r; $k > 0; $k -= 6) imagefilledellipse($im, $x, $y, $k * 2, $k * 2, rgba($im, $c, 118 - (int)(30 * (1 - $k / $r))));
    }
}
function spotlight(GdImage $im, int $x, int $topY, int $bottomY, int $width, array $c): void {
    for ($i = 0; $i < 18; $i++) {
        $w = $width * (1 - $i / 18);
        imagefilledpolygon($im, [$x - 10, $topY, $x + 10, $topY, (int)($x + $w), $bottomY, (int)($x - $w), $bottomY], rgba($im, $c, 122));
    }
}
function audience(GdImage $im, int $rows, int $startY, array $c): void {
    for ($r = 0; $r < $rows; $r++) {
        $y = $startY + $r * 46; $size = 26 + $r * 6; $color = rgba($im, $c, max(0, 20 - $r * 6));
        for ($x = -20 + ($r % 2) * 30; $x < W + 40; $x += $size + 18) {
            imagefilledellipse($im, $x, $y, $size, $size, $color);
            imagefilledellipse($im, $x, $y + (int)($size * 1.1), (int)($size * 1.8), (int)($size * 1.4), $color);
        }
    }
}
function tables(GdImage $im, int $seed, array $cloth, array $glow): void {
    mt_srand($seed);
    foreach ([[220, 470], [600, 440], [980, 470], [380, 640], [820, 640]] as [$x, $y]) {
        imagefilledellipse($im, $x, $y + 18, 300, 90, rgba($im, [10, 18, 28], 40));
        imagefilledellipse($im, $x, $y, 280, 80, rgba($im, $cloth));
        for ($a = 0; $a < 360; $a += 45) {
            $cx = (int)($x + cos(deg2rad($a)) * 170); $cy = (int)($y + sin(deg2rad($a)) * 60) - 30;
            imagefilledellipse($im, $cx, $cy, 34, 34, rgba($im, [20, 32, 46], 10));
            imagefilledellipse($im, $cx, $cy + 34, 54, 44, rgba($im, [20, 32, 46], 10));
        }
        imagefilledellipse($im, $x, $y - 8, 26, 26, rgba($im, $glow, 30));
    }
}
function save(GdImage $im, string $out, string $label): void {
    $tmp = $out . '/tmp-' . $label . '.jpg'; imagejpeg($im, $tmp, 86); imagedestroy($im);
    $name = hash_file('sha256', $tmp) . '.jpg'; rename($tmp, $out . '/' . $name); chmod($out . '/' . $name, 0644);
    echo $label, ' ', $name, PHP_EOL;
}

// 1. Conférence : scène, projecteurs et public.
$im = canvas([12, 22, 38], [8, 70, 84]);
bokeh($im, 1, 40, [[255, 196, 92], [120, 220, 230]], 300);
imagefilledrectangle($im, 300, 170, 900, 430, rgba($im, [235, 244, 248], 10));
imagefilledrectangle($im, 330, 200, 870, 400, rgba($im, [8, 127, 140], 30));
imagefilledrectangle($im, 360, 240, 640, 260, rgba($im, [235, 244, 248], 20));
imagefilledrectangle($im, 360, 285, 800, 300, rgba($im, [235, 244, 248], 50));
imagefilledrectangle($im, 360, 320, 720, 335, rgba($im, [235, 244, 248], 50));
spotlight($im, 250, 0, 520, 220, [255, 230, 170]); spotlight($im, 950, 0, 520, 220, [255, 230, 170]);
imagefilledrectangle($im, 0, 470, W, 500, rgba($im, [20, 32, 46]));
audience($im, 5, 540, [6, 12, 20]);
save($im, $out, 'conference');

// 2. Séminaire : tables rondes dans une salle lumineuse.
$im = canvas([236, 241, 245], [196, 214, 224]);
for ($x = 0; $x < W; $x += 200) imagefilledrectangle($im, $x + 40, 40, $x + 160, 300, rgba($im, [255, 255, 255], 20));
for ($x = 0; $x < W; $x += 200) imagefilledrectangle($im, $x + 40, 40, $x + 160, 300, rgba($im, [140, 200, 210], 100));
imagefilledrectangle($im, 0, 330, W, H, rgba($im, [176, 150, 120]));
tables($im, 2, [250, 250, 248], [8, 127, 140]);
save($im, $out, 'seminaire');

// 3. Soirée d'entreprise : lumières dorées et piste.
$im = canvas([30, 10, 40], [12, 18, 34]);
bokeh($im, 3, 90, [[255, 190, 80], [255, 120, 150], [140, 200, 255]], 520);
for ($i = 0; $i < 9; $i++) { $x = 80 + $i * 130; imageline($im, $x, 0, $x, 120 + ($i % 3) * 30, rgba($im, [255, 220, 150], 60)); imagefilledellipse($im, $x, 130 + ($i % 3) * 30, 26, 26, rgba($im, [255, 220, 150], 10)); }
imagefilledellipse($im, 600, 720, 1300, 300, rgba($im, [90, 40, 90], 60));
audience($im, 3, 600, [8, 6, 16]);
save($im, $out, 'soiree');

// 4. Team building en plein air : collines et soleil.
$im = canvas([255, 206, 150], [140, 200, 220]);
imagefilledellipse($im, 900, 260, 220, 220, rgba($im, [255, 245, 220], 10));
imagefilledellipse($im, 300, 760, 1400, 520, rgba($im, [60, 130, 110]));
imagefilledellipse($im, 950, 800, 1300, 520, rgba($im, [40, 105, 95]));
imagefilledellipse($im, 600, 900, 1600, 500, rgba($im, [30, 85, 80]));
mt_srand(4);
for ($i = 0; $i < 7; $i++) { $x = 260 + $i * 110; $y = 560 + ($i % 2) * 20;
    imagefilledellipse($im, $x, $y - 70, 34, 34, rgba($im, [20, 40, 50])); imagefilledrectangle($im, $x - 14, $y - 52, $x + 14, $y + 10, rgba($im, [20, 40, 50])); }
imagesetthickness($im, 6); imageline($im, 260, 520, 920, 520, rgba($im, [255, 245, 220], 20));
save($im, $out, 'plein-air');

// 5. Atelier : tables de travail vues de dessus.
$im = canvas([242, 238, 230], [226, 220, 208]);
mt_srand(5);
foreach ([[160, 150], [640, 150], [160, 430], [640, 430]] as [$x, $y]) {
    imagefilledrectangle($im, $x + 12, $y + 12, $x + 412, $y + 222, rgba($im, [0, 0, 0], 105));
    imagefilledrectangle($im, $x, $y, $x + 400, $y + 210, rgba($im, [255, 255, 255]));
    for ($n = 0; $n < 6; $n++) { $px = $x + 20 + ($n % 3) * 125; $py = $y + 20 + intdiv($n, 3) * 100;
        $notes = [[255, 214, 102], [8, 127, 140], [242, 140, 120], [140, 190, 240]];
        imagefilledrectangle($im, $px, $py, $px + 90, $py + 70, rgba($im, $notes[mt_rand(0, 3)], 10)); }
}
save($im, $out, 'atelier');

// 6. Cocktail de lancement : verres et lumière douce.
$im = canvas([16, 40, 52], [8, 127, 140]);
bokeh($im, 6, 60, [[255, 230, 180], [200, 255, 250]], 400);
for ($i = 0; $i < 7; $i++) { $x = 180 + $i * 140; $y = 470 + ($i % 2) * 30; $c = rgba($im, [235, 250, 250], 40);
    imagefilledpolygon($im, [$x - 40, $y - 120, $x + 40, $y - 120, $x + 8, $y - 30, $x - 8, $y - 30], $c);
    imagefilledrectangle($im, $x - 4, $y - 30, $x + 4, $y + 40, $c); imagefilledellipse($im, $x, $y + 44, 70, 16, $c);
    imagefilledpolygon($im, [$x - 34, $y - 110, $x + 34, $y - 110, $x + 8, $y - 50, $x - 8, $y - 50], rgba($im, [255, 196, 92], 40)); }
imagefilledrectangle($im, 0, 540, W, H, rgba($im, [10, 22, 30], 20));
save($im, $out, 'cocktail');
