<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('DATA_FILE', DATA_DIR . '/data.php');
define('ADMIN_FILE', DATA_DIR . '/admin.php');
define('LIMIT_FILE', DATA_DIR . '/limit.php');
const GUARD = "<?php http_response_code(403); exit; ?>\n";
const DEFAULT_DATA = [
    'settings' => ['title' => 'Data Open FT', 'name' => 'Noval', 'price' => 2000, 'profit' => 1000],
    'entries'  => [],
];

if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0775, true);
}

/* ---------- Penyimpanan (file JSON dengan penjaga PHP + flock) ---------- */

function db_decode(string $raw, array $default): array
{
    $pos = strpos($raw, "\n");
    if ($pos === false) return $default;
    $d = json_decode(substr($raw, $pos + 1), true);
    return is_array($d) ? $d : $default;
}

function db_read(string $file, array $default): array
{
    if (!is_file($file)) return $default;
    $fh = @fopen($file, 'rb');
    if (!$fh) return $default;
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return db_decode((string)$raw, $default);
}

function db_update(string $file, array $default, callable $fn)
{
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        throw new RuntimeException('Tidak bisa menulis ke folder data/. Cek izin folder (chmod 775).');
    }
    flock($fh, LOCK_EX);
    $data = db_decode((string)stream_get_contents($fh), $default);
    $result = $fn($data);
    $out = GUARD . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $out);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function get_data(): array
{
    $d = db_read(DATA_FILE, DEFAULT_DATA);
    $d['settings'] = array_merge(DEFAULT_DATA['settings'], is_array($d['settings'] ?? null) ? $d['settings'] : []);
    $d['entries'] = array_values(is_array($d['entries'] ?? null) ? $d['entries'] : []);
    return $d;
}

function append_entry(int $count, string $date, string $note): void
{
    db_update(DATA_FILE, DEFAULT_DATA, function (array &$d) use ($count, $date, $note) {
        $s = array_merge(DEFAULT_DATA['settings'], is_array($d['settings'] ?? null) ? $d['settings'] : []);
        $d['settings'] = $s;
        if (!is_array($d['entries'] ?? null)) $d['entries'] = [];
        $d['entries'][] = [
            'id' => bin2hex(random_bytes(4)),
            'name' => $s['name'],
            'count' => $count,
            'date' => $date,
            'note' => $note,
            'price' => (int)$s['price'],
            'profit' => (int)$s['profit'],
            'ts' => time(),
        ];
    });
}

/* ---------- Pembatas percobaan (anti-spam / anti brute force) ---------- */

function client_key(): string
{
    return sha1($_SERVER['REMOTE_ADDR'] ?? 'x');
}

function rl_count(string $bucket, int $window): int
{
    $d = db_read(LIMIT_FILE, []);
    $min = time() - $window;
    $n = 0;
    foreach (($d[$bucket] ?? []) as $t) {
        if ($t > $min) $n++;
    }
    return $n;
}

function rl_hit(string $bucket): void
{
    db_update(LIMIT_FILE, [], function (array &$d) use ($bucket) {
        $min = time() - 3600;
        foreach ($d as $k => $arr) {
            $keep = array_values(array_filter((array)$arr, function ($t) use ($min) { return $t > $min; }));
            if ($keep) $d[$k] = $keep; else unset($d[$k]);
        }
        $d[$bucket][] = time();
    });
}

/* ---------- Helper umum ---------- */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rp($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function tgl(string $s): string
{
    if (!valid_date($s)) return $s === '' ? '-' : $s;
    $bln = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    [$y, $m, $d] = array_map('intval', explode('-', $s));
    return $d . ' ' . $bln[$m] . ' ' . $y;
}

function valid_date(string $s): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

function parse_int($v, int $min, int $max): ?int
{
    $v = trim((string)$v);
    if ($v === '' || !ctype_digit($v) || strlen($v) > 10) return null;
    $n = (int)$v;
    return ($n >= $min && $n <= $max) ? $n : null;
}

function clean_note($s): string
{
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$s);
    return trim((string)$s);
}

function clip(string $s, int $len): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $len, 'UTF-8') : substr($s, 0, $len);
}

/* ---------- Sesi, CSRF, header keamanan ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('dfsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $t = (string)($_POST['csrf'] ?? '');
    return $t !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function send_headers(bool $private): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
    if ($private) {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }
}

function page_head(string $title, string $base, bool $noindex = false): void
{
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . e($title) . '</title>';
    if ($noindex) echo '<meta name="robots" content="noindex,nofollow">';
    echo '<link rel="stylesheet" href="' . e($base) . 'assets/style.css"></head><body><div class="w">';
}

function page_foot(string $base): void
{
    echo '</div><script src="' . e($base) . 'assets/app.js"></script></body></html>';
}
