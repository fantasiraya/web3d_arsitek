# 📁 PitchArch — Directory & Folder Structure

Dokumen ini adalah acuan resmi struktur folder untuk aplikasi **PitchArch**: monorepo Laravel 12 + Inertia.js + Vue 3 (bukan backend/frontend terpisah).

> **v2.7 Changelog:** Menambahkan file-file baru modul AHSP (`RabPriceItemComponent.php`, `RecalculatePriceItemAction.php`, `RabPriceItemComponentController.php`), Fase B/C RAB (`ImportQuantityTakeoffAction.php`, `ApplyGlbQuantitiesAction.php`, `TakeoffParserService.php`, `RabMappingService.php`), Export (`ExportRabAction.php`), Mapping RAB (`RabMappingController.php`), profil arsitek (`IndonesiaRegionController.php`, `useIndonesiaRegion.ts`, `AddressInput.vue`), halaman baru Rab (`GlbEstimator.vue`, `Import.vue`, `Mappings.vue`, `PriceItemComponents.vue`, `TemplateShow.vue`), composable `useGlbQuantities.ts`. Tambah rencana folder `Domains/Drawing/` (belum dibuat). Update routes baru.
>
> **v2.6 Changelog:** Menambahkan `app/Domains/Rab/` dan `resources/js/pages/Rab/` (modul RAB & Lembar Kerja).
>
> **v2.5 Changelog:** Diperbarui dari struktur fiktif `backend/` + `frontend/` terpisah ke struktur **monorepo Laravel Inertia** yang aktual. Mengganti referensi Nuxt 3 → Inertia/Vue 3, PostgreSQL → MySQL, Cloudflare R2 → local disk public, Sanctum → Fortify. Menambahkan `camera_presets`, `Events/`, `Middleware/` aktual, layout Inertia (`LandingLayout`, `AdminLayout`, `UnifiedLayout`), dan folder `routes/` aktual.

---

```text
web3d_arsitek/                  # Root monorepo Laravel 12 + Inertia.js + Vue 3
│
├── .github/                    # CI/CD Workflows (GitHub Actions)
│   └── workflows/tests.yml
│
├── guide/                      # 📂 FOLDER PANDUAN & KOORDINASI AI
│   ├── AI_INSTRUCTIONS.md      # System prompt & aturan wajib AI Coding
│   ├── PRD.md                  # Product Requirement Document (v2.5)
│   ├── RTCF.md                 # Arsitektur teknis & stack spec
│   ├── RISE.md                 # Framework draf prompt & tugas
│   ├── DATABASE_SCHEMA.md      # Dokumentasi tabel, kolom, UUID, ERD (v2.5)
│   ├── PROGRESS_TRACKER.md     # Log & status pengerjaan fitur
│   └── FOLDER_STRUCTURE.md     # Referensi peta direktori project ini (file ini)
│
├── app/                        # 🐘 LARAVEL 12 APPLICATION LAYER
│   │
│   ├── Actions/                # Application-level actions (di luar domain)
│   │   ├── Auth/
│   │   │   └── MatchInvitedClientEmailAction.php   # Cocokkan email login dengan project_clients
│   │   └── Fortify/
│   │       ├── CreateNewUser.php                    # Registrasi user baru via Fortify
│   │       └── ResetUserPassword.php                # Reset password via Fortify
│   │
│   ├── Concerns/               # Shared traits / PHP Concerns
│   │   ├── PasswordValidationRules.php
│   │   └── ProfileValidationRules.php
│   │
│   ├── Console/
│   │   └── Commands/
│   │       └── ServeCommand.php
│   │
│   ├── Domains/                # 🎯 BOUNDED CONTEXTS — LOGIKA BISNIS (DDD)
│   │   │
│   │   ├── Auth/               # Identitas & Autentikasi
│   │   │   ├── Actions/
│   │   │   │   └── ToggleUserStatusAction.php       # Aktifkan/nonaktifkan akun user
│   │   │   └── Models/
│   │   │       └── User.php                         # implements MustVerifyEmail, PasskeyUser
│   │   │
│   │   ├── Billing/            # Integrasi Midtrans SaaS & Subscription
│   │   │   ├── Actions/
│   │   │   │   ├── ChangeUserPlanAction.php
│   │   │   │   ├── CreateSnapTokenAction.php        # Generate Midtrans Snap token
│   │   │   │   ├── HandleWebhookAction.php          # Proses notifikasi Midtrans
│   │   │   │   ├── RemoveUserProjectLimitOverrideAction.php
│   │   │   │   └── SetUserProjectLimitOverrideAction.php
│   │   │   ├── Gateway/
│   │   │   │   ├── Contracts/                       # Interface payment gateway
│   │   │   │   ├── DTO/                             # Data Transfer Objects
│   │   │   │   ├── PaymentGatewayManager.php
│   │   │   │   └── Providers/                       # Provider konkret (Midtrans, dll.)
│   │   │   ├── Models/
│   │   │   │   ├── Plan.php
│   │   │   │   ├── Subscription.php
│   │   │   │   ├── Transaction.php
│   │   │   │   └── UserPlanOverride.php
│   │   │   └── Services/
│   │   │       └── SubscriptionLimitService.php     # Hitung effective project limit
│   │   │
│   │   ├── Chat/               # Real-time Messaging Arsitek ↔ Klien per Project
│   │   │   ├── Controllers/
│   │   │   │   └── ChatController.php               # index (histori) + store (kirim pesan)
│   │   │   ├── Models/
│   │   │   │   └── ChatMessage.php
│   │   │   └── Requests/                            # SendChatMessageRequest.php
│   │   │
│   │   ├── Comment/            # Spatial Pin Annotation & Feedback
│   │   │   ├── Actions/
│   │   │   │   └── PinCommentAction.php
│   │   │   ├── Controllers/
│   │   │   │   ├── CommentController.php
│   │   │   │   └── PinCommentController.php
│   │   │   ├── Models/
│   │   │   │   └── Comment.php
│   │   │   └── Requests/
│   │   │       └── StoreCommentRequest.php
│   │   │
│   │   ├── Project/            # Manajemen Model 3D, Storage & Client Invitation
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProjectAction.php          # Validasi kuota + buat project
│   │   │   │   ├── InviteClientAction.php           # Assign email Klien ke project_clients
│   │   │   │   ├── RevokeClientAccessAction.php     # Cabut akses Klien
│   │   │   │   └── UploadProjectFileAction.php      # Handle upload .glb/.gltf
│   │   │   ├── Controllers/
│   │   │   │   └── CameraPresetController.php       # CRUD camera view presets
│   │   │   ├── Jobs/
│   │   │   │   └── DracoCompressionJob.php          # Background queue: kompresi Draco via gltf-pipeline
│   │   │   ├── Models/
│   │   │   │   ├── Project.php
│   │   │   │   ├── ProjectCameraPreset.php
│   │   │   │   ├── ProjectClient.php
│   │   │   │   └── ProjectVersion.php
│   │   │   └── Notifications/
│   │   │       └── ClientInvitationNotification.php # Email undangan ke Klien
│   │   │
│   │   ├── Rab/                # RAB & Lembar Kerja (template / CSV / .glb)
│   │   │   ├── Actions/
│   │   │   │   ├── CreateRabDocumentAction.php
│   │   │   │   ├── UpdateRabDocumentAction.php
│   │   │   │   ├── UpsertRabItemAction.php
│   │   │   │   ├── DeleteRabItemAction.php
│   │   │   │   ├── RecalculateRabAction.php
│   │   │   │   ├── FinalizeRabDocumentAction.php    # finalize + reopen
│   │   │   │   ├── ToggleRabClientVisibilityAction.php
│   │   │   │   ├── SavePriceItemAction.php
│   │   │   │   ├── SaveRabTemplateAction.php
│   │   │   │   ├── RecalculatePriceItemAction.php   # AHSP: satu sumber kebenaran kalkulasi unit_price dari komponen
│   │   │   │   ├── ImportQuantityTakeoffAction.php  # Fase B: import CSV/XLSX
│   │   │   │   ├── ApplyGlbQuantitiesAction.php     # Fase C: apply hasil Three.js ke RAB (is_estimate=true)
│   │   │   │   └── ExportRabAction.php              # Multi-sheet XLSX: Rekap, Detail, AHSP per item
│   │   │   ├── Controllers/
│   │   │   │   ├── RabDocumentController.php
│   │   │   │   ├── RabItemController.php
│   │   │   │   ├── RabPriceItemController.php
│   │   │   │   ├── RabTemplateController.php
│   │   │   │   ├── RabMappingController.php         # CRUD mapping RAB (/rab/mappings)
│   │   │   │   └── RabPriceItemComponentController.php  # AHSP components per price item
│   │   │   ├── Models/
│   │   │   │   ├── RabDocument.php
│   │   │   │   ├── RabItem.php
│   │   │   │   ├── RabPriceItem.php
│   │   │   │   ├── RabPriceItemComponent.php        # AHSP sub-komponen (tenaga/bahan/peralatan)
│   │   │   │   ├── RabTemplate.php
│   │   │   │   ├── RabTemplateItem.php
│   │   │   │   └── RabMapping.php
│   │   │   ├── Requests/                            # StoreRabDocumentRequest, UpsertRabItemRequest, StorePriceItemRequest, dll.
│   │   │   └── Services/
│   │   │       ├── RabAccessService.php             # Gating can_use_rab + akses lihat (switch klien)
│   │   │       ├── TakeoffParserService.php         # Parse CSV (auto-detect delimiter) + XLSX via phpspreadsheet
│   │   │       └── RabMappingService.php            # Pencocokan exact/wildcard/material + saveMappings
│   │   │
│   │   └── SystemConfig/       # Konfigurasi Kuota Global & Backoffice Super Admin
│   │       ├── Models/
│   │       │   └── AuditLog.php
│   │       ├── Repositories/                        # SystemSettingRepository.php
│   │       └── Services/                            # GetSettingsService.php
│   │
│   │   # ─── RENCANA v2.7 (belum dibuat) ─────────────────────────────────────
│   │   # └── Drawing/          # Editor Gambar Teknik 2D — hanya Enterprise
│   │   #     ├── Actions/      # CreateDrawingSheetAction, ExportDxfAction, ExportSvgAction
│   │   #     ├── Controllers/  # DrawingSheetController, DrawingElementController
│   │   #     └── Models/       # DrawingSheet.php, DrawingElement.php
│   │
│   ├── Events/                 # Domain Events untuk Broadcasting (Reverb)
│   │   ├── ChatMessageSent.php             # implements ShouldBroadcast
│   │   ├── ClientAccessRevoked.php
│   │   ├── ClientInvitationReceived.php
│   │   └── ClientStatusUpdated.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                      # Admin Panel controllers (/admin/*)
│   │   │   │   ├── AuditLogController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── PlansController.php
│   │   │   │   ├── ProjectsController.php
│   │   │   │   ├── TransactionsController.php
│   │   │   │   └── UsersController.php
│   │   │   ├── Auth/
│   │   │   │   └── GoogleAuthController.php         # Callback Google OAuth via Socialite
│   │   │   ├── Project/
│   │   │   │   ├── ProjectController.php            # CRUD project
│   │   │   │   └── ViewerController.php             # Serve Viewer page + data model
│   │   │   ├── Settings/
│   │   │   │   └── ProfileController.php            # Update profile, avatar, badan usaha, alamat wilayah
│   │   │   ├── BillingController.php
│   │   │   ├── CheckoutController.php               # Inisiasi Midtrans Snap
│   │   │   ├── DashboardController.php
│   │   │   ├── IndonesiaRegionController.php        # API /region/* (provinces,cities,districts,villages) — tanpa auth
│   │   │   ├── PlansController.php
│   │   │   ├── ProjectsPageController.php
│   │   │   ├── TeamsPageController.php
│   │   │   ├── UserSearchController.php
│   │   │   └── WelcomeController.php                # Landing page (Welcome.vue)
│   │   │
│   │   └── Middleware/
│   │       ├── CheckAccountStatus.php               # Cek apakah akun aktif/suspended
│   │       ├── EnsureSuperAdmin.php                 # Guard route /admin/*
│   │       ├── HandleAppearance.php                 # Dark/light/system mode
│   │       ├── HandleInertiaRequests.php            # Share data global ke Inertia
│   │       ├── ProjectClientAccessMiddleware.php    # Validasi email undangan vs user login
│   │       └── RevisionLimitEnforcementMiddleware.php  # Blokir pin jika kuota habis
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── FortifyServiceProvider.php              # Konfigurasi Fortify features & views
│
├── resources/                  # 🟢 FRONTEND — Inertia.js + Vue 3
│   │
│   ├── css/
│   │   └── app.css             # Tailwind CSS v4 entry point
│   │
│   ├── js/                     # TypeScript/Vue source
│   │   ├── app.ts              # Entry point: createApp(InertiaApp) + mount
│   │   │
│   │   ├── components/         # Reusable UI Components
│   │   │   ├── app/
│   │   │   │   ├── AppHeader.vue
│   │   │   │   └── AppSidebar.vue          # Menu baru: "RAB & Lembar Kerja", "RAB Template", "Mapping RAB"
│   │   │   ├── landing/        # Apple-Style Landing Page Components
│   │   │   │   ├── ArchitecturalCinematicSection.vue
│   │   │   │   ├── BentoGridSection.vue
│   │   │   │   ├── CtaSection.vue
│   │   │   │   ├── FaqSection.vue
│   │   │   │   ├── HeroSection.vue
│   │   │   │   ├── InteractiveShowcaseSection.vue
│   │   │   │   ├── LandingFooter.vue
│   │   │   │   ├── LandingNavbar.vue
│   │   │   │   ├── PricingSection.vue
│   │   │   │   └── ScrollyExperienceSection.vue
│   │   │   ├── ui/             # shadcn/ui Vue port components
│   │   │   │   ├── badge/
│   │   │   │   ├── button/
│   │   │   │   ├── dialog/
│   │   │   │   ├── input/
│   │   │   │   ├── label/
│   │   │   │   ├── skeleton/
│   │   │   │   ├── sonner/
│   │   │   │   └── spinner/
│   │   │   └── AddressInput.vue            # Cascading dropdown wilayah Indonesia (province→city→district→village)
│   │   │
│   │   ├── composables/        # Shared Vue Composables
│   │   │   ├── useConfirm.ts
│   │   │   ├── usePageLoading.ts
│   │   │   ├── useSidebar.ts
│   │   │   ├── useGlbQuantities.ts         # Hitung luas/volume/count per mesh dari .glb (Three.js); baca custom properties Blender
│   │   │   └── useIndonesiaRegion.ts       # Cascading dropdown wilayah Indonesia via /region/* API
│   │   │
│   │   ├── layouts/            # Inertia Page Layouts
│   │   │   ├── AdminLayout.vue         # Layout untuk halaman /admin/*
│   │   │   ├── LandingLayout.vue       # Layout untuk landing page & auth pages
│   │   │   └── UnifiedLayout.vue       # Layout untuk settings pages
│   │   │
│   │   ├── lib/
│   │   │   ├── echo.ts         # Inisialisasi Laravel Echo + Reverb connector
│   │   │   └── utils.ts        # Helper functions
│   │   │
│   │   ├── pages/              # Inertia Pages (satu file = satu route)
│   │   │   ├── Welcome.vue             # Landing page (route: /)
│   │   │   ├── Dashboard.vue           # Dashboard utama
│   │   │   ├── ShowcaseDemo.vue        # Demo showcase
│   │   │   ├── Plans.vue               # Halaman pricing/plans
│   │   │   ├── Error.vue               # Halaman error (404, 403, dll.)
│   │   │   ├── Admin/                  # Halaman Admin Panel (/admin/*)
│   │   │   │   ├── Dashboard.vue
│   │   │   │   ├── Users/Index.vue
│   │   │   │   ├── Projects/Index.vue
│   │   │   │   ├── Plans/Index.vue
│   │   │   │   ├── Transactions/Index.vue
│   │   │   │   └── AuditLogs/Index.vue
│   │   │   ├── auth/                   # Halaman autentikasi
│   │   │   │   ├── Login.vue
│   │   │   │   ├── Register.vue
│   │   │   │   ├── ForgotPassword.vue
│   │   │   │   ├── ResetPassword.vue
│   │   │   │   ├── VerifyEmail.vue
│   │   │   │   ├── TwoFactorChallenge.vue
│   │   │   │   └── ConfirmPassword.vue
│   │   │   ├── Billing/
│   │   │   │   └── Index.vue           # Billing history & subscription info
│   │   │   ├── Checkout/
│   │   │   │   └── Index.vue           # Midtrans Snap checkout
│   │   │   ├── Project/
│   │   │   │   └── Viewer.vue          # 3D Viewer (Three.js, pin comments, chat)
│   │   │   ├── Rab/
│   │   │   │   ├── Index.vue           # Daftar RAB per project
│   │   │   │   ├── Show.vue            # Editor/preview RAB + switch visibilitas klien
│   │   │   │   ├── PriceItems.vue      # Master harga satuan (tombol FlaskConical → AHSP)
│   │   │   │   ├── PriceItemComponents.vue  # Tabel AHSP per harga satuan (tenaga/bahan/peralatan)
│   │   │   │   ├── Templates.vue       # Template RAB
│   │   │   │   ├── TemplateShow.vue    # Detail/edit item dalam template
│   │   │   │   ├── Import.vue          # Wizard 3-step import CSV/XLSX (Fase B)
│   │   │   │   ├── GlbEstimator.vue    # Estimator dari .glb (Fase C) — auto-load, tabel mesh, basis selector
│   │   │   │   └── Mappings.vue        # Halaman mapping RAB (/rab/mappings)
│   │   │   └── Settings/
│   │   │       ├── Profile.vue             # Profil + badan usaha + alamat wilayah Indonesia (AddressInput.vue)
│   │   │       ├── Password.vue
│   │   │       └── Appearance.vue
│   │   │
│   │   ├── routes/             # Typed route helpers (Ziggy/manual)
│   │   └── types/              # TypeScript type definitions
│   │
│   └── views/
│       └── app.blade.php       # Blade shell — titik masuk Inertia (@inertia)
│
├── routes/                     # Laravel Route Files
│   ├── web.php                 # Routes utama: landing, dashboard, project, billing, auth
│   │                           # Tambah v2.7: /region/provinces|cities|districts|villages (tanpa auth)
│   │                           # Tambah v2.7: /rab/mappings, /rab/price-items/{id}/components, /rab/price-items/{id}/overhead
│   ├── admin.php               # Routes admin panel (/admin/*) — guard EnsureSuperAdmin
│   ├── settings.php            # Routes settings (profile, password, appearance)
│   └── channels.php            # Broadcasting channel authorization (private channels Reverb)
│
├── database/
│   ├── migrations/             # MySQL UUID Table Migrations
│   ├── factories/              # Model Factories
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── SystemSettingSeeder.php
│
├── config/
│   ├── fortify.php             # Fortify features: registration, resetPasswords,
│   │                           # emailVerification, twoFactor, passkeys
│   └── reverb.php              # Laravel Reverb WebSocket server config
│
├── storage/
│   └── app/
│       └── public/             # File 3D yang diupload (.glb/.gltf) — local disk "public"
│                                # (bukan Cloudflare R2; symlink ke public/storage via artisan)
│
├── public/
│   └── storage/                # Symlink ke storage/app/public (via php artisan storage:link)
│
├── .env                        # Environment config (DB_CONNECTION=mysql, QUEUE_CONNECTION=database)
├── .env.example
├── package.json                # Vite + Vue 3 dependencies
├── vite.config.ts
├── tailwind.config.ts          # Tailwind CSS v4 config
└── composer.json               # Laravel 12 PHP dependencies
```

---

## Catatan Penting Arsitektur

### Monorepo — Bukan Backend/Frontend Terpisah
PitchArch adalah **monorepo tunggal** berbasis Laravel. Frontend (Vue 3) di-serve melalui Inertia.js — tidak ada server Node.js/Nuxt terpisah. Build frontend dilakukan via Vite dan hasilnya dimasukkan ke `public/build/`.

### Routing
Routing **tidak menggunakan Nuxt file-based routing**. Semua route didefinisikan di `routes/web.php`, `routes/admin.php`, dan `routes/settings.php`. Inertia me-render komponen Vue yang sesuai berdasarkan nama page yang dikembalikan controller.

### Auth (Fortify, bukan Sanctum)
- Autentikasi menggunakan **Laravel Fortify** (bukan Sanctum API tokens).
- Konfigurasi di `config/fortify.php` dan `app/Providers/FortifyServiceProvider.php`.
- Features yang aktif: `registration`, `resetPasswords`, `emailVerification`, `twoFactor`, `passkeys`.
- Google OAuth: redirect-based flow via `laravel/socialite` → `GoogleAuthController`.
- Email verification: `User` model implements `MustVerifyEmail`. Google OAuth otomatis set `email_verified_at`.

### Storage
- Development & Production menggunakan **Laravel `public` disk lokal** (`storage/app/public/`).
- File diakses via `Storage::url()` → `/storage/...` (memerlukan `php artisan storage:link`).
- **Tidak ada Cloudflare R2** saat ini — integrasi cloud storage adalah future enhancement.

### Queue
- Queue driver: **database** (bukan Redis).
- `DracoCompressionJob` di-dispatch ke queue `default`.
- Worker: `php artisan queue:work`.
