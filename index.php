<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
send_headers(true);
start_session();

$base   = '../';
$admin  = db_read(ADMIN_FILE, []);
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? (string)($_POST['action'] ?? '') : '';
$err    = '';
$lim    = 'login:' . client_key();
$userRx = '/^[A-Za-z0-9_.-]{3,32}$/';

function go(): void { header('Location: ./'); exit; }
function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'] = [$t, $m]; }

/* ================= SETUP (pertama kali) ================= */
if (empty($admin['user']) || empty($admin['hash'])) {
    if ($action === 'setup') {
        if (!csrf_check()) {
            $err = 'Sesi habis, muat ulang halaman.';
        } else {
            $u  = trim((string)($_POST['user'] ?? ''));
            $p  = (string)($_POST['pass'] ?? '');
            $p2 = (string)($_POST['pass2'] ?? '');
            if (!preg_match($userRx, $u))      $err = 'Username 3-32 karakter (huruf, angka, _ . -).';
            elseif (strlen($p) < 8)            $err = 'Password minimal 8 karakter.';
            elseif ($p !== $p2)                $err = 'Konfirmasi password tidak sama.';
            else {
                $created = db_update(ADMIN_FILE, [], function (array &$d) use ($u, $p) {
                    if (!empty($d['user'])) return false;
                    $d = ['user' => $u, 'hash' => password_hash($p, PASSWORD_DEFAULT)];
                    return true;
                });
                if ($created) {
                    session_regenerate_id(true);
                    $_SESSION['ok'] = 1;
                    flash('Akun admin dibuat. Selamat datang!');
                    go();
                }
                $err = 'Akun admin sudah dibuat.';
            }
        }
    }
    page_head('Buat akun admin', $base, true); ?>
    <div class="box login adm">
      <h2>Buat akun admin</h2>
      <p class="note">Langkah satu kali. Setelah dibuat, halaman ini berubah menjadi halaman login.</p>
      <?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="action" value="setup"><?= csrf_field() ?>
        <div class="f">
          <div><label for="user">Username</label><input id="user" name="user" required></div>
          <div><label for="pass">Password (min. 8 karakter)</label><input id="pass" name="pass" type="password" required></div>
          <div><label for="pass2">Ulangi password</label><input id="pass2" name="pass2" type="password" required></div>
          <button type="submit">Buat akun</button>
        </div>
      </form>
    </div>
    <?php page_foot($base); exit;
}

/* ================= LOGIN ================= */
if (empty($_SESSION['ok'])) {
    if ($action === 'login') {
        if (rl_count($lim, 900) >= 5) {
            $err = 'Terlalu banyak percobaan gagal. Coba lagi 15 menit lagi.';
        } elseif (!csrf_check()) {
            $err = 'Sesi habis, muat ulang halaman.';
        } else {
            $u = (string)($_POST['user'] ?? '');
            $p = (string)($_POST['pass'] ?? '');
            $okU = hash_equals((string)$admin['user'], $u);
            $okP = password_verify($p, (string)$admin['hash']);
            if ($okU && $okP) {
                session_regenerate_id(true);
                $_SESSION['ok'] = 1;
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                go();
            }
            rl_hit($lim);
            $err = 'Username atau password salah.';
        }
    }
    page_head('Login Admin', $base, true); ?>
    <div class="box login adm">
      <h2>Login Admin</h2>
      <?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="login"><?= csrf_field() ?>
        <div class="f">
          <div><label for="user">Username</label><input id="user" name="user" required autocomplete="username"></div>
          <div><label for="pass">Password</label><input id="pass" name="pass" type="password" required autocomplete="current-password"></div>
          <button type="submit">Masuk</button>
        </div>
      </form>
    </div>
    <?php page_foot($base); exit;
}

/* ================= EKSPOR CSV ================= */
if ($method === 'GET' && ($_GET['export'] ?? '') === 'csv') {
    $d = get_data();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data-open-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Tanggal', 'Nama', 'Open', 'Harga/open', 'Untung/open', 'Total', 'Untung', 'Catatan'], ',', '"', '\\');
    foreach ($d['entries'] as $en) {
        $c = (int)($en['count'] ?? 0);
        $note = (string)($en['note'] ?? '');
        if ($note !== '' && strpos("=+-@", $note[0]) !== false) $note = "'" . $note;
        fputcsv($o, [$en['date'] ?? '', $en['name'] ?? '', $c, (int)($en['price'] ?? 0), (int)($en['profit'] ?? 0),
            $c * (int)($en['price'] ?? 0), $c * (int)($en['profit'] ?? 0), $note], ',', '"', '\\');
    }
    fclose($o);
    exit;
}

/* ================= AKSI (sudah login) ================= */
if ($method === 'POST') {
    if (!csrf_check()) { flash('Sesi tidak valid, coba lagi.', 'err'); go(); }

    switch ($action) {
        case 'logout':
            $_SESSION = [];
            session_destroy();
            go();

        case 'add':
            $count = parse_int($_POST['count'] ?? '', 1, 100000);
            $date  = (string)($_POST['date'] ?? '');
            if ($count === null) { flash('Jumlah open harus angka 1 sampai 100000.', 'err'); break; }
            if (!valid_date($date)) $date = date('Y-m-d');
            append_entry($count, $date, clip(clean_note($_POST['note'] ?? ''), 120));
            flash('Data ditambahkan.');
            break;

        case 'delete':
            $id = (string)($_POST['id'] ?? '');
            db_update(DATA_FILE, DEFAULT_DATA, function (array &$d) use ($id) {
                $d['entries'] = array_values(array_filter((array)($d['entries'] ?? []), function ($en) use ($id) {
                    return ($en['id'] ?? '') !== $id;
                }));
            });
            flash('Data dihapus.');
            break;

        case 'settings':
            $title  = clip(clean_note($_POST['title'] ?? ''), 60);
            $name   = clip(clean_note($_POST['name'] ?? ''), 40);
            $price  = parse_int($_POST['price'] ?? '', 0, 100000000);
            $profit = parse_int($_POST['profit'] ?? '', 0, 100000000);
            if ($title === '' || $name === '' || $price === null || $profit === null) {
                flash('Judul, nama, harga, dan untung harus diisi dengan benar.', 'err');
                break;
            }
            db_update(DATA_FILE, DEFAULT_DATA, function (array &$d) use ($title, $name, $price, $profit) {
                $d['settings'] = ['title' => $title, 'name' => $name, 'price' => $price, 'profit' => $profit];
            });
            flash('Pengaturan disimpan.');
            break;

        case 'account':
            $u   = trim((string)($_POST['user'] ?? ''));
            $cur = (string)($_POST['cur'] ?? '');
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify($cur, (string)$admin['hash']))      flash('Password saat ini salah.', 'err');
            elseif (!preg_match($userRx, $u))                        flash('Username 3-32 karakter (huruf, angka, _ . -).', 'err');
            elseif ($new !== '' && strlen($new) < 8)                 flash('Password baru minimal 8 karakter.', 'err');
            else {
                $hash = $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : (string)$admin['hash'];
                db_update(ADMIN_FILE, [], function (array &$d) use ($u, $hash) { $d = ['user' => $u, 'hash' => $hash]; });
                flash('Akun diperbarui.');
            }
            break;
    }
    go();
}

/* ================= DASHBOARD ================= */
$d = get_data();
$s = $d['settings'];
$entries = $d['entries'];
$opens = $rev = $pro = 0;
foreach ($entries as $en) {
    $c = (int)($en['count'] ?? 0);
    $opens += $c;
    $rev   += $c * (int)($en['price'] ?? 0);
    $pro   += $c * (int)($en['profit'] ?? 0);
}
$list = array_reverse($entries);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

page_head('Admin - ' . $s['title'], $base, true); ?>
  <div class="top">
    <div>
      <h1><?= e($s['title']) ?> <span class="tag">admin</span></h1>
      <p class="sub">Harga <?= e(rp($s['price'])) ?> · untung <?= e(rp($s['profit'])) ?> per 1x open</p>
    </div>
    <div>
      <a class="btn" href="../" target="_blank" rel="noopener">Lihat website</a>
      <form class="inl" method="post"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button class="o" type="submit">Keluar</button></form>
    </div>
  </div>

  <?php if ($flash): ?><div class="al <?= $flash[0] === 'err' ? 'err' : '' ?>"><?= e($flash[1]) ?></div><?php endif; ?>

  <div class="g">
    <div class="c"><small>Total open</small><b><?= number_format($opens, 0, ',', '.') ?></b></div>
    <div class="c"><small>Total omzet</small><b><?= e(rp($rev)) ?></b></div>
    <div class="c"><small>Total untung</small><b class="gn"><?= e(rp($pro)) ?></b></div>
    <div class="c"><small>Jumlah data</small><b><?= count($entries) ?></b></div>
  </div>

  <div class="box adm">
    <h2>Tambah data open</h2>
    <form method="post">
      <input type="hidden" name="action" value="add"><?= csrf_field() ?>
      <div class="f">
        <div><label for="count">Jumlah open</label><input id="count" name="count" type="number" min="1" max="100000" value="1" required></div>
        <div><label for="date">Tanggal</label><input id="date" name="date" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div><label for="note">Catatan</label><input id="note" name="note" maxlength="120" placeholder="opsional"></div>
        <button type="submit">Tambah</button>
      </div>
    </form>
  </div>

  <div class="box">
    <h2>Data open</h2>
    <input id="q" placeholder="Cari catatan…" style="margin-bottom:12px">
    <div class="ov"><table>
      <thead><tr><th>Tanggal</th><th>Nama</th><th class="r">Open</th><th class="r">Total</th><th class="r">Untung</th><th>Catatan</th><th></th></tr></thead>
      <tbody id="rows">
      <?php foreach ($list as $en): $c = (int)($en['count'] ?? 0); ?>
        <tr>
          <td><?= e(tgl((string)($en['date'] ?? ''))) ?></td>
          <td><?= e($en['name'] ?? '') ?></td>
          <td class="r"><?= $c ?></td>
          <td class="r"><?= e(rp($c * (int)($en['price'] ?? 0))) ?></td>
          <td class="r gn"><?= e(rp($c * (int)($en['profit'] ?? 0))) ?></td>
          <td class="mu"><?= e($en['note'] ?? '') ?></td>
          <td>
            <form class="inl" method="post" data-confirm="Hapus data ini?">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($en['id'] ?? '') ?>"><?= csrf_field() ?>
              <button class="s" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="em" id="empty" <?= $list ? 'hidden' : '' ?>>Belum ada data.</div>
    <p style="margin:12px 0 0"><a class="btn" href="?export=csv">Unduh CSV</a></p>
  </div>

  <div class="box">
    <h2>Pengaturan website</h2>
    <form method="post">
      <input type="hidden" name="action" value="settings"><?= csrf_field() ?>
      <div class="f">
        <div><label for="title">Judul website</label><input id="title" name="title" maxlength="60" value="<?= e($s['title']) ?>" required></div>
        <div><label for="name">Nama di data baru</label><input id="name" name="name" maxlength="40" value="<?= e($s['name']) ?>" required></div>
        <div><label for="price">Harga 1x open (Rp)</label><input id="price" name="price" type="number" min="0" value="<?= (int)$s['price'] ?>" required></div>
        <div><label for="profit">Untung 1x open (Rp)</label><input id="profit" name="profit" type="number" min="0" value="<?= (int)$s['profit'] ?>" required></div>
        <button type="submit">Simpan</button>
      </div>
    </form>
    <p class="note" style="margin:12px 0 0">Perubahan harga/untung/nama hanya berlaku untuk data baru. Data lama tetap memakai nilai saat dicatat.</p>
  </div>

  <div class="box">
    <h2>Akun admin</h2>
    <form method="post" autocomplete="off">
      <input type="hidden" name="action" value="account"><?= csrf_field() ?>
      <div class="f">
        <div><label for="au">Username</label><input id="au" name="user" value="<?= e($admin['user']) ?>" required></div>
        <div><label for="ac">Password saat ini</label><input id="ac" name="cur" type="password" required></div>
        <div><label for="an">Password baru (kosongkan jika tetap)</label><input id="an" name="new" type="password"></div>
        <button type="submit">Perbarui</button>
      </div>
    </form>
  </div>
<?php page_foot($base);
