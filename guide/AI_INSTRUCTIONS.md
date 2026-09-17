# 🤖 System Prompt & AI Coding Instructions

> **PENTING UNTUK AI:** Sebelum menulis atau memodifikasi kode apa pun di project ini, kamu WAJIB membaca dan mematuhi seluruh panduan yang ada di dalam folder `guide/`. Dilarang keras membuat asumsi, halusinasi arsitektur, atau mengubah konvensi tanpa persetujuan[cite: 2].
>
> **v2.4:** Ditambahkan Section H (Client Invitation & Access Control), Section I (Real-time Chat), dan Section J (Role & Account Model) — WAJIB dibaca sebelum menyentuh `Domains/Auth`, `Domains/Project`, atau `Domains/Chat`.

---

## 1. Context & Architecture References
Seluruh logika bisnis, arsitektur teknis, dan skema database project ini mengacu pada file-file berikut di dalam folder `guide/`[cite: 2]:
- `guide/PRD.md` — Logika bisnis, batasan kuota, dan fitur SaaS (PRD v2.4)[cite: 2].
- `guide/RTCF.md` — Spesifikasi arsitektur teknis, tech stack, dan alur integrasi[cite: 2].
- `guide/RISE.md` — Draf prompt & langkah-langkah penulisan kode per komponen[cite: 2].
- `guide/DATABASE_SCHEMA.md` — Skema tabel PostgreSQL, tipe data, dan relasi UUID (v2.4)[cite: 2].
- `guide/PROGRESS_TRACKER.md` — Status pengerjaan menu & fitur terupdate[cite: 2].

---

## 2. Mandatory Rules for Code Generation

### A. DDD Architectural Principles (Strict Rule)
1. **No Role-Based Domains:** Dilarang keras membuat domain bernama `Admin`, `Client`, atau `Architect`. Domain HARUS murni berdasarkan Bounded Context logika bisnis[cite: 2]:
   - `Domains/Auth` (Autentikasi & Identitas)[cite: 2]
   - `Domains/SystemConfig` (Konfigurasi Aturan Bisnis & Kuota Global — termasuk backoffice panel Super Admin)[cite: 2]
   - `Domains/Project` (Manajemen Model 3D, Storage & Client Invitation)[cite: 2]
   - `Domains/Comment` (Spatial Pin Annotation & Feedback)[cite: 2]
   - `Domains/Chat` (Real-time Messaging Arsitek ↔ Klien per Project) 🆕[v2.4]
   - `Domains/Billing` (Transaksi, Subscription & Midtrans)[cite: 2]
2. **Primary Key UUID:** Selalu gunakan UUID untuk ID tabel database[cite: 2].
3. **Dynamic Quotas:** Selalu baca batasan kuota Free/Pro Tier dari `system_settings` via Redis Cache, bukan *hardcode* angka di controller[cite: 2].
4. **No Spaghetti Code:** Terapkan pemisahan tanggung jawab yang jelas (DTO, Action/Service, Repository)[cite: 2].

### B. Automatic Progress Tracking Requirement
- **TUGAS AI:** Setiap kali kamu selesai menulis, merefaktor, atau menguji suatu fungsi/menu/fitur hingga berhasil running tanpa error, kamu **WAJIB secara otomatis memperbarui file `guide/PROGRESS_TRACKER.md`**[cite: 2].
- Ubah status fitur terkait dari 🔴 **Pending** / 🟡 **In Progress** menjadi 🟢 **Completed**, serta catat tanggal dan ringkasan perubahannya pada tabel dan Activity Logs[cite: 2].

### C. Laravel Artifacts Naming & Location Rules
1. **Form Requests:**
   Wajib dibuat per-action/domain di dalam domain masing-masing, contoh[cite: 2]:
   - `app/Domains/Project/Requests/StoreProjectRequest.php`[cite: 2]
   - `app/Domains/Project/Requests/InviteClientRequest.php` 🆕
   - `app/Domains/Comment/Requests/StoreCommentRequest.php`[cite: 2]
   - `app/Domains/Chat/Requests/SendChatMessageRequest.php` 🆕
2. **Factories:**
   Ditaruh di `database/factories/` dengan suffix `Factory`, contoh[cite: 2]:
   - `database/factories/ProjectFactory.php`[cite: 2]
   - `database/factories/CommentFactory.php`[cite: 2]
   - `database/factories/SubscriptionFactory.php`[cite: 2]
   - `database/factories/ProjectClientFactory.php` 🆕
   - `database/factories/ChatMessageFactory.php` 🆕
3. **Seeders:**
   Ditaruh di `database/seeders/` untuk data master & pengujian, contoh[cite: 2]:
   - `database/seeders/SystemSettingSeeder.php` (Pengisian default kuota Free & Pro Tier)[cite: 2]
   - `database/seeders/DatabaseSeeder.php`[cite: 2]

### D. Automated Testing Strategy (Pest / PHPUnit & Vitest)
1. **Unit & Feature Testing (Backend):**
   - Gunakan **Pest PHP** untuk pengujian di Laravel[cite: 2].
   - Lokasi test disesuaikan dengan struktur domain[cite: 2]:
     - `tests/Unit/Domains/Project/CreateProjectTest.php`[cite: 2]
     - `tests/Feature/Domains/Billing/MidtransWebhookTest.php`[cite: 2]
     - `tests/Feature/Domains/Project/InviteClientTest.php` 🆕
     - `tests/Feature/Domains/Chat/SendChatMessageTest.php` 🆕
   - Setiap Action/Service di domain wajib memiliki *Unit Test* untuk menguji logika bisnis utama (termasuk validasi kuota & error handling)[cite: 2].
2. **Frontend Testing:**
   - Gunakan **Vitest** untuk pengujian komponen Vue/Nuxt dan composables[cite: 2].
3. **Trigger Post-MVP:**
   - Setelah semua fitur MVP berstatus 🟢 **Completed** di `PROGRESS_TRACKER.md`, AI akan menjalankan fase pembuatan unit test secara komprehensif berdasarkan instruksi di atas[cite: 2].

### E. Google OAuth & Token Handling Rules
1. **Frontend to Backend Flow:**
   - Frontend mengirimkan `id_token` dari Google OAuth / One-Tap ke API backend endpoint `/api/v1/auth/google`[cite: 2].
   - Backend memvalidasi `id_token` dan mengembalikan Laravel Sanctum Bearer Token[cite: 2].
2. **Environment Configuration:**
   - Variabel `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` wajib dibaca dari `.env`, dilarang keras menaruh token/kredensial asli di kode program[cite: 2].
3. **Mocking Strategy for Testing (Pest / PHPUnit):**
   - Saat menulis Feature/Unit Test untuk Auth Domain, AI WAJIB menggunakan **Mocking/Socialite::fake()** atau memalsukan verifikator ID Token[cite: 2].
   - Test TIDAK BOLEH melakukan real HTTP request ke server Google agar test dapat berjalan secara offline dan tidak gagal/error karena expired token[cite: 2].

### F. Client Revision Limit & Enforcement Rules
1. **Dynamic & Project-Level Revision Quota:**
   - AI wajib mengecek `projects.max_revisions_allowed` (serta batas default `free_tier_default_client_revisions` via Redis Cache, dipakai sebagai nilai awal saat project dibuat) sebelum mengizinkan client menambahkan pin komentar baru atau sebelum arsitek mengunggah versi `.glb` baru.
   - Perbandingan yang dipakai: `projects.current_revision_count >= projects.max_revisions_allowed` → blokir.
2. **Counter Increment Rule:**
   - `projects.current_revision_count` WAJIB di-increment oleh `Domains/Comment` setiap kali client membuat pin komentar **root baru** (bukan reply/thread, dan bukan komentar dari arsitek sendiri).
   - Kolom ini dibaca (bukan di-increment) oleh `Domains/Project` saat memvalidasi apakah arsitek boleh mengunggah versi `.glb` baru terkait revisi tersebut.
3. **Quota Enforcement Exception:**
   - Jika `current_revision_count` sudah melebihi/menyamai `max_revisions_allowed`, backend `Domains/Project` & `Domains/Comment` wajib melempar `403 RevisionLimitExceededException` dengan respons JSON:
     ```json
     {
       "status": "error",
       "message": "Batas kuota revisi untuk project ini telah tercapai. Silakan hubungi arsitek terkait untuk penambahan kuota."
     }
     ```
4. **Frontend Counter Badge Requirement:**
   - Komponen 3D Viewer di `features/viewer-3d` wajib menampilkan status counter revisi secara transparan, dihitung dari `current_revision_count` / `max_revisions_allowed` (contoh: *"Status Revisi: Versi 2 dari 3"*).
   - Jika kuota revisi habis, sistem harus mengunci (*disable*) form submission pin komentar bagi client dan menampilkan badge peringatan merah.

### G. Subscription & Billing Sync Rule
1. **Dual-Write Consistency:**
   - Saat `Domains/Billing` menerima webhook Midtrans dengan status `settlement`, Action `HandleWebhookAction` WAJIB menulis ke DUA tempat secara atomik (dalam satu DB transaction): tabel `subscriptions` (riwayat langganan) DAN kolom `users.subscription_status` (cache cepat untuk middleware).
   - Dilarang hanya meng-update salah satu — akan menyebabkan data tidak sinkron antara riwayat billing dan status akses real-time.

### H. Client Invitation & Access Control Rule 🆕 [v2.4]
1. **No Public Read-Only Access:** Model link publik terbuka (siapa saja yang pegang link bisa lihat) **DIHAPUS TOTAL**. Setiap request ke `p/{share_token}` maupun endpoint API terkait project (viewer data, comments, chat) WAJIB melalui `ProjectClientAccessMiddleware`.
2. **Invitation Flow:**
   - Arsitek meng-invite Klien via `InviteClientAction` (`Domains/Project`) dengan input email → membuat/update baris `project_clients` (status `pending`) → mengirim `ClientInvitationNotification` berisi link `/p/{share_token}`.
   - Saat Klien login (Google OAuth/email), `Domains/Auth` WAJIB menjalankan pencocokan: cari baris `project_clients` dengan `email` = email user yang login dan `project_id` sesuai konteks undangan → jika ketemu dan status `pending`, update jadi `accepted`, isi `user_id`, set `accepted_at`.
3. **Access Validation:** `ProjectClientAccessMiddleware` mengizinkan akses HANYA jika salah satu benar:
   - User yang login adalah `projects.user_id` (Arsitek pemilik), ATAU
   - Ada baris `project_clients` dengan `user_id` = user login, `project_id` sesuai, dan `status = 'accepted'`.
   - Selain itu → `403 Forbidden` dengan format JSON konsisten (gunakan pola yang sama seperti `RevisionLimitExceededException` di Section F).
4. **Revoke Access:** Arsitek dapat mengubah status `project_clients` menjadi `revoked` kapan saja via `RevokeClientAccessAction`; middleware WAJIB menolak akses begitu status bukan `accepted`.
5. **Dilarang Hardcode/Bypass:** Dilarang membuat endpoint atau flag apa pun yang melewati validasi email-matching ini (termasuk untuk kebutuhan testing/demo) — gunakan `Socialite::fake()` / factory state pada test, bukan bypass middleware production.
6. **Quota Enforcement:** Jumlah maksimum email yang bisa diundang per project dibatasi oleh `system_settings` (`free_tier_max_invited_clients` / `pro_tier_max_invited_clients`), dicek dengan pola yang sama seperti kuota project/file (baca dari Redis Cache, bukan hardcode).

### I. Real-time Chat Rule 🆕 [v2.4]
1. **Broadcasting Stack:** Gunakan `laravel/reverb` sebagai WebSocket server backend dan `laravel-echo` di frontend. Dilarang menambahkan provider broadcasting lain (Pusher Cloud, Ably, dll) tanpa persetujuan eksplisit — ini untuk menjaga stack tetap self-hosted sesuai `RTCF.md`.
2. **Private Channel Convention:** Setiap project punya channel privat dengan format `project.{project_id}.chat`. Otorisasi channel di `routes/channels.php` WAJIB memakai validasi yang identik dengan `ProjectClientAccessMiddleware` (Section H.3) — Arsitek pemilik project ATAU Klien `accepted`.
3. **Message Persistence:** Setiap pesan chat WAJIB disimpan ke `chat_messages` sebelum event `ChatMessageSent` di-broadcast (persist-then-broadcast, bukan broadcast-only/ephemeral).
4. **Access Parity with Comments:** Aturan siapa boleh mengirim pesan chat SAMA PERSIS dengan siapa boleh membuat pin comment (Section F) — jangan buat middleware/logic otorisasi terpisah yang bisa berbeda hasil dari Domain Comment/Project.
5. **No Chat Bypass of Revision Limit:** Chat TIDAK dihitung sebagai revisi (`current_revision_count` tidak ter-increment oleh chat message) — chat murni komunikasi teks, bukan pengajuan revisi formal.

### J. Role & Account Model Rule 🆕 [v2.4]
1. **No Role Selection at Registration:** Form Register / callback Google OAuth di `Domains/Auth` DILARANG memiliki input/field pemilihan role ("Saya Arsitek" / "Saya Klien"). Tabel `users` TIDAK memiliki kolom `role`.
2. **Architect = Default Capability:** Setiap akun yang berhasil register otomatis bisa membuat project (`Domains/Project`), dibatasi kuota Free Tier secara default. Tidak perlu approval atau upgrade role apa pun untuk mulai memakai kapabilitas Arsitek.
3. **Client = Derived Per-Project Status:** Status "Klien" TIDAK disimpan sebagai atribut pada `users`. Status ini selalu diturunkan (derived) dari keberadaan baris `project_clients` yang match dengan email user tsb, per project. Dilarang membuat kolom `users.is_client` atau semacamnya — akan menyebabkan duplikasi sumber kebenaran dengan `project_clients`.
4. **Dual-Capacity Supported Natively:** Karena Arsitek adalah kapabilitas default dan Klien adalah status per-project, satu akun otomatis bisa keduanya sekaligus tanpa logic tambahan — AI dilarang membuat validasi yang mencegah seorang Arsitek menerima undangan sebagai Klien di project Arsitek lain (atau sebaliknya).
5. **Super Admin Exception:** `super_admin` adalah SATU-SATUNYA role eksplisit via `spatie/laravel-permission`. Role ini TIDAK PERNAH di-assign melalui endpoint publik (register/OAuth) — hanya melalui `database/seeders/` atau Artisan command internal yang dijalankan manual oleh tim.

---

## 3. Workflow Procedure for AI
1. Periksa `guide/PROGRESS_TRACKER.md` untuk melihat fitur mana yang perlu dikerjakan selanjutnya[cite: 2].
2. Baca skema tabel terkait di `guide/DATABASE_SCHEMA.md`[cite: 2].
3. Tulis kode sesuai aturan DDD dan konvensi project (termasuk Section H/I/J jika menyentuh Auth, Project, atau Chat)[cite: 2].
4. Update `guide/PROGRESS_TRACKER.md` begitu pekerjaan selesai[cite: 2].
