# 📄 Product Requirement Document (PRD) v2.4
## SaaS Web 3D Architecture Presentation & Feedback Platform

> **Revision Note:** v2.4 — disinkronkan dengan `AI_INSTRUCTIONS.md`, `DATABASE_SCHEMA.md`, `FOLDER_STRUCTURE.md`, `RTCF.md`, `RISE.md`. Perubahan utama:
> 1. Landing page menggunakan **Apple-Style Aesthetic dipadukan dengan Scrollytelling Engine** (pinned sections, scroll-driven UI mockup reveals, bento grid, deep obsidian dark mode — murni animasi layer UI hardware-accelerated tanpa WebGL 3D demo berat di halaman publik demi FCP instan).
> 2. Fitur **In-App Real-time Chat** antara Arsitek & Klien per project.
> 3. **Perombakan model akses**: link publik read-only dihapus, diganti **Client Invitation by Email** — Klien wajib login & emailnya wajib terdaftar sebagai undangan pada project terkait sebelum bisa membuka 3D Viewer, memberi komentar, atau chat.
> 4. **Model Role & Akun** diklarifikasi: tidak ada pilihan role saat registrasi; satu akun bisa merangkap Arsitek dan Klien sekaligus.

---

## 1. Executive Summary & Problem Statement

### **Background**
Proses presentasi desain arsitektur ke klien sering mengalami hambatan komunikasi (*communication gap*). Gambar 2D atau render statis kurang memberikan gambaran spasial yang utuh, sedangkan membagikan file asli (seperti `.skp` dari SketchUp) mengharuskan klien menginstal aplikasi berat.

### **Problem Statement**
1. **Misinterpretasi & Revisi Berlebihan:** Client sering meminta revisi tanpa batas karena tidak adanya sistem transparansi dan pembatasan kuota revisi yang jelas[cite: 1, 3].
2. **Keterbatasan Aksesibilitas:** Klien ingin melihat model 3D langsung tanpa perlu mengunduh aplikasi tambahan atau file berukuran besar[cite: 1].
3. **Tracking Revisi Tercecer:** Feedback terpisah di WhatsApp, email, atau catatan rapat tanpa kejelasan versi revisi ke berapa[cite: 1, 3].
4. **Monetisasi & Resource Limit:** Diperlukan sistem pembatasan penggunaan (*Quotas*) agar resource server tetap efisien dan menghasilkan pendapatan berulang (*Recurring Revenue*)[cite: 1].
5. **Kebocoran Akses & Link Bocor:** Link publik read-only tanpa kontrol siapa pun yang memegang link bisa membuka model 3D klien, berisiko bagi kerahasiaan desain arsitek terhadap pihak yang tidak berkepentingan.
6. **Komunikasi Terpisah dari Konteks Visual:** Diskusi teks tentang revisi sering terjadi di luar platform (chat pribadi/email), terpisah dari pin komentar spasial sehingga konteks percakapan hilang.

### **Objective**
Membangun platform SaaS berbasis Web 3D interaktif yang memungkinkan Arsitek mengunggah hasil desain 3D, **mengundang klien tertentu via email** ke project, membatasi serta memantau kuota revisi client secara transparan, mengumpulkan feedback berbasis *spatial pin annotation* dan **chat real-time**, serta memfasilitasi model bisnis berlangganan (*Subscription Plan*)[cite: 1, 3].

---

## 2. User Roles, Akun & Personas

### 2.A Model Registrasi & Role (Aturan Wajib)
- **Tidak ada pemilihan role saat registrasi.** Form Register / Google OAuth hanya membuat satu jenis akun (`users`), tanpa kolom pilihan "Saya Arsitek" atau "Saya Klien".
- **"Arsitek" adalah kapabilitas default setiap akun** — begitu register, user otomatis bisa membuat project sendiri (dibatasi kuota Free/Pro Tier).
- **"Klien" BUKAN role global**, melainkan **status per-project** yang didapat otomatis ketika email akun tersebut cocok dengan baris undangan di tabel `project_clients` yang dibuat oleh Arsitek lain (lihat Section 4.x & `DATABASE_SCHEMA.md`).
- **Satu akun dapat merangkap dua sisi sekaligus (dual-capacity)**: memiliki project sendiri sebagai Arsitek, sekaligus menjadi Klien yang diundang ke project milik Arsitek lain — tanpa perlu akun terpisah atau switch role manual.
- **Super Admin** adalah satu-satunya role eksplisit via `spatie/laravel-permission`, dan **tidak pernah bisa didapat lewat registrasi publik** — hanya di-assign manual oleh tim internal (seeder/artisan command).

### 2.B Tabel Persona

| Role/Kapasitas | Deskripsi | Akses, Otorisasi & Batasan Kuota |
| :--- | :--- | :--- |
| **Arsitek (Free Tier)** | Kapabilitas default setiap akun baru yang ingin mencoba platform[cite: 1, 2]. | • Register/Login (Email & **Google OAuth**)[cite: 2]<br>• Upload & Kelola File 3D (Maksimal **1 project**, bisa tambah & edit project miliknya)<br>• Atur Batas Kuota Revisi Client per Project (default: 3x revisi, tersimpan di `projects.max_revisions_allowed`)[cite: 1, 3]<br>• **Mengundang Klien via email** (`project_clients`) — bukan lagi generate link publik terbuka<br>• Chat real-time dengan Klien yang sudah accept undangan |
| **Arsitek (Pro Subscriber)** | Kapasitas berbayar untuk individual atau tim kecil, status langganan tercatat di tabel `subscriptions`[cite: 1, 2]. | • Batas kuota **20 project** (dapat di-override oleh Admin)<br>• File maks 100MB (`pro_tier_max_file_size_mb`)[cite: 1, 2]<br>• Prioritas kompresi file 3D & kustomisasi batas revisi client hingga unlimited[cite: 1, 3]<br>• Undangan Klien hingga batas team plan<br>• Fitur export & productivity tools |
| **Arsitek (Enterprise)** | Paket organisasi/perusahaan skala besar dengan kebutuhan keamanan, integrasi, dan skalabilitas tinggi. | • **Unlimited projects** (kuota `null` / tanpa batas)<br>• Advanced analytics & audit logs<br>• SSO (Single Sign-On), API Access, Custom Branding<br>• Priority support & dedicated SLA |
| **Klien (Invited Reviewer)** | Status per-project yang didapat saat email akun cocok dengan undangan Arsitek — **bukan role terpisah**, akun yang sama bisa juga jadi Arsitek di project lain[cite: 1, 2]. | • Menerima email undangan berisi link akses project (`/p/{share_token}`)<br>• **Wajib Login/Register** (Email / **Google OAuth**) sebelum bisa membuka apa pun — tidak ada lagi mode publik read-only tanpa login<br>• Sistem memvalidasi: email akun yang login HARUS match dengan email di `project_clients` untuk project tsb, jika tidak → `403 Forbidden`<br>• Setelah lolos validasi: buka 3D Viewer, lihat Counter Status Revisi (cth: "Revisi 2 dari 3")[cite: 1, 3], menempatkan *Pin Comment* selama kuota revisi belum habis[cite: 1, 3], dan **chat real-time** dengan Arsitek project tsb |
| **Super Admin** | Role eksplisit internal via Spatie `super_admin`, tidak bisa didapat via self-registration[cite: 1, 2]. | • Akses Backoffice Admin Panel (`/admin/*`)<br>• Mengatur konfigurasi kuota sistem secara **dinamis** (`plans` & `system_settings`) tanpa *re-deploy* kode[cite: 1, 2]<br>• Mengelola pengguna, suspend/aktivasi akun, ubah subscription plan<br>• Memberikan **Custom Project Limit Override** secara individual per user<br>• Memonitor transaksi, project lintas user, audit log perubahan admin |

---

## 3.1 MoSCoW Feature Matrix

| Kategori | Fitur | Deskripsi Detail | Priority |
| :--- | :--- | :--- | :--- |
| **Must Have** | Auth & Single Sign-On | Register/Login untuk semua akun (tanpa pilihan role) menggunakan Email/Password dan **Google OAuth (One-Tap Login)** via `laravel/socialite` & `spatie/laravel-permission`[cite: 2]. | P0 |
| **Must Have** | 3D Web Viewer & Revision Badge | Viewport 3D berbasis WebGL (Orbit control) via TresJS / Three.js dilengkapi Badge Indicator Status Revisi Client (cth: "Revisi 2 dari 3")[cite: 2, 3]. | P0 |
| **Must Have** | File Upload & Draco Pipeline | Upload `.glb` / `.gltf` + Kompresi Draco otomatis di background queue (`gltf-pipeline`)[cite: 2]. | P0 |
| **Must Have** | **Client Invitation & Access Control** | Arsitek meng-*assign* email Klien ke project (`project_clients`). Klien wajib login & emailnya wajib cocok dengan undangan sebelum bisa mengakses project — menggantikan model link publik read-only lama. | P0 |
| **Must Have** | Custom Client Revision Limit | Arsitek dapat mengatur batas maksimum revisi yang diizinkan untuk client pada tiap project (misal: max 3x revisi), disimpan di `projects.max_revisions_allowed`[cite: 1, 3]. | P0 |
| **Must Have** | 3D Spatial Pin Annotation | Klik pada permukaan model 3D untuk menempatkan pin komentar berdasarkan koordinat $(x,y,z)$[cite: 2]. | P0 |
| **Must Have** | Revision Gatekeeper Wall | Mencegah client mengajukan pin revisi baru jika `projects.current_revision_count` >= `max_revisions_allowed` (`403 RevisionLimitExceeded`)[cite: 1, 3]. | P0 |
| **Must Have** | **In-App Real-time Chat** | Chat 1-ke-1 antara Arsitek & Klien per project (di luar thread pin komentar), untuk diskusi umum yang tidak terikat koordinat 3D tertentu. Hanya bisa diakses oleh Arsitek pemilik project dan Klien yang sudah ter-validasi undangannya. | P0 |
| **Must Have** | **Dynamic Quota Configuration** | Admin dapat mengubah batasan Free & Pro Tier (Maks Project, Maks File Size, Maks Revisi) via Admin Panel secara dinamis (`system_settings`)[cite: 2, 3]. | P0 |
| **Must Have** | **Payment Gateway Integration** | Integrasi Midtrans (`midtrans/midtrans-php`) untuk pembayaran otomatis QRIS/VA + Webhook Handler yang menulis ke tabel `transactions` dan `subscriptions`[cite: 2]. | P0 |
| **Should Have** | **Apple-Style Scrollytelling Landing Page** | Landing page berestetika Apple (deep obsidian `#000000`, frosted glass, display typography, bento grid) dipadukan dengan teknik *scrollytelling* (pinned narrative, scrubbed scroll transitions, reveal bertahap UI mockup produk & pin comment) tanpa memuat engine 3D WebGL di halaman publik demi FCP instan & feel visual ultra-premium. | P1 |
| **Should Have** | Camera View Presets | Arsitek bisa menyimpan *angle* kamera penting (misal: "Kamar Utama", "Fasad")[cite: 2]. | P1 |
| **Should Have** | Sectioning / Clipping Tool | Potongan melintang (Cutaway view) untuk melihat interior bangunan tanpa merusak geometri[cite: 2]. | P1 |
| **Should Have** | Comment Thread & Status | Balasan komentar bertingkat dan penandaan status (*Open*, *In Progress*, *Resolved*)[cite: 2]. | P1 |
| **Should Have** | Invoice & Billing History | Arsitek dapat mengunduh bukti transaksi / invoice pembayaran (PDF)[cite: 2]. | P1 |
| **Should Have** | Chat Read Receipt & Typing Indicator | Indikator "sedang mengetik" & status pesan terbaca pada fitur chat. | P1 |
| **Could Have** | First-Person Walkthrough | Mode navigasi berjalan di dalam ruangan menggunakan *WASD / Arrow Keys*[cite: 2]. | P2 |
| **Could Have** | Material Swapper | Klien bisa mengganti warna/tekstur material secara interaktif pada komponen tertentu[cite: 2]. | P2 |
| **Won't Have** | In-Browser 3D Editing | Mengedit bentuk geometri mesh 3D atau menarik/menggeser komponen (*Drag/Move*) di web[cite: 2]. | Out of Scope |
| **Won't Have** | Public Read-Only Link (tanpa login/undangan) | Model akses publik terbuka **DIHAPUS** total, digantikan Client Invitation by Email (lihat aturan bisnis Section 4.x). | Deprecated |
| **Won't Have** | Public 3D Demo / WebGL on Landing Page | **DIHAPUS / DITIADAKAN** — Tidak ada canvas atau demo 3D interaktif di landing page publik. Engine 3D hanya aktif di dalam aplikasi untuk user/klien terotentikasi agar performa FCP instan & hemat bandwidth. | Out of Scope |

## 3.x Landing Page & Public Pages Requirements (Apple Style + Scrollytelling)

### 3.x.A Design Philosophy & Aesthetic (Apple Style)
- **Ultra-Clean Dark Mode Aesthetic:** Mengadopsi bahasa visual Apple Pro — latar belakang deep black/obsidian (`#000000` & `#0A0A0C`), subtle border lines (`rgba(255, 255, 255, 0.08)`), dan frosted glass efek (`backdrop-blur-2xl bg-white/[0.03]`).
- **Typography-First Hierarchy:** Tipografi ekspansif dengan kontras tinggi (Inter / SF Pro font display), headline besar dengan gradasi halus (*white to metallic gray*), teks pengantar ringkas dan berbobot, serta pill badges minimalis (misal: `"Web3D Platform • Generasi Baru"`).
- **Subtle Depth & Ambient Lighting:** Glow radial halus di belakang elemen utama (indigo/blue ambient hue yang sangat lembut), floating product frames dengan rounded corners lebar (`rounded-3xl` / `rounded-[32px]`), dan shadow realistis bergaya macOS/iOS window.
- **Micro-Interactions & Smooth Momentum:** Transisi hover lembut, tombol CTA bertipe pill dengan efek glassmorphism dan shimmer saat disentuh kursor.

### 3.x.B Scrollytelling Engine & Storyboard
Landing page dibangun dengan narasi terarah (*product storytelling*) yang digerakkan oleh posisi scroll pengguna via GSAP ScrollTrigger / CSS scroll-driven animation (hardware-accelerated transforms), **tanpa memuat engine 3D WebGL / canvas 3D publik**:
1. **Act I — The Monumental Hero:**
   - Headline monumental: *"Presentasi Arsitektur. Diciptakan Ulang."*
   - Subheadline elegan tentang menghilangkan file berat dan komunikasi tercecer.
   - Pinned hero container: Frame UI Web3D Viewer melayang dari bawah dengan scale up dinamis (`scale 0.9` ke `1.0`), menampilkan visual render fasad arsitektur modern dalam mock window beresolusi tinggi.
2. **Act II — The Problem vs Breakthrough (Text Scrubbing):**
   - Transisi teks scrollytelling di mana problem statement ("File SKP 500MB yang macet di email, revisi tanpa batas di WhatsApp") memudar keluar (*fade & blur out*), digantikan oleh solusi platform ("Satu link undangan email. Satu kanvas interaktif presisi.").
3. **Act III — The Pinned Product Journey (Interactive Mockup Layering):**
   Section ter-pin di viewport, di mana scroll pengguna mengontrol rangkaian layer animasi pada UI mockup:
   - *Stage 1 (Draco Upload Engine):* Kartu file `.glb` terkompresi hingga 85% secara visual dalam hitungan detik.
   - *Stage 2 (Spatial Pin Drop):* Animasi titik koordinat pin komentar jatuh tepat ke sudut fasad bangunan pada mockup, menarik leader line SVG dinamis dengan bubble komentar *"Tinggi plafon tolong disesuaikan ke 3.8m"*.
   - *Stage 3 (Client Revision Gatekeeper):* Kartu status revisi berubah dari *"Revisi 2 dari 3"* menjadi gatekeeper badge yang melindungi arsitek dari revisi tak terhingga.
   - *Stage 4 (Direct In-App Chat):* Panel chat real-time meluncur masuk dari samping kanan frame mockup, memperlihatkan pesan konfirmasi instan dari klien.
4. **Act IV — Apple-Style Bento Grid (Deep Architecture Breakdown):**
   Grid modular bergaya Apple Pro Bento Box:
   - *Box 1 (Hero Bento - Security & Privacy):* Client Invitation by Email — sistem eksklusif anti bocor link.
   - *Box 2:* Dynamic Quota & Subscription System.
   - *Box 3:* Lightning-Fast Draco Compression Engine.
   - *Box 4:* Dual-Capacity Architecture (1 Akun untuk Arsitek & Klien).
5. **Act V — Tiered Pricing Table:**
   Kartu perbandingan harga bergaya Apple Store: Free vs Pro Tier dengan switcher periode langganan dan rincian kuota dinamis dari `system_settings`.
6. **Act VI — Minimalist Final CTA & Footer:**
   Headline penutup yang ringkas dan kuat, tombol CTA *"Mulai Sekarang — Gratis"*, didukung footer minimalis dengan tautan legalitas dan copyright.

### 3.x.C Performance & Architecture Guardrails
- **Zero WebGL Overhead:** Tidak ada engine Three.js/TresJS atau aset model `.glb` yang di-download di landing page publik. Seluruh efek scrollytelling memanfaatkan CSS `translate3d`, `opacity`, `transform-gpu`, dan SVG paths yang diorkestrasi GSAP.
- **Blazing Fast FCP & LCP:** First Contentful Paint < 1.0 detik dengan dukungan Nuxt SSR/SSG.
- **Reduced Motion Friendly:** Mendukung `@media (prefers-reduced-motion)` di mana scrollytelling secara graceful berubah menjadi tata letak vertikal standar bagi pengguna yang sensitif terhadap gerakan animasi.
---

## 4. Dynamic Business Rules & Subscription Plan Configuration

Batas kuota dan izin fitur diatur secara dinamis melalui tabel `plans` (dengan fallback/pelengkap di `system_settings`), serta dapat di-override secara individual per user via `user_plan_overrides`:

| Plan Slug | Project Limit | Can Create | Can Edit | Can Delete | Fitur Tambahan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `free` | `1` | Yes | Yes (Bisa Tambah & Edit) | Yes | Kuota 1 project. Akses basic 3D viewer & pinned comment. |
| `pro` | `20` | Yes | Yes | Yes | Kuota 20 project, export, productivity tools, higher file size. |
| `enterprise` | `null` (Unlimited) | Yes | Yes | Yes | Unlimited projects, advanced analytics, audit logs, SSO, API access, custom branding, priority support. |

### 4.A Formula Effective Project Limit
$$\text{Effective Limit} = \text{Custom User Override (jika ada)} \mathbin{??} \text{Subscription Plan Limit} \mathbin{??} 1$$
- Nilai `null` merepresentasikan **Unlimited** (bukan angka arbitrer seperti 999999).
- Jika admin menghapus custom limit, batas kuota otomatis kembali ke default paket langganan.
- Downgrade paket tidak pernah menghapus project pengguna yang sudah ada; jika jumlah project melebihi batas paket baru, sistem menampilkan status peringatan dan memblokir pembuatan project baru sampai kuota mencukupi.

### 4.x Client Invitation & Access Control Flow (Ringkasan Bisnis)
1. Arsitek membuka project miliknya → memasukkan alamat email Klien pada form "Invite Client" → sistem membuat baris di `project_clients` (status `pending`) & mengirim email undangan berisi link `/p/{share_token}`.
2. Klien membuka link tersebut → jika belum login, diarahkan ke halaman Login/Register (Google OAuth atau Email) dengan email pre-filled dari undangan.
3. Setelah login, sistem mencocokkan email akun yang login dengan `project_clients.email` untuk project tsb:
   - **Match & masih `pending`** → status diubah jadi `accepted`, `user_id` di-attach, `accepted_at` di-set, Klien diarahkan ke 3D Viewer.
   - **Match & sudah `accepted`** → langsung masuk ke 3D Viewer (kunjungan berikutnya).
   - **Tidak match (email login ≠ email undangan)** → `403 Forbidden`, tidak boleh mengakses project sama sekali.
4. Arsitek bisa melihat daftar Klien yang diundang beserta statusnya (`pending`/`accepted`) di dashboard project, serta mencabut akses (revoke) kapan saja.
5. Chat real-time & pin comment hanya terbuka untuk Klien berstatus `accepted` pada project terkait.

---

## 5. MVP Technical Architecture Requirements

### **A. Backend Domain-Driven Design (DDD) - Latest Stable Stack**
* **Framework:** Laravel 13 (Arsitektur Domain-Driven Design / DDD)[cite: 2].
* **Packages:** `laravel/socialite` (Google OAuth), `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `midtrans/midtrans-php`, `predis/predis`, `laravel/reverb` (WebSocket server untuk Real-time Chat)[cite: 2].
* **Storage Abstraction:** Laravel Filesystem S3-Compatible Driver (Local Disk di Dev / Cloudflare R2 di Prod)[cite: 2].
* **Domain Modules** — *domain murni berdasarkan bounded context, BUKAN berbasis role (lihat aturan wajib di `AI_INSTRUCTIONS.md` Section 2.A)*[cite: 2]:
  * `Domains/Auth`: Menangani registrasi (tanpa pilihan role), login email/password, callback Google OAuth, dan pencocokan email undangan saat login[cite: 2].
  * `Domains/SystemConfig`: Menangani konfigurasi kuota & aturan bisnis global (`system_settings`), termasuk backoffice panel untuk Super Admin[cite: 2].
  * `Domains/Project`: Menangani upload, versioning revisi, validasi kuota dinamis, kuota revisi client, **dan Client Invitation (`project_clients`)**[cite: 1, 2, 3].
  * `Domains/Comment`: Menangani koordinat $X, Y, Z$, normal vector, dan thread balasan[cite: 2].
  * `Domains/Chat`: Menangani pesan real-time antara Arsitek & Klien per project, broadcasting via Laravel Reverb.
  * `Domains/Billing`: Menangani integrasi Midtrans, webhook listener, dan status `Subscription`[cite: 2].

### **B. Frontend Feature-Driven Architecture**
* **Framework:** Vue 3 / Nuxt 3 dengan pendekatan Feature-Based (`features/auth`, `features/viewer-3d`, `features/spatial-comments`, `features/chat`, `features/billing`, `features/landing`)[cite: 2].
* **3D Engine & Utils (Aplikasi Internal):** Three.js, `@tresjs/core`, `three-stdlib` (DRACOLoader), Pinia, `@vueuse/core`, `lucide-vue-next`, `vue3-google-signin`[cite: 2] — hanya aktif di 3D Viewer internal setelah login/undangan.
* **Apple-Style Motion & Scrollytelling Engine:** `gsap` + plugin `ScrollTrigger` untuk pinned section, scrubbing animations, layered mockup transformations, dan smooth scroll pacing bergaya Apple product landing page (murni hardware-accelerated CSS/SVG, 0% WebGL di landing page).
* **Real-time Client:** `laravel-echo` + adapter Reverb (`@laravel/echo` pusher-compatible protocol) untuk konsumsi WebSocket chat di frontend.
* **Raycasting:** `THREE.Raycaster` untuk kalkulasi interseksi $(X, Y, Z)$ saat objek diklik[cite: 2].

### **C. Storage, Processing & Database**
* **Database & Cache:** PostgreSQL + Redis (Caching & Queue Worker)[cite: 2].
* **Tabel Inti:** `users`, `system_settings`, `projects`, `project_versions`, `project_clients`, `comments`, `chat_messages`, `subscriptions`, `transactions` — lihat rincian lengkap di `DATABASE_SCHEMA.md`[cite: 2].
* **Storage & CDN:** Local Storage (Dev) / Cloudflare R2 (S3-Compatible Object Storage Prod) + Cloudflare CDN[cite: 2].
