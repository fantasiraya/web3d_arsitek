# 📄 Product Requirement Document (PRD) v2.10

> **v2.10 Note:** Menambahkan detail implementasi penuh **Section 10 (Drawing 2D — Editor Denah, Tampak & Potongan)**. Update MoSCoW matrix: Drawing 2D (Konva.js CAD editor, Multi-Paper Frame Canvas, AutoCAD Model Space, Figma-style edge/corner drag resize, Kop Gambar auto-wrap, CAD hotkeys, Clean Print & PDF export via isolated iframe, DXF/SVG/PNG export) → ✅ Selesai.
>
> **v2.9 Note:** Menambahkan **Section 12 (AI Vision Studio)** dan **Section 13 (Arsitektur Caching & Proteksi Konkurensi Skala Tinggi)**. Update MoSCoW matrix: AI Vision Studio (Image/Video generative, Paid-Only) & Caching Redis Multi-DB + Concurrency Protection → ✅ Selesai.
>
> **v2.8 Note:** Menambahkan **Section 11 (Pencahayaan Real-Time di Viewer & Snapshot Captures)**. Update MoSCoW matrix: Real-Time Solar Lighting Simulation (Three.js + SunCalc) & 3D Snapshot Captures → ✅ Selesai.
>
> **v2.7 Note:** Menambahkan **Section 9 (Profil Arsitek)**. Update MoSCoW matrix: RAB Fase A/B/C, AHSP, Export RAB, Mapping RAB → ✅ Selesai.

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
| **Should Have** | Real-Time Solar Lighting Simulation (Three.js + SunCalc, arah utara, night mode LAMPU_*) | ✅ Selesai |
| **Should Have** | 3D Snapshot Captures (Viewport/FHD PNG, kuota gambar, galeri per-ruangan & jam, switch visibilitas klien) | ✅ Selesai |
| **Should Have** | AI Vision Studio (Image & Video prompts arsitektural, Paid-Only gating, local persistence) | ✅ Selesai |
| **Should Have** | Arsitektur Cache Redis Multi-DB & Proteksi Konkurensi Skala 1M User (atomic locks) | ✅ Selesai |
| **Should Have** | Drawing 2D (Denah/Tampak/Potongan, konva.js, export DXF+SVG, PDF, Multi-Paper) — Enterprise only | ✅ Selesai |
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

## 10. Drawing 2D — Editor Denah, Tampak & Potongan (`Domains/Drawing` — 🟢 Selesai)

### 10.A Tujuan & Gambaran Umum
Menyediakan editor CAD 2D interaktif berbasis web untuk menggambar denah (plan), tampak (elevation), dan potongan (section) langsung di browser tanpa perlu software CAD desktop pihak ketiga. Fitur ini dirancang khusus untuk paket **Enterprise** (`plans.can_use_drawing = true`) dan role Super Admin.

### 10.B Arsitektur Domain & Database
- **Domain:** `Domains/Drawing`
- **Tabel `drawing_sheets`:** UUID PK, `project_id`, `user_id`, `title`, `type` (`plan | elevation | section`), `width`, `height`, `scale`, `sort_order`.
- **Tabel `drawing_elements`:** UUID PK, `sheet_id`, `element_type` (`wall`, `rect`, `line`, `door`, `window`, `column`, `stair`, `dimension`, `room_label`, `furniture`), `properties` (JSON koordinat, ketebalan, warna, rotasi, ukuran), `layer`, `z_index`.
- **Library Frontend:** **konva.js** (vektor performa tinggi hardware-accelerated via HTML5 Canvas)
- **Export & Output:**
  - **DXF:** Ekspor format AutoCAD R12 ASCII compliant via `ExportDxfAction`.
  - **SVG & PNG:** Format gambar vektor murni dan raster resolusi tinggi.
  - **PDF & Clean Print:** Cetak langsung standar ISO (A4–A0) dan download PDF melalui isolated hidden iframe tanpa bleed UI editor.

### 10.C Fitur & Kapabilitas Utama
1. **Mode Lembar Kerja Ganda:**
   - **Frame Kertas (Paper Space):** Menampilkan batas lembar kerja standar (A4, A3, A2, A1, A0 baik Portrait maupun Landscape) atau ukuran kustom, dilengkapi Kop Gambar resmi arsitek di sudut kanan bawah.
   - **AutoCAD Model Space (∞):** Mode kanvas tak terbatas berlatar gelap untuk kebebasan menggambar layout skala besar tanpa batasan tepi kertas.
2. **Multi-Paper Frame pada Satu Kanvas:**
   - Mendukung penambahan lembar kertas baru ke dalam kanvas yang sama (`+ Kertas ke Kanvas`).
   - Setiap lembar kertas memiliki tombol hapus independen di sudut kanvas (`Hapus Kertas`).
3. **Interaktivitas Resize Kertas Mirip Figma:**
   - Handle resize interaktif pada tepi kanan, tepi bawah, dan sudut kanan bawah kertas.
   - Live drag resize non-destruktif (tanpa destroy Konva node selama `dragmove`) dengan tooltip dimensi real-time.
   - Caching instan dimensi kertas ke browser `localStorage` (`cad_paper_sizes_{sheet_id}`) sehingga ukuran tetap presisi saat refresh, sebelum tersinkronisasi ke database.
4. **Kop Gambar (Title Block) Arsitektur Dinamis:**
   - Auto word-wrap untuk teks judul proyek dan nama instansi/arsitek.
   - Tinggi baris adaptif sehingga teks panjang tidak keluar batas garis kotak.
   - Layer Kop Gambar diposisikan di atas grid (`paperBgLayer` → `gridLayer` → `paperLayer`) sehingga garis grid tidak memotong teks.
5. **Alat Gambar Arsitektur Lengkap:**
   - Dinding tebal (`wall` - W) dengan ketebalan standar arsitek.
   - Kotak ruangan (`rect` - R), Garis bebas (`line` - L).
   - Simbol pintu swing (`door` - D), bukaan jendela (`window` - J).
   - Kolom beton struktural (`column` - C), tangga 2D (`stair` - S).
   - Dimensi ukuran otomatis (`dimension` - M) dan teks label ruangan (`room_label` - T).
   - Library perabot/furniture (tempat tidur, meja, sanitair/toilet).
   - Penghapus elemen (`eraser` - E).
6. **Sistem Navigasi & Seleksi AutoCAD Standar:**
   - AutoCAD Box Select: seleksi crossing (kanan ke kiri, hijau putus-putus) dan window select (kiri ke kanan, biru solid).
   - Multi-seleksi dengan `Ctrl` + Klik, Clone Drag langsung dengan `Ctrl` + Drag.
   - Hotkeys: `V` (Select), `H`/`Spasi` (Pan), `Z` (Pusatkan Gambar), `Ctrl+A` (Pilih Semua), `Ctrl+C` (Salin), `Ctrl+V` (Tempel), `Ctrl+D` (Duplikat), `Ctrl+Z` (Undo), `Ctrl+Y`/`Ctrl+Shift+Z` (Redo), `Delete`/`Backspace` (Hapus), `Esc` (Batal/Deselect).
7. **Responsivitas Layar & Toolbar CAD:**
   - Header baris kedua dilengkapi indikator horizontal overflow "Menu Lainnya ▶" yang berkedip jika layar sempit.
   - Navbottom HUD responsif dengan scroll cues kiri & kanan.
   - Sidebar toolbar kiri diproteksi batas bawah `bottom-18 sm:bottom-20 z-30` agar tidak menabrak bottom HUD di layar mobile, lengkap dengan indikator scroll vertikal (`▲` / `▼`).

---

## 11. Pencahayaan Real-Time di Viewer & 3D Snapshot Captures (`Domains/Project`)

### 11.A Tujuan
1. **Pencahayaan Matahari Real-Time:** Menghitung posisi matahari dari jam (00:00–23:59), tanggal, lokasi geografis (latitude/longitude), dan arah orientasi utara (`north_angle` 0°–360°). Menggerakkan `DirectionalLight` (matahari), soft shadow mapping (PCFSoftShadowMap), dan interpolasi warna langit/ambient.
2. **Night Mode Otomatis:** Saat matahari berada di bawah ufuk (altitude < 0) atau preset malam: matahari dinonaktifkan, ambient diredupkan, dan objek mesh dengan prefix nama `LAMPU_*` atau `lampu_*` menyala secara otomatis (emissive material + point lights).
3. **Preset Cepat:** Pagi (08:00), Siang (12:00), Sore (17:00), dan Malam (20:00), plus slider scrub interaktif agar klien bisa menggeser jam sendiri di browser secara lokal tanpa biaya server.
4. **Pengaturan Lokasi & Arah Utara:** Hanya Arsitek yang berhak mengubah koordinat (lat/long), arah utara, dan tanggal simulasi matahari per-project. Default lokasi proyek di-fallback ke koordinat Indonesia (Jakarta: -6.2088, 106.8456) jika profil arsitek belum diisi.
5. **3D Snapshot Captures:** Mengambil tangkapan layar langsung dari canvas Three.js (`preserveDrawingBuffer: true`), mendukung resolusi Viewport dan Full HD (1920x1080). Snapshot dikaitkan dengan Camera Preset (ruangan) dan jam simulasi.
6. **Batas Kuota Simpan:** Hanya Arsitek yang boleh menyimpan gambar ke server (`SaveCaptureAction`). Batas kuota dihitung **per-gambar** (Free Tier: 5 gambar, Pro Tier: 50 gambar, Enterprise: unlimited). Klien hanya bisa mengunduh PNG secara lokal.
7. **Galeri Snapshot:** Drawer galeri di Viewer untuk melihat, unduh PNG, menghapus (arsitek saja), dan switch toggle visibilitas ke klien (`is_visible_to_clients`).

---

## 12. AI Vision Studio (`Domains/VisionStudio`)

### 12.A Tujuan & Konsep
AI Vision Studio adalah lingkungan studio generatif arsitektur berbasis AI yang berdiri independen dari proyek 3D. Memfasilitasi arsitek dalam menghasilkan visualisasi konsep, render realistis, sketsa suasana, hingga prompt video sinematik.

### 12.B Aturan Bisnis & Batasan Akses
1. **Eksklusif Tier Berbayar (Paid-Only):**
   - Hanya pengguna dengan paket aktif **Pro**, **Enterprise**, atau role **Super Admin** yang dapat mengakses AI Vision Studio (`SubscriptionLimitService::canAccessVisionStudio($user)`).
   - Pengguna paket **Free** yang mengakses route web `/vision-studio` otomatis di-redirect ke halaman `/plans` disertai flash alert upgrade.
   - Panggilan API dari paket Free mengembalikan respons error `403 Forbidden`.
   - Di `AppSidebar.vue`, menu AI Vision Studio dilengkapi ikon gembok dan badge amber `PRO` bagi pengguna Free.
2. **Galeri Kreasi Mandiri (Terpisah Penuh):**
   - Hasil kreasi studio disimpan pada tabel `vision_creations`.
   - Penyimpanan gambar dan kreasi AI **TIDAK digabung** dengan snapshot 3D proyek (`project_captures`).
   - Tidak ada fitur atau switch "Tampilkan ke Klien", menjaga privasi proses eksplorasi desain arsitek.
3. **Persistensi State Browser (LocalStorage):**
   - Parameter prompt, jenis kreasi ('image'/'video'), dan riwayat studio disinkronkan ke browser `localStorage`.
   - Me-refresh halaman tidak akan mereset atau menghilangkan tombol serta input yang sedang dikerjakan.
4. **Lifecycle & Cleanup Storage:**
   - Menghapus entri kreasi di studio secara otomatis memicu event model Eloquent yang menghapus file media fisik terkait di disk storage `public`.

---

## 13. Arsitektur Caching & Proteksi Konkurensi Skala Tinggi (1 Juta Pengguna)

### 13.A Arsitektur Redis Multi-Database
Untuk menjamin stabilitas sistem dengan target 1.000.000 pengguna bersamaan:
1. **Pemisahan Database Redis:**
   - **DB 0:** Framework internal, default connection, queue dispatcher.
   - **DB 1 (`REDIS_CACHE_DB=1`):** Cache aplikasi, master data plans (`Plan::getActivePlansForDisplay()`), denormalized metadata.
   - **DB 2 (`REDIS_SESSION_DB=2`):** Sesi pengguna aktif.
   - **Manfaat:** Eksekusi `php artisan cache:clear` pada Redis DB 1 sama sekali tidak akan menghapus sesi login pengguna aktif di Redis DB 2.
2. **Safe Driver-Agnostic Tagging:**
   - Helper `CacheKeys::tags(['plans'])` membungkus pemanggilan tag Redis sehingga tidak crash bila aplikasi berpindah ke driver cache tanpa fitur tagging.
3. **Cache Invalidation Terjadwal & Reaktif:**
   - Pembaruan paket di backoffice admin (`Admin/PlanController`) atau upgrade akun (`ChangeUserPlanAction`) langsung mem-flush tag `plans` dan user cache secara otomatis.

### 13.B Proteksi Race Condition (Distributed Atomic Locking)
Operasi kritis yang memvalidasi kuota dan status kini diamankan dengan atomic distributed lock Redis:
- **Pembuatan Proyek (`CreateProjectAction`):** `Cache::lock("user:{$user->id}:create_project", 15)->block(5)` mencegah arsitek mengeklik berkali-kali untuk menembus batas maksimal kuota proyek.
- **Penyimpanan Snapshot 3D (`SaveCaptureAction`):** `Cache::lock("user:{$user->id}:create_capture", 15)->block(5)` mencegah bypass kuota capture per plan.
- **Penambahan Pin Komentar Revisi (`PinCommentAction`):** `Cache::lock("project:{$project->id}:pin_comment", 15)->block(5)` mencegah inkonsistensi nomor revisi saat beberapa klien mengirim komentar di detik yang sama.
- **Pembayaran Midtrans (`CheckoutController`):** Callback dan webhook diverifikasi di dalam `DB::transaction` dengan `lockForUpdate()` pada order pending untuk mencegah aktivasi ganda pesanan.

### 13.C Eliminasi N+1 Query Sistemik
- Event listener `User::retrieved` di `AppServiceProvider.php` telah dihapus.
- Eager loading relasi diterapkan pada seluruh endpoint admin dan listing data (`with(['user.activeSubscription.planModel', 'user.planOverride', 'invitedClients'])` dan `withCount('projects')`).

