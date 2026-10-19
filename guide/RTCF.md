> **v3.1 Note:** Ditambahkan alur Q (Architectural Working Booklet PDF & Key Plan Canvas), alur R (Bidirectional 2D/3D Code System), alur S (Direct AI Render Generation), alur T (Worksheet to Per-Room RAB Generator), dan alur U (Interactive 3D Project Cards). Update route structure: tambah `/drawings*`, `/projects/{project}/booklet`, `/projects/{project}/captures/{capture}/ai-render*`, `/projects/{project}/captures/{capture}/generate-ai`, dan `/projects/{project}/rab/from-worksheet`.
>
> **v3.0 Note:** Alur M (Drawing 2D) telah selesai diimplementasikan. Integrasi booklet fields dan upload render AI pada `project_captures`.
>
> **v2.9 Note:** Ditambahkan alur O (AI Vision Studio, Paid-Only gating, local persistence) dan alur P (Redis Multi-DB Caching & High Concurrency Control). Update route structure: tambah `/vision-studio*`.
>
> **v2.8 Note:** Ditambahkan alur N (Pencahayaan Matahari Real-Time SunCalc & 3D Snapshot Captures). Update route structure: tambah `/projects/{project}/captures*` dan `/projects/{project}/sun-settings`.
>
> **v2.7 Note:** Ditambahkan alur L (Profil Arsitek & Wilayah Indonesia). Update route structure: tambah `/region/*`, `/rab/mappings`, `/rab/price-items/{id}/components`, `/rab/price-items/{id}/overhead`.
>
> **v2.6 Note:** Ditambahkan alur K (RAB & Lembar Kerja) dan route RAB.
>
> **v2.5 Note:** File ini diperbarui agar mencerminkan kondisi aplikasi **PitchArch** yang aktual. Perubahan dari v2.4: Laravel 13 → **Laravel 12**, Nuxt 3 → **Inertia.js + Vue 3** (monorepo), PostgreSQL → **MySQL**, Sanctum → **Laravel Fortify**, Cloudflare R2 → **local disk `public`**, queue Redis → **database driver**, tambah Passkeys & Email Verification.

[ROLE]
Bertindaklah sebagai Senior Full-Stack Developer & Software Architect spesialis Laravel 12 (DDD) dan Inertia.js + Vue 3.

[TASK]
Buatkan rancangan arsitektur teknis dan panduan integrasi sistem untuk platform SaaS Web 3D Arsitek **PitchArch** berdasarkan PRD v3.1.

[CONTEXT]
1. Platform diperuntukkan bagi arsitek untuk mempresentasikan model 3D ke klien terundang (bukan publik), mengumpulkan pin komentar 3D, dan berkomunikasi via chat real-time.

2. **Model Akun & Role:**
   - Tidak ada pilihan role saat registrasi. Setiap akun otomatis memiliki kapabilitas Arsitek (boleh membuat project).
   - Status "Klien" bersifat per-project, ditentukan oleh kecocokan email pada `project_clients`.
   - Satu akun bisa dual-capacity (Arsitek + Klien sekaligus).
   - Hanya `super_admin` yang merupakan role eksplisit (Spatie), di-assign manual/internal.

3. **Backend Tech Stack (AKTUAL):**
   - Framework: **Laravel 12** (arsitektur Domain-Driven Design / DDD, domain murni berbasis bounded context: `Auth`, `SystemConfig`, `Project`, `Comment`, `Chat`, `Billing`, `Rab`, `VisionStudio` — bukan berbasis role).
   - Auth: **Laravel Fortify** (features: registration, resetPasswords, emailVerification, twoFactor, passkeys) + **laravel/socialite** (Google OAuth redirect-based flow).
   - Email Verification: `User` model implements `MustVerifyEmail`. Google OAuth otomatis set `email_verified_at`.
   - Database: **MySQL** (bukan PostgreSQL).
   - Cache Driver: **Redis** (`CACHE_STORE=redis`, DB 1 cache aplikasi via `CacheKeys.php` safe tagging, DB 2 session pengguna).
   - Concurrency Control: **Redis Distributed Atomic Locks** (`Cache::lock()->block()`) & Database Transactions dengan `lockForUpdate()`.
   - Storage: **Laravel `public` disk lokal** (`storage/app/public/`) — bukan Cloudflare R2.
   - Queue: **database driver** (`QUEUE_CONNECTION=database`) — bukan Redis.
   - Real-time: **Laravel Reverb** (WebSocket server self-hosted) untuk broadcasting chat & event, private channel per project.
   - Packages: `laravel/socialite`, `spatie/laravel-permission`, `midtrans/midtrans-php`, `laravel/reverb`, `league/csv`, `phpoffice/phpspreadsheet`, `laravolt/indonesia`.

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

   **M. Drawing 2D — Editor CAD Konva.js (`Domains/Drawing` — 🟢 Selesai)**
   - Domain `Domains/Drawing`, tabel `drawing_sheets` (plan/elevation/section, paper_size, width, height, scale, sort_order) + `drawing_elements`.
   - Editor frontend: **konva.js** (canvas 2D vektor hardware-accelerated).
   - Multi-paper frame canvas: tambah kertas (`+ Kertas ke Kanvas`) dan hapus lembar independen.
   - Mode ganda: Paper Space (A0–A4 portrait/landscape & custom) vs AutoCAD Model Space (∞).
   - Figma-style edge/corner live resize handle tanpa destroy Konva node + instant `localStorage` cache.
   - Kop Gambar (Title Block) auto word-wrap dengan tinggi adaptif & layer isolation (`paperBgLayer` → `gridLayer` → `paperLayer`).
   - Clean PDF export & direct browser print via hidden isolated iframe beresolusi tinggi tanpa bleed UI editor.
   - Export format: DXF (AutoCAD R12 ASCII compliant via `ExportDxfAction`), SVG scalable (`ExportSvgAction`), dan PNG resolusi tinggi.
   - Gating: kolom `plans.can_use_drawing = true`, khusus tier Enterprise & Super Admin.

   **N. Simulasi Pencahayaan Matahari Real-Time & 3D Snapshot Captures (`Domains/Project`)**
   - Perhitungan sudut azimuth & altitude matahari via library `SunCalc` di `useSunSimulation.ts`.
   - Orientasi arah utara (`north_angle` 0°–360°) merotasi vektor posisi `DirectionalLight` dalam ruang 3D Three.js.
   - Interpolasi warna dinamis: langit (`scene.background`), cahaya matahari (`directionalLight.color`), dan ambient light (`ambientLight.color`).
   - Night Mode otomatis (altitude < 0 atau slider malam): matahari mati (`intensity = 0`), ambient redup (`#08081a`), dan mesh berprefix `LAMPU_*` / `lampu_*` menyala (emissive material + point lights).
   - Preset: Pagi (08:00), Siang (12:00), Sore (17:00), Malam (20:00), plus slider scrub interaktif (00:00–23:59).
   - Pengaturan lokasi & arah utara per-project hanya dapat disimpan oleh Arsitek (`UpdateProjectSunSettingsAction`). Default lokasi di-fallback ke koordinat Indonesia (Jakarta: -6.2088, 106.8456).
   - Snapshot Captures: Three.js renderer dikonfigurasi `preserveDrawingBuffer: true` dan shadow map soft. Snapshot dapat di-export sebagai PNG lokal atau disimpan ke server (`SaveCaptureAction`).
   - Quota limit dihitung per gambar (`SubscriptionLimitService::canCreateCapture`): Free tier 5 gambar, Pro tier 50 gambar.
   - Galeri: Drawer di `Viewer.vue` untuk filter per-ruangan & jam, unduh PNG, hapus (arsitek), dan toggle visibilitas ke klien (`is_visible_to_clients`).

   **O. AI Vision Studio (`Domains/VisionStudio`)**
   - Gating Berbayar: `SubscriptionLimitService::canAccessVisionStudio($user)` dipadukan dengan middleware `CheckVisionStudioAccess` (`vision.access`). Pengguna Free dilarang masuk dan diarahkan ke `/plans`.
   - Arsitektur Terpisah: Media kreasi disimpan di tabel `vision_creations`, tidak pernah dicampur dengan capture 3D proyek dan tidak memiliki fitur publish ke klien.
   - Persistensi LocalStorage: State studio dan history kreasi disimpan pada browser `localStorage` agar tidak hilang saat refresh halaman.
   - Cleanup File: Model `VisionCreation` otomatis membersihkan file fisik di storage saat dihapus.

   **P. Redis Multi-DB Caching & High Concurrency Control (1 Juta Pengguna)**
   - Multi-Database Separation: Redis DB 1 dialokasikan untuk cache, Redis DB 2 dialokasikan untuk session pengguna.
   - Safe Cache Tagging: `CacheKeys::tags(['plans'])` membungkus pemanggilan cache ber-tag agar portabel ke berbagai cache driver.
   - Eliminasi N+1: Penghapusan global model event listener `User::retrieved` di `AppServiceProvider.php` dan penerapan eager loading di seluruh listing controller.
   - Distributed Atomic Locking: Mutasi kuota kritis dilindungi dengan `Cache::lock()->block()`, dan webhook pembayaran Midtrans diisolasi dengan `lockForUpdate()`.

   **Q. Architectural Working Booklet PDF & Key Plan Canvas (`Domains/Drawing` & `Domains/Project`)**
   - Halaman builder booklet presentasi standar studio arsitektur landscape ISO (A3/A4) di `Booklet.vue`.
   - Komposisi multi-halaman: Halaman 1 Cover dengan watermark arsitek, Halaman 2 Master Plan Denah 2D rasio 80/20, Halaman 3+ Visual 3D/AI 68% + catatan + Key Plan locator + Kop Gambar 20%.
   - Mini denah SVG otomatis (`KeyPlanCanvas.vue`): menghitung bounding box dari elemen denah dan menyorot ruangan aktif dengan **warna oranye menyala (`#EA580C`)**.
   - Dedicated print engine CSS `@page { size: A3 landscape; margin: 0; }` untuk 1-click cetak dan simpan PDF presisi.

   **R. Bidirectional 2D ↔ 3D Reference & Code System (`Domains/Drawing` & `Domains/Project`)**
   - Kode capture otomatis (`EXT-01`, `R-01`) dengan input kustom dan auto-save on blur di Lightbox modal.
   - Filter kategori kode pada drawer galeri 3D (`R-*`, `EXT-*`, `Kustom`).
   - Live Combobox Inspector di CAD Editor 2D (`Drawing/Editor.vue`) untuk menautkan snapshot visual ke elemen label ruangan, dilengkapi badge status "Sedang Dipakai" atau "Sudah Digunakan di [Ruangan]".
   - Tampilan badge kamera oranye `📷 R-01` langsung di atas label ruangan pada kanvas Konva.

   **S. Direct AI Render Generation from 3D Captures (`Domains/Project`)**
   - Action `GenerateAiRenderFromCaptureAction` mentransformasikan snapshot 3D WebGL menjadi render fotorealistik arsitektural menggunakan Google Gemini API.
   - Batch AI render generator (`POST /projects/{project}/captures/generate-all-ai`) untuk memproses seluruh ruangan proyek sekaligus.
   - Mood switcher dan prompt kontekstual otomatis dengan ketaatan 100% pada geometri referensi 3D.
   - Toggle mode "Render AI" vs "3D Asli" di galeri dan booklet.

   **T. Generator Draf RAB Per Ruangan dari Lembar Kerja (`Domains/Rab`)**
   - Action `GenerateRabFromWorksheetAction` (`POST /projects/{project}/rab/from-worksheet`) membaca elemen denah dan booklet.
   - Otomatis mengelompokkan item RAB per `section = Nama Ruangan` (`TERAS`, `KASIR`, `AREA VIP`, dll.).
   - Memetakan ke harga satuan arsitek, memberi label `is_estimate = true`, dan mengkalkulasi ulang subtotal, overhead, serta PPN melalui `RecalculateRabAction`.

   **U. Interactive 3D Project Cards (`ProjectCard.vue` & `Project3DPreview.vue`)**
   - Kartu proyek pada halaman `/dashboard` dan `/projects` terintegrasi dengan WebGL mini viewport.
   - Auto-rotation halus, orbit control mouse interaktif, ambient & directional lighting, fallback graceful ke thumbnail 2D.
   - Menampilkan informasi klien proyek (`client_name`, `client_address`).

2. **Struktur Folder (lihat `FOLDER_STRUCTURE.md` untuk detail lengkap)**
   - Monorepo: `app/Domains/`, `app/Http/`, `app/Support/`, `resources/js/pages/`, `resources/js/components/`, `resources/js/layouts/`.
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

   # Captures & Sun Settings (auth, project.access)
   GET|POST /projects/{project}/captures          → ProjectCaptureController@index|store
   DELETE   /projects/{project}/captures/{capture}→ ProjectCaptureController@destroy
   PATCH    /projects/{project}/captures/{capture}/visibility → ProjectCaptureController@toggleVisibility
   PATCH    /projects/{project}/captures/{capture}/prompt     → ProjectCaptureController@updatePrompt
   POST     /projects/{project}/captures/generate-prompt      → ProjectCaptureController@generatePrompt
   POST     /projects/{project}/captures/{capture}/ai-render  → ProjectCaptureController@uploadAiRender
   POST     /projects/{project}/captures/{capture}/generate-ai→ ProjectCaptureController@generateAiRender
   DELETE   /projects/{project}/captures/{capture}/ai-render  → ProjectCaptureController@deleteAiRender
   PATCH    /projects/{project}/sun-settings       → ProjectCaptureController@updateSunSettings

   # Drawing 2D & Booklet (auth, verified, enterprise gating: plans.can_use_drawing)
   GET      /drawings                              → DrawingSheetController@index        (Drawing/Index.vue)
   POST     /drawings                              → DrawingSheetController@store
   GET      /drawings/{sheet}                      → DrawingSheetController@show         (Drawing/Editor.vue)
   PATCH    /drawings/{sheet}                      → DrawingSheetController@update
   DELETE   /drawings/{sheet}                      → DrawingSheetController@destroy
   POST     /drawings/{sheet}/sync                 → DrawingElementController@sync
   POST     /drawings/{sheet}/sync-captures        → DrawingSheetController@syncCaptures
   GET      /drawings/{sheet}/export-dxf           → DrawingSheetController@exportDxf
   GET|POST /drawings/{sheet}/export-svg           → DrawingSheetController@exportSvg
   GET      /projects/{project}/booklet            → BookletController@show              (Drawing/Booklet.vue)

   # AI Vision Studio (auth, vision.access — khusus berbayar)
   GET    /vision-studio                          → VisionStudioController@index (VisionStudio/Index.vue)
   POST   /vision-studio/generate                 → VisionStudioController@generate
   GET    /vision-studio/load/{creation}          → VisionStudioController@loadStudio
   DELETE /vision-studio/creations/{creation}     → VisionStudioController@destroy

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
   POST   /projects/{project}/rab/from-worksheet     → RabDocumentController@generateFromWorksheet
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
