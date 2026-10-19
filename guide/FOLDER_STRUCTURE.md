# 📁 PitchArch — Directory & Folder Structure

Dokumen ini adalah acuan resmi struktur folder untuk aplikasi **PitchArch**: monorepo Laravel 12 + Inertia.js + Vue 3 (bukan backend/frontend terpisah).

> **v3.1 Changelog:** Menambahkan implementasi penuh Architectural Working Booklet (`BookletController.php`, `Booklet.vue`, `KeyPlanCanvas.vue`), Direct AI Render Generation (`GenerateAiRenderFromCaptureAction.php`, route `.../generate-ai`), Generator Draf RAB Per Ruangan dari Lembar Kerja (`GenerateRabFromWorksheetAction.php`), kartu preview 3D interaktif (`Project3DPreview.vue`, `ProjectCard.vue`), implementasi domain Drawing 2D (`app/Domains/Drawing/*`, `Drawing/Index.vue`, `Drawing/Editor.vue`), dan seeder booklet arsitektur nyata (`SemangkokAeBookletSeeder.php`).
>
> **v3.0 Changelog:** Migrasi kolom booklet pada `project_captures` (`code`, `ai_rendered_path`, `drawing_sheet_id`, `room_element_id`, `description`, `sort_order`), kolom klien pada `projects` (`client_name`, `client_address`), serta Action `UploadAiRenderedCaptureAction.php`.
>
> **v2.9 Changelog:** Menambahkan Domain AI Vision Studio: `app/Domains/VisionStudio/Models/VisionCreation.php`, `app/Domains/Project/Actions/GenerateVisionPromptAction.php`, `app/Http/Controllers/VisionStudioController.php`, `app/Http/Middleware/CheckVisionStudioAccess.php`, `resources/js/pages/VisionStudio/Index.vue`. Menambahkan `app/Support/CacheKeys.php` (safe cache tagging, Redis multi-DB cache/session separation, distributed locking).
>
> **v2.8 Changelog:** Menambahkan modul Simulasi Pencahayaan Matahari Real-Time (Three.js + SunCalc) & Snapshot Captures: `app/Domains/Project/Actions/SaveCaptureAction.php`, `DeleteCaptureAction.php`, `ToggleCaptureVisibilityAction.php`, `UpdateProjectSunSettingsAction.php`, `ProjectCaptureController.php`, `ProjectCapture.php`, `StoreCaptureRequest.php`, `UpdateProjectSunSettingsRequest.php`, `ProjectCaptureFactory.php`, composable `useSunSimulation.ts`, migrasi `sun_settings` dan `project_captures`.
>
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
│   │   ├── Project/            # Manajemen Model 3D, Storage, Lighting & Captures
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProjectAction.php          # Validasi kuota + buat project (distributed lock)
│   │   │   │   ├── DeleteCaptureAction.php          # Hapus snapshot capture & storage file
│   │   │   │   ├── GenerateAiRenderFromCaptureAction.php # Direct AI architectural render generator (Gemini API)
│   │   │   │   ├── GenerateVisionPromptAction.php   # Prompt generator cerdas untuk AI Vision Studio
│   │   │   │   ├── InviteClientAction.php           # Assign email Klien ke project_clients
│   │   │   │   ├── RevokeClientAccessAction.php     # Cabut akses Klien
│   │   │   │   ├── SaveCaptureAction.php            # Simpan snapshot Three.js (PNG), kuota per-gambar & prompt AI (distributed lock)
│   │   │   │   ├── ToggleCaptureVisibilityAction.php # Switch visibilitas capture ke klien
│   │   │   │   ├── UpdateCapturePromptAction.php    # Update prompt AI capture (auto-save on blur)
│   │   │   │   ├── UpdateProjectSunSettingsAction.php # Simpan lat/long/arah utara/tanggal
│   │   │   │   ├── UploadAiRenderedCaptureAction.php # Upload dan ganti visual snapshot dengan render AI
│   │   │   │   └── UploadProjectFileAction.php      # Handle upload .glb/.gltf
│   │   │   ├── Controllers/
│   │   │   │   ├── CameraPresetController.php       # CRUD camera view presets
│   │   │   │   └── ProjectCaptureController.php     # CRUD snapshot capture, AI render, sun settings
│   │   │   ├── Jobs/
│   │   │   │   └── DracoCompressionJob.php          # Background queue: kompresi Draco via gltf-pipeline
│   │   │   ├── Models/
│   │   │   │   ├── Project.php                      # Ditambah client fields, sun settings & relasi captures/drawings
│   │   │   │   ├── ProjectCameraPreset.php
│   │   │   │   ├── ProjectCapture.php               # UUID, code (R-01/EXT-01), drawing_sheet_id, ai_rendered_path
│   │   │   │   ├── ProjectClient.php
│   │   │   │   └── ProjectVersion.php
│   │   │   ├── Notifications/
│   │   │   │   └── ClientInvitationNotification.php # Email undangan ke Klien
│   │   │   └── Requests/
│   │   │       ├── StoreCaptureRequest.php          # Validasi snapshot payload
│   │   │       ├── UpdateProjectSunSettingsRequest.php # Validasi koordinat & arah utara
│   │   │       └── UploadAiRenderedCaptureRequest.php # Validasi file render AI
│   │   │
│   │   ├── Rab/                # RAB & Lembar Kerja (template / CSV / .glb / worksheet)
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
│   │   │   │   ├── GenerateRabFromWorksheetAction.php # Generator Draf RAB Per Ruangan dari denah/booklet
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
│   │   ├── Drawing/            # Editor CAD 2D & Architectural Working Booklet (Enterprise Only)
│   │   │   ├── Actions/
│   │   │   │   ├── CreateDrawingSheetAction.php     # Buat lembar gambar (plan/elevation/section)
│   │   │   │   ├── UpdateDrawingSheetAction.php     # Update dimensi (width/height), skala, kertas
│   │   │   │   ├── SyncDrawingElementsAction.php    # Batch sync elemen Konva ke database
│   │   │   │   ├── ExportDxfAction.php              # Standar AutoCAD R12/2000 ASCII compliant
│   │   │   │   └── ExportSvgAction.php              # Export format vektor SVG scalable
│   │   │   ├── Controllers/
│   │   │   │   ├── BookletController.php            # Booklet PDF Builder & ISO A3/A4 print view
│   │   │   │   ├── DrawingElementController.php     # Batch sync elemen CAD
│   │   │   │   └── DrawingSheetController.php       # CRUD lembar kerja CAD & export
│   │   │   ├── Models/
│   │   │   │   ├── DrawingElement.php               # Elemen gambar teknik (wall, door, window, room_label, dll.)
│   │   │   │   └── DrawingSheet.php                 # Metadata lembar kertas A0-A4, model space, dimensi px
│   │   │   ├── Requests/
│   │   │   │   ├── CreateDrawingSheetRequest.php
│   │   │   │   ├── SyncDrawingElementsRequest.php
│   │   │   │   └── UpdateDrawingSheetRequest.php
│   │   │   └── Services/
│   │   │       └── DrawingAccessService.php         # Gating plans.can_use_drawing Enterprise
│   │   │
│   │   ├── VisionStudio/       # AI Generative Studio (Paid-Only)
│   │   │   └── Models/
│   │   │       └── VisionCreation.php               # UUID, user_id, prompt_type, file_path, storage cleanup
│   │   │
│   │   └── SystemConfig/       # Konfigurasi Kuota Global & Backoffice Super Admin
│   │       ├── Models/
│   │       │   └── AuditLog.php
│   │       ├── Repositories/                        # SystemSettingRepository.php
│   │       └── Services/                            # GetSettingsService.php
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
│   │   │   ├── CheckoutController.php               # Inisiasi Midtrans Snap (concurrency lockForUpdate)
│   │   │   ├── DashboardController.php
│   │   │   ├── IndonesiaRegionController.php        # API /region/* (provinces,cities,districts,villages) — tanpa auth
│   │   │   ├── PlansController.php                  # Menggunakan cache Plan::getActivePlansForDisplay()
│   │   │   ├── ProjectsPageController.php
│   │   │   ├── TeamsPageController.php
│   │   │   ├── UserSearchController.php
│   │   │   ├── VisionStudioController.php           # AI Generative Studio (generate, load, delete)
│   │   │   └── WelcomeController.php                # Landing page (Welcome.vue)
│   │   │
│   │   └── Middleware/
│   │       ├── CheckAccountStatus.php               # Cek apakah akun aktif/suspended
│   │       ├── CheckVisionStudioAccess.php          # Gating fitur AI Vision Studio (khusus berbayar)
│   │       ├── EnsureSuperAdmin.php                 # Guard route /admin/*
│   │       ├── HandleAppearance.php                 # Dark/light/system mode
│   │       ├── HandleInertiaRequests.php            # Share data global ke Inertia
│   │       ├── ProjectClientAccessMiddleware.php    # Validasi email undangan vs user login
│   │       └── RevisionLimitEnforcementMiddleware.php  # Blokir pin jika kuota habis
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php                  # Tanpa listener berat User::retrieved (eliminasi N+1)
│   │   └── FortifyServiceProvider.php              # Konfigurasi Fortify features & views
│   │
│   └── Support/                # Helper arsitektur sistem
│       └── CacheKeys.php                           # Safe Redis cache tagging, plan keys, flush invalidation
│
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
│   │   │   │   └── AppSidebar.vue          # Menu: "RAB", "Lembar Kerja 2D" (ENT/CAD), "AI Vision Studio", dll.
│   │   │   ├── drawing/        # Architectural Drawing Components
│   │   │   │   └── KeyPlanCanvas.vue       # Mini denah responsif SVG dengan highlight oranye (#EA580C) ruangan aktif
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
│   │   │   ├── AddressInput.vue            # Cascading dropdown wilayah Indonesia (province→city→district→village)
│   │   │   ├── Project3DPreview.vue        # Kartu preview 3D WebGL interaktif (orbit, auto-rotate, lights)
│   │   │   └── ProjectCard.vue             # Komponen kartu proyek modular dengan 3D preview & status badge
│   │   │
│   │   ├── composables/        # Shared Vue Composables
│   │   │   ├── useConfirm.ts
│   │   │   ├── usePageLoading.ts
│   │   │   ├── useSidebar.ts
│   │   │   ├── useGlbQuantities.ts         # Hitung luas/volume/count per mesh dari .glb (Three.js); baca custom properties Blender
│   │   │   ├── useIndonesiaRegion.ts       # Cascading dropdown wilayah Indonesia via /region/* API
│   │   │   ├── useSunSimulation.ts         # Simulasi posisi matahari (SunCalc), arah utara, night mode (LAMPU_*), dan ambient light lerp
│   │   │   ├── usePostProcessing.ts        # Three.js EffectComposer: GTAOPass (ambient contact shadows), UnrealBloomPass, ACESFilmic ToneMapping, offscreen high-res 1080p/4K render
│   │   │   └── usePathTracer.ts            # Three.js WebGLPathTracer (three-gpu-pathtracer + three-mesh-bvh): True GPU Ray-Tracing, progressive samples accumulation, global illumination
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
│   │   │   ├── Dashboard.vue           # Dashboard utama arsitek & klien dengan kartu proyek 3D interaktif
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
│   │   │   ├── Drawing/                # Lembar Kerja CAD 2D & Booklet (Enterprise)
│   │   │   │   ├── Index.vue           # Daftar lembar kerja CAD per project + badge 3D linked
│   │   │   │   ├── Editor.vue          # Editor CAD Konva.js (Multi-Paper, model space, figma resize, inspector combobox)
│   │   │   │   └── Booklet.vue         # Architectural Working Booklet Builder & ISO A3/A4 Landscape PDF Engine
│   │   │   ├── Project/
│   │   │   │   └── Viewer.vue          # 3D Viewer (Three.js, pin comments, chat, snapshot captures & AI render)
│   │   │   ├── Projects/
│   │   │   │   └── Index.vue           # Galeri semua proyek arsitek dengan live 3D preview cards
│   │   │   ├── Rab/
│   │   │   │   ├── Index.vue           # Daftar RAB per project
│   │   │   │   ├── Show.vue            # Editor/preview RAB + switch visibilitas klien + generator from worksheet
│   │   │   │   ├── PriceItems.vue      # Master harga satuan (tombol FlaskConical → AHSP)
│   │   │   │   ├── PriceItemComponents.vue  # Tabel AHSP per harga satuan (tenaga/bahan/peralatan)
│   │   │   │   ├── Templates.vue       # Template RAB
│   │   │   │   ├── TemplateShow.vue    # Detail/edit item dalam template
│   │   │   │   ├── Import.vue          # Wizard 3-step import CSV/XLSX (Fase B)
│   │   │   │   ├── GlbEstimator.vue    # Estimator dari .glb (Fase C) — auto-load, tabel mesh, basis selector
│   │   │   │   └── Mappings.vue        # Halaman mapping RAB (/rab/mappings)
│   │   │   ├── Settings/
│   │   │   │   ├── Profile.vue             # Profil + badan usaha + alamat wilayah Indonesia (AddressInput.vue)
│   │   │   │   ├── Password.vue
│   │   │   │   └── Appearance.vue
│   │   │   └── VisionStudio/
│   │   │       └── Index.vue               # AI Vision Studio (Image & Video prompts, gallery, localStorage state)
│   │   │
│   │   ├── routes/             # Typed route helpers (Ziggy/manual)
│   │   └── types/              # TypeScript type definitions
│   │
│   └── views/
│       └── app.blade.php       # Blade shell — titik masuk Inertia (@inertia)
│
├── routes/                     # Laravel Route Files
│   ├── web.php                 # Routes utama: landing, dashboard, projects, viewer, captures, drawing, booklet, rab, billing, auth
│   │                           # /projects/{project}/booklet (Architectural Working Booklet)
│   │                           # /projects/{project}/captures/{capture}/ai-render (Upload/Delete AI render)
│   │                           # /projects/{project}/captures/{capture}/generate-ai (Direct Gemini/AI generation)
│   │                           # /projects/{project}/rab/from-worksheet (Generate per-room RAB)
│   │                           # /drawings/* (CRUD sheet, sync elements, sync captures, export DXF/SVG)
│   │                           # /region/* (provinces, cities, districts, villages — tanpa auth)
│   ├── admin.php               # Routes admin panel (/admin/*) — guard EnsureSuperAdmin
│   ├── settings.php            # Routes settings (profile, password, appearance)
│   └── channels.php            # Broadcasting channel authorization (private channels Reverb)
│
├── database/
│   ├── migrations/             # MySQL UUID Table Migrations
│   ├── factories/              # Model Factories
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── SemangkokAeBookletSeeder.php  # Seeder booklet arsitektur nyata: 2D master plan, 3D captures, AI renders & KeyPlan
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

### Caching & Redis Architecture (Skala 1 Juta Pengguna)
- Driver: **Redis** (`CACHE_STORE=redis`).
- Multi-Database Separation:
  - Database 0: Framework & general default connection.
  - Database 1: Application cache (`REDIS_CACHE_DB=1`), safe cache tagging via `CacheKeys::tags()`, master data `Plan::getActivePlansForDisplay()` dengan TTL 1 jam.
  - Database 2: User sessions (`REDIS_SESSION_DB=2`), memastikan perintah `cache:clear` / flush cache tidak mendrop session user yang sedang online.
- Fallback Config: `config/session.php` mendeteksi driver secara dinamis agar kompatibel saat driver bernilai `database` maupun `redis`.

### Concurrency & Race Condition Protection
- Distributed Atomic Locks (`Cache::lock(...)->block(5)`): Digunakan pada `CreateProjectAction`, `SaveCaptureAction`, dan `PinCommentAction` untuk melindungi kuota dan revisi dari race condition.
- Transaction Isolation: Penanganan webhook dan callback pembayaran Midtrans di `CheckoutController` dibungkus `DB::transaction` dengan `lockForUpdate()` pada record order pending.
- Eliminasi N+1: Tidak ada listener `User::retrieved` di service provider; seluruh controller wajib menggunakan eager loading relasi (`with(...)` / `withCount(...)`).
