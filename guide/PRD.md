# 📄 Product Requirement Document (PRD) v2.7

> **v2.7 Note:** Menambahkan **Section 9 (Profil Arsitek)** dan **Section 10 (Drawing 2D — Rencana)**. Update MoSCoW matrix: RAB Fase A/B/C, AHSP, Export RAB, Mapping RAB → ✅ Selesai. Drawing 2D ditambahkan sebagai Should Have 🔴 Pending.

> **v2.6 Note:** Menambahkan modul **RAB & Lembar Kerja** (`Domains/Rab`): template + harga satuan, import quantity take-off CSV/Excel, estimator dari `.glb`, switch visibilitas RAB ke Klien, gating `plans.can_use_rab`. Lihat Section 8.

## PitchArch — Platform Presentasi & Feedback Arsitektur 3D Berbasis Web

> **Revision Note:** v2.5 — disinkronkan dengan kondisi aplikasi aktual. Perubahan utama dari v2.4:
> 1. **Nama brand:** "SaaS Web 3D" → **PitchArch** (tagline: Spatial CAD, domain: pitcharch.id).
> 2. **Tech stack aktual:** Laravel 12 (bukan 13), Inertia.js + Vue 3 (bukan Nuxt 3), MySQL (bukan PostgreSQL), Laravel Fortify (bukan Sanctum), storage `public` disk lokal (bukan Cloudflare R2), queue driver database (bukan Redis).
> 3. **Email Verification:** sudah diimplementasikan — `User` implements `MustVerifyEmail`, Google OAuth auto-verify.
> 4. Tambah tabel `camera_presets` ke skema database.
> 5. Klarifikasi fitur mana yang sudah selesai vs masih pending.

---

## 1. Executive Summary & Problem Statement

### **Background**
Proses presentasi desain arsitektur ke klien sering mengalami hambatan komunikasi (*communication gap*). Gambar 2D atau render statis kurang memberikan gambaran spasial yang utuh, sedangkan membagikan file asli (seperti `.skp` dari SketchUp) mengharuskan klien menginstal aplikasi berat.

### **Problem Statement**
1. **Misinterpretasi & Revisi Berlebihan:** Klien sering meminta revisi tanpa batas karena tidak adanya sistem transparansi dan pembatasan kuota revisi yang jelas.
2. **Keterbatasan Aksesibilitas:** Klien ingin melihat model 3D langsung tanpa perlu mengunduh aplikasi tambahan atau file berukuran besar.
3. **Tracking Revisi Tercecer:** Feedback terpisah di WhatsApp, email, atau catatan rapat tanpa kejelasan versi revisi ke berapa.
4. **Monetisasi & Resource Limit:** Diperlukan sistem pembatasan penggunaan (*Quotas*) agar resource server tetap efisien dan menghasilkan pendapatan berulang (*Recurring Revenue*).
5. **Kebocoran Akses & Link Bocor:** Link publik read-only tanpa kontrol berisiko bagi kerahasiaan desain arsitek.
6. **Komunikasi Terpisah dari Konteks Visual:** Diskusi teks tentang revisi sering terjadi di luar platform, terpisah dari pin komentar spasial.

### **Objective**
Membangun platform SaaS berbasis Web 3D interaktif bernama **PitchArch** yang memungkinkan Arsitek mengunggah hasil desain 3D, mengundang klien tertentu via email ke project, membatasi serta memantau kuota revisi client, mengumpulkan feedback berbasis *spatial pin annotation* dan **chat real-time**, serta memfasilitasi model bisnis berlangganan (*Subscription Plan*).

---

## 2. Brand Identity

| Atribut | Nilai |
| :--- | :--- |
| **Nama Produk** | PitchArch |
| **Tagline** | Spatial CAD |
| **Domain** | pitcharch.id |
| **Email Notifikasi** | noreply@pitcharch.id |
| **Email Support** | support@pitcharch.id |

---

## 3. User Roles, Akun & Personas

### 3.A Model Registrasi & Role (Aturan Wajib)
- **Tidak ada pemilihan role saat registrasi.** Form Register / Google OAuth hanya membuat satu jenis akun (`users`), tanpa kolom pilihan "Saya Arsitek" atau "Saya Klien".
- **"Arsitek" adalah kapabilitas default setiap akun** — begitu register, user otomatis bisa membuat project sendiri (dibatasi kuota Free/Pro Tier).
- **"Klien" BUKAN role global**, melainkan **status per-project** yang didapat otomatis ketika email akun tersebut cocok dengan baris undangan di tabel `project_clients`.
- **Satu akun dapat merangkap dua sisi sekaligus (dual-capacity):** memiliki project sendiri sebagai Arsitek, sekaligus menjadi Klien yang diundang ke project milik Arsitek lain.
- **Super Admin** adalah satu-satunya role eksplisit via `spatie/laravel-permission`, dan **tidak pernah bisa didapat lewat registrasi publik** — hanya di-assign manual lewat seeder/artisan.

### 3.B Tabel Persona

| Role/Kapasitas | Deskripsi | Akses & Batasan |
| :--- | :--- | :--- |
| **Arsitek (Free Tier)** | Kapabilitas default setiap akun baru. | Register/Login (Email + Google OAuth), Maksimal **1 project**, bisa tambah & edit project, mengundang Klien via email, chat real-time dengan Klien |
| **Arsitek (Pro Subscriber)** | Kapasitas berbayar untuk individual atau tim kecil. | Batas kuota **20 project**, file maks 100MB, kustomisasi batas revisi client, undangan Klien hingga batas team plan |
| **Arsitek (Enterprise)** | Paket organisasi/perusahaan skala besar. | **Unlimited projects**, advanced analytics & audit logs, SSO, API Access, Custom Branding, priority support |
| **Klien (Invited Reviewer)** | Status per-project yang didapat saat email akun cocok dengan undangan Arsitek. | Wajib Login sebelum mengakses apa pun, sistem validasi email vs `project_clients`, buka 3D Viewer, lihat Counter Status Revisi, tempatkan Pin Comment, chat real-time dengan Arsitek |
| **Super Admin** | Role eksplisit internal via Spatie `super_admin`. | Akses Admin Panel (`/admin/*`), kelola users/projects/plans/transactions/audit logs, atur konfigurasi kuota dinamis |

---

## 4. MoSCoW Feature Matrix

| Kategori | Fitur | Status |
| :--- | :--- | :--- |
| **Must Have** | Auth: Email/Password Register+Login (tanpa pilihan role) | ✅ Selesai |
| **Must Have** | Google OAuth via Socialite | ✅ Selesai |
| **Must Have** | Passkeys (WebAuthn/FIDO2) | ✅ Selesai |
| **Must Have** | 2FA (Two-Factor Authentication) | ✅ Selesai |
| **Must Have** | Email Verification (MustVerifyEmail) | ✅ Selesai |
| **Must Have** | 3D Web Viewer (Three.js Orbit Controls) + Revision Badge | ✅ Selesai |
| **Must Have** | File Upload `.glb` / `.gltf` + Draco Compression Queue | ✅ Selesai |
| **Must Have** | Client Invitation by Email + Access Control | ✅ Selesai |
| **Must Have** | 3D Spatial Pin Annotation (Raycaster X,Y,Z) | ✅ Selesai |
| **Must Have** | Revision Gatekeeper (403 jika kuota habis) | ✅ Selesai |
| **Must Have** | In-App Real-time Chat (Reverb/WebSocket) | ✅ Selesai |
| **Must Have** | Dynamic Quota Configuration via Admin Panel | ✅ Selesai |
| **Must Have** | Payment Gateway Midtrans (Snap + Webhook) | ✅ Selesai |
| **Should Have** | Apple-Style Scrollytelling Landing Page | ✅ Selesai |
| **Should Have** | Camera View Presets | ✅ Selesai |
| **Should Have** | Admin Panel (/admin/*) lengkap | ✅ Selesai |
| **Should Have** | Settings: Profile, Password, Appearance | ✅ Selesai |
| **Should Have** | Sectioning / Clipping Tool di Viewer | 🔴 Pending |
| **Should Have** | Invoice PDF Download | 🔴 Pending |
| **Should Have** | Chat Read Receipt & Typing Indicator | 🔴 Pending |
| **Should Have** | RAB Fase A: Harga Satuan, Template RAB, RAB Manual, Switch Visibilitas Klien | ✅ Selesai |
| **Should Have** | AHSP (Analisa Harga Satuan Pekerjaan) — `rab_price_item_components`, RecalculatePriceItemAction | ✅ Selesai |
| **Should Have** | RAB Fase B: Import Quantity Take-off (CSV/Excel) via `league/csv` + `phpspreadsheet` | ✅ Selesai |
| **Should Have** | RAB Fase C: Estimator RAB dari `.glb` (label "Estimasi") — `useGlbQuantities.ts` | ✅ Selesai |
| **Should Have** | Export RAB ke Excel multi-sheet dengan kop surat arsitek | ✅ Selesai |
| **Should Have** | Mapping RAB (`/rab/mappings`) — tipe exact/name_pattern/material | ✅ Selesai |
| **Should Have** | Profil Arsitek Lengkap (badan usaha, phone, alamat wilayah Indonesia) | ✅ Selesai |
| **Should Have** | Drawing 2D (Denah/Tampak/Potongan, konva.js, export DXF+SVG) — Enterprise only | 🔴 Pending |
| **Could Have** | First-Person Walkthrough Mode | 🔴 Pending |
| **Could Have** | Material Swapper | 🔴 Pending |
| **Could Have** | Import IFC (data BIM) untuk RAB | 🔴 Pending |
| **Could Have** | Invoice PDF Download | 🔴 Pending |
| **Won't Have** | In-Browser 3D Mesh Editing | Out of Scope |
| **Won't Have** | Public Read-Only Link (tanpa login/undangan) | Deprecated |
| **Won't Have** | Public 3D WebGL Demo di Landing Page | Out of Scope |

---

## 5. Landing Page & Public Pages

### 5.A Design Philosophy
- **Ultra-Clean Dark Mode Aesthetic:** Deep black/obsidian (`#000000`, `#0A0A0C`), frosted glass (`backdrop-blur-2xl bg-white/[0.03]`), subtle borders (`rgba(255,255,255,0.08)`).
- **Typography-First:** Headline besar dengan gradasi halus, pill badges minimalis.
- **Scrollytelling Engine:** Pinned sections dengan narasi terarah — digerakkan posisi scroll, murni CSS transforms + GSAP ScrollTrigger, **tanpa WebGL/Three.js di landing page publik**.

### 5.B Section Storyboard (Semua Sudah Diimplementasikan)
1. **HeroSection.vue** — Monumental Apple hero, pill badge, frame UI mockup melayang.
2. **ScrollyExperienceSection.vue** — Track scroll-pinned (450vh), 5-stage product journey: Draco Upload, Client Invite, Spatial Pin, Gatekeeper, Chat.
3. **ArchitecturalCinematicSection.vue** — Pinned 4-layer spatial anatomy inspection.
4. **BentoGridSection.vue** — Apple Pro Bento Grid (Zero-Leak Security, Dual-Capacity, Draco, WebSocket).
5. **InteractiveShowcaseSection.vue** — Dynamic click-to-pin raycast simulator.
6. **PricingSection.vue** — Apple Store comparison cards (Free, Pro, Enterprise).
7. **FaqSection.vue** — Apple-style accordion FAQ.
8. **CtaSection.vue** — Closing CTA.
9. **LandingNavbar.vue + LandingFooter.vue** — Full-width frosted glass navbar + minimalist footer.

### 5.C Performance Guardrails
- **Zero WebGL di landing publik:** Tidak ada Three.js/engine 3D di landing page. Semua efek memanfaatkan CSS `transform3d`, `opacity`, dan GSAP.
- **FCP Target < 1.0s** via Vite bundling optimal + Inertia SSR-ready layout.
- **Reduced Motion:** Mendukung `@media (prefers-reduced-motion)`.

---

## 6. Dynamic Business Rules & Subscription Plans

| Plan Slug | Project Limit | Fitur Tambahan |
| :--- | :--- | :--- |
| `free` | `1` | Akses basic 3D viewer, spatial pin comments, 1 project |
| `pro` | `20` | 20 project, export, productivity tools, higher file size |
| `enterprise` | `null` (Unlimited) | Unlimited projects, analytics, audit logs, SSO, API, custom branding |

### Formula Effective Project Limit
```
Effective Limit = Custom User Override (jika ada) ?? Plan Limit ?? 1
```
- Nilai `null` = Unlimited.
- Downgrade tidak menghapus project existing; hanya memblokir pembuatan baru jika kuota terlampaui.

### Client Invitation Flow
1. Arsitek memasukkan email Klien → sistem buat baris `project_clients` (status `pending`) → kirim email undangan via `noreply@pitcharch.id`.
2. Klien buka link → diarahkan login/register → sistem cocokkan email akun dengan `project_clients.email`.
3. Match + pending → status `accepted`, `user_id` di-attach → masuk Viewer.
4. Match + accepted → langsung masuk Viewer.
5. Tidak match → `403 Forbidden`.
6. Chat + Pin Comment hanya untuk Klien berstatus `accepted`.

---

## 7. Technical Architecture (Stack Aktual)

### A. Backend
| Komponen | Teknologi |
| :--- | :--- |
| **Framework** | Laravel 12 (Domain-Driven Design / DDD) |
| **Auth** | Laravel Fortify (email/password, 2FA, email verification, passkeys) + Laravel Socialite (Google OAuth) |
| **Database** | MySQL |
| **Queue** | Database driver (`QUEUE_CONNECTION=database`) |
| **WebSocket** | Laravel Reverb |
| **Storage** | Laravel `public` disk lokal (`storage/app/public/`) |
| **Packages** | `laravel/socialite`, `spatie/laravel-permission`, `midtrans/midtrans-php`, `laravel/reverb` |

### B. Frontend
| Komponen | Teknologi |
| :--- | :--- |
| **Rendering** | Inertia.js + Vue 3 (SPA monorepo, bukan Nuxt 3 terpisah) |
| **Build Tool** | Vite |
| **CSS** | Tailwind CSS v4 |
| **UI Components** | shadcn/ui (Vue port via `components.json`) |
| **3D Engine** | Three.js (internal viewer, bukan di landing publik) |
| **Scrollytelling** | GSAP + ScrollTrigger |
| **Real-time Client** | laravel-echo + Reverb connector |
| **Routing** | Inertia.js (bukan Nuxt file-based routing) |

### C. Database
- **Engine:** MySQL
- **Primary Key:** UUID
- **Tabel inti:** `users`, `system_settings`, `plans`, `user_plan_overrides`, `audit_logs`, `projects`, `project_versions`, `project_clients`, `comments`, `camera_presets`, `chat_messages`, `transactions`, `subscriptions`
- **Sessions & Jobs:** `sessions`, `jobs`, `cache` (tabel bawaan Laravel)

### D. Domain Modules (DDD)
| Domain | Tanggung Jawab |
| :--- | :--- |
| `Domains/Auth` | Registrasi (tanpa pilihan role), login, Google OAuth callback, toggle user status |
| `Domains/SystemConfig` | Konfigurasi kuota global, audit logs, repositories, services |
| `Domains/Project` | Upload, versioning, validasi kuota, Client Invitation, Camera Presets |
| `Domains/Comment` | Spatial pin annotation, pin/unpin, koordinat X/Y/Z |
| `Domains/Chat` | Real-time messaging Arsitek ↔ Klien per project via Reverb |
| `Domains/Billing` | Midtrans Snap, webhook handler, subscription management |
| `Domains/Rab` | Harga satuan, AHSP (komponen biaya), template RAB, dokumen RAB, import take-off, estimator `.glb`, mapping, visibilitas ke Klien, export Excel |
| `Domains/Drawing` | *(Rencana v2.7)* Editor denah/tampak/potongan 2D, export DXF+SVG — hanya Enterprise |

---

## 8. RAB & Lembar Kerja (`Domains/Rab`)

### 8.A Tujuan
Mempercepat Arsitek membuat lembar kerja dan Rencana Anggaran Biaya (RAB) dengan mengurangi pengetikan ulang dan perhitungan volume manual.

### 8.B Tiga Jalur Sumber Data (urutan build: A → B → C)
| Fase | Jalur | Sumber angka | Akurasi |
| :--- | :--- | :--- | :--- |
| A | Template RAB + harga satuan tersimpan | Input manual Arsitek | Tinggi |
| B | Import quantity take-off CSV/Excel | Revit / SketchUp | Tinggi |
| C | Estimator dari `.glb` | Geometri model (Three.js) | Estimasi awal |

### 8.B.1 AHSP (Analisa Harga Satuan Pekerjaan)
Setiap item harga satuan (`rab_price_items`) dapat memiliki sub-komponen biaya (tenaga, bahan, peralatan) di tabel `rab_price_item_components`. Jika `has_components = true`, `unit_price` dikalkulasi otomatis dari jumlah `amount` semua komponen ditambah `overhead_percent`. `RecalculatePriceItemAction` adalah satu sumber kebenaran untuk kalkulasi ini. Halaman `PriceItemComponents.vue` menampilkan tabel AHSP standar dengan tombol akses dari `PriceItems.vue` (ikon FlaskConical).

### 8.C Aturan Bisnis
1. **Gating paket:** kolom `plans.can_use_rab` (default `false`; `pro` dan `enterprise` = `true`). Downgrade tidak menghapus data; hanya memblokir pembuatan/edit baru, pemilik tetap bisa melihat RAB lamanya.
2. **Kepemilikan:** RAB milik Arsitek pemilik project. Harga satuan, template, dan pemetaan milik user masing-masing.
3. **Switch visibilitas Klien:** setiap dokumen RAB punya `is_visible_to_clients` (default `false`). Jika dinyalakan, Klien berstatus `accepted` pada project tersebut dapat melihat RAB **read-only**; Klien tidak pernah bisa mengubahnya.
4. **Rumus:** `subtotal_item = qty × (1 + waste%) × harga_satuan`; `total = subtotal + overhead + PPN` (PPN dihitung atas subtotal + overhead).
5. **Snapshot harga:** `rab_items.unit_price` disalin saat item dibuat; mengubah harga master tidak mengubah RAB yang sudah ada.
6. **Status:** `draft` (bisa diedit) dan `final` (terkunci, bisa di-reopen oleh pemilik).
7. **Jalur `.glb`:** hanya estimasi awal (bukan RAB final kontrak). Item wajib berlabel `is_estimate = true`. `.glb` tidak memuat data BIM, jadi bagian yang tidak dimodelkan tidak terhitung.
8. **Konvensi nama objek** (panduan di UI): `KATEGORI_JENIS_SPEK`, contoh `DINDING_BATA_15`, `LANTAI_KERAMIK_60`.

### 8.D Alur
1. Arsitek membuka project → tab **RAB** → pilih sumber (Manual/Template, Import CSV/Excel, Hitung dari model 3D).
2. Sistem membuat `rab_documents` (`draft`) beserta `rab_items`; item yang cocok dengan harga satuan terisi otomatis, sisanya ditandai **belum dipetakan**.
3. Arsitek mengecek dan mengedit, lalu `final`. Opsional: nyalakan switch agar Klien dapat melihat.
4. Export ke Excel/PDF.

**Import CSV (Fase B):** upload → pilih kolom Nama/Satuan/Kuantitas → pencocokan lewat `rab_mappings` → review item belum dipetakan → simpan. Parser via `TakeoffParserService` mendukung auto-detect delimiter CSV dan format XLSX. `RabMappingService` menangani pencocokan exact/wildcard/material.
**Estimator `.glb` (Fase C):** luas, volume (hanya mesh tertutup), dan jumlah dihitung di `GlbEstimator.vue` dengan `useGlbQuantities.ts` (Three.js) → dikirim ke server → dipetakan lewat `rab_mappings`. Peringatan otomatis untuk skala tidak wajar, mesh tidak tertutup, dan objek tanpa nama. Item yang dihasilkan selalu `is_estimate = true`.
**Custom Properties Blender:** file `.glb` dari Blender dapat menyertakan custom properties di setiap mesh melalui `mesh.userData` yang dibaca oleh `useGlbQuantities.ts`. Field yang dibaca: `unit_price`, `unit`, `category`, `section`, `quantity_basis`.
**Export Excel:** `ExportRabAction` menghasilkan XLSX multi-sheet: Sheet 1 Rekapitulasi RAB, Sheet 2 Daftar RAB Detail, Sheet 3+ AHSP per item harga satuan. Kop surat dari profil arsitek: nama badan usaha, alamat, telepon (center, tanpa nama personal).

---

## 9. Profil Arsitek (`users` — Badan Usaha & Wilayah Indonesia)

### 9.A Tujuan
Melengkapi profil Arsitek dengan informasi badan usaha (perusahaan/individu) dan alamat terperinci berbasis wilayah administratif Indonesia. Data profil ini dipakai sebagai kop surat pada dokumen yang dihasilkan (misalnya Export RAB Excel).

### 9.B Kolom Baru di Tabel `users`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `company_type` | `string(30)` NULLABLE | Tipe badan usaha: `perorangan`, `cv`, `pt`, `firma`, dll. |
| `company_name` | `string` NULLABLE | Nama badan usaha / studio / perorangan |
| `phone` | `string(20)` NULLABLE | Nomor telepon |
| `province_id` | `string` NULLABLE | ID provinsi dari `laravolt/indonesia` |
| `city_id` | `string` NULLABLE | ID kabupaten/kota |
| `district_id` | `string` NULLABLE | ID kecamatan |
| `village_id` | `string` NULLABLE | ID kelurahan/desa |
| `province_name` | `string` NULLABLE | Nama provinsi (denormalized cache) |
| `city_name` | `string` NULLABLE | Nama kota (denormalized cache) |
| `district_name` | `string` NULLABLE | Nama kecamatan (denormalized cache) |
| `village_name` | `string` NULLABLE | Nama kelurahan (denormalized cache) |
| `address` | `text` NULLABLE | Alamat jalan lengkap |
| `postal_code` | `string(10)` NULLABLE | Kode pos |

### 9.C Integrasi Wilayah via `laravolt/indonesia`
- Package `laravolt/indonesia` menyediakan tabel `indonesia_provinces`, `indonesia_cities`, `indonesia_districts`, `indonesia_villages` beserta data seed.
- API route `/region/provinces|cities|districts|villages` tersedia **tanpa middleware auth** (public, web group) untuk keperluan AJAX cascading dropdown.
- Controller: `IndonesiaRegionController` di `app/Http/Controllers/`.
- Komponen frontend: `useIndonesiaRegion.ts` (composable) + `AddressInput.vue` (cascading dropdown).
- `HandleInertiaRequests` diperbarui untuk share semua field profil ke frontend.

### 9.D Kop Surat Export
`ExportRabAction` membaca `company_name`, `address`, `city_name`, `province_name`, `postal_code`, dan `phone` dari profil pemilik project untuk mengisi baris kop surat di Sheet 1 dan Sheet 2 Export RAB Excel. Kop surat ditampilkan di baris atas, rata tengah, tanpa nama personal.

---

## 10. Drawing 2D — Editor Denah, Tampak & Potongan *(Rencana v2.7 — 🔴 Pending)*

> **Status:** Fitur ini **belum diimplementasikan**. Hanya direncanakan. Tunggu perintah implementasi.

### 10.A Tujuan
Menyediakan editor gambar teknik 2D (denah, tampak, potongan) langsung di platform tanpa perlu software CAD eksternal. Terbatas untuk paket **Enterprise** (`plans.can_use_drawing = true`).

### 10.B Rancangan Domain
- Domain baru: `Domains/Drawing`
- Tabel: `drawing_sheets` (lembar gambar, tipe: `plan | elevation | section`) dan `drawing_elements` (elemen vektor: garis, dimensi, teks, simbol)
- Library frontend: **konva.js** (canvas 2D, npm)
- Export: **DXF** (`nzcreations/dxf`, composer) dan **SVG** (built-in konva)

### 10.C Aturan Bisnis (Rencana)
1. **Gating:** hanya Enterprise — kolom `plans.can_use_drawing = true`.
2. **Tipe sheet:** `plan` (denah), `elevation` (tampak), `section` (potongan).
3. **Library simbol:** pintu, jendela, kolom, tangga (pre-built SVG symbols di konva canvas).
4. **Export format:** DXF (kompatibel AutoCAD) + SVG.
5. **Kepemilikan:** drawing sheet milik Arsitek pemilik project; Klien hanya bisa melihat (jika switch visibilitas aktif).
