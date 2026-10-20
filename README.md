# Data Open FT - Netlify

Versi ini sudah diubah dari PHP menjadi HTML + Netlify Functions sehingga dapat dijalankan di Netlify.

## Struktur
- `index.html` — halaman publik
- `admin/index.html` — halaman admin
- `assets/` — CSS dan JavaScript
- `netlify/functions/` — backend/server-side
- `netlify.toml` — konfigurasi Netlify
- `package.json` — dependency Netlify Blobs

## Deploy
1. Upload/push seluruh isi folder ini ke GitHub.
2. Import repository tersebut ke Netlify.
3. Build command: kosongkan.
4. Publish directory: `.`
5. Tambahkan environment variable:
   `SESSION_SECRET` = string acak panjang, minimal 32 karakter.
6. Deploy.
7. Buka `/admin/` untuk membuat akun admin pertama kali.

## Penyimpanan
Data disimpan menggunakan Netlify Blobs, bukan file `data/data.php`. Ini penting karena filesystem Function Netlify tidak cocok untuk penyimpanan permanen.

Data awal dari project PHP lama sudah dimasukkan sebagai data awal.
