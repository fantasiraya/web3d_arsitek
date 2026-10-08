> **v2.7 Note:** Ditambahkan alur L (Profil Arsitek & Wilayah Indonesia) dan alur M (Drawing 2D — Rencana). Update route structure: tambah `/region/*`, `/rab/mappings`, `/rab/price-items/{id}/components`, `/rab/price-items/{id}/overhead`.
>
> **v2.6 Note:** Ditambahkan alur K (RAB & Lembar Kerja) dan route RAB.
>
> **v2.5 Note:** File ini diperbarui agar mencerminkan kondisi aplikasi **PitchArch** yang aktual. Perubahan dari v2.4: Laravel 13 → **Laravel 12**, Nuxt 3 → **Inertia.js + Vue 3** (monorepo), PostgreSQL → **MySQL**, Sanctum → **Laravel Fortify**, Cloudflare R2 → **local disk `public`**, queue Redis → **database driver**, tambah Passkeys & Email Verification.

[ROLE]
Bertindaklah sebagai Senior Full-Stack Developer & Software Architect spesialis Laravel 12 (DDD) dan Inertia.js + Vue 3.

[TASK]
Buatkan rancangan arsitektur teknis dan panduan integrasi sistem untuk platform SaaS Web 3D Arsitek **PitchArch** berdasarkan PRD v2.5.

[CONTEXT]
1. Platform diperuntukkan bagi arsitek untuk mempresentasikan model 3D ke klien terundang (bukan publik), mengumpulkan pin komentar 3D, dan berkomunikasi via chat real-time.

2. **Model Akun & Role:**
   - Tidak ada pilihan role saat registrasi. Setiap akun otomatis memiliki kapabilitas Arsitek (boleh membuat project).
   - Status "Klien" bersifat per-project, ditentukan oleh kecocokan email pada `project_clients`.
   - Satu akun bisa dual-capacity (Arsitek + Klien sekaligus).
   - Hanya `super_admin` yang merupakan role eksplisit (Spatie), di-assign manual/internal.

3. **Backend Tech Stack (AKTUAL):**
   - Framework: **Laravel 12** (arsitektur Domain-Driven Design / DDD, domain murni berbasis bounded context: `Auth`, `SystemConfig`, `Project`, `Comment`, `Chat`, `Billing` — bukan berbasis role).
   - Auth: **Laravel Fortify** (features: registration, resetPasswords, emailVerification, twoFactor, passkeys) + **laravel/socialite** (Google OAuth redirect-based flow).
   - Email Verification: `User` model implements `MustVerifyEmail`. Google OAuth otomatis set `email_verified_at`.
   - Database: **MySQL** (bukan PostgreSQL).
   - Storage: **Laravel `public` disk lokal** (`storage/app/public/`) — bukan Cloudflare R2.
   - Queue: **database driver** (`QUEUE_CONNECTION=database`) — bukan Redis.
   - Real-time: **Laravel Reverb** (WebSocket server self-hosted) untuk broadcasting chat & event, private channel per project.
   - Packages: `laravel/socialite`, `spatie/laravel-permission`, `midtrans/midtrans-php`, `laravel/reverb`.

4. **Frontend Tech Stack (AKTUAL):**
   - Framework: **Inertia.js + Vue 3** (monorepo Laravel, bukan Nuxt 3 terpisah).
   - Build Tool: **Vite**.
   - CSS: **Tailwind CSS v4**.
   - UI Components: **shadcn/ui** (Vue port via `components.json`).
   - Routing: **Inertia.js** (bukan Nuxt file-based routing) — routes didefinisikan di `routes/web.php`.
   - 3D Engine: **Three.js** (hanya untuk authenticated 3D Viewer di dalam aplikasi — bukan di landing publik).
   - Motion & Scrollytelling: **GSAP + ScrollTrigger** untuk Apple-style pinned sections (tanpa WebGL di landing page).
   - Real-time Client: **laravel-echo** (adapter Reverb) untuk konsumsi WebSocket chat (`lib/echo.ts`).
   - Layouts: `LandingLayout.vue` (landing + auth), `AdminLayout.vue` (admin panel), `UnifiedLayout.vue` (settings).

5. **Fitur Utama & Aturan Bisnis:**
   - Google OAuth / redirect-based flow via Socialite → `GoogleAuthController` → Fortify session.
   - Dynamic System Settings (`system_settings`) untuk batas kuota Free & Pro Tier, dibaca dari database.
   - **Client Invitation by Email**: Arsitek meng-assign email Klien ke project (`project_clients`); tidak ada link publik read-only. Klien wajib login & lolos validasi email-matching.
   - **In-App Real-time Chat**: percakapan umum Arsitek↔Klien per project via Reverb, terpisah dari pin comment spasial.
   - SaaS Billing dengan Midtrans Snap + Webhook Handler.
   - Background Queue (database driver) untuk Draco Compression via `gltf-pipeline`.
   - Client Revision Limit dinamis per project (`max_revisions_allowed` & `current_revision_count`) dengan gatekeeper 403.
   - **Apple-Style Scrollytelling Landing Page**: estetika Apple Pro (obsidian `#000000`, frosted glass, display typography bold, bento grid) tanpa WebGL demi FCP < 1.0s.
   - **Camera View Presets**: simpan/load angle kamera penting di Viewer.
   - **Admin Panel** (`/admin/*`): dashboard, users, projects, plans, transactions, audit logs.

[FORMAT]
Sajikan output secara terstruktur dalam format Markdown:

1. **Alur Arsitektur Sistem:**

   **A. Authentication Flow (Fortify + Socialite, tanpa role picker)**
   - Email/Password: Form Register/Login → Fortify action (`CreateNewUser`) → session → Inertia redirect ke `/dashboard`.
   - Email Verification: Setelah register, user diarahkan ke `/verify-email` (`VerifyEmail.vue`). Email verifikasi dikirim via `noreply@pitcharch.id`. Google OAuth auto-verify `email_verified_at`.
   - Google OAuth: `/auth/google/redirect` → Socialite → Google callback → `/auth/google/callback` → `GoogleAuthController` → `MatchInvitedClientEmailAction` (cek `project_clients` pending) → session → Inertia redirect.
   - 2FA: Fortify built-in, `TwoFactorChallenge.vue`.
   - Passkeys: Fortify + `PasskeyUser` interface di `User` model.

   **B. Client Invitation & Access Validation Flow**
   - Arsitek invite email Klien → `InviteClientAction` → insert `project_clients` (status `pending`) → `ClientInvitationNotification` (email dari `noreply@pitcharch.id`).
   - Klien buka link `/p/{share_token}` → wajib login → `MatchInvitedClientEmailAction` cocokkan email → jika match & pending → update `accepted`, isi `user_id`, set `accepted_at` → masuk Viewer.
   - `ProjectClientAccessMiddleware`: izinkan jika user = owner project ATAU `project_clients.status = 'accepted'`; selain itu `403 Forbidden`.

   **C. Real-time Chat Flow (Reverb)**
   - `ChatController@store` → simpan ke `chat_messages` → broadcast `ChatMessageSent` ke private channel `project.{project_id}.chat`.
   - Channel authorization di `routes/channels.php` memakai validasi identik dengan `ProjectClientAccessMiddleware`.
   - Frontend: `lib/echo.ts` init Echo → `.private('project.{id}.chat').listen('ChatMessageSent', ...)`.

   **D. Payment Webhook Flow (Midtrans)**
   - User klik upgrade → `CheckoutController` → `CreateSnapTokenAction` → Snap token → Midtrans Snap pop-up.
   - Midtrans kirim webhook `settlement` → `HandleWebhookAction` → dual-write: update `subscriptions` + `users.subscription_status = 'pro'` dalam satu DB transaction.

   **E. Quota Validation**
   - Setiap `CreateProjectAction` baca `system_settings` (key: `free_tier_max_projects` / `pro_tier_max_projects`) + cek `user_plan_overrides` → hitung effective limit.
   - Formula: `Override ?? Plan Limit ?? 1`. Nilai `null` = Unlimited.

   **F. Revision Limit Gatekeeper**
   - `RevisionLimitEnforcementMiddleware`: cek `projects.current_revision_count >= max_revisions_allowed` → `403 RevisionLimitExceededException`.
   - Counter di-increment oleh `Domains/Comment` setiap client membuat pin komentar root baru.

   **G. File Upload & Draco Queue**
   - Upload `.glb`/`.gltf` → `UploadProjectFileAction` → simpan ke `storage/app/public/projects/{uuid}/` → insert `project_versions` → dispatch `DracoCompressionJob` ke queue (database driver).
   - `DracoCompressionJob`: jalankan `gltf-pipeline` Node.js subprocess → update `project_versions.is_draco_compressed = true`.
   - Queue worker: `php artisan queue:work`.

   **H. Storage (Local Disk)**
   - Semua file disimpan di `storage/app/public/` (symlink ke `public/storage/` via `php artisan storage:link`).
   - URL diakses via `Storage::url($path)` → `/storage/projects/...`.
   - Saat project dihapus, file dihapus dari disk secara otomatis.

   **I. 3D Viewer (Inertia Page)**
   - Route `/projects/{project}/viewer` → `ViewerController@show` → return Inertia page `Project/Viewer.vue` dengan props: project data, comments, camera_presets, clients.
   - `Viewer.vue` load file `.glb` via Three.js `GLTFLoader`/`DRACOLoader` dari `/storage/...`.
   - Raycaster deteksi hit → koordinat X/Y/Z + Normal → tampilkan form pin → `POST /projects/{project}/comments`.

   **J. Apple Scrollytelling Landing Page (Inertia + GSAP)**
   - Route `/` → `WelcomeController` → return Inertia page `Welcome.vue` dengan `LandingLayout`.
   - `Welcome.vue` compose semua section components (`HeroSection`, `ScrollyExperienceSection`, dll.).
   - GSAP ScrollTrigger di-init dalam `onMounted()` setiap section component.
   - Tidak ada Three.js/WebGL di halaman ini. Semua animasi: CSS transforms + GSAP.

   **K. RAB & Lembar Kerja (`Domains/Rab`)**
   - Gating: `RabAccessService` cek `plans.can_use_rab`; pemilik project saja yang boleh membuat/mengedit.
   - Fase A: harga satuan + template → `CreateRabDocumentAction` (salin item template, snapshot harga) → `UpsertRabItemAction` → `RecalculateRabAction`.
   - AHSP: `rab_price_item_components` (tenaga/bahan/peralatan) → `RecalculatePriceItemAction` (koefisien × unit_price + overhead_percent → `rab_price_items.unit_price`). `has_components = true` → lock manual input harga.
   - Fase B: upload CSV/XLSX via `TakeoffParserService` → `ImportQuantityTakeoffAction` → pencocokan `rab_mappings` via `RabMappingService` (exact/wildcard/material) → review item belum dipetakan. Wizard 3-step di `Rab/Import.vue`.
   - Fase C: `useGlbQuantities.ts` hitung luas/volume di Three.js, baca `mesh.userData` custom properties Blender (`unit_price`, `unit`, `category`, `section`, `quantity_basis`) → `ApplyGlbQuantitiesAction` → item `is_estimate = true`. Halaman `GlbEstimator.vue`.
   - Export: `ExportRabAction` → XLSX multi-sheet (Rekap, Detail RAB, AHSP per item). Kop surat dari profil arsitek (`company_name`, `address`, `city_name`, `postal_code`, `phone`).
   - Mapping: `RabMappingController`, route `/rab/mappings`, halaman `Rab/Mappings.vue`. Tipe: `exact`, `name_pattern`, `material`.
   - Switch `is_visible_to_clients`: Klien `accepted` melihat RAB read-only bila menyala. Tombol RAB di Viewer (hijau, muncul jika ada dokumen visible).

   **L. Profil Arsitek & Wilayah Indonesia**
   - `ProfileController` diperbarui untuk simpan field badan usaha + alamat wilayah ke `users`.
   - `IndonesiaRegionController` serve route `/region/provinces|cities|districts|villages` (tanpa auth middleware) untuk cascading dropdown AJAX.
   - `laravolt/indonesia`: tabel `indonesia_provinces`, `indonesia_cities`, `indonesia_districts`, `indonesia_villages` — sudah di-seed.
   - Nama wilayah di-denormalize ke kolom `*_name` di `users`. Jangan join tabel wilayah saat baca profil.
   - `HandleInertiaRequests` di-update untuk share semua field profil baru ke frontend.
   - `AddressInput.vue` + `useIndonesiaRegion.ts` menangani cascading dropdown di `Settings/Profile.vue`.

   **M. Drawing 2D — Rencana *(Belum Diimplementasikan)***
   - Domain baru `Domains/Drawing`, tabel `drawing_sheets` (plan/elevation/section) + `drawing_elements`.
   - Editor frontend: **konva.js** (canvas 2D). Export: DXF (`nzcreations/dxf`) + SVG.
   - Gating: kolom `plans.can_use_drawing = true`, hanya Enterprise.
   - Library simbol: pintu, jendela, kolom, tangga (pre-built SVG di konva).
   - Status: **🔴 Pending** — tunggu perintah implementasi.

2. **Struktur Folder (lihat `FOLDER_STRUCTURE.md` untuk detail lengkap)**
   - Monorepo: `app/Domains/`, `app/Http/`, `resources/js/pages/`, `resources/js/components/`, `resources/js/layouts/`.
   - Routes: `routes/web.php`, `routes/admin.php`, `routes/settings.php`, `routes/channels.php`.

3. **Route Structure Aktual**

   ```
   # Landing & Public
   GET  /                          → WelcomeController@index            (Welcome.vue)
   GET  /showcase                  → ShowcaseDemoController             (ShowcaseDemo.vue)
   GET  /plans                     → PlansController@index              (Plans.vue)

   # Auth (Fortify handles most automatically)
   GET  /login                     → Fortify                            (auth/Login.vue)
   GET  /register                  → Fortify                            (auth/Register.vue)
   GET  /forgot-password           → Fortify                            (auth/ForgotPassword.vue)
   POST /reset-password            → Fortify                            (auth/ResetPassword.vue)
   GET  /verify-email              → Fortify                            (auth/VerifyEmail.vue)
   GET  /two-factor-challenge      → Fortify                            (auth/TwoFactorChallenge.vue)
   GET  /auth/google/redirect      → GoogleAuthController@redirect
   GET  /auth/google/callback      → GoogleAuthController@callback

   # Dashboard & Projects (auth required)
   GET  /dashboard                 → DashboardController@index          (Dashboard.vue)
   GET  /projects                  → ProjectsPageController@index       (Projects page)
   POST /projects                  → ProjectController@store
   GET  /projects/{project}/edit   → ProjectController@edit
   PUT  /projects/{project}        → ProjectController@update
   DELETE /projects/{project}      → ProjectController@destroy
   GET  /projects/{project}/viewer → ViewerController@show             (Project/Viewer.vue)
   POST /projects/{project}/comments        → CommentController@store
   PATCH /projects/{project}/comments/{id} → CommentController@update
   DELETE /projects/{project}/comments/{id}→ CommentController@destroy
   POST /projects/{project}/clients        → InviteClientAction
   DELETE /projects/{project}/clients/{id} → RevokeClientAccessAction
   GET|POST /projects/{project}/camera-presets    → CameraPresetController
   DELETE /projects/{project}/camera-presets/{id} → CameraPresetController@destroy

   # Chat (Reverb)
   GET  /projects/{project}/chat   → ChatController@index
   POST /projects/{project}/chat   → ChatController@store

   # Billing
   GET  /billing                   → BillingController@index            (Billing/Index.vue)
   GET  /checkout                  → CheckoutController@index           (Checkout/Index.vue)
   POST /checkout/snap-token       → CheckoutController@snapToken
   POST /billing/webhook           → HandleWebhookAction (no auth)

   # Settings (routes/settings.php)
   GET  /settings/profile          → ProfileController@edit             (Settings/Profile.vue)
   PUT  /settings/profile          → ProfileController@update
   GET  /settings/password         → (Fortify)                          (Settings/Password.vue)
   GET  /settings/appearance       → (Appearance controller)            (Settings/Appearance.vue)

   # RAB & Lembar Kerja (pemilik + can_use_rab; klien read-only bila is_visible_to_clients)
   GET    /projects/{project}/rab                    → RabDocumentController@index
   POST   /projects/{project}/rab                    → RabDocumentController@store
   GET    /projects/{project}/rab/{rab}              → RabDocumentController@show
   PUT    /projects/{project}/rab/{rab}              → RabDocumentController@update
   PATCH  /projects/{project}/rab/{rab}/visibility   → RabDocumentController@toggleVisibility
   POST   /projects/{project}/rab/{rab}/finalize     → RabDocumentController@finalize
   POST   /projects/{project}/rab/{rab}/reopen       → RabDocumentController@reopen
   POST|PUT|DELETE /projects/{project}/rab/{rab}/items[/{item}] → RabItemController
   POST   /projects/{project}/rab/import             → RabDocumentController@import   (Fase B)
   POST   /projects/{project}/rab/from-glb           → RabDocumentController@fromGlb  (Fase C)
   GET    /projects/{project}/rab/{rab}/export       → RabDocumentController@export
   GET|POST|PUT|DELETE /rab/price-items              → RabPriceItemController
   GET|POST|PUT|DELETE /rab/templates                → RabTemplateController
   GET|POST|DELETE     /rab/mappings                 → RabMappingController

   # Admin Panel (routes/admin.php — guard: EnsureSuperAdmin)
   GET  /admin/dashboard           → Admin\DashboardController
   GET  /admin/users               → Admin\UsersController
   GET  /admin/projects            → Admin\ProjectsController
   GET  /admin/plans               → Admin\PlansController
   GET  /admin/transactions        → Admin\TransactionsController
   GET  /admin/audit-logs          → Admin\AuditLogController
   ```
