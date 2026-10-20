<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
send_headers(false);

$err = '';
$ok = isset($_GET['ok']);
$old = ['count' => '1', 'date' => date('Y-m-d'), 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['count'] = trim((string)($_POST['count'] ?? ''));
    $old['date']  = (string)($_POST['date'] ?? '');
    $old['note']  = clip(clean_note($_POST['note'] ?? ''), 120);

    // Honeypot: bot mengisi kolom tersembunyi ini
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        header('Location: ./?ok=1');
        exit;
    }

    $bucket = 'add:' . client_key();
    $count  = parse_int($old['count'], 1, 100);
    $today  = date('Y-m-d');
    $min    = date('Y-m-d', strtotime('-365 days'));

    if (rl_count($bucket, 600) >= 10) {
        $err = 'Terlalu sering menambah data. Coba lagi beberapa menit lagi.';
    } elseif ($count === null) {
        $err = 'Jumlah open harus angka 1 sampai 100.';
    } elseif (!valid_date($old['date']) || $old['date'] > $today || $old['date'] < $min) {
        $err = 'Tanggal tidak valid.';
    } else {
        try {
            append_entry($count, $old['date'], $old['note']);
            rl_hit($bucket);
            header('Location: ./?ok=1');
            exit;
        } catch (Throwable $ex) {
            $err = 'Gagal menyimpan data. Coba lagi nanti.';
        }
    }
}

$d = get_data();
$s = $d['settings'];
$entries = $d['entries'];
$totalOpen = 0;
foreach ($entries as $en) $totalOpen += (int)($en['count'] ?? 0);
$list = array_slice(array_reverse($entries), 0, 500);

page_head($s['title'], '');
?>
  <h1><?= e($s['title']) ?></h1>
  <p class="sub">Setiap 1x open <?= e(rp($s['price'])) ?></p>

  <div class="g">
    <div class="c"><small>Total open</small><b><?= number_format($totalOpen, 0, ',', '.') ?></b></div>
    <div class="c"><small>Jumlah data</small><b><?= count($entries) ?></b></div>
  </div>

  <div class="box">
    <h2>Tambah data open</h2>
    <?php if ($ok): ?><div class="al">Data berhasil ditambahkan.</div><?php endif; ?>
    <?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>
    <form method="post" action="./" autocomplete="off">
      <div class="f">
        <div><label for="count">Jumlah open</label><input id="count" name="count" type="number" min="1" max="100" required value="<?= e($old['count']) ?>"></div>
        <div><label for="date">Tanggal</label><input id="date" name="date" type="date" required value="<?= e($old['date']) ?>"></div>
        <div><label for="note">Catatan</label><input id="note" name="note" maxlength="120" placeholder="opsional" value="<?= e($old['note']) ?>"></div>
        <div class="hp" aria-hidden="true"><label>Website</label><input name="website" tabindex="-1" autocomplete="off"></div>
        <button type="submit">Tambah</button>
      </div>
    </form>
  </div>

  <div class="box">
    <h2>Riwayat open</h2>
    <input id="q" placeholder="Cari catatan…" style="margin-bottom:12px">
    <div class="ov"><table>
      <thead><tr><th>Tanggal</th><th>Nama</th><th class="r">Open</th><th>Catatan</th></tr></thead>
      <tbody id="rows">
      <?php foreach ($list as $en): ?>
        <tr>
          <td><?= e(tgl((string)($en['date'] ?? ''))) ?></td>
          <td><?= e($en['name'] ?? '') ?></td>
          <td class="r"><?= (int)($en['count'] ?? 0) ?></td>
          <td class="mu"><?= e($en['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="em" id="empty" <?= $list ? 'hidden' : '' ?>>Belum ada data.</div>
    <?php if (count($entries) > 500): ?><p class="note" style="margin:10px 0 0">Menampilkan 500 data terbaru.</p><?php endif; ?>
  </div>
<?php page_foot(''); ?>
