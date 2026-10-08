# 📊 PitchArch — Progress Tracker

> **CATATAN UNTUK AI:** File ini dikelola dan diperbarui secara otomatis oleh AI setiap kali sebuah menu, modul, atau fitur selesai dikerjakan dan berhasil dijalankan.

---

## 🚦 Status Legend
- 🔴 **Pending:** Belum dikerjakan.
- 🟡 **In Progress:** Sedang dalam proses koding/refactoring.
- 🟢 **Completed:** Selesai, lulus tes, dan berfungsi dengan baik.
- ⚪ **Removed:** Dihapus dari scope.

---

## 📋 Feature & Menu Checklist

### 1. Identity & Auth Domain (`Domains/Auth`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Email & Password Register/Login (tanpa pilihan role) | Backend & Frontend | 🟢 Completed | Fortify-based, form register TIDAK ada field pilih role |
| Google OAuth via Socialite (redirect-based) | Backend (`socialite`) & Frontend | 🟢 Completed | `GoogleAuthController`, redirect flow (bukan token-based Sanctum) |
| Passkeys (WebAuthn/FIDO2) | Backend & Frontend | 🟢 Completed | `User` implements `PasskeyUser`, config di `fortify.php` |
| 2FA (Two-Factor Authentication) | Backend & Frontend | 🟢 Completed | Fortify built-in, `TwoFactorChallenge.vue` |
| Email Verification | Backend & Frontend | 🟢 Completed | `User` implements `MustVerifyEmail`, Google OAuth auto-verify `email_verified_at`, `VerifyEmail.vue` |
| Forgot / Reset Password | Backend & Frontend | 🟢 Completed | Fortify built-in, `ForgotPassword.vue`, `ResetPassword.vue` |
| Role & Permission Authorization | Backend Middleware | 🟢 Completed | Hanya untuk `super_admin` via Spatie; Arsitek/Klien bukan Spatie role |
| Client Invitation Email Matching on Login | Backend `Actions/Auth` | 🟢 Completed | `MatchInvitedClientEmailAction` — cocokkan email login dengan `project_clients.email` |
| Toggle User Status (Admin) | Backend `Domains/Auth` | 🟢 Completed | `ToggleUserStatusAction`, `CheckAccountStatus` middleware |
| Dual-Capacity Account Support (Arsitek + Klien 1 akun) | Backend / QA | 🟢 Completed | Tidak ada logic yang mencegah 1 akun jadi Arsitek & Klien bersamaan |

### 2. Platform Config & Admin Backoffice (`Domains/SystemConfig` & `/admin/*`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `system_settings` | Database | 🟢 Completed | MySQL UUID |
| Dynamic Config Seeder | Database Seeder | 🟢 Completed | `SystemSettingSeeder` — quota Free & Pro Tier, default client revisions |
| Database Migration `plans` | Database | 🟢 Completed | Dynamic Plan configuration (Free, Pro, Enterprise) |
| Database Migration `user_plan_overrides` | Database | 🟢 Completed | Admin custom override project limit |
| Database Migration `audit_logs` | Database | 🟢 Completed | Administrative audit trail |
| Subscription Limit & Feature Gating Service | Backend DDD | 🟢 Completed | `SubscriptionLimitService`, formula: Override ?? Plan Limit ?? 1 |
| Admin Dashboard (`/admin/`) | Backend & Frontend | 🟢 Completed | `Admin/Dashboard.vue`, stats global |
| Admin User Management (`/admin/users`) | Backend & Frontend | 🟢 Completed | List users, suspend/aktivasi, ganti plan |
| Admin Project Management (`/admin/projects`) | Backend & Frontend | 🟢 Completed | List semua project lintas user |
| Admin Plans Management (`/admin/plans`) | Backend & Frontend | 🟢 Completed | CRUD rencana subscription |
| Admin Transactions (`/admin/transactions`) | Backend & Frontend | 🟢 Completed | Riwayat transaksi billing |
| Admin Audit Logs (`/admin/audit-logs`) | Backend & Frontend | 🟢 Completed | Log perubahan admin |
| `EnsureSuperAdmin` Middleware | Backend | 🟢 Completed | Guard semua route `/admin/*` |
| System Settings Caching | Backend | 🔴 Pending | Saat ini baca langsung DB; Redis/database cache belum diimplementasikan |

### 3. Project & Storage Domain (`Domains/Project`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `projects` | Database | 🟢 Completed | UUID PK, `max_revisions_allowed` & `current_revision_count` |
| Database Migration `project_versions` | Database | 🟢 Completed | Support multi-revisi .glb & history log |
| Database Migration `project_clients` | Database | 🟢 Completed | Unique `(project_id, email)`, status, invited_by |
| Database Migration `camera_presets` | Database | 🟢 Completed | Tabel `camera_presets` untuk simpan angle kamera (nama, posisi, target) |
| Create Project Action & Quota Check | Backend DDD | 🟢 Completed | `CreateProjectAction`, validasi kuota |
| File Upload Handler (local public disk) | Backend Storage | 🟢 Completed | `UploadProjectFileAction`, support `.glb` & `ProjectVersion`, disimpan di `storage/app/public/` |
| Background Draco Queue Worker | Queue (database driver) | 🟢 Completed | `DracoCompressionJob` via `gltf-pipeline` |
| Client Invitation Action (`InviteClientAction`) | Backend DDD | 🟢 Completed | Assign email Klien + `ClientInvitationNotification` (email via noreply@pitcharch.id) |
| Client Access Validation Middleware | Backend | 🟢 Completed | `ProjectClientAccessMiddleware` |
| Revoke Client Access Action | Backend DDD & UI | 🟢 Completed | `RevokeClientAccessAction`, hapus record dari daftar klien |
| Invitation Email Notification | Backend | 🟢 Completed | `ClientInvitationNotification` |
| Client Access List UI (Dashboard) | Frontend Dashboard | 🟢 Completed | Lihat status pending/accepted per Klien, tombol revoke |
| Custom Client Revision Setting | Backend / Dashboard | 🟢 Completed | Arsitek bisa set batas revisi khusus per project |
| Revision Limit Enforcement Middleware | Backend | 🟢 Completed | `RevisionLimitEnforcementMiddleware` |
| Edit Project Data & Model Re-upload | Backend & Frontend | 🟢 Completed | Edit judul, deskripsi, batas revisi, opsional ganti file 3D |
| Delete Project (dengan cleanup storage) | Backend & Frontend | 🟢 Completed | Hapus project + file di disk saat delete |
| Camera View Presets | Backend & Frontend | 🟢 Completed | `CameraPresetController`, `ProjectCameraPreset` model, simpan/load angle kamera di Viewer |
| Realtime Events: ClientStatusUpdated, ClientAccessRevoked, ClientInvitationReceived | Backend | 🟢 Completed | Event classes di `app/Events/` via Reverb |

### 4. 3D Viewer & Annotation Domain (`Domains/Comment`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| WebGL 3D Viewport Rendering | Frontend (Three.js + Vue 3 — `Viewer.vue`) | 🟢 Completed | Orbit controls + screen-space panning dinamis |
| Raycaster Intersect & Coordinates | Frontend Vue 3 | 🟢 Completed | Vector X, Y, Z & Normal surface hit detection |
| Database Migration `comments` | Database | 🟢 Completed | Spatial vector fields X, Y, Z & Normal |
| Pin Comment Marker Overlay | Frontend | 🟢 Completed | Marker koordinat 3D, form input pin, SVG leader lines |
| Auth Wall + Invitation Wall for Commenting | Frontend / Backend | 🟢 Completed | `ProjectClientAccessMiddleware` + `RevisionLimitEnforcementMiddleware` |
| Revision Counter Increment Logic | Backend DDD | 🟢 Completed | Sinkronisasi `projects.current_revision_count` otomatis |
| Revision Counter & Status Badge UI | Frontend Viewer | 🟢 Completed | Badge reaktif "Revisi X dari Y", alert limit, disable form saat kuota habis |
| Edit Komentar (Inline Editing) | Frontend & Backend | 🟢 Completed | `PATCH /projects/{project}/comments/{comment}` dengan otorisasi |
| Unpin & Hapus Komentar dari Database | Frontend & Backend | 🟢 Completed | `DELETE /projects/{project}/comments/{comment}`, konfirmasi aksi |
| Draggable Comment Cards & Leader Lines | Frontend | 🟢 Completed | Drag-and-drop bebas + SVG leader line dinamis tetap terhubung ke pin 3D |
| Mobile Touch Navigation & Drag Fix | Frontend | 🟢 Completed | `THREE.TOUCH.ROTATE`/`PAN`, Pointer Capture, `touch-none` |
| LocalStorage Persistence (layout & preferences) | Frontend | 🟢 Completed | Posisi kartu, status drawer, toggle annotasi tersimpan per `projectId` |
| Sectioning / Clipping Tool | Frontend (Three.js) | 🔴 Pending | Cutaway view interior bangunan |
| First-Person Walkthrough Mode | Frontend | 🔴 Pending | Navigasi WASD di dalam ruangan |
| Material Swapper | Frontend | 🔴 Pending | Ganti warna/tekstur material interaktif |

### 5. Chat & Communication Domain (`Domains/Chat`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `chat_messages` | Database | 🟢 Completed | UUID PK, index `(project_id, created_at)` |
| Laravel Reverb Setup (WebSocket Server) | Backend Infra | 🟢 Completed | `laravel/reverb`, `config/reverb.php` |
| Channel Authorization (`routes/channels.php`) | Backend | 🟢 Completed | Private channel `project.{project_id}.chat`, otorisasi identik dengan `ProjectClientAccessMiddleware` |
| Send Chat Message + Event Broadcast | Backend DDD | 🟢 Completed | `ChatController@store` + `ChatMessageSent` event (persist-then-broadcast) |
| Chat History Endpoint | Backend API | 🟢 Completed | `ChatController@index` |
| Frontend Chat UI di Viewer | Frontend (`Viewer.vue`) | 🟢 Completed | Panel chat terintegrasi dalam halaman Viewer |
| Laravel Echo Client Setup | Frontend | 🟢 Completed | `lib/echo.ts`, koneksi ke Reverb |
| Typing Indicator & Read Receipt | Frontend / Backend | 🔴 Pending | P1 — Should Have |

### 6. Billing & Subscription Domain (`Domains/Billing`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `transactions` | Database | 🟢 Completed | Order ID TRX-YYYYMMDD, UUID PK |
| Database Migration `subscriptions` | Database | 🟢 Completed | Riwayat status langganan, relasi ke `transactions` |
| Midtrans Snap Integration | Backend / Gateway | 🟢 Completed | `CreateSnapTokenAction`, `PaymentGatewayManager` |
| Payment Webhook Handler | Backend Webhook | 🟢 Completed | `HandleWebhookAction` — update `subscriptions` & `users.subscription_status` |
| Billing History Page (`/billing`) | Frontend | 🟢 Completed | `Billing/Index.vue` |
| Checkout Page (`/checkout`) | Frontend | 🟢 Completed | `Checkout/Index.vue`, Midtrans Snap pop-up |
| Change User Plan (Admin) | Backend | 🟢 Completed | `ChangeUserPlanAction` |
| Set/Remove Project Limit Override (Admin) | Backend | 🟢 Completed | `SetUserProjectLimitOverrideAction`, `RemoveUserProjectLimitOverrideAction` |
| Invoice PDF Download | Frontend & Backend | 🔴 Pending | Unduh bukti transaksi / invoice dalam format PDF |

### 7. Settings & User Profile
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Profile Settings (nama, email, avatar) | Backend & Frontend | 🟢 Completed | `ProfileController`, `Settings/Profile.vue` |
| Password Settings | Backend & Frontend | 🟢 Completed | Fortify built-in, `Settings/Password.vue` |
| Appearance Settings (dark/light/system) | Frontend | 🟢 Completed | `HandleAppearance` middleware, `Settings/Appearance.vue` |
| Profil Badan Usaha (company_type, company_name, phone) | Backend & Frontend | 🟢 Completed | Migration kolom baru di `users`, tipe picker (PT/CV/UD/dll), preview kop surat — selesai 2026-10-07 |
| Alamat Wilayah Indonesia (chained dropdown) | Backend & Frontend | 🟢 Completed | `laravolt/indonesia` seeded (38 provinsi/514 kota/7285 kecamatan/83762 desa), `IndonesiaRegionController`, `/region/*` routes, `useIndonesiaRegion.ts`, `AddressInput.vue` — selesai 2026-10-07 |

### 8. Landing Page & Public Pages
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Monumental Hero & Pinned Frame | `components/landing/HeroSection.vue` | 🟢 Completed | Typography monumental Apple, pill badge, window titanium villa |
| Scroll-Driven Product Journey 5-Stages | `components/landing/ScrollyExperienceSection.vue` | 🟢 Completed | Sticky pinned scroll track (450vh), 5 tahapan alur kerja |
| Architectural Cinematic Scrollytelling | `components/landing/ArchitecturalCinematicSection.vue` | 🟢 Completed | Pinned 4-layer spatial anatomy inspection |
| Apple Pro Bento Grid | `components/landing/BentoGridSection.vue` | 🟢 Completed | Bento cards (Zero-Leak Security, Dual-Capacity, Draco, WebSocket) |
| Interactive 3D Spatial Simulator | `components/landing/InteractiveShowcaseSection.vue` | 🟢 Completed | Dynamic click-to-pin, koordinat & normal dinamis |
| Tiered Pricing Table | `components/landing/PricingSection.vue` | 🟢 Completed | Apple Store comparison cards (Free, Pro, Enterprise), toggle bulanan/tahunan |
| FAQ Accordion | `components/landing/FaqSection.vue` | 🟢 Completed | Apple-style accordion FAQ |
| Full CTA Section | `components/landing/CtaSection.vue` | 🟢 Completed | Closing call-to-action |
| Full-Width Navbar & Footer | `components/landing/LandingNavbar.vue`, `LandingFooter.vue` | 🟢 Completed | Edge-to-edge, frosted glass backdrop blur, Inertia routes |
| ~~Sample 3D Model WebGL Demo~~ | Frontend | ⚪ Removed | Ditiadakan; scrollytelling murni animasi layer UI hardware-accelerated |

### 9. Testing & Data Seeding
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| `SystemSettingSeeder` | Database Seeder | 🟢 Completed | Seed nilai default kuota Free & Pro Tier |
| Model Factories (User, Project, Comment, Subscription) | Database Factory | 🟢 Completed | Support UUID generation |
| Model Factories (ProjectClient, ChatMessage) | Database Factory | 🟢 Completed | |
| Auth Domain Tests | `Domains/Auth` | 🟢 Completed | Login, Register, Google OAuth, Invitation Email Matching |
| Project Domain Tests | `Domains/Project` | 🟢 Completed | Upload, Quota Exceeded, Draco Job, Edit, Revoke Client |
| Client Invitation & Access Control Tests | `Domains/Project` & `Domains/Auth` | 🟢 Completed | Undang email, 403 email tidak match, revoke |
| Revision Limit & Gatekeeper Tests | `Domains/Project` & `Domains/Comment` | 🟢 Completed | Pemblokiran revisi jika kuota habis, increment counter |
| Project Update & File Upload Feature Tests | `Domains/Project` | 🟢 Completed | Update metadata, replace file 3D, otorisasi non-pemilik |
| Comment Spatial Pin Unit Tests | `Domains/Comment` | 🔴 Pending | Test Vector Coordinates & Raycaster |
| Chat Domain Feature Tests | `Domains/Chat` | 🔴 Pending | Test broadcasting, channel authorization |
| Billing & Webhook Feature Tests | `Domains/Billing` | 🔴 Pending | Test Midtrans Signature, Status Updates |

### 10. RAB & Lembar Kerja (`Domains/Rab`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Migration `plans.can_use_rab` + `rab_*` (6 migration) | Database | 🟢 Completed | Dijalankan 2026-10-07, semua tabel aktif di DB |
| Models: `RabDocument`, `RabItem`, `RabPriceItem`, `RabTemplate`, `RabTemplateItem`, `RabMapping` | Backend DDD | 🟢 Completed | HasUuids, casts(), relasi lengkap, helper isDraft/isFinal/calculateSubtotal |
| `RabAccessService` | Backend DDD | 🟢 Completed | canEdit/canView/canAccessProject/hasPlanAccess/authorizeEdit/authorizeView via SubscriptionLimitService |
| Form Requests RAB | Backend | 🟢 Completed | `StoreRabDocumentRequest`, `UpdateRabDocumentRequest`, `UpsertRabItemRequest`, `StorePriceItemRequest` (unique per user), `StoreRabTemplateRequest` |
| Actions Fase A | Backend DDD | 🟢 Completed | `RecalculateRabAction` (satu sumber kebenaran), `CreateRabDocumentAction` (salin template + snapshot harga), `UpdateRabDocumentAction`, `UpsertRabItemAction`, `DeleteRabItemAction`, `FinalizeRabDocumentAction` (finalize+reopen), `ToggleRabClientVisibilityAction`, `SavePriceItemAction`, `SaveRabTemplateAction` |
| Controllers + routes RAB (20 routes) | Backend | 🟢 Completed | `RabDocumentController`, `RabItemController`, `RabPriceItemController`, `RabTemplateController` — verified via `php artisan route:list` |
| UI Harga Satuan (`/rab/price-items`) | Frontend | 🟢 Completed | `Rab/PriceItems.vue` — grouped by category, search, CRUD modal, tombol FlaskConical ke AHSP |
| UI Template RAB (`/rab/templates`) | Frontend | 🟢 Completed | `Rab/Templates.vue` + `Rab/TemplateShow.vue` — grid card, CRUD modal, kelola item template |
| UI Daftar RAB per Project (`/projects/{id}/rab`) | Frontend | 🟢 Completed | `Rab/Index.vue` — daftar dokumen, buat baru dari template, upgrade notice, tombol Estimasi 3D |
| UI Editor RAB (`/projects/{id}/rab/{rab}`) | Frontend | 🟢 Completed | `Rab/Show.vue` — item CRUD per section, summary cards, finalize/reopen, toggle visibilitas klien, tombol Import CSV + Estimasi 3D + Export Excel |
| AHSP (Analisa Harga Satuan Pekerjaan) | Backend & Frontend | 🟢 Completed | `rab_price_item_components`, `RecalculatePriceItemAction`, `RabPriceItemComponentController`, `PriceItemComponents.vue` — 2026-10-07 |
| Mapping RAB (`/rab/mappings`) | Backend & Frontend | 🟢 Completed | `RabMappingController`, `Mappings.vue` (tipe: exact/wildcard/material), menu sidebar — 2026-10-07 |
| Menu RAB di AppSidebar | Frontend | 🟢 Completed | Icon `ClipboardList`, menu Mapping RAB (GitMerge), active state detection |
| Tombol RAB di Viewer | Frontend | 🟢 Completed | Dropdown hijau di top bar Viewer, daftar dokumen RAB visible ke klien |
| Tombol RAB & Lihat RAB di Dashboard | Frontend | 🟢 Completed | Tombol RAB di kartu proyek arsitek, tombol Lihat RAB di kartu klien |
| Pest tests Action RAB | Testing | 🔴 Pending | Dijadwalkan post-MVP |
| Fase B: Import Quantity Take-off CSV/Excel | Backend & Frontend | 🟢 Completed | `league/csv` 9.28 + `phpspreadsheet` 3.10, `TakeoffParserService`, `RabMappingService`, `ImportQuantityTakeoffAction`, wizard 3-step `Rab/Import.vue` — 2026-10-07 |
| Fase C: Estimator RAB dari `.glb` + Custom Props Blender | Frontend (Three.js) & Backend | 🟢 Completed | `useGlbQuantities.ts` (baca `mesh.userData` custom properties Blender), `ApplyGlbQuantitiesAction`, `GlbEstimator.vue` + panduan format Blender — 2026-10-07 |
| Export RAB Excel multi-sheet + kop surat | Backend | 🟢 Completed | Sheet 1 Rekapitulasi, Sheet 2 Detail RAB, Sheet 3+ AHSP. Kop: nama badan usaha center, alamat, telepon — 2026-10-07 |
| Import IFC | Backend | 🔴 Pending | Could Have |

### 11. Drawing 2D (`Domains/Drawing`) — Rencana Enterprise
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Migration `plans.can_use_drawing` + `drawing_sheets` + `drawing_elements` | Database | 🔴 Pending | Hanya Enterprise |
| Model `DrawingSheet`, `DrawingElement` | Backend DDD | 🔴 Pending | type: plan/elevation/section |
| `DrawingAccessService` (gating can_use_drawing) | Backend DDD | 🔴 Pending | |
| Editor Denah / Plan (konva.js) | Frontend | 🔴 Pending | Grid snap, toolbar: dinding/pintu/jendela/kolom/tangga/dimensi/teks |
| Editor Tampak / Elevation | Frontend | 🔴 Pending | |
| Editor Potongan / Section | Frontend | 🔴 Pending | |
| Library Simbol (pintu swing, jendela, kolom, tangga) | Frontend | 🔴 Pending | Pre-built konva shapes |
| Dimensi Otomatis & Grid Referensi | Frontend | 🔴 Pending | A/B/C... dan 1/2/3..., anotasi ukuran |
| Title Block / Kop Gambar dari Profil | Backend & Frontend | 🔴 Pending | Nama badan usaha, no. lembar, skala, tanggal |
| Export DXF (AutoCAD compatible) | Backend | 🔴 Pending | `nzcreations/dxf`, layer per tipe elemen |
| Export SVG | Backend | 🔴 Pending | Scalable, bisa dibuka Inkscape/browser |
| Undo/Redo | Frontend | 🔴 Pending | Stack history konva |

---

## 📝 Activity Logs

- **2026-09-16:** Inisialisasi struktur dokumen guide (`PRD.md`, `RTCF.md`, `RISE.md`, `DATABASE_SCHEMA.md`, `AI_INSTRUCTIONS.md`, `PROGRESS_TRACKER.md`).
- **2026-09-16:** Penambahan fitur *Client Revision Limit & Counter Display* untuk proteksi arsitek dari revisi berlebihan.
- **2026-09-16:** Sinkronisasi dokumen v2.3 — menambahkan kolom `max_revisions_allowed` & `current_revision_count` ke `projects`, menambahkan tabel `subscriptions`, memperbaiki referensi domain.
- **2026-09-17:** Sinkronisasi dokumen v2.4 — Domain baru `Domains/Chat`, Client Invitation by Email, Role & Account Model tanpa pilihan role saat registrasi.
- **2026-09-17:** Fondasi Arsitektur DDD & Database Migrations — seluruh migrasi MySQL UUID, Eloquent Domain Models, Factories, dan `SystemSettingSeeder`. 47 Pest tests lulus 100%.
- **2026-09-20:** 3D Viewer, Spatial Annotation Pin & Draggable Comments — Three.js WebGL viewport, OrbitControls, raycaster hit detection, pin comment popover, draggable cards + SVG leader lines, edit/unpin komentar, mobile touch navigation, revision counter badge. 75 Pest tests lulus 100%.
- **2026-09-20 (Update):** Edit Data Proyek & Pengelolaan Klien Dashboard — edit judul/deskripsi/revisi/file 3D dari modal, revoke client access, `ProjectUpdateTest.php`. 81 Pest tests lulus 100%.
- **2026-09-23:** LocalStorage Persistence di Viewer — posisi kartu komentar, status drawer, toggle annotasi tersimpan per `projectId` di `localStorage`.
- **2026-09-28:** Redesain Total Landing Page — Apple-Style + Scroll-Driven Scrollytelling. Eliminasi demo WebGL publik. `ScrollyExperienceSection.vue` (450vh, 5 stages), `ArchitecturalCinematicSection.vue` (4-layer anatomy), `FaqSection.vue`, full-width Navbar. Build Vite sukses, 81 Pest tests lulus 100%.
- **2026-09-28:** Sinkronisasi dokumen v2.5 — Update seluruh guide folder agar mencerminkan kondisi aplikasi PitchArch aktual: nama brand PitchArch, Laravel 12 (bukan 13), Inertia.js + Vue 3 (bukan Nuxt 3), MySQL (bukan PostgreSQL), Laravel Fortify (bukan Sanctum), local disk public (bukan Cloudflare R2), queue database driver (bukan Redis), tambah tabel `camera_presets`, email verification sudah diimplementasikan, Admin Panel sudah selesai, fitur billing sudah selesai (kecuali invoice PDF).
- **2026-10-07:** Sinkronisasi dokumen v2.6 — rancangan modul RAB (`Domains/Rab`) digabung ke PRD, DATABASE_SCHEMA, FOLDER_STRUCTURE, AI_INSTRUCTIONS, RTCF, RISE. Ditulis migration + Action Fase A (🟡, belum dijalankan/diuji di repo).
- **2026-10-07 (Update):** Implementasi RAB Fase A selesai penuh — 6 migration dijalankan, 6 models, RabAccessService, 5 Form Requests, 9 Actions (RecalculateRabAction sebagai satu sumber kebenaran), 4 Controllers, 20 routes aktif, 4 halaman Vue (PriceItems, Templates, Index, Show), menu sidebar. Vite build sukses 3331 modules, PHP syntax check 18 file OK.
- **2026-10-07 (Update):** Implementasi RAB Fase B selesai — Install `league/csv` 9.28 + `phpoffice/phpspreadsheet` 3.10. `TakeoffParserService` (CSV auto-detect delimiter + XLSX), `RabMappingService` (exact/wildcard/material match + saveMappings), `ImportQuantityTakeoffAction` (3-step: preview/dryRun/execute). `ImportTakeoffRequest`, 3 method baru di `RabDocumentController`, 4 routes import, halaman wizard `Rab/Import.vue`. Vite build sukses.
- **2026-10-07 (Update):** Implementasi RAB Fase C selesai — `useGlbQuantities.ts` (load .glb via Three.js GLTFLoader, hitung luas permukaan via triangle area sum, volume via divergence theorem, count per named mesh, scale detection + warnings). `ApplyGlbQuantitiesAction` (buat items `is_estimate=true`, snapshot harga). `ApplyGlbQuantitiesRequest`. 2 routes baru (GET glb-estimator + POST from-glb). Halaman `Rab/GlbEstimator.vue` (auto-load .glb, tabel per mesh, checkbox include, basis selector area/volume/count/length, price item picker modal, sticky submit bar). Tombol "Estimasi 3D" di Show.vue + Index.vue. Vite build 252 modules, 0 error.
- **2026-10-07 (Update):** Implementasi AHSP (Analisa Harga Satuan Pekerjaan) selesai — migration `add_ahsp_columns_to_rab_price_items` (overhead_percent, has_components) + `create_rab_price_item_components` (tenaga/bahan/peralatan, koefisien, amount). Model `RabPriceItemComponent`, update `RabPriceItem` (+components relation, +computeUnitPrice). `RecalculatePriceItemAction` (saveComponent + execute). `RabPriceItemComponentController` (show/store/update/destroy/updateOverhead). 5 routes AHSP. Halaman `PriceItemComponents.vue` (tabel AHSP standar, grouped per tipe, overhead inline, preview amount). Tombol FlaskConical di `PriceItems.vue`. `ExportRabAction` multi-sheet: Sheet 1 Rekapitulasi, Sheet 2 Detail RAB, Sheet 3+ AHSP per item. Build 256 modules, 0 error.
- **2026-10-07 (Update):** Mapping RAB selesai — `RabMappingController` (CRUD), routes `/rab/mappings`, halaman `Rab/Mappings.vue` (filter by type, search, modal tipe exact/wildcard/material + basis selector). Menu "Mapping RAB" di sidebar (icon GitMerge).
- **2026-10-07 (Update):** Profil Arsitek & Wilayah Indonesia selesai — migration `add_profile_columns_to_users_table` (company_type/name, phone, wilayah 8 kolom, address, postal_code). Install `laravolt/indonesia`, seed 38 provinsi/514 kota/7285 kecamatan/83762 desa. `IndonesiaRegionController` (route `/region/*`, publik, cache 24h). `useIndonesiaRegion.ts` (cascading, string ID = code). `AddressInput.vue`. `Settings/Profile.vue` diperbarui (badan usaha + preview kop + alamat). `HandleInertiaRequests` share semua field profil. `ExportRabAction.buildLetterhead` — kop surat center, nama badan usaha saja (tanpa nama personal), di semua sheet (Rekap + Detail + AHSP).
- **2026-10-07 (Update):** Sinkronisasi dokumen v2.7 — semua file guide (PRD, DATABASE_SCHEMA, FOLDER_STRUCTURE, AI_INSTRUCTIONS, RTCF, RISE, PROGRESS_TRACKER) diperbarui untuk mencerminkan kondisi sistem aktual. Ditambahkan rancangan Drawing 2D (belum diimplementasikan, menunggu perintah).
