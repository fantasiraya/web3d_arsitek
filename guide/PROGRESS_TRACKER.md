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
| System Settings & Plan Caching (Redis) | Backend & Infra | 🟢 Completed | `CacheKeys.php` safe tagging, `Plan::getActivePlansForDisplay()` TTL 1h, pemisahan Redis DB 1 cache / DB 2 session — 2026-10-15 |
| Admin Settings & API Key Config (`/admin/settings`) | Backend & Frontend | 🟢 Completed | Pengaturan API Key AI & general config — 2026-10-15 |

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
| Sun Simulation & Geographic Settings | Backend & Frontend | 🟢 Completed | Kolom `latitude`, `longitude`, `north_angle`, `solar_date` di `projects`, `UpdateProjectSunSettingsAction`, `useSunSimulation.ts` (SunCalc), preset pagi/siang/sore/malam, slider jam, mode malam & scan `LAMPU_*` — 2026-10-14 |
| 3D Canvas Snapshot Captures & Quota | Backend DDD & Frontend | 🟢 Completed | Tabel `project_captures`, `SaveCaptureAction`, `DeleteCaptureAction`, `ToggleCaptureVisibilityAction`, `ProjectCaptureController`, gating kuota per gambar via `SubscriptionLimitService`, Form Request `StoreCaptureRequest`, 7 Pest tests lulus — 2026-10-14 |
| 3D Captures Gallery & Lightbox | Frontend (`Viewer.vue`) | 🟢 Completed | Galeri per ruangan & per jam, preview Lightbox resolusi penuh, switch tampil klien, download PNG — 2026-10-14 |

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
| Migration `plans.can_use_drawing` + `drawing_sheets` + `drawing_elements` | Database | 🟢 Completed | UUID PK, FK cascade, index sort_order & user_id — selesai 2026-10-15 |
| Model `DrawingSheet`, `DrawingElement` | Backend DDD | 🟢 Completed | Types: plan/elevation/section, relationships, casts array/json — selesai 2026-10-15 |
| `DrawingAccessService` (gating can_use_drawing) | Backend DDD | 🟢 Completed | Akses khusus Enterprise (`plans.can_use_drawing = true`), otorisasi edit & view klien — selesai 2026-10-15 |
| Form Requests & Actions DDD | Backend DDD | 🟢 Completed | `CreateDrawingSheetAction`, `UpdateDrawingSheetAction`, `SyncDrawingElementsAction`, Form Requests — selesai 2026-10-15 |
| Menu Sidebar "Lembar Kerja 2D" | Frontend | 🟢 Completed | Icon `DraftingCompass`, badge ENT/CAD, gating modal upgrade jika non-Enterprise — selesai 2026-10-15 |
| Halaman Daftar Lembar Kerja (`/drawings`) | Frontend | 🟢 Completed | `Drawing/Index.vue`, filter per proyek & tipe (denah/tampak/potongan), modal lembar kerja baru — selesai 2026-10-15 |
| Editor CAD 2D Interaktif (Konva.js) | Frontend | 🟢 Completed | `Drawing/Editor.vue`, canvas vektor interaktif, grid snap, zoom/pan viewport, paper frame A1-A4 & Title Block / Kop Gambar — selesai 2026-10-15 |
| Architectural CAD Tools & Library Simbol | Frontend | 🟢 Completed | Dinding tebal, kotak ruangan, garis bebas (L), simbol pintu swing arsitek, bukaan jendela, kolom beton, tangga, perabot/furniture, dimensi otomatis, label ruangan, eraser — selesai 2026-10-15 |
| Multi-Paper Frame Canvas & AutoCAD Model Space | Frontend | 🟢 Completed | Mode Frame Kertas vs AutoCAD Model Space (∞). Dukungan tambah kertas (+ Kertas ke Kanvas) & hapus kertas independen dari kanvas — selesai 2026-10-16 |
| Figma-Style Live Paper Drag Resize & LocalStorage Cache | Frontend | 🟢 Completed | Drag handle interaktif di tepi kanan, bawah, dan sudut kanan bawah dengan update non-destruktif tanpa destroy Konva node. Dimensi kertas tersimpan instan di `localStorage` (`cad_paper_sizes_{sheet_id}`) + auto-sync DB — selesai 2026-10-16 |
| Kop Gambar (Title Block) Auto Word-Wrap & Layer Isolation | Frontend | 🟢 Completed | Teks panjang wrap otomatis dengan tinggi adaptif tanpa tembus kotak, layer diletakkan di atas grid (`paperBgLayer` → `gridLayer` → `paperLayer`) agar grid tidak memotong teks — selesai 2026-10-16 |
| Clean PDF Direct Download & Print via Isolated Iframe | Frontend | 🟢 Completed | Unduh PDF presisi sesuai dimensi kertas ISO (A4–A0) dan cetak langsung browser via iframe tersembunyi tanpa bleed atau gangguan elemen UI editor — selesai 2026-10-16 |
| Responsive 2-Row Header & Bottom HUD dengan Overflow Cues | Frontend | 🟢 Completed | Header tools 2 baris teratur dengan deteksi horizontal overflow & pulsing indicator ("Menu Lainnya ▶"), bottom HUD dengan scroll cues kiri-kanan — selesai 2026-10-16 |
| Non-Colliding Left Sidebar dengan Indikator Scroll Vertikal | Frontend | 🟢 Completed | Sidebar tools diberi batas aman `bottom-18 sm:bottom-20 z-30` sehingga tidak menabrak bottom HUD pada mobile/layar kecil, ditambah scroll cue panah atas/bawah (`▲` / `▼`) — selesai 2026-10-16 |
| Panduan & Pintasan Keyboard CAD Lengkap | Frontend | 🟢 Completed | Modal bantuan CAD (`showHelpModal`) disinkronkan 100% dengan implementasi keyboard: Select (V), Pan (H/Spasi), Fit (Z), Tools (W, R, L, D, J, C, S, M, T, E), Delete/Backspace, Esc, Ctrl+A/C/V/D/Z/Y — selesai 2026-10-16 |
| Export DXF (AutoCAD compatible) | Backend | 🟢 Completed | `ExportDxfAction`, standar AutoCAD R12/2000 ASCII compliant, layer terpisah (WALLS, DOORS, WINDOWS, DIMENSIONS, TEXT) — selesai 2026-10-15 |
| Export SVG & PNG | Backend & Frontend | 🟢 Completed | `ExportSvgAction` (vektor scalable) & export gambar raster PNG resolusi tinggi — selesai 2026-10-15 |
| Pest Automated Tests (Drawing Domain) | Testing | 🟢 Completed | `tests/Feature/Domains/Drawing/DrawingTest.php` (5 tests, 21 assertions lulus 100%) — selesai 2026-10-15 |

### 12. AI Vision Studio Domain (`Domains/VisionStudio`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `vision_creations` | Database | 🟢 Completed | UUID PK, `user_id` FK cascadeOnDelete, `title`, `room_name`, `prompt_type` ('image'/'video'), `file_path`, `end_file_path`, `file_size_bytes`, `width`, `height`, `prompt`, `negative_prompt`, `metadata`, index `(user_id, created_at)` — 2026-10-15 |
| Model `VisionCreation` | Backend DDD | 🟢 Completed | `HasUuids`, casts, relasi `user()`, accessor `url` & `end_url`, hook pembersihan storage saat model dihapus (`Storage::disk('public')->delete`) — 2026-10-15 |
| Controller & Actions `VisionStudioController` | Backend | 🟢 Completed | Endpoints `/vision-studio` (index), `/vision-studio/generate` (POST), `/vision-studio/load/{creation}` (GET), `/vision-studio/creations/{creation}` (DELETE) — 2026-10-15 |
| Feature Gating (Khusus Berbayar: Pro, Enterprise & Super Admin) | Backend DDD & Middleware | 🟢 Completed | `SubscriptionLimitService::canAccessVisionStudio($user)`, middleware `CheckVisionStudioAccess` (`vision.access`), redirect free tier ke `/plans` dengan pesan upgrade atau 403 API — 2026-10-15 |
| Navigasi Sidebar & Pricing Gating | Frontend | 🟢 Completed | `AppSidebar.vue` menampilkan lock icon + badge `PRO` bagi user Free; `PricingSection.vue` mencantumkan fitur AI Vision Studio — 2026-10-15 |
| Galeri Kreasi AI Independen | Frontend & Backend | 🟢 Completed | Galeri kreasi AI terpisah penuh dari 3D project captures, tanpa opsi switch tampil ke klien, sinkronisasi hapus storage — 2026-10-15 |
| LocalStorage Studio Persistence | Frontend (`VisionStudio/Index.vue`) | 🟢 Completed | Form generator prompt, selected presets, dan riwayat kreasi tersimpan di browser `localStorage` agar tidak reset saat refresh halaman — 2026-10-15 |

### 13. High Concurrency, Cache Architecture & Performance (1 Juta Pengguna)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Pemisahan Database Redis (Cache vs Session) | Backend Infra | 🟢 Completed | `config/database.php` & `config/session.php`: DB 0 default, DB 1 cache (`REDIS_CACHE_DB=1`), DB 2 session (`REDIS_SESSION_DB=2`) agar `cache:clear` tidak mendrop session user aktif — 2026-10-15 |
| Safe Driver-Agnostic Tagging Wrapper | Backend Support | 🟢 Completed | `CacheKeys::tags()` mendukung Redis native tagging dengan graceful fallback untuk driver non-taggable — 2026-10-15 |
| Caching Master Data Plans | Backend DDD | 🟢 Completed | `Plan::getActivePlansForDisplay()` dicache di Redis (TTL 1 jam, tag `plans`). Invalidation otomatis pada `Admin/PlanController` dan `ChangeUserPlanAction` — 2026-10-15 |
| Eliminasi Root Cause N+1 Queries Global | Backend Infra | 🟢 Completed | Menghapus event listener `User::retrieved` di `AppServiceProvider.php` yang memicu puluhan query SQL `loadMissing('roles')` setiap kali user di-hydrate — 2026-10-15 |
| Eager Loading di Controller Admin | Backend | 🟢 Completed | Eager load relasi `user.activeSubscription.planModel`, `user.planOverride`, `invitedClients`, dan `withCount('projects')` di `Admin/ProjectController` dan `Admin/SubscriptionController` — 2026-10-15 |
| Distributed Atomic Locks (Race Condition Prevention) | Backend DDD | 🟢 Completed | `Cache::lock(...)->block(5)` pada `CreateProjectAction`, `SaveCaptureAction`, dan `PinCommentAction` untuk menjamin kuota tidak jebol saat concurrency tinggi — 2026-10-15 |
| Payment Concurrency & Idempotency Protection | Backend | 🟢 Completed | `CheckoutController::paymentCallback` dan webhook notification dibungkus `DB::transaction` dengan `lockForUpdate()` pada record order pending untuk mencegah aktivasi ganda — 2026-10-15 |

#### 14. Architectural Working Booklet, Direct AI Render & Interactive 3D Previews (`Domains/Project`, `Domains/Drawing`, `Domains/Rab`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Migrasi Skema Booklet `project_captures` | Database | 🟢 Completed | Kolom `ai_rendered_path`, `drawing_sheet_id` (FK `drawing_sheets`, nullOnDelete), `room_element_id`, `description`, `sort_order`, auto-cleanup file fisik saat delete — selesai 2026-10-16 |
| Migrasi Informasi Klien `projects` | Database | 🟢 Completed | Kolom `client_name` dan `client_address` di `projects` untuk kebutuhan Kop Gambar & Title Block Booklet — selesai 2026-10-16 |
| Upload Render AI & Mode Switcher Galeri | Backend DDD & UI | 🟢 Completed | `UploadAiRenderedCaptureAction`, `UploadAiRenderedCaptureRequest`, route upload & delete render AI, prioritas toggle "Render AI" vs "3D Asli" — selesai 2026-10-16 |
| Direct AI Architectural Render Generation (Gemini API) | Backend DDD & UI | 🟢 Completed | `GenerateAiRenderFromCaptureAction` memanfaatkan Google Gemini API multimodal vision, ketaatan 100% pada geometri 3D, modal generate responsif dengan pilihan mood & prompt kustom, tombol batch generate semua capture (`POST /projects/{project}/captures/generate-all-ai`) — selesai 2026-10-16 |
| Sinkronisasi Nama Ruangan 2D ↔ 3D | Backend & Frontend | 🟢 Completed | Mengambil label ruangan dari denah 2D (`drawing_elements`), datalist autocomplete di 3D Viewer & auto-bind `drawing_sheet_id`/`room_element_id` saat capture — selesai 2026-10-16 |
| Auto Key-Plan Renderer (`KeyPlanCanvas.vue`) | Frontend (SVG) | 🟢 Completed | Denah mini otomatis menghitung bounding box, merender denah monokrom dengan highlight warna oranye menyala (`#EA580C`) dan badge ruangan aktif persis standar lembar kerja studio arsitek — selesai 2026-10-16 |
| Bidirectional Code & Reference System (3D ↔ 2D) | Backend & Frontend | 🟢 Completed | Penambahan kolom `code` pada `project_captures` (auto-code `R-01`/`EXT-01` + custom editing), group filter (`R-*`, `EXT-*`, `Kustom`) di galeri 3D, Select2/Combobox pencarian referensi visual di Inspector CAD Editor 2D (top 5 default, badge `📷 R-01` di kanvas Konva), dan indikator visual di daftar `/drawings` — selesai 2026-10-16 |
| Halaman Booklet Builder & PDF Preview (`Booklet.vue`) | Frontend & Backend | 🟢 Completed | Layout A3/A4 Landscape studio: Halaman 1 Cover + watermark `DESIGN BY [company_name]`, Halaman 2 Master Plan Denah 2D 80/20, Halaman 3+ Eksterior & Interior per ruangan (Visual 3D/AI + Key Plan Locator + Kop Gambar Title Block 20%), 1-Click CSS print engine — selesai 2026-10-16 |
| Generator Draf RAB Per Ruangan dari Lembar Kerja | Backend DDD & UI | 🟢 Completed | `GenerateRabFromWorksheetAction`, `POST /projects/{project}/rab/from-worksheet`, mengelompokkan item RAB per `section = Nama Ruangan` (`TERAS`, `KASIR`, `AREA VIP`, dll.), auto-match master harga arsitek, `is_estimate = true`, kalkulasi total via `RecalculateRabAction` — selesai 2026-10-16 |
| Interactive 3D Project Cards & Dashboard Preview | Frontend & Backend | 🟢 Completed | Komponen modular `ProjectCard.vue` dan `Project3DPreview.vue`, viewport mini Three.js WebGL interaktif (auto-rotate, mouse orbit, pencahayaan) pada Dashboard (`Dashboard.vue`) dan galeri proyek (`Projects/Index.vue`) — selesai 2026-10-16 |
| Seeder Proyek Arsitektur Nyata (Semangkok Ae) | Database Seeder | 🟢 Completed | `SemangkokAeBookletSeeder.php` menyajikan data proyek denah 2D lengkap dengan elemen dinding, pintu, jendela, kolom, furnitur, label ruangan, 3D captures, dan render AI fotorealistik — selesai 2026-10-16 |
| Resolusi Menyeluruh TypeScript Error & Strict Typing | Codebase Quality | 🟢 Completed | Menghilangkan file legacy `three.d.ts`, memperbaiki typing `ref`, `computed`, event handlers, props di seluruh Vue komponen (`Booklet.vue`, `Editor.vue`, `Index.vue`, `Dashboard.vue`, `Viewer.vue`, `GlbEstimator.vue`, `Input.vue`, `useConfirm.ts`, `useGlbQuantities.ts`), build Vite 3771 modul 0 error — selesai 2026-10-16 |
| Automated Pest Tests Suite | Backend Testing | 🟢 Completed | `UploadAiRenderedCaptureTest`, `BookletAccessTest`, `GenerateRabFromWorksheetTest`, 15 feature tests lulus 100%, total 146 tests seluruh sistem lulus tanpa error — selesai 2026-10-16 |

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
- **2026-10-14:** Implementasi Pencahayaan Real-time Matahari (Three.js) & 3D Snapshot Captures — `useSunSimulation.ts` (kalkulasi posisi matahari SunCalc dari jam, tanggal, latitude, longitude, dan arah utara), bayangan dinamis Three.js (DirectionalLight shadows), background lerp, mode malam otomatis & scan objek `LAMPU_*` (emissive + point lights). Form preset pagi/siang/sore/malam + slider jam interaktif. Migration kolom sun settings di `projects` & tabel `project_captures`. Action DDD `SaveCaptureAction` (gating kuota per gambar, otorisasi hanya arsitek pemilik), `DeleteCaptureAction`, `ToggleCaptureVisibilityAction`, `UpdateProjectSunSettingsAction`. Controller `ProjectCaptureController` + 5 routes web. Frontend Viewer UI: floating panel pencahayaan matahari, modal ambil gambar snapshot canvas (viewport / 1080p FHD / 4K UHD, integrasi camera presets), drawer galeri gambar terstruktur per ruangan & per jam, lightbox modal preview, unduh PNG. 7 Pest tests lulus 100%.
- **2026-10-14 (Update):** Three.js Photorealistic Post-Processing (Studio HD Render) & Admin Quota Controls — `usePostProcessing.ts` memanfaatkan `EffectComposer`, `GTAOPass` (Ground Truth Ambient Occlusion untuk bayangan kontak sudut dinding/lantai arsitektural), `UnrealBloomPass` (pendaran cahaya lembut jendela & lampu), `OutputPass`, dan `ACESFilmicToneMapping`. Tombol interaktif "Studio HD" pada toolbar atas & toggle preview di modal capture snapshot canvas dengan dukungan resolusi offscreen Viewport, 1080p Full HD, dan 4K Ultra HD (3840×2160). Admin backoffice (`/admin/plans`) diperbarui dengan kontrol kuota capture untuk Free Tier & Pro Tier. 116 Pest tests lulus 100%.
- **2026-10-14 (Update):** Tingkat 2: True GPU Ray-Tracing & Path-Tracing (`three-gpu-pathtracer`) — Composable `usePathTracer.ts` mengintegrasikan `WebGLPathTracer` dan `three-mesh-bvh` untuk kalkulasi Global Illumination (GI) fisik, pantulan cermin nyata, dan soft shadows tanpa artefak shadow map. Mode switcher pada modal Ambil Gambar ("Real-Time Studio" vs "Path-Tracing PRO"), pilihan kualitas sampel (15 Cepat, 30 Standar, 60 Ultra), overlay progress bar interaktif dengan animasi persentase & hitungan sampel real-time, tombol batalkan render, dan auto-fallback resolusi hingga 4K Ultra HD. 116 Pest tests lulus 100%, Vite build sukses.
- **2026-10-14 (Update):** AI Prompt Generator & Auto-Save on Blur untuk 3D Captures — Generator prompt arsitektural otomatis berbasis konteks 3D (ruangan, jam pencahayaan matahari, engine render, judul proyek). Editable textarea di modal ambil gambar dengan tombol Salin Prompt & Reset. Kolom `prompt` di tabel `project_captures`, `UpdateCapturePromptAction` + Form Request + route `PATCH /projects/{project}/captures/{capture}/prompt`. Tampilan prompt di Drawer Galeri & Lightbox modal dengan fitur **Auto-Save otomatis saat kursor keluar (blur)** tanpa tombol simpan manual, dilengkapi indikator status visual (Menyimpan... / Tersimpan ✓). 119 Pest tests lulus 100%.
- **2026-10-15:** Implementasi AI Vision Studio (`Domains/VisionStudio`) & Pengaturan API Key Admin — Penambahan menu AI Vision Studio dengan kapabilitas Image & Video generative prompt arsitektural. Pembuatan migration `vision_creations`, Model `VisionCreation` (dengan hook pembersihan storage file), dan `VisionStudioController`. Integrasi pengaturan API key AI di admin panel (`/admin/settings`). Fitur galeri mandiri terpisah dari 3D capture (tanpa opsi tampil ke klien). Dukungan persistensi parameter prompt dan state generator ke browser `localStorage`.
- **2026-10-15 (Update):** Feature Gating AI Vision Studio Berbayar (Paid-Only) — Fitur AI Vision Studio diubah menjadi eksklusif untuk tier berbayar (Pro, Enterprise, dan Super Admin). Penambahan method `canAccessVisionStudio` di `SubscriptionLimitService`, middleware `CheckVisionStudioAccess` (`vision.access`), lock badge di `AppSidebar.vue`, serta benefit card di `PricingSection.vue`.
- **2026-10-15 (Update):** Arsitektur Caching Skala 1 Juta Pengguna & Optimasi Redis — Konfigurasi multi-database Redis (`config/database.php` DB 1 untuk cache, DB 2 untuk session user), helper `CacheKeys.php` dengan safe-tagging wrapper, caching master data paket langganan aktif `Plan::getActivePlansForDisplay()` dengan TTL 1 jam dan auto-invalidation pada edit admin/plan changes. Perbaikan fallback session connection agar tidak crash pada driver non-redis saat build Wayfinder/Vite.
- **2026-10-15 (Update):** Proteksi Race Condition & Eliminasi N+1 Query Sistemik — Penghapusan hook berat `User::retrieved` di `AppServiceProvider.php` yang mengeliminasi puluhan query overhead per request. Eager loading relasi admin pagination di `ProjectController` dan `SubscriptionController`. Implementasi Redis distributed atomic lock (`Cache::lock()->block()`) pada `CreateProjectAction`, `SaveCaptureAction`, dan `PinCommentAction`. Penerapan `DB::transaction` + `lockForUpdate()` pada penanganan webhook dan callback pembayaran Midtrans di `CheckoutController`. 132 Pest tests lulus 100%.
- **2026-10-15 (Update):** Implementasi Penuh Lembar Kerja 2D (Drawing CAD — `Domains/Drawing`) — Menu baru "Lembar Kerja 2D" di `AppSidebar.vue` dengan icon `DraftingCompass` dan gating Enterprise (`plans.can_use_drawing = true`). Domain backend lengkap: migrasi `drawing_sheets` & `drawing_elements`, model Eloquent, `DrawingAccessService`, `CreateDrawingSheetAction`, `UpdateDrawingSheetAction`, `SyncDrawingElementsAction`, `ExportDxfAction` (AutoCAD ASCII R12 standard), `ExportSvgAction`. Frontend: halaman daftar lembar kerja `Drawing/Index.vue` & editor CAD kanvas interaktif `Drawing/Editor.vue` berbasis `konva.js` (grid snap, paper frame A1-A4 & Kop Gambar arsitek, tool dinding tebal, kotak ruangan, pintu swing, jendela, penggaris dimensi otomatis, label ruangan, zoom/pan viewport, export DXF/SVG/PNG). 137 Pest tests lulus 100%.
- **2026-10-15 (Update):** Dynamic Resizable Sheet (Figma Frames & AutoCAD Model Space) — Penambahan kolom `width` & `height` pada `drawing_sheets` melalui migrasi `add_dimensions_to_drawing_sheets`. Editor kanvas interaktif `Drawing/Editor.vue` dilengkapi: (1) Handle resize interaktif di sudut kanan bawah dan tepi lembar kerja (drag to resize dinamis seperti frame Figma) dengan live dimension tooltip, (2) Kop Gambar arsitektural responsif yang otomatis menempel di sudut kanan bawah lembar saat ukuran berubah, (3) Kontrol preset ISO (A0–A4 & Custom) serta numeric inputs `W` dan `H` (px/mm) pada top bar, (4) Switcher mode "Frame Kertas" vs "AutoCAD Model Space (∞)" untuk kanvas tanpa batas, (5) Auto-sync ukuran lembar kerja ke database secara atomik. 138 Pest tests lulus 100%.
- **2026-10-16:** Penyempurnaan Multi-Paper, Export Bersih, Responsivitas Mobile & Audit Hotkeys CAD —
  1. **Multi-Paper Frame Canvas:** Dukungan tambah kertas (+ Kertas ke Kanvas) dan tombol hapus kertas independen dari kanvas (`removePaperSheet`).
  2. **Non-Destructive Live Drag Resize & LocalStorage Cache:** Interaktivitas drag handle tepi dan sudut kertas tanpa merusak (destroy) Konva node saat `dragmove`, dilengkapi penyimpanan ukuran instan ke `localStorage` (`cad_paper_sizes_{sheet_id}`) agar dimensi tidak ter-reset saat refresh sebelum sinkron ke database.
  3. **Kop Gambar (Title Block) Text Wrapping & Layer Stacking:** Teks judul proyek dan instansi menggunakan `wrap: 'word'` dan tinggi baris adaptif agar tidak meluber keluar kotak; urutan layer kanvas diatur `paperBgLayer` → `gridLayer` → `paperLayer` sehingga garis grid tidak pernah memotong teks kop gambar.
  4. **Clean PDF & Isolated Iframe Print:** Mengatasi masalah kebocoran UI pada cetak dengan pembuatan hidden print iframe berukuran target kertas ISO murni (A4–A0), rendering resolusi tinggi tanpa elemen UI editor.
  5. **Header 2-Row & Bottom HUD Responsif:** Header tools 2 baris terorganisir dengan deteksi overflow horizontal dan indikator visual berkedip ("Menu Lainnya ▶"); navbottom HUD responsif dengan scroll cue kiri dan kanan.
  6. **Sidebar CAD Anti-Tabrakan & Scroll Vertikal:** Left sidebar diberi batas bawah aman `bottom-18 sm:bottom-20 z-30` sehingga tidak tumpang tindih dengan bottom HUD pada mode mobile / layar sempit, dilengkapi indikator panah scroll vertikal (`▲` / `▼`).
  7. **Sinkronisasi & Audit Panduan Hotkeys CAD:** Melengkapi modal panduan (`showHelpModal`) dengan pintasan Garis Bebas (`L`), Hapus Objek (`Delete`/`Backspace`), Batal Seleksi (`Esc`), dan Redo (`Ctrl+Y`/`Ctrl+Shift+Z`), terverifikasi 100% konsisten dengan keydown handler kanvas.
- **2026-10-16 (Update):** Implementasi Penuh Architectural Working Booklet PDF & Per-Room RAB Generator —
  1. **Database & Lifecycle Storage Cleanup:** Migrasi kolom `ai_rendered_path`, `drawing_sheet_id`, `room_element_id`, `description`, `sort_order` pada `project_captures` dengan hook penghapusan otomatis file fisik saat record dihapus.
  2. **Upload Render AI & Mode Prioritas:** Action `UploadAiRenderedCaptureAction` & route `POST /projects/{project}/captures/{capture}/ai-render` + toggle prioritas "Render AI" vs "3D Asli" di Booklet dan 3D Viewer.
  3. **Sinkronisasi Nama Ruangan 2D ↔ 3D:** Data elemen `room_label` dari denah 2D dibaca otomatis oleh `ViewerController` dan disediakan sebagai datalist autocomplete pada form snapshot 3D serta edit nama ruangan capture, otomatis mengikat `drawing_sheet_id` dan `room_element_id`.
  4. **Auto Key-Plan Renderer (`KeyPlanCanvas.vue`):** Mini denah berbasis SVG responsif beresolusi vektor tajam yang menghitung bounding box denah 2D secara otomatis, menampilkan seluruh denah dalam palet monokrom bersih, dan **menyorot ruangan aktif dengan warna oranye menyala (`#EA580C`) beserta badge namanya** persis seperti standar studio arsitek (referensi proyek TRIVES).
  5. **Halaman Booklet Builder & PDF Engine (`Drawing/Booklet.vue`):** Tata letak ISO A3/A4 Landscape standar studio: Halaman 1 Cover + watermark monumental `DESIGN BY [company_name]`, Halaman 2 Master Floor Plan Denah 2D 80/20, Halaman 3+ Eksterior & Interior per ruangan (Visual 3D/AI 68% + Nama Ruangan & Catatan + Key Plan Locator + Kop Gambar Title Block 20%), dilengkapi CSS print engine `@page { size: A3 landscape; margin: 0; }` untuk 1-Click download/cetak PDF tanpa pemotongan elemen.
  6. **Worksheet to Per-Room RAB Generator:** Action `GenerateRabFromWorksheetAction` & endpoint `POST /projects/{project}/rab/from-worksheet` yang otomatis mengelompokkan item RAB per `section = Nama Ruangan` (`TERAS`, `KASIR`, `AREA VIP`, dll.), memetakan ke harga satuan arsitek, dan mengkalkulasi ulang subtotal, overhead 10%, PPN 11%, dan total melalui `RecalculateRabAction`.
  7. **Testing:** 15 Pest Feature Tests baru lulus 100%, total 146 tests seluruh sistem lulus tanpa error. Vite build sukses.
- **2026-10-16 (Update):** Sistem Kode Otomatis & Sinkronisasi Referensi Dua Arah (3D ↔ 2D CAD) —
  1. **Auto Code Generation & Custom Editing (`Viewer.vue`):** Penambahan kolom `code` pada `project_captures` (migrasi MySQL). Kode di-generate otomatis saat snapshot diambil (`EXT-01`, `EXT-02` untuk tampak/fasad eksterior dan `R-01`, `R-02` untuk interior ruangan) atau diisi custom oleh arsitek. Dapat diedit langsung di Lightbox dengan auto-save on blur (`autoSaveCaptureCode`).
  2. **Filter Pengelompokan Kode di Galeri 3D:** Drawer galeri dilengkapi filter pengelompokan kode (`Semua Kode`, `R-* (Interior)`, `EXT-* (Eksterior)`, `Kustom`), serta badge kode kontras di atas thumbnail snapshot.
  3. **Pencarian Referensi Visual 3D (Select2 / Combobox) di CAD Editor (`Drawing/Editor.vue`):** Pada panel Inspector properti elemen saat label ruangan (`room_label`) atau objek denah dipilih, disediakan input pencarian live combobox (default menampilkan 5 referensi teratas lengkap dengan thumbnail, badge kode, nama ruangan, dan jam). Item yang sudah tertaut otomatis diberi penanda status: badge **"Sudah Digunakan"** (disertai nama ruangan tempat visual tersebut terpasang) atau **"Sedang Dipakai"** untuk elemen yang sedang aktif, sehingga arsitek terhindar dari duplikasi penautan.
  4. **Badge Visual pada Kanvas CAD Konva:** Elemen label ruangan yang terhubung ke snapshot 3D menampilkan badge oranye elegan `📷 R-01` langsung di atas label denah untuk kejelasan visual instan.
  5. **Indikator Referensi pada Daftar Lembar Kerja (`Drawing/Index.vue`):** Kartu denah menampilkan badge jumlah visual 3D terhubung (`📷 {count} 3D`) dan tombol akses cepat langsung ke Booklet PDF. Build Vite (3767 modules) sukses, 146 Pest tests lulus 100%.
  6. **Penentuan Cover Booklet & Integrasi Key Plan (`Booklet.vue` & `KeyPlanCanvas.vue`):** Penambahan pemilih gambar Cover di toolbar & floating cover preview (arsitek bebas memilih gambar spesifik atau default otomatis memprioritaskan kode `EXT-*` / tampak depan). Pada halaman interior per ruangan, badge kode (`R-01`) ditampilkan tebal di samping nama ruangan dan dihubungkan secara akurat ke Key Plan mini locator denah via `activeCode` dan `activeElementId`.
- **2026-10-16 (Update):** Direct AI Render Generation (Google Gemini API), Interactive 3D Previews & System Polish —
  1. **Direct AI Architectural Render Generator:** Action `GenerateAiRenderFromCaptureAction` mentransformasikan snapshot 3D WebGL menjadi render fotorealistik arsitektur via Google Gemini API dengan 100% adherence pada geometri asli. Tersedia tombol individual render dan tombol batch **"Generate Semua"** (`POST /projects/{project}/captures/generate-all-ai`), modal interaktif responsif untuk memilih mood cahaya dan prompt arsitektural.
  2. **Interactive 3D Project Cards:** Komponen modular `ProjectCard.vue` dan `Project3DPreview.vue` dipasang pada Dashboard (`Dashboard.vue`) dan halaman Proyek (`Projects/Index.vue`), menampilkan mini WebGL viewport dengan auto-rotation lembut dan kontrol mouse orbit interaktif.
  3. **Migrasi Informasi Klien Proyek:** Menambahkan kolom `client_name` dan `client_address` pada tabel `projects` (`2026_10_16_130000_add_client_fields_to_projects_table.php`) untuk sinkronisasi otomatis kop surat title block booklet dan kartu proyek.
  4. **Penyempurnaan Booklet & KeyPlan:** Perbaikan layout KeyPlan agar tidak terpotong pada rasio kanvas ekstrem, perbaikan logika drag lembar denah, validasi keharusan menghubungkan gambar sebelum membuka booklet viewer.
  5. **Tabel RAB Show.vue:** Penyesuaian layout dan styling tabel rincian RAB agar lebih elegan dan informatif.
  6. **TypeScript Strict Type Polish:** Menghilangkan error TypeScript di seluruh halaman dan komponen (`Input.vue`, `useConfirm.ts`, `useGlbQuantities.ts`, `Dashboard.vue`, `Booklet.vue`, `Editor.vue`, `Index.vue`, `Viewer.vue`, `GlbEstimator.vue`, `VisionStudio/Index.vue`), menghapus `three.d.ts` yang usang. Seluruh build Vite (3771 modul) dan test suite Pest (146 tests, 529 assertions) lulus 100% sempurna tanpa error.




