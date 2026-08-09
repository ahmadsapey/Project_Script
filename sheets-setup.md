# Google Sheets Backend (Quick setup)

Langkah cepat untuk menggunakan Google Sheets sebagai backend yang mengembalikan JSON.

1) Buat Google Sheet baru dan isi kolom: `title`, `category`, `image`, `safelinku_url` pada baris pertama (header), lalu tambahkan baris data di bawahnya.

2) Buka Extensions → Apps Script di Google Sheets, lalu ganti `Code.gs` dengan kode berikut:

```javascript
function doGet(e) {
  const ss = SpreadsheetApp.getActive();
  const sheet = ss.getActiveSheet();
  const data = sheet.getDataRange().getValues();
  const headers = data.shift();
  const out = data.map(row => {
    const obj = {};
    headers.forEach((h, i) => obj[h] = row[i]);
    return obj;
  });
  const callback = e.parameter.callback;
  const json = JSON.stringify(out);
  const output = callback ? callback + '(' + json + ')' : json;
  return ContentService.createTextOutput(output).setMimeType(ContentService.MimeType.JSON);
}
```

3) Deploy → New deployment → pilih "Web app". Set `Who has access` ke "Anyone" (atau sesuai kebutuhan). Catat URL Web App (mis. `https://script.google.com/macros/s/XXXXX/exec`).

4) Di project Anda, buat file `sheet-config.js` (tidak dibagikan publik) dengan isi:

```javascript
// Example: set your deployed Apps Script URL here
const SHEET_ENDPOINT = 'https://script.google.com/macros/s/XXXXX/exec';
```

5) `index.html` sudah diperbarui untuk mencoba memuat dari `SHEET_ENDPOINT` jika tersedia. Pastikan `sheet-config.js` dimuat sebelum `index.html` script (tambahkan `<script src="sheet-config.js"></script>` di head atau tulis variabel ini inline jika lebih mudah).

Catatan:
- Jika Apps Script mengembalikan JSONP (callback), client harus men-support JSONP; kode di atas mengembalikan JSON default. Jika Anda butuh JSONP, tambahkan `?callback=...` pada URL.
- Untuk sinkronisasi dua arah (edit di situs lalu tulis kembali ke Sheets) perlu endpoint POST dan otentikasi; itu sedikit lebih kompleks.

Upload file JS via admin (opsional)
---------------------------------
Jika Anda lebih suka meng-generate file JS dari Apps Script dan meng-upload hasilnya melalui panel admin (seperti `data_sheet.js`), `index.html` sudah mendukungnya.

Format yang diharapkan untuk `data_sheet.js`:

```javascript
// data_sheet.js (contoh)
window.DATA_SHEET = [
  { "title": "Chou", "category": "Script", "image": "", "safelinku_url": "https://..." },
  // ... lebih banyak objek
];
```

Letakkan file `data_sheet.js` di root publik situs (atau pastikan admin dapat meng-upload ke `/data_sheet.js`). Saat pengunjung membuka landing page, `index.html` akan mencoba memuat `data_sheet.js` terlebih dahulu dan menggunakan `window.DATA_SHEET` jika tersedia. Jika tidak ada, sistem akan mencoba Google Sheets, lalu `files.json`, lalu fallback ke `localStorage`.

Catatan tambahan untuk project berbasis server:
- Admin panel sekarang bisa mengirim data JS ke endpoint server `api/save-sheet.php`.
- Server akan menulis ulang `/data_sheet.js` dan mendownload semua gambar remote yang valid ke folder `/Gambar/`.
- Jadi link gambar Drive preview `https://drive.google.com/file/d/ID/view?...` akan dikonversi otomatis menjadi `https://drive.google.com/uc?export=view&id=ID`, lalu didownload ke server.
- Pastikan project dijalankan melalui server (Laragon) agar endpoint PHP bekerja.

