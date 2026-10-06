# 🤖 System Prompt & AI Coding Instructions — PitchArch

> **PENTING UNTUK AI:** Sebelum menulis atau memodifikasi kode apa pun di project ini, kamu WAJIB membaca dan mematuhi seluruh panduan yang ada di dalam folder `guide/`. Dilarang keras membuat asumsi, halusinasi arsitektur, atau mengubah konvensi tanpa persetujuan.
>
> **v2.5:** Stack aktual diperbarui — Laravel 12 (bukan 13), Inertia.js + Vue 3 (bukan Nuxt 3), MySQL (bukan PostgreSQL), Fortify (bukan Sanctum), local disk public (bukan R2), queue database driver (bukan Redis). Ditambahkan Section K (Email Verification Rule) dan Section L (Inertia & Frontend Rules).

---

## 1. Context & Architecture References

Seluruh logika bisnis, arsitektur teknis, dan skema database project ini mengacu pada file-file berikut di dalam folder `guide/`:
- `guide/PRD.md` — Logika bisnis, batasan kuota, dan fitur SaaS (PRD v2.5).
- `guide/RTCF.md` — Spesifikasi arsitektur teknis, tech stack, dan alur integrasi.
- `guide/RISE.md` — Draf prompt & langkah-langkah penulisan kode per komponen.
- `guide/DATABASE_SCHEMA.md` — Skema tabel MySQL, tipe data, dan relasi UUID (v2.5).
- `guide/PROGRESS_TRACKER.md` — Status pengerjaan menu & fitur terupdate.
- `guide/FOLDER_STRUCTURE.md` — Peta direktori monorepo aktual.

---

## 2. Mandatory Rules for Code Generation

### A. DDD Architectural Principles (Strict Rule)
1. **No Role-Based Domains:** Dilarang keras membuat domain bernama `Admin`, `Client`, atau `Architect`. Domain HARUS murni berdasarkan Bounded Context logika bisnis:
   - `Domains/Auth` (Autentikasi & Identitas)
   - `Domains/SystemConfig` (Konfigurasi Aturan Bisnis & Kuota Global — termasuk backoffice panel Super Admin)
   - `Domains/Project` (Manajemen Model 3D, Storage & Client Invitation)
   - `Domains/Comment` (Spatial Pin Annotation & Feedback)
   - `Domains/Chat` (Real-time Messaging Arsitek ↔ Klien per Project)
   - `Domains/Billing` (Transaksi, Subscription & Midtrans)
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

---

## 3. Workflow Procedure for AI
1. Periksa `guide/PROGRESS_TRACKER.md` untuk melihat fitur mana yang perlu dikerjakan selanjutnya.
2. Baca skema tabel terkait di `guide/DATABASE_SCHEMA.md`.
3. Baca peta folder di `guide/FOLDER_STRUCTURE.md` untuk menentukan lokasi file yang benar.
4. Tulis kode sesuai aturan DDD dan konvensi project (termasuk Section H/I/J/K/L jika menyentuh Auth, Project, Chat, atau Frontend).
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
| Queue | **database driver** | ~~Redis~~, ~~SQS~~ |
| OAuth Flow | **Redirect-based** (Socialite) | ~~id_token API endpoint~~ |
| JSON type | **`json`** (MySQL) | ~~`jsonb`~~ (PostgreSQL only) |
| Brand | **PitchArch** | ~~Aether 3D~~, ~~SaaS Web 3D~~ |
| Email | **noreply@pitcharch.id** | email lain |
