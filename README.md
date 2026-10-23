# Jeda Store — praktikum REST API

## Tujuan dan batas pekerjaan

Backend Laravel untuk frontend React Jeda Store di `D:\Kampus\tekweb\tugas2new`. Frontend tidak diubah. Sumber katalog asli: `src/data/products.js`, berisi 12 produk dan 5 kategori: Kopi & Teh, Dapur, Perabot, Aksesoris, Perawatan. Kategori `Semua` hanya filter UI, bukan baris database.

Folder backend semula kosong; Laravel dipasang langsung di `D:\Kampus\tekweb\laravel\latihan-laravel`, bukan dibuat berlapis. `BAB III REST API.pdf` tidak ditemukan pada penelusuran folder tekweb. Cakupan mengikuti permintaan praktikum, bukan klaim telah membaca modul yang tidak tersedia.

## Environment yang diperiksa

- PHP CLI 8.2.12, Laravel 12.70.0; Laravel 12 memerlukan PHP >=8.2.
- Composer 2.10.3. Launcher `composer` gagal di Git Bash karena path `/c/composer/composer.phar`; `php C:/composer/composer.phar` bekerja. Tidak mengubah instalasi global.
- XAMPP menyediakan MariaDB 10.4.32, bukan Oracle MySQL. Dipakai koneksi Laravel `mariadb` melalui ekstensi `pdo_mysql`. Jika memakai MySQL asli, ubah `DB_CONNECTION=mysql`.
- Ekstensi pdo_mysql, pdo_sqlite, mbstring, openssl, fileinfo, curl, xml dan zip tersedia. GD tidak aktif; bukan syarat menerima upload gambar.
- `intl` tidak aktif. Untuk perintah diagnostik `db:show`, gunakan `php -d extension=intl artisan db:show`; tidak perlu mengubah php.ini global.
- Database development baru: `jeda_store`. Database lain tidak disentuh. Jangan gunakan `migrate:fresh` pada database berisi data penting.

## Client-server

React mengirim HTTP request ke route `/api/...`. Middleware memeriksa token, validasi memeriksa input, controller memakai model Eloquent untuk query database. API Resource membentuk JSON untuk React. File gambar disimpan pada disk public; database menyimpan path, bukan binary. React mengambil URL gambar dari response.

Backend belum dihubungkan ke halaman React. Penggantian import data lokal menjadi fetch adalah pekerjaan integrasi berikutnya; cart dan checkout belum menjadi transaksi backend.

## Setup lokal (PowerShell)

Jalankan dari folder backend. Untuk salinan baru saja, salin `.env.example` menjadi `.env` jika `.env` belum ada; jangan menimpa `.env` aktif.

```powershell
Set-Location 'D:\Kampus\tekweb\laravel\latihan-laravel'
php C:/composer/composer.phar install
# Hanya jika .env belum ada:
# Copy-Item .env.example .env
# php artisan key:generate
```

Konfigurasi pokok:

```dotenv
APP_NAME="Jeda Store"
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jeda_store
DB_USERNAME=root
DB_PASSWORD=
```

Isi kredensial database lokal sendiri. Jangan membagikan `.env`, APP_KEY, password, atau token. Penggunaan root lokal mengikuti XAMPP untuk praktikum; deployment memerlukan akun database terbatas dan password kuat. `.env.example` tidak menyimpan secret.

Buat database kosong melalui phpMyAdmin atau SQL:

```sql
CREATE DATABASE IF NOT EXISTS jeda_store
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

Server lokal: `http://127.0.0.1:8000`. Server development hanya untuk komputer lokal. Aktifkan MySQL pada XAMPP sebelum migration/server.

## Struktur kode

- `routes/api.php`: daftar endpoint, throttle, middleware `auth:sanctum`.
- `bootstrap/app.php`: mengaktifkan API routes dan memaksa error `/api/*` menjadi JSON, termasuk request tanpa header Accept.
- `app/Models/Category.php`, `Product.php`: fillable, cast, dan relasi Eloquent.
- `app/Models/User.php`: trait `HasApiTokens`, cast password `hashed`, password dan remember_token disembunyikan.
- `app/Http/Controllers/ProductController.php`: validasi, CRUD, transaksi database dan pergantian file.
- `app/Http/Controllers/AuthController.php`: register, login, me, logout.
- `app/Http/Resources/ProductResource.php`: format produk JSON, nama kategori dan URL gambar.
- `database/migrations/`: tabel users, cache/jobs bawaan, token Sanctum, categories, products.
- `database/seeders/`: `CategorySeeder` lalu `ProductSeeder` dipanggil berurutan oleh `DatabaseSeeder` dalam satu transaksi, fixture `data/products.json` dan 12 gambar asli di `images/`.
- `tests/Feature/`: AuthTest, ProductTest, SeedTest. `tests/TestCase.php` memaksa SQLite in-memory agar pengujian tidak menghapus database development.
- `scripts/verify_api.py`: pemeriksaan HTTP nyata dengan standard library Python, tanpa dependency tambahan.
- `postman/`: collection otomatis dan collection upload manual.

## Migration dan relasi

Dipilih konvensi Laravel `id`, bukan primary key khusus `product_id`/`category_id`. `products.category_id` tetap foreign key ke `categories.id`. Karena konvensi ini, model tidak memerlukan properti `$primaryKey`.

`categories`: id bigint unsigned, name varchar, slug unique, timestamps.

`products`: id bigint unsigned, category_id foreign key, name, slug unique, price decimal(12,2), image nullable, stock unsigned integer default 0, details JSON nullable, rating decimal(2,1) default 0, sold unsigned integer default 0, description text nullable, timestamps. MariaDB merepresentasikan JSON sebagai longtext dengan constraint json_valid.

Satu kategori memiliki banyak produk melalui `Category::products()` (hasMany). Satu produk memiliki satu kategori melalui `Product::category()` (belongsTo). Foreign key mencegah kategori yang masih dipakai dihapus. Slug menjadi identitas seed agar menjalankan ulang seeder tidak menggandakan data. ID mengikuti auto-increment; jangan menganggap selalu sama dengan ID hardcoded frontend pada database yang sudah berisi data.

`Product::with('category')` memuat kategori bersama daftar produk untuk menghindari N+1 query. Harga dan rating di-cast decimal sehingga response berupa string presisi tetap (`"78000.00"`, `"4.8"`). Details di-cast array. React bisa memakai `Number(product.price)` untuk kalkulasi.

## Seeder

Data fixture disalin dari frontend asli, bukan katalog buatan. Seeder memakai updateOrCreate berdasarkan slug, menyalin SVG tepercaya ke `storage/app/public/products/seed`, dan membuat kategori sebelum produk. `php artisan db:seed` dapat diulang tanpa duplikasi; nilai produk seed dikembalikan ke fixture. Jangan memakainya untuk mempertahankan perubahan manual atas produk seed. Tidak ada akun atau password demo bawaan.

## Endpoint aktual

Semua path di bawah diawali base URL `http://127.0.0.1:8000`.

| Method    | Path               | Auth         | Status sukses |
| --------- | ------------------ | ------------ | ------------- |
| POST      | /api/register      | Publik       | 201           |
| POST      | /api/login         | Publik       | 200           |
| GET       | /api/me            | Bearer token | 200           |
| POST      | /api/logout        | Bearer token | 200           |
| GET       | /api/categories    | Bearer token | 200           |
| GET       | /api/products      | Bearer token | 200           |
| POST      | /api/products      | Bearer token | 201           |
| GET       | /api/products/{id} | Bearer token | 200           |
| PUT/PATCH | /api/products/{id} | Bearer token | 200           |
| DELETE    | /api/products/{id} | Bearer token | 200           |

Seluruh apiResource products dilindungi sesuai permintaan praktikum. Belum ada route katalog publik. Sanctum mengautentikasi pengguna, bukan membedakan admin dan pembeli: setiap pengguna terdaftar dapat melakukan CRUD. Ini batas praktikum, BUKAN kebijakan otorisasi toko production. Sebelum deployment, tambahkan policy/role dan pisahkan kebutuhan katalog publik secara eksplisit.

Register/login dibatasi 10 request per menit oleh middleware throttle; jika 429, tunggu periode limit. Jangan menonaktifkan proteksi untuk memaksa test berulang.

## Request dan response

Header: `Accept: application/json`. JSON memakai `Content-Type: application/json`. Route terproteksi memakai `Authorization: Bearer <token>`.

Register (ganti placeholder dengan password development pribadi, minimum 8 karakter):

```json
{
    "name": "Mahasiswa Jeda",
    "email": "mahasiswa@example.test",
    "password": "<password-pribadi>",
    "password_confirmation": "<password-pribadi>"
}
```

Login cukup field email/password. Register langsung menghasilkan token; login menghasilkan token baru. Bentuk response keduanya adalah `{"data":{"user":{...},"token":"<token-baru>"}}`. Token asli hanya diberikan saat dibuat; tidak ditaruh di dokumentasi. GET /api/me menghasilkan `{"data":{...data pengguna...}}`, tanpa password. Logout menghapus token yang dipakai saja, bukan semua token perangkat lain. Response logout: `{"data":null,"message":"Logged out."}`.

Contoh body membuat produk (ganti category_id dengan hasil GET categories):

```json
{
    "name": "Produk Uji Praktikum",
    "slug": "produk-uji-praktikum",
    "category_id": 1,
    "price": 25000,
    "stock": 5,
    "description": "Produk sementara untuk pengujian",
    "details": ["Detail pengujian"],
    "rating": 4.5,
    "sold": 0
}
```

Contoh bentuk response satu produk seed (ID mengikuti database):

```json
{
    "data": {
        "id": 1,
        "name": "Kopi Robusta Toraja",
        "slug": "kopi-robusta-toraja",
        "price": "78000.00",
        "category": "Kopi & Teh",
        "category_id": 1,
        "image": "http://127.0.0.1:8000/storage/products/seed/kopi-toraja.svg",
        "stock": 24,
        "details": [
            "Netto 250 gram, biji utuh",
            "Tingkat sangrai: medium dark",
            "Kemasan zipper kedap udara"
        ],
        "rating": "4.8",
        "sold": 156,
        "description": "Biji robusta pilihan dari kebun Toraja dengan profil rasa cokelat gelap dan sedikit rempah."
    }
}
```

Daftar produk dan kategori menggunakan `{"data":[...]}`; DELETE sukses menggunakan `{"data":null}`. PUT mensyaratkan name, slug, price, category_id, stock; PATCH menerima subset, misalnya `{"stock":10}`. Field yang tidak masuk validasi tidak disimpan.

Validasi: slug huruf kecil/angka dipisah tanda minus dan unik; kategori harus ada; harga 0–9999999999.99 dengan maksimal 2 desimal; stock/sold integer 0–4294967295; rating 0–5 maksimal 1 desimal; details array maksimal 50 string, setiap string maksimal 1000 karakter. Deskripsi nullable maksimal 10000 karakter.

Error: 401 untuk token hilang/tidak valid/dicabut; 404 untuk ID produk tidak ditemukan; 422 untuk validasi atau kredensial login salah; 429 untuk batas request. Contoh login salah:

```json
{
    "message": "Invalid credentials.",
    "errors": { "email": ["Invalid credentials."] }
}
```

APP_DEBUG dibuat false agar stack trace dan konfigurasi tidak muncul pada response error. Untuk debugging lokal baca `storage/logs/laravel.log`; jangan membagikan log tanpa memeriksa secret.

## Upload dan File Storage

Postman: Body > form-data, isi field produk sebagai Text dan `image` sebagai File. Pilih JPG/JPEG, PNG, atau WebP maksimal 2048 KB. Jangan menetapkan Content-Type secara manual; Postman membuat multipart boundary. Details dikirim sebagai `details[0]`, `details[1]`.

Untuk mengganti gambar gunakan POST `/api/products/{id}` dengan `_method=PUT` dan semua field wajib, atau `_method=PATCH` untuk subset. Method spoofing menghindari keterbatasan parsing multipart PUT PHP. File lama tetap ada bila tidak mengirim field image. Mengirim image kosong tidak berarti menghapus gambar: validasi menolaknya.

File disimpan menggunakan disk public; URL dibentuk API Resource. File baru dibuang bila transaksi DB gagal. Setelah update/delete berhasil, file lama dihapus hanya bila tidak ada produk lain yang memakainya. SVG hanya diterima dari fixture lokal tepercaya; upload SVG ditolak untuk mengurangi risiko konten aktif. Pemeriksaan shared file dan kegagalan transaksi terdapat di ProductTest.

`php artisan storage:link` menghubungkan public/storage ke storage/app/public. Jika Windows menolak pembuatan link, periksa izin terminal/Developer Mode; jangan menyalin file diam-diam untuk menyamarkan kegagalan link. Pada environment ini link berhasil dan semua 12 URL gambar seed merespons 200.

## Postman dan pengujian ulang

1. Import `postman/JedaStore.postman_collection.json`. Jalankan collection secara berurutan dengan base_url default. Pre-request script membuat email/password unik; test script mengambil token, category_id dan product_id otomatis.
2. Collection menguji register valid/duplikat, login benar/salah, profile, CRUD, validasi, tanpa token, logout, dan penolakan token setelah logout. Produk sementara dihapus, akun pengujian tetap ada tanpa token aktif.
3. Untuk pengujian login manual, POST /api/login dengan email/password pribadi. Salin `data.token`, buka Authorization > Bearer Token pada request terproteksi, tempel token tanpa menambahkan kata Bearer lagi.
4. Import collection upload terpisah `postman/JedaStore.upload.postman_collection.json`. Isi token, category_id, product_id; pilih file lokal pada field image. Simpan ID dari response upload sebelum menjalankan ganti gambar. Hapus produk uji setelah selesai.
5. Jangan membagikan export collection/environment sesudah run sebelum mengosongkan token/password. File collection bawaan tidak mengandung kredensial asli.

Perintah pemeriksaan (PowerShell):

```powershell
php artisan test
python scripts/verify_api.py
npx --yes newman run postman/JedaStore.postman_collection.json
php artisan route:list --path=api
php artisan migrate:status
```

Python smoke test memerlukan server aktif dan katalog awal 12 produk/5 kategori. Test membuat akun unik dengan password acak yang tidak ditampilkan/disimpan; akun ini bukan akun demo untuk login manual. PHPUnit memakai SQLite memory dan fake storage; pengujian HTTP/Newman memakai MariaDB development serta storage nyata. Newman hanya tool pengujian opsional melalui npx, bukan dependency aplikasi Laravel.

## Hasil aktual dan kendala

- `php artisan migrate --seed`: berhasil membuat categories/products pada jeda_store beserta foreign key. Base migration dan token Sanctum sudah dijalankan saat install:api.
- `php artisan storage:link`: berhasil.
- `php artisan test`: 15 test lulus, 297 assertion, termasuk shared image, rollback file, validasi, relasi dan seed idempotent.
- `python scripts/verify_api.py`: 40 pemeriksaan HTTP lulus, termasuk 12 URL gambar seed, gambar upload byte-identik, pergantian/penghapusan file fisik, profile dan pencabutan token.
- Newman: 18 request, 21 assertion; 0 gagal. Collection upload manual tidak diklik dalam aplikasi Postman; upload diuji lewat HTTP Python dan PHPUnit.
- Pemeriksaan SQL nyata: 5 kategori, 12 produk; products mempunyai unique slug dan foreign key ke categories.
- Percobaan HTTP pertama mengharapkan 404 untuk gambar terhapus, tetapi route storage bawaan mengembalikan 403. Pemeriksaan disesuaikan untuk 403/404 DAN memastikan file fisik tidak ada. Endpoint produk terhapus tetap 404. Produk/token sisa percobaan pertama dibersihkan.
- Composer launcher Git Bash dan diagnostik driver MySQL awal gagal; diselesaikan dengan composer.phar langsung dan driver mariadb. Tidak mengubah instalasi PHP global.
- npm memberi peringatan deprecation dependency internal Newman; run tetap berhasil. Tidak menambahkan paket tersebut ke package.json backend/frontend.
- Modul BAB III tidak tersedia, jadi kesesuaian halaman/urutan persis modul belum dapat diverifikasi.
- Belum diuji pada Oracle MySQL asli, server production, atau UI React. Frontend, keranjang dan checkout tidak diubah.

## Integrasi React (sudah dikerjakan)

Frontend yang dihubungkan: `D:\Kampus\tekweb\tugas2new` (React + Vite + Tailwind, JavaScript). File frontend yang disentuh:

- `src/api.js` (baru): pemanggilan fetch, penyimpanan token Sanctum di localStorage, normalisasi harga/rating dari string desimal Laravel ke Number, dan cache katalog di sessionStorage.
- `src/context/CartContext.jsx`: ditambah `CatalogProvider`/`useCatalog` berisi produk, kategori, status muat, dan `refresh` (memakai `useCallback` agar tidak memicu loop di `useEffect`).
- `src/App.jsx`: membungkus route dengan `CatalogProvider`, menampilkan `LoginPage` bila token belum ada, meneruskan `onLogout`.
- `src/pages/LoginPage.jsx` (baru): form register/login memakai `/api/register` dan `/api/login`, lalu menyimpan token.
- `src/pages/frontpages/Dashboard.jsx`: sumber data dari API melalui `useCatalog`, filter kategori dihitung dari nama kategori produk, indikator "Memuat katalog dari API..." dan peringatan bila memakai hasil cache.
- `src/pages/frontpages/ProductDetail.jsx`: produk dicari dari hasil API berdasarkan `id` dari URL.
- `src/pages/adminpages/AdminDashboard.jsx`: statistik memakai produk API, data lokal hanya sebagai pratinjau sebelum katalog termuat.
- `src/components/Navbar.jsx`, `src/layouts/MainLayout.jsx`: tombol Keluar memanggil `/api/logout`, menghapus token, lalu kembali ke halaman masuk.
- `vite.config.js`: proxy `/api` dan `/storage` ke `http://127.0.0.1:8000` supaya tidak perlu CORS dan URL absolut saat pengembangan.
- `src/data/products.js` TIDAK dihapus: masih dipakai `formatPrice`, dan menjadi pratinjau admin sebelum katalog API termuat.

Karena seluruh route katalog dilindungi `auth:sanctum`, frontend meminta register/login dulu. Ini konsekuensi permintaan praktikum "lindungi endpoint manajemen produk". Storefront publik memerlukan route baca terpisah di backend (belum dibuat).

Menjalankan dua server (dua terminal PowerShell):

```powershell
# terminal 1 - backend
Set-Location 'D:\Kampus\tekweb\laravel\latihan-laravel'
php artisan serve --host=127.0.0.1 --port=8000

# terminal 2 - frontend
Set-Location 'D:\Kampus\tekweb\tugas2new'
npm install
npm run dev
```

Buka `http://localhost:5173`, klik "Daftar dulu", buat akun development sendiri, lalu katalog 12 produk dimuat dari MariaDB. Token tersimpan di localStorage browser; tombol Keluar mencabutnya di backend dan mengembalikan halaman ke form masuk.

Catatan gambar: `ProductImage` memakai `loading="lazy"`, jadi gambar yang belum masuk layar belum terunduh. Itu normal, bukan gambar gagal; setelah digulir semua gambar seed termuat.

### Hasil pengujian integrasi UI

Diuji langsung di browser terhadap MariaDB dan storage nyata:

- Halaman masuk tampil saat token belum ada; register akun baru mengembalikan 201 dan menyimpan token.
- Dashboard menampilkan **12 produk** dan 5 tombol kategori dari API.
- Detail produk `/product/1`: Kopi Robusta Toraja, rating 4.8, terjual 156, stok 24; gambar termuat dari `/storage/products/seed/kopi-toraja.svg`.
- Keranjang dan halaman Checkout berjalan dengan harga hasil normalisasi (`Rp 78.000`).
- Panel admin menampilkan total produk 12 dan kategori 5 dari API.
- Logout: token lokal hilang, halaman kembali ke form masuk, token lama ditolak **401** pada `/api/me` dan `/api/products`.
- `npm run build` berhasil (64 modul) tanpa error.
