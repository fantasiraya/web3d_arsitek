# 🤖 System Prompt & AI Coding Instructions — PitchArch

> **PENTING UNTUK AI:** Sebelum menulis atau memodifikasi kode apa pun di project ini, kamu WAJIB membaca dan mematuhi seluruh panduan yang ada di dalam folder `guide/`. Dilarang keras membuat asumsi, halusinasi arsitektur, atau mengubah konvensi tanpa persetujuan.
> **v3.1:** Ditambahkan Section R (Architectural Working Booklet & Bidirectional Reference), Section S (Direct AI Render Generation), dan Section T (Interactive 3D Previews & Dashboard).
>
> **v3.0:** Modul Architectural Working Booklet PDF & Per-Room RAB Generator telah selesai diimplementasikan.
>
> **v2.8:** Modul Drawing 2D (`Domains/Drawing`) telah diimplementasikan penuh (Section O: multi-paper canvas, Figma-style paper resize handles, AutoCAD model space, kop gambar auto-wrap, hotkeys lengkap, export PDF clean iframe, DXF, SVG, PNG, responsive header & HUD).
>
> **v2.7:** Ditambahkan Section N (Profil Arsitek & Wilayah), Section P (AI Vision Studio), dan Section Q (High Concurrency & Redis Performance).
>
> **v2.6:** Ditambahkan `Domains/Rab` dan Section M (RAB Rule).
>
> **v2.5:** Stack aktual diperbarui — Laravel 12 (bukan 13), Inertia.js + Vue 3 (bukan Nuxt 3), MySQL (bukan PostgreSQL), Fortify (bukan Sanctum), local disk public (bukan R2), queue database driver (bukan Redis). Ditambahkan Section K (Email Verification Rule) dan Section L (Inertia & Frontend Rules).

---

## 1. Context & Architecture References

Seluruh logika bisnis, arsitektur teknis, dan skema database project ini mengacu pada file-file berikut di dalam folder `guide/`:
- `guide/PRD.md` — Logika bisnis, batasan kuota, dan fitur SaaS (PRD v3.1).
- `guide/RTCF.md` — Spesifikasi arsitektur teknis, tech stack, dan alur integrasi.
- `guide/RISE.md` — Draf prompt & langkah-langkah penulisan kode per komponen.
- `guide/DATABASE_SCHEMA.md` — Skema tabel MySQL, tipe data, dan relasi UUID (v3.1).
- `guide/PROGRESS_TRACKER.md` — Status pengerjaan menu & fitur terupdate.
- `guide/FOLDER_STRUCTURE.md` — Peta direktori monorepo aktual.

---

## 2. Mandatory Rules for Code Generation

### A. DDD Architectural Principles (Strict Rule)
1. **No Role-Based Domains:** Dilarang keras membuat domain bernama `Admin`, `Client`, atau `Architect`. Domain HARUS murni berdasarkan Bounded Context logika bisnis:
   - `Domains/Auth` (Autentikasi & Identitas)
   - `Domains/SystemConfig` (Konfigurasi Aturan Bisnis & Kuota Global — termasuk backoffice panel Super Admin)
   - `Domains/Project` (Manajemen Model 3D, Storage, Lighting & Client Invitation)
   - `Domains/Comment` (Spatial Pin Annotation & Feedback)
   - `Domains/Chat` (Real-time Messaging Arsitek ↔ Klien per Project)
   - `Domains/Billing` (Transaksi, Subscription & Midtrans)
   - `Domains/Rab` (RAB & Lembar Kerja: harga satuan, AHSP, template, import take-off, estimator `.glb`, generator worksheet, export Excel)
   - `Domains/VisionStudio` (AI Generative Studio: Image & Video prompts arsitektural, paid-only)
   - `Domains/Drawing` (Editor CAD 2D: denah, tampak, potongan, konva.js, export DXF/SVG, Booklet PDF Builder — hanya Enterprise)
2. **Primary Key UUID:** Selalu gunakan UUID untuk ID tabel database. Gunakan `$table->uuid('id')->primary()` di migration.
3. **Dynamic Quotas:** Selalu baca batasan kuota Free/Pro Tier dari `system_settings` via database query. **Jangan hardcode** angka kuota di controller atau action.
4. **No Spaghetti Code:** Terapkan pemisahan tanggung jawab yang jelas (Form Request, Action/Service, Repository).

### B. Automatic Progress Tracking Requirement
- **TUGAS AI:** Setiap kali kamu selesai menulis, merefaktor, atau menguji suatu fungsi/menu/fitur hingga berhasil running tanpa error, kamu **WAJIB secara otomatis memperbarui file `guide/PROGRESS_TRACKER.md`**.
- Ubah status fitur terkait dari 🔴 **Pending** / 🟡 **In Progress** menjadi 🟢 **Completed**, serta catat tanggal dan ringkasan perubahannya pada tabel dan Activity Logs.

### C. Laravel Artifacts Naming & Location Rules
1. **Form Requests:** Wajib dibuat per-action/domain di dalam domain masing-masing:
   - `app/Domains/Project/Requests/StoreProjectRequest.php`
   - `app/Domains/Project/Requests/InviteClientRequest.php`
   - `app/Domains/Comment/Requests/StoreCommentRequest.php`
   - `app/Domains/Chat/Requests/SendChatMessageRequest.php`
2. **Factories:** Ditaruh di `database/factories/` dengan suffix `Factory`:
   - `database/factories/ProjectFactory.php`
   - `database/factories/CommentFactory.php`
   - `database/factories/SubscriptionFactory.php`
   - `database/factories/ProjectClientFactory.php`
   - `database/factories/ChatMessageFactory.php`
3. **Seeders:** Ditaruh di `database/seeders/`:
   - `database/seeders/SystemSettingSeeder.php`
   - `database/seeders/DatabaseSeeder.php`
4. **Events:** Ditaruh di `app/Events/` (bukan di dalam domain):
   - `app/Events/ChatMessageSent.php`
   - `app/Events/ClientAccessRevoked.php`
   - `app/Events/ClientInvitationReceived.php`
   - `app/Events/ClientStatusUpdated.php`

### D. Automated Testing Strategy (Pest PHP)
1. **Unit & Feature Testing (Backend):** Gunakan **Pest PHP** untuk pengujian di Laravel.
   - Lokasi test disesuaikan dengan struktur domain:
     - `tests/Unit/Domains/Project/CreateProjectTest.php`
     - `tests/Feature/Domains/Billing/MidtransWebhookTest.php`
     - `tests/Feature/Domains/Project/InviteClientTest.php`
     - `tests/Feature/Domains/Chat/SendChatMessageTest.php`
   - Setiap Action/Service di domain wajib memiliki Unit Test untuk menguji logika bisnis utama.
2. **Trigger Post-MVP:** Setelah semua fitur MVP berstatus 🟢 **Completed**, jalankan fase pembuatan unit test komprehensif.

### E. Google OAuth & Auth Flow Rules (Fortify + Socialite)
1. **Redirect-Based Flow (bukan token API):**
   - Google OAuth menggunakan redirect flow: `GET /auth/google/redirect` → Socialite redirect → `GET /auth/google/callback` → `GoogleAuthController@callback`.
   - Tidak ada `id_token` yang dikirim dari frontend ke backend API endpoint seperti Sanctum. Auth bersifat session-based via Fortify.
2. **Auto Email Verification:**
   - Saat user login/register via Google OAuth, `email_verified_at` WAJIB diset ke `now()` karena Google sudah memverifikasi email → user tidak perlu verifikasi email ulang.
3. **Environment Configuration:**
   - Variabel `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, dan `GOOGLE_REDIRECT_URI` wajib dibaca dari `.env`.
4. **Mocking Strategy for Testing:**
   - Gunakan `Socialite::fake()` atau `Socialite::shouldReceive()` — JANGAN buat real HTTP request ke Google saat testing.

### F. Client Revision Limit & Enforcement Rules
1. **Dynamic & Project-Level Revision Quota:**
   - Cek `projects.max_revisions_allowed` (default dari `system_settings`) sebelum mengizinkan client menambahkan pin komentar baru.
   - Perbandingan: `projects.current_revision_count >= projects.max_revisions_allowed` → blokir.
2. **Counter Increment Rule:**
   - `projects.current_revision_count` WAJIB di-increment oleh `Domains/Comment` setiap kali client membuat pin komentar **root baru** (bukan reply/thread, bukan komentar dari arsitek).
3. **Quota Enforcement Exception:**
   ```json
   {
     "status": "error",
     "message": "Batas kuota revisi untuk project ini telah tercapai. Silakan hubungi arsitek terkait untuk penambahan kuota."
   }
   ```
4. **Frontend Counter Badge Requirement:**
   - Komponen 3D Viewer di `Viewer.vue` wajib menampilkan status counter revisi (contoh: *"Revisi 2 dari 3"*).
   - Jika kuota habis, kunci form submission pin komentar dan tampilkan badge peringatan.

### G. Subscription & Billing Sync Rule
1. **Dual-Write Consistency:**
   - Saat `Domains/Billing` menerima webhook Midtrans dengan status `settlement`, Action `HandleWebhookAction` WAJIB menulis ke DUA tempat secara atomik dalam satu DB transaction: tabel `subscriptions` (riwayat) DAN `users.subscription_status = 'pro'` (cache cepat middleware).
   - Dilarang hanya meng-update salah satu.
2. **Storage:** Gunakan `Storage::disk('public')` — bukan `s3`, `r2`, atau driver eksternal lainnya.
3. **Queue:** Gunakan `queue('default')` dengan database driver — bukan Redis-specific syntax.

### H. Client Invitation & Access Control Rule
1. **No Public Read-Only Access:** Setiap request ke `p/{share_token}` dan seluruh endpoint terkait project (viewer data, comments, chat) WAJIB melalui `ProjectClientAccessMiddleware`.
2. **Invitation Flow:**
   - `InviteClientAction` membuat/update baris `project_clients` (status `pending`) → kirim `ClientInvitationNotification` berisi link `/p/{share_token}` via email `noreply@pitcharch.id`.
   - `MatchInvitedClientEmailAction` di `app/Actions/Auth/` dijalankan saat login (baik email/password maupun Google OAuth) — cari baris `project_clients` dengan `email` = email user → jika `pending`, update jadi `accepted`, isi `user_id`, set `accepted_at`.
3. **Access Validation:** `ProjectClientAccessMiddleware` izinkan akses HANYA jika:
   - User login adalah `projects.user_id` (Arsitek pemilik), ATAU
   - Ada baris `project_clients` dengan `user_id` = user login, `project_id` sesuai, dan `status = 'accepted'`.
   - Selain itu → `403 Forbidden` format JSON konsisten.
4. **Revoke Access:** `RevokeClientAccessAction` hapus record dari `project_clients` — middleware WAJIB langsung menolak akses setelah ini.
5. **Dilarang Hardcode/Bypass:** Dilarang membuat endpoint atau flag yang melewati validasi email-matching ini. Gunakan factory state pada test.

### I. Real-time Chat Rule
1. **Broadcasting Stack:** Gunakan `laravel/reverb` sebagai WebSocket server backend dan `laravel-echo` di frontend (`lib/echo.ts`). Dilarang menambahkan Pusher Cloud, Ably, atau provider lain tanpa persetujuan.
2. **Private Channel Convention:** Format `project.{project_id}.chat`. Otorisasi di `routes/channels.php` WAJIB memakai validasi identik dengan `ProjectClientAccessMiddleware` (Section H.3).
3. **Message Persistence:** Setiap pesan chat WAJIB disimpan ke `chat_messages` sebelum di-broadcast (persist-then-broadcast).
4. **No Chat Bypass of Revision Limit:** Chat TIDAK meng-increment `current_revision_count`.

### J. Role & Account Model Rule
1. **No Role Selection at Registration:** Form Register / callback Google OAuth di `Domains/Auth` DILARANG memiliki input pemilihan role. Tabel `users` TIDAK memiliki kolom `role`.
2. **Architect = Default Capability:** Setiap akun yang berhasil register otomatis bisa membuat project, dibatasi kuota Free Tier.
3. **Client = Derived Per-Project Status:** Status "Klien" TIDAK disimpan sebagai atribut pada `users`. Dilarang membuat kolom `users.is_client`.
4. **Dual-Capacity Supported Natively:** Dilarang membuat validasi yang mencegah seorang Arsitek menerima undangan sebagai Klien di project lain.
5. **Super Admin Exception:** `super_admin` adalah SATU-SATUNYA role eksplisit via Spatie. Tidak pernah di-assign melalui endpoint publik.

### K. Email Verification Rule (Fortify — MustVerifyEmail)
1. **User Model:** `User` model di `app/Domains/Auth/Models/User.php` implements `MustVerifyEmail`. Dilarang menghapus interface ini.
2. **Route Protection:** Semua route yang memerlukan akses terautentikasi HARUS menggunakan middleware `verified` (di atas `auth`), atau dikombinasikan via Fortify's built-in check.
3. **Google OAuth Auto-Verify:** Saat `GoogleAuthController@callback` membuat atau login user via Google, WAJIB set `email_verified_at = now()` — tidak perlu kirim email verifikasi ulang karena Google sudah memverifikasinya.
4. **Resend Verification:** Fortify menyediakan endpoint `POST /email/verification-notification` untuk resend. Halaman `auth/VerifyEmail.vue` menampilkan tombol resend ini.
5. **Testing:** Test yang menyentuh route dengan middleware `verified` HARUS menggunakan user dengan `email_verified_at` yang sudah diset. Gunakan `User::factory()->create(['email_verified_at' => now()])`.

### L. Inertia.js & Frontend Rules
1. **Bukan Nuxt 3:** Jangan membuat komponen `pages/` dengan Nuxt file-based routing (`definePageMeta`, `useRoute`, `navigateTo`). Semua navigasi menggunakan Inertia: `router.visit()`, `router.post()`, atau komponen `<Link>` dari `@inertiajs/vue3`.
2. **Routing via Controller:** Semua routes didefinisikan di file Laravel (`routes/web.php`, `routes/admin.php`, `routes/settings.php`). Tidak ada router Vue terpisah.
3. **Data Props:** Data dari controller ke Vue dikirim sebagai Inertia props di `Inertia::render('PageName', [...data])`. Tidak ada Vue `store` global untuk data server — gunakan props.
4. **Layouts:** Gunakan layout yang sesuai via `defineOptions({ layout: LandingLayout })` atau assign di AppServiceProvider:
   - `LandingLayout.vue` → halaman landing & auth pages.
   - `AdminLayout.vue` → halaman `/admin/*`.
   - `UnifiedLayout.vue` → halaman settings.
5. **Response dari Controller:** Gunakan `return Inertia::render('PageName', [...])` untuk halaman, `return to_route('route.name')` atau `return back()` untuk redirect/form response. Jangan `return response()->json(...)` untuk halaman Inertia.
6. **UI Components:** Gunakan shadcn/ui Vue port yang sudah ada di `resources/js/components/ui/`. Jangan menginstall library UI lain tanpa persetujuan.
7. **Tailwind v4:** Konfigurasi di `resources/css/app.css` menggunakan Tailwind CSS v4 syntax. Jangan gunakan `tailwind.config.js` gaya lama jika bertentangan.

### M. RAB Rule (`Domains/Rab`)
1. **Gating:** fitur RAB (buat/edit) hanya jika `plans.can_use_rab = true` untuk paket aktif user; cek lewat `RabAccessService`. Jangan hardcode slug paket di controller/action.
2. **Kepemilikan:** harga satuan, template, dan mapping milik `user_id` masing-masing. Setiap query WAJIB difilter `user_id` pemilik.
3. **Akses lihat:** pemilik project selalu boleh. Klien hanya boleh jika `project_clients.status = 'accepted'` **dan** `rab_documents.is_visible_to_clients = true`, read-only. Klien dilarang membuat/mengubah RAB.
4. **Snapshot harga:** `rab_items.unit_price` disalin saat item dibuat. Mengubah harga master dilarang mengubah item RAB yang ada.
5. **Final lock:** dokumen `final` tidak boleh diedit; hanya `reopen` oleh pemilik. Switch visibilitas boleh diubah kapan saja.
6. **Estimasi `.glb`:** item dari jalur `.glb` WAJIB `is_estimate = true` dan berlabel "Estimasi" di UI. Jangan menyajikannya sebagai RAB final.
7. **Perhitungan RAB:** seluruh hitung ulang lewat `RecalculateRabAction` (satu sumber kebenaran); dilarang menghitung total di controller atau frontend sebagai nilai tersimpan.
8. **AHSP (`rab_price_item_components`):** sub-komponen biaya dari harga satuan. Jika `rab_price_items.has_components = true`, `unit_price` dikalkulasi dari penjumlahan `amount` semua komponen + `overhead_percent`. `RecalculatePriceItemAction` adalah **satu sumber kebenaran** untuk kalkulasi ini — dilarang menghitung ulang di controller, view, atau frontend sebagai nilai tersimpan.
9. **Custom Properties Blender (Fase C):** `useGlbQuantities.ts` membaca `mesh.userData` dari file `.glb` untuk field: `unit_price`, `unit`, `category`, `section`, `quantity_basis`. Jika field hadir, digunakan sebagai nilai default saat import estimasi.
10. **Form Request & Test:** Form Request per action di `app/Domains/Rab/Requests/`; setiap Action wajib punya tes Pest di `tests/Feature/Domains/Rab/`.
11. **Paket baru** (XLSX/PDF export) wajib persetujuan sebelum dipasang.

### N. Profil Arsitek & Wilayah Indonesia Rule
1. **Field baru di `users`:** `company_type`, `company_name`, `phone`, `province_id`, `city_id`, `district_id`, `village_id`, `province_name`, `city_name`, `district_name`, `village_name`, `address`, `postal_code`. Migration telah dijalankan.
2. **API Wilayah:** route `/region/provinces`, `/region/cities?province_id=X`, `/region/districts?city_id=X`, `/region/villages?district_id=X` di-serve oleh `IndonesiaRegionController`. Route ini bersifat **public** (web group, tanpa middleware `auth`) karena dropdown wilayah diperlukan sebelum user tersimpan.
3. **Package:** `laravolt/indonesia` menyediakan tabel `indonesia_provinces`, `indonesia_cities`, `indonesia_districts`, `indonesia_villages`. Jangan membuat migration atau data wilayah sendiri.
4. **Denormalized cache:** simpan nama wilayah (`province_name`, `city_name`, dll.) ke kolom `users` saat profil disimpan. Ini menghindari join saat membaca profil atau mengisi kop surat.
5. **HandleInertiaRequests:** share semua field profil baru ke frontend agar tersedia sebagai `$page.props.auth.user.*`.
6. **Kop surat export:** `ExportRabAction` membaca `company_name`, `address`, `city_name`, `province_name`, `postal_code`, `phone` dari profil pemilik project — bukan dari nama personal (`users.name`).

### O. Drawing 2D Rule (`Domains/Drawing` — Fitur Enterprise)
1. **Gating & Akses:** Fitur Lembar Kerja CAD 2D hanya dapat diakses oleh paket **Enterprise** (`plans.can_use_drawing = true`) dan role Super Admin. Verifikasi melalui `DrawingAccessService` dan SubscriptionLimitService. Klien berstatus `accepted` memiliki hak akses read-only jika diperbolehkan arsitek.
2. **Domain & Data Modeling:** Seluruh backend drawing terisolasi di `Domains/Drawing`:
   - Tabel `drawing_sheets` (UUID PK, `project_id`, `user_id`, `title`, `type`, `width`, `height`, `scale`, `sort_order`).
   - Tabel `drawing_elements` (UUID PK, `sheet_id`, `element_type`, `properties` JSON, `layer`, `z_index`).
   - Tipe sheet wajib enum: `plan` (denah), `elevation` (tampak), `section` (potongan).
3. **Canvas Engine & Konva.js Architecture:**
   - Gunakan `konva.js` (npm) untuk rendering kanvas vektor performa tinggi.
   - Hirarki layer kanvas wajib: `paperBgLayer` (latar putih kertas) → `gridLayer` (grid garis mm/cm) → `paperLayer` (border kertas + Kop Gambar arsitek di sudut kanan bawah) → `mainLayer` (elemen CAD) → `tempLayer` (preview gambar / box selection) → `overlayLayer` (transform handles).
   - Penempatan layer ini menjamin garis grid **tidak pernah** menabrak atau memotong teks di dalam Kop Gambar (Title Block).
4. **Mode Lembar Kerja (Paper Space vs Model Space):**
   - Mode default adalah **Frame Kertas (Paper Space)**: mendukung lembar kerja ISO (A0–A4 portrait/landscape) serta ukuran kustom.
   - Mode alternatif adalah **AutoCAD Model Space (∞)**: kanvas gelap tanpa batas tanpa border kertas untuk perancangan layout bebas.
5. **Multi-Paper Frame & Interaktivitas Figma-Style:**
   - Multi-paper didukung pada satu kanvas (`+ Kertas ke Kanvas`). Tiap kertas memiliki tombol hapus mandiri di pojok kanvas.
   - Interaktivitas resize tepi (kanan dan bawah) serta sudut (kanan bawah) menggunakan drag handle responsif tanpa merusak (destroy) Konva node selama proses `dragmove`.
   - Ukuran kertas kustom wajib disimpan secara instan ke `localStorage` (`cad_paper_sizes_{sheet_id}`) dan disinkronkan secara atomik ke database via debounced/blur API call.
6. **Kop Gambar (Title Block) Standar Arsitek:**
   - Kop gambar wajib menempel di sudut kanan bawah kertas dengan margin aman.
   - Teks judul proyek dan instansi wajib menggunakan auto word-wrap (`wrap: 'word'`) dengan tinggi baris adaptif agar teks panjang tidak pernah meluber keluar batas kotak.
7. **Pintasan Keyboard (Hotkeys) Standar CAD:**
   - `V`: Select, `H` / `Spasi`: Pan kanvas, `Z`: Zoom extents/pusatkan, `W`: Dinding tebal, `R`: Ruangan kotak, `L`: Garis bebas (Line), `D`: Pintu swing, `J`: Jendela, `C`: Kolom beton, `S`: Tangga 2D, `M`: Dimensi ukuran otomatis, `T`: Teks/Label ruang, `E`: Penghapus.
   - `Ctrl+A`: Select All, `Ctrl+C`: Copy, `Ctrl+V`: Paste, `Ctrl+D`: Duplicate, `Ctrl+Z`: Undo, `Ctrl+Y` / `Ctrl+Shift+Z`: Redo, `Delete` / `Backspace`: Hapus objek, `Esc`: Batal/Deselect/Tutup menu.
   - `Ctrl + Drag`: Clone drag langsung, `Ctrl + Klik`: Multi-seleksi.
8. **Export & Print Architecture:**
   - **DXF:** `ExportDxfAction` menghasilkan format AutoCAD ASCII compliant dengan layer terpisah (`WALLS`, `DOORS`, `WINDOWS`, `DIMENSIONS`, `TEXT`).
   - **SVG & PNG:** Export vektor SVG via Konva data-URL dan raster PNG resolusi tinggi.
   - **PDF & Cetak Bersih:** Export PDF standar kertas ISO (A4–A0) dan cetak browser dilakukan melalui isolasi iframe tersembunyi (`hidden print iframe`) dengan ukuran kertas target dan resolusi 2×/3× tanpa bleed atau kebocoran elemen UI editor.
9. **Responsivitas Toolbar & HUD:**
   - Header baris kedua (paper presets & quick actions) dilengkapi deteksi scroll overflow horizontal dan indikator visual pulsing ("Menu Lainnya ▶").
   - Bottom HUD responsif dengan scroll cues kiri/kanan.
   - Left sidebar CAD tools diberi batas bawah aman (`bottom-18 sm:bottom-20 z-30`) agar tidak pernah tumpang tindih dengan bottom HUD pada layar mobile, serta dilengkapi indikator scroll vertikal (`▲` / `▼`).

### P. AI Vision Studio Rule (`Domains/VisionStudio`)
1. **Gating Berbayar (Strict):** Fitur AI Vision Studio hanya boleh diakses oleh pengguna paket **Pro**, **Enterprise**, atau role **Super Admin**. Gunakan `SubscriptionLimitService::canAccessVisionStudio($user)` dan middleware `CheckVisionStudioAccess` (`vision.access`). Free tier wajib di-redirect ke `/plans` atau menerima error 403.
2. **Penyimpanan Terpisah Penuh:** Galeri kreasi AI menggunakan tabel `vision_creations`. Dilarang menggabungkan media AI studio dengan `project_captures`. Tidak ada fitur "tampilkan ke klien" di studio ini.
3. **Pembersihan Storage Fisik:** Model `VisionCreation` wajib mempertahankan hook `booted()::deleted` untuk menghapus file fisik di `Storage::disk('public')` (`file_path` dan `end_file_path`) ketika entri dihapus.
4. **Persistensi State Browser:** Form prompt, pilihan mode, dan riwayat kreasi wajib disimpan di browser `localStorage` agar tidak hilang saat pengguna melakukan refresh halaman.

### Q. High Concurrency & Database Performance Rule (1 Juta Pengguna)
1. **Dilarang Memasang Global Hooks Berat pada Model:** Dilarang keras menambahkan event listener seperti `User::retrieved(fn ($u) => $u->loadMissing('roles'))` di Service Provider. Hook model global yang berjalan setiap kali record di-hydrate akan melipatgandakan query SQL di seluruh request.
2. **Eager Loading Eksplisit Wajib:** Setiap controller yang menampilkan daftar/pagination (terutama di area admin) WAJIB meng-eager-load relasi (`with(['user.activeSubscription.planModel', 'user.planOverride', 'invitedClients'])`) atau `withCount(...)` untuk mencegah N+1 queries.
3. **Distributed Atomic Locking pada Mutasi Kuota:** Setiap aksi yang memverifikasi kuota dan membuat resource baru (`CreateProjectAction`, `SaveCaptureAction`, `PinCommentAction`) WAJIB dibungkus atomic lock Redis:
   ```php
   Cache::lock("user:{$user->id}:action_name", 15)->block(5, function () {
       // logic verifikasi kuota & simpan
   });
   ```
4. **Transaction & Row-Level Locking pada Pembayaran:** Penanganan callback dan webhook payment gateway di `CheckoutController` WAJIB dibungkus `DB::transaction` dengan `lockForUpdate()` pada order pending untuk mencegah race condition duplicate upgrade.
5. **Multi-Database Redis Architecture:**
   - Gunakan `CacheKeys.php` dengan safe tagging wrapper `CacheKeys::tags()`.
   - Redis DB 1 dialokasikan khusus untuk cache (`REDIS_CACHE_DB=1`).
   - Redis DB 2 dialokasikan khusus untuk session pengguna (`REDIS_SESSION_DB=2`) agar command `cache:clear` tidak mendrop session pengguna yang aktif.

### R. Architectural Working Booklet & Bidirectional Reference Rule (`Domains/Drawing` & `Domains/Project`)
1. **Standar Presentasi Studio:** Halaman booklet di `Drawing/Booklet.vue` wajib mendukung tata letak landscape ISO (A3/A4) dengan komposisi baku: Halaman 1 Cover + watermark `DESIGN BY [COMPANY_NAME]`, Halaman 2 Master Plan Denah 2D 80/20, Halaman 3+ Eksterior & Interior per ruangan (Visual 68% + catatan + Key Plan + Kop Gambar 20%).
2. **Auto Key-Plan Locator (`KeyPlanCanvas.vue`):** Wajib menggunakan render SVG murni tanpa dependensi eksternal berat. Bounding box dihitung dinamis dari seluruh elemen denah 2D. Ruangan yang sedang aktif WAJIB disorot dengan warna oranye terang arsitektural (`#EA580C`) beserta nomor/label kode ruangan.
3. **Dedicated Print Engine:** Booklet menggunakan isolated CSS `@page { size: A3 landscape; margin: 0; }` dan utility `.booklet-page { break-after: page; }` agar hasil cetak browser atau save to PDF presisi 100% tanpa margin terpotong atau halaman kosong.
4. **Bidirectional Reference System (3D ↔ 2D):**
   - Kolom `code` pada `project_captures` (contoh: `R-01`, `EXT-01`) wajib unik dan sinkron antara 3D capture dan denah 2D.
   - Panel Inspector pada CAD Editor 2D (`Drawing/Editor.vue`) menyediakan combobox interaktif untuk menautkan visual 3D ke elemen label ruangan, dengan proteksi badge "Sedang Dipakai" atau "Sudah Digunakan di [Ruangan]".
   - Kanvas Konva CAD wajib menampilkan badge kamera oranye `📷 R-01` di atas label ruangan yang telah terhubung.
5. **Worksheet to RAB Generator:** Pembuatan RAB per ruangan melalui `GenerateRabFromWorksheetAction` (`POST /projects/{project}/rab/from-worksheet`) WAJIB mengelompokkan item berdasarkan `section = Nama Ruangan` dan melakukan kalkulasi ulang melalui `RecalculateRabAction`.

### S. Direct AI Render Generation Rule (`Domains/Project`)
1. **Action & Provider:** Gunakan `GenerateAiRenderFromCaptureAction` sebagai satu-satunya titik masuk untuk generasi render fotorealistik AI dari snapshot 3D.
2. **Kepatuhan Geometri 3D (100% Geometry Adherence):** Prompt yang dikirim ke Google Gemini API wajib menekankan ketaatan mutlak terhadap geometri referensi gambar 3D (`strict adherence to reference geometry`).
3. **Storage & Pathing:** File hasil generate AI disimpan di `storage/app/public/projects/{id}/captures/ai/{uuid}.png` dan path disimpan pada kolom `ai_rendered_path` di tabel `project_captures`.
4. **Mode Visual Switcher:** Frontend wajib menyediakan toggle visual untuk berganti secara instan antara "Render AI" dan "3D Asli" baik di 3D Viewer, drawer galeri, maupun di Working Booklet.

### T. Interactive 3D Previews & Dashboard Rule (`resources/js/components/`)
1. **Komponen Modular:** Tampilan preview 3D interaktif pada kartu proyek diisolasi dalam `Project3DPreview.vue` dan di-consume oleh `ProjectCard.vue`.
2. **Optimasi Performa WebGL:** Animasi auto-rotate Three.js wajib menggunakan requestAnimationFrame yang hemat baterai dan men-dispose Three.js scene, geometry, material, dan texture saat komponen di-unmount.
3. **Data Klien:** Kartu proyek menampilkan `client_name` dan `client_address` jika tersedia.

---

## 3. Workflow Procedure for AI
1. Periksa `guide/PROGRESS_TRACKER.md` untuk melihat fitur mana yang perlu dikerjakan selanjutnya.
2. Baca skema tabel terkait di `guide/DATABASE_SCHEMA.md`.
3. Baca peta folder di `guide/FOLDER_STRUCTURE.md` untuk menentukan lokasi file yang benar.
4. Tulis kode sesuai aturan DDD dan konvensi project (termasuk Section H/I/J/K/L/M/N/O/P/Q/R/S/T).
5. Update `guide/PROGRESS_TRACKER.md` begitu pekerjaan selesai.

---

## 4. Quick Reference: Tech Stack Aktual

| Komponen | Yang Benar | Yang SALAH (Jangan Gunakan) |
| :--- | :--- | :--- |
| Framework | Laravel **12** | ~~Laravel 13~~ |
| Frontend | **Inertia.js + Vue 3** (monorepo) | ~~Nuxt 3~~ (server terpisah) |
| Auth | **Laravel Fortify** | ~~Laravel Sanctum~~ (token-based) |
| Database | **MySQL** | ~~PostgreSQL~~ |
| Storage | **local `public` disk** (`storage/app/public/`) | ~~Cloudflare R2~~, ~~S3~~ |
| Cache Driver | **Redis** (DB 1 Cache, DB 2 Session) | Driver file untuk produksi 1M user |
| Concurrency | **Redis Distributed Locks** (`Cache::lock()->block()`) | Check-then-act tanpa lock |
| Queue | **database driver** | ~~SQS~~ |
| OAuth Flow | **Redirect-based** (Socialite) | ~~id_token API endpoint~~ |
| JSON type | **`json`** (MySQL) | ~~`jsonb`~~ (PostgreSQL only) |
| Brand | **PitchArch** | ~~Aether 3D~~, ~~SaaS Web 3D~~ |
| Email | **noreply@pitcharch.id** | email lain |
| Canvas 2D (Drawing) | **konva.js** (npm) | library canvas lain |
| Export DXF | **nzcreations/dxf** (composer) | library DXF lain |
| AI Render Vision | **Google Gemini API** (`GenerateAiRenderFromCaptureAction`) | API generator tanpa ketaatan geometri |
| Key-Plan Locator | **KeyPlanCanvas.vue** (SVG responsive) | Bitmap snapshot raster |
| Booklet Print Engine | **Pure CSS @page ISO Landscape** (`Booklet.vue`) | Browser screenshot bleed |
| Wilayah Indonesia | **laravolt/indonesia** | buat tabel sendiri |
| Kalkulasi harga satuan | **RecalculatePriceItemAction** | hitung di controller/frontend |
| Kalkulasi total RAB | **RecalculateRabAction** | hitung di controller/frontend |
