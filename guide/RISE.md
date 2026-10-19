> **v3.1 Note:** Ditambahkan langkah 21-25 (Working Booklet PDF & Key Plan Canvas, Bidirectional 2D/3D Code System, Direct AI Render Generation, Worksheet to Per-room RAB Generator, Interactive 3D Project Cards).
>
> **v3.0 Note:** Langkah 17 (Drawing 2D) telah selesai diimplementasikan.
>
> **v2.9 Note:** Ditambahkan langkah 18-20 (Simulasi Pencahayaan & Captures, AI Vision Studio, Redis Multi-DB Cache & Distributed Concurrency Locking).
>
> **v2.8 Note:** Ditambahkan langkah 18 (Pencahayaan Matahari Real-Time Three.js + SunCalc & 3D Snapshot Captures).
>
> **v2.7 Note:** Ditambahkan langkah 15-17 (AHSP, Profil Arsitek & Wilayah, Drawing 2D).
>
> **v2.6 Note:** Ditambahkan langkah 13-14 (modul RAB).
>
> **v2.5 Note:** File ini diperbarui agar mencerminkan kondisi aplikasi **PitchArch** yang aktual. Perubahan dari v2.4: Laravel 13 → **Laravel 12**, Nuxt 3 → **Inertia.js + Vue 3** (monorepo), PostgreSQL → **MySQL**, Sanctum → **Laravel Fortify**, Cloudflare R2 → **local disk `public`**, queue Redis → **database driver**. Ditambahkan langkah untuk Camera Presets dan Email Verification.

[ROLE]
Bertindaklah sebagai Senior Tech Lead spesialis Laravel 12 (Domain-Driven Design / DDD), Inertia.js, dan Vue 3 dengan keahlian Three.js.

[INPUT]
Mengacu pada PRD v3.1 "PitchArch — Platform Presentasi & Feedback Arsitektur 3D" beserta spesifikasi teknis terkini:

- **Backend:** Laravel 12 (DDD), MySQL, Redis (DB 1 Cache, DB 2 Session), queue driver `database`, storage `public` disk lokal, `spatie/laravel-permission` (khusus `super_admin`), `midtrans/midtrans-php`, `laravel/socialite`, `laravel/reverb`.
- **Auth:** Laravel Fortify (registration, resetPasswords, emailVerification, twoFactor, passkeys). Google OAuth via Socialite (redirect-based, bukan token API).
- **Frontend:** Inertia.js + Vue 3 (monorepo Laravel, bukan Nuxt 3 terpisah). Routing via `routes/web.php`. Build via Vite. CSS: Tailwind CSS v4. UI: shadcn/ui Vue port.
- **3D Engine:** Three.js (internal viewer only — bukan di landing publik). GSAP + ScrollTrigger untuk scrollytelling landing.
- **Real-time:** laravel-echo + Reverb connector (`lib/echo.ts`).
- **Model Akun:** TIDAK ada pilihan role saat registrasi. Arsitek = kapabilitas default akun. Klien = status per-project via `project_clients`. Dual-capacity 1 akun didukung native.

[STEPS]
1. Tuliskan skema migration MySQL untuk tabel `users` (kolom `google_id`, `avatar`, Fortify columns: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` — TANPA kolom `role`), `system_settings`, `subscriptions`, `projects` (dengan `max_revisions_allowed`, `current_revision_count`), `project_versions`, `project_clients`, `chat_messages`, `comments`, dan `camera_presets` (kolom `project_id`, `user_id`, `name`, `position_x/y/z`, `target_x/y/z`).

2. Tuliskan `GoogleAuthController` pada `app/Http/Controllers/Auth/` untuk menangani Google OAuth via Socialite (redirect-based: `redirect()` + `callback()`), men-set `email_verified_at` otomatis, menjalankan `MatchInvitedClientEmailAction`, dan redirect via Inertia — TANPA pilihan role di request, TANPA generate Sanctum token (session-based Fortify).

3. Tuliskan Action Class `CreateProjectAction` pada `app/Domains/Project/Actions/` yang memvalidasi batasan kuota dinamis dari `system_settings` (baca dari database, pertimbangkan cache masa depan), cek `user_plan_overrides`, buat project, simpan file ke `storage/app/public/`, dispatch `DracoCompressionJob`.

4. Tuliskan `HandleWebhookAction` di `app/Domains/Billing/Actions/` untuk menangani notifikasi Midtrans `settlement` dan otomatis meng-update tabel `subscriptions` BESERTA `users.subscription_status = 'pro'` dalam satu DB transaction (dual-write, lihat `AI_INSTRUCTIONS.md` Section G).

5. Tuliskan composable Vue 3 `useRaycaster` (bisa di `Viewer.vue` atau composable terpisah) untuk menangkap koordinat interseksi 3D (X, Y, Z) dan Vector Normal saat mesh 3D diklik pada Three.js scene.

6. Tuliskan Middleware `RevisionLimitEnforcementMiddleware` pada `app/Http/Middleware/` yang mengecek `projects.current_revision_count >= max_revisions_allowed` sebelum mengizinkan client membuat pin komentar baru, dan melempar `403` sesuai format JSON di `AI_INSTRUCTIONS.md` Section F.

7. Tuliskan `InviteClientAction` pada `app/Domains/Project/Actions/` yang menerima email Klien, membuat/update baris `project_clients` (status `pending`), mengecek kuota `system_settings` untuk max invited clients, dan mengirim `ClientInvitationNotification` (email dari `noreply@pitcharch.id`). Sertakan juga `RevokeClientAccessAction`.

8. Tuliskan `ProjectClientAccessMiddleware` pada `app/Http/Middleware/` yang memvalidasi: request diteruskan HANYA jika user login adalah owner project ATAU punya baris `project_clients` dengan `status = 'accepted'`; selain itu lempar `403 Forbidden` sesuai format JSON.

9. Tuliskan Domain `Chat` lengkap: `ChatController` (`app/Domains/Chat/Controllers/`) dengan method `index` (histori + pagination) dan `store` (simpan + broadcast), Event `ChatMessageSent` (implements `ShouldBroadcast`, private channel `project.{project_id}.chat`) di `app/Events/`, dan isi `routes/channels.php` untuk otorisasi channel (validasi identik dengan Section H.3 `AI_INSTRUCTIONS.md`).

10. Tuliskan setup Laravel Echo di frontend (`resources/js/lib/echo.ts`) untuk menginisialisasi koneksi ke Laravel Reverb dan contoh penggunaan di `Viewer.vue` untuk mendengarkan `ChatMessageSent` event.

11. Tuliskan `CameraPresetController` pada `app/Domains/Project/Controllers/` (store, index, destroy) dan contoh penggunaan di `Viewer.vue` untuk menyimpan dan me-load camera angle presets.

12. Tuliskan komponen Landing Page bertema Apple Style dengan Scrollytelling (contoh: `HeroSection.vue`, `ScrollyExperienceSection.vue`) pada `resources/js/components/landing/` menggunakan GSAP ScrollTrigger untuk mem-pin section narasi produk — tanpa memuat WebGL/Three.js publik. Komponen di-compose di `resources/js/pages/Welcome.vue` dengan `LandingLayout`.

13. Tuliskan migration `plans.can_use_rab`, `rab_price_items`, `rab_templates`, `rab_template_items`, `rab_mappings`, `rab_documents`, `rab_items`, serta Action `CreateRabDocumentAction`, `UpsertRabItemAction`, `RecalculateRabAction`, `FinalizeRabDocumentAction`, `ToggleRabClientVisibilityAction` di `app/Domains/Rab/` dengan `RabAccessService` (gating + akses lihat klien). Patuhi AI_INSTRUCTIONS Section M.

14. Tuliskan `ImportQuantityTakeoffAction` + `TakeoffParserService` (CSV/XLSX, pemilihan kolom, pencocokan `rab_mappings`) dan composable `useGlbQuantities.ts` + `ApplyGlbQuantitiesAction` (luas/volume/count dari Three.js, baca `mesh.userData` custom properties Blender, item `is_estimate = true`).

15. Tuliskan AHSP (Analisa Harga Satuan Pekerjaan): migration `rab_price_item_components` (component_type: tenaga/bahan/peralatan, coefficient, unit_price, amount), kolom `overhead_percent` + `has_components` di `rab_price_items`, model `RabPriceItemComponent`, `RecalculatePriceItemAction` (satu sumber kebenaran: amount = coefficient × unit_price, unit_price_final = Σamount + overhead%), `RabPriceItemComponentController` (CRUD + updateOverhead), halaman `PriceItemComponents.vue` (tabel AHSP standar: No|Item|Kode|Satuan|Koefisien|Harga Satuan|Jumlah Harga, grouped Tenaga/Bahan/Peralatan, overhead inline, baris Harga Satuan Pekerjaan kuning). Tombol FlaskConical di `PriceItems.vue`. `ExportRabAction` multi-sheet: Sheet 1 Rekapitulasi, Sheet 2 Detail RAB, Sheet 3+ AHSP per item.

16. Tuliskan **Profil Arsitek & Wilayah Indonesia**: migration kolom baru di `users` (company_type, company_name, phone, province_id/name, city_id/name, district_id/name, village_id/name, address, postal_code), install `laravolt/indonesia` + seed 38 provinsi/514 kota/7285 kecamatan/83762 kelurahan, `IndonesiaRegionController` (route `/region/provinces|cities|districts|villages`, tanpa auth, publik, cached 24h dengan `.toArray()`), composable `useIndonesiaRegion.ts` (cascading, string ID = code laravolt, `window.location.origin` prefix fetch, watcher chain), komponen `AddressInput.vue`, update `ProfileValidationRules` + `User` fillable, update `HandleInertiaRequests` share semua field profil, update `Settings/Profile.vue` (form badan usaha + tipe picker + alamat + preview kop surat). Update `ExportRabAction` `buildLetterhead`: hanya nama badan usaha (tanpa nama personal), semua center, di Sheet 1 + Sheet 2 + AHSP sheets.

17. Tuliskan implementasi **Drawing 2D CAD (`Domains/Drawing` — Enterprise Only)**: domain `Domains/Drawing`, migration `drawing_sheets` (project_id, type: plan/elevation/section, scale, paper_size, orientation, grid_spacing, unit, width, height) + `drawing_elements` (sheet_id, type: wall/door/window/stair/column/dimension/text/line/room_label/furniture, data JSON, sort_order), frontend editor berbasis `konva.js` (`Drawing/Editor.vue`) dengan toolbar lengkap + multi-paper canvas (+ kertas ke kanvas, hapus kertas independen), mode Paper Space vs AutoCAD Model Space (∞), Figma-style live edge/corner drag resize handle dengan persistensi `localStorage`, kop gambar auto word-wrap & layer isolation, export DXF via `nzcreations/dxf` (`ExportDxfAction`), SVG (`ExportSvgAction`), PNG resolusi tinggi, dan clean PDF/print engine melalui hidden isolated iframe. Gating `plans.can_use_drawing = true`.

18. Tuliskan **Simulasi Pencahayaan Matahari Real-Time & 3D Snapshot Captures**: migration kolom sun simulation di `projects` (`latitude`, `longitude`, `north_angle`, `solar_date`) dan tabel `project_captures` (dengan kolom `prompt`), composable `useSunSimulation.ts` (SunCalc + arah utara + auto night mode `LAMPU_*`), composable `usePostProcessing.ts` (GTAOPass contact shadows, UnrealBloomPass, ACESFilmic ToneMapping), composable `usePathTracer.ts` (True GPU Path-Tracing, progressive samples), Action `SaveCaptureAction` (gating kuota per-gambar via `SubscriptionLimitService`, distributed lock), `DeleteCaptureAction`, `ToggleCaptureVisibilityAction`, `UpdateCapturePromptAction` (auto-save on blur), `ProjectCaptureController`, dan UI di `Viewer.vue` (modal snapshot, drawer galeri, lightbox preview).

19. Tuliskan **AI Vision Studio (`Domains/VisionStudio`)**: migration tabel `vision_creations`, model `VisionCreation` (hook `deleted` untuk hapus file fisik di storage), controller `VisionStudioController`, Form Request/Actions, middleware `CheckVisionStudioAccess` (`vision.access`) yang membatasi akses khusus pengguna berbayar (Pro, Enterprise, Super Admin) dan me-redirect Free tier ke `/plans`, lock badge di `AppSidebar.vue`, halaman Vue `VisionStudio/Index.vue` dengan persistensi browser `localStorage` dan galeri mandiri terpisah dari 3D snapshot captures.

20. Tuliskan **Arsitektur Caching & Proteksi Konkurensi Skala Tinggi (1 Juta Pengguna)**: konfigurasi Redis multi-database (`REDIS_CACHE_DB=1` untuk cache, `REDIS_SESSION_DB=2` untuk session), helper `CacheKeys.php` dengan safe tagging wrapper, caching master data `Plan::getActivePlansForDisplay()` dengan TTL 1 jam + auto invalidation di `Admin/PlanController` dan `ChangeUserPlanAction`, implementasi Redis distributed atomic lock (`Cache::lock()->block(5)`) pada `CreateProjectAction`, `SaveCaptureAction`, dan `PinCommentAction`, `DB::transaction` + `lockForUpdate()` pada webhook pembayaran Midtrans di `CheckoutController`, serta eliminasi global `User::retrieved` listener dan penerapan eager loading eksplisit untuk mencegah N+1 queries.

21. Tuliskan **Architectural Working Booklet PDF & Auto Key-Plan Renderer (`Domains/Drawing` & `Domains/Project`)**: `BookletController.php`, route `/projects/{project}/booklet`, halaman builder `Drawing/Booklet.vue` dengan ISO A3/A4 landscape layout standar studio arsitektur (Cover dengan watermark, Master Floor Plan 80/20, per-room visual sheets 68% + catatan + Kop Gambar 20%), komponen SVG mini locator `KeyPlanCanvas.vue` dengan highlight oranye `#EA580C` untuk ruangan aktif, serta CSS print engine `@page { size: A3 landscape; margin: 0; }`.

22. Tuliskan **Sistem Kode & Referensi Dua Arah (3D ↔ 2D CAD)**: migration kolom `code` pada `project_captures` (auto-code `EXT-*`, `R-*`, custom edit), filter kategori kode pada drawer galeri 3D (`R-*`, `EXT-*`, `Kustom`), live combobox pencarian visual di Inspector CAD Editor 2D (`Drawing/Editor.vue`) dengan status badge "Sedang Dipakai" / "Sudah Digunakan di [Ruangan]", serta badge visual `📷 R-01` di kanvas Konva.

23. Tuliskan **Direct AI Render Generation from 3D Captures (`Domains/Project`)**: Action `GenerateAiRenderFromCaptureAction` berbasis Google Gemini API multimodal vision, endpoint generate single (`.../generate-ai`) dan batch (`.../generate-all-ai`), Action `UploadAiRenderedCaptureAction` (`.../ai-render`), serta mode switcher visual "Render AI" vs "3D Asli".

24. Tuliskan **Generator Draf RAB Per Ruangan dari Lembar Kerja (`Domains/Rab`)**: Action `GenerateRabFromWorksheetAction` (`POST /projects/{project}/rab/from-worksheet`) yang membaca denah dan booklet, mengelompokkan item RAB per `section = Nama Ruangan`, auto-match harga satuan arsitek, dan kalkulasi ulang via `RecalculateRabAction`.

25. Tuliskan **Interactive 3D Project Cards (`ProjectCard.vue` & `Project3DPreview.vue`)**: Komponen kartu proyek WebGL interaktif di Dashboard dan Galeri, auto-rotation halus, orbit control mouse, lighting responsif, dan integrasi metadata klien (`client_name`, `client_address`).

[EXPECTATION]
Output berupa contoh kode modular, rapi, aman, dan siap pakai (Developer-Ready / Production-Grade) tanpa penjelasan teori yang bertele-tele. Setiap kode yang menyentuh akses project (viewer, comment, chat) WAJIB melalui `ProjectClientAccessMiddleware` — tidak ada jalur akses tanpa login.

Konvensi tambahan:
- Gunakan UUID untuk semua primary key (`Str::uuid()` atau `$table->uuid('id')->primary()`).
- Storage path: `Storage::disk('public')->put(...)` — bukan `s3` atau `r2`.
- Queue dispatch: `DracoCompressionJob::dispatch(...)` — bukan Redis-specific syntax.
- Cache & Sessions: Redis multi-DB (`REDIS_CACHE_DB=1`, `REDIS_SESSION_DB=2`).
- Concurrency: Redis distributed lock `Cache::lock()->block()` untuk operasi kuota kritis.
- Inertia redirect: `return to_route('dashboard')` atau `return Inertia::render('PageName', [...])`.
- Tidak ada `return response()->json(...)` untuk halaman utama — gunakan Inertia.
- Form validation: gunakan Form Request class per domain.
- Nama brand dalam kode dan notifikasi: **PitchArch**, email: `noreply@pitcharch.id`.

