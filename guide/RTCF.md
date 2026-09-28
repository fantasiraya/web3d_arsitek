> **v2.4 Note:** File ini adalah versi terkonversi & disinkronkan dari `RTCF.txt` awal, mengikuti PRD v2.4 (Apple-Style Scrollytelling Landing Page tanpa 3D WebGL publik, Client Invitation by Email, In-App Chat, Role Model tanpa pilihan role).

[ROLE]
Bertindaklah sebagai Senior Full-Stack Developer & Software Architect spesialis Laravel 13 (DDD) dan Vue 3 / Nuxt 3 (Feature-Driven).

[TASK]
Buatkan rancangan arsitektur teknis dan panduan integrasi sistem untuk platform SaaS Web 3D Arsitek berdasarkan PRD v2.4.

[CONTEXT]
1. Platform diperuntukkan bagi arsitek untuk mempresentasikan model 3D ke klien terundang (bukan publik), mengumpulkan pin komentar 3D, dan berkomunikasi via chat real-time.
2. **Model Akun & Role:** Tidak ada pilihan role saat registrasi. Setiap akun otomatis memiliki kapabilitas Arsitek (boleh membuat project). Status "Klien" bersifat per-project, ditentukan oleh kecocokan email pada `project_clients`. Satu akun bisa dual-capacity (Arsitek + Klien sekaligus). Hanya `super_admin` yang merupakan role eksplisit (Spatie), di-assign manual/internal.
3. Backend Tech Stack:
   - Framework: Laravel 13 (versi stabil terbaru, arsitektur Domain-Driven Design / DDD, domain murni berbasis bounded context: `Auth`, `SystemConfig`, `Project`, `Comment`, `Chat`, `Billing` — bukan berbasis role).
   - Auth: Laravel Sanctum + laravel/socialite (Google OAuth Single Sign-On), tanpa field pemilihan role di form register.
   - Database: PostgreSQL + Redis (Caching & Queue).
   - Storage Abstraction: Laravel Filesystem dengan driver S3-Compatible (Dinamis: Local Disk untuk environment Development / Cloudflare R2 S3-Compatible Object Storage untuk environment Production).
   - Real-time: **Laravel Reverb** (WebSocket server self-hosted) untuk broadcasting chat, private channel per project.
   - Packages: spatie/laravel-permission (khusus role `super_admin`), spatie/laravel-medialibrary, midtrans/midtrans-php, predis/predis, laravel/reverb.
4. Frontend Tech Stack:
   - Framework: Vue 3 / Nuxt 3 (Feature-Driven Architecture).
   - 3D Engine: Three.js, @tresjs/core, three-stdlib (DRACOLoader) — hanya untuk authenticated 3D Viewer di dalam aplikasi.
   - Motion & Scrollytelling: **gsap** + plugin **ScrollTrigger** untuk Apple-style pinned sections, text scrubbing, dan translasi animasi mockup UI produk (tanpa WebGL di landing page).
   - Real-time Client: **laravel-echo** (adapter Reverb) untuk konsumsi WebSocket chat.
   - Utilities & UI: Pinia, @vueuse/core, lucide-vue-next, vue3-google-signin, Tailwind CSS.
5. Fitur Utama & Aturan Bisnis:
   - Google OAuth / One-Tap Login untuk kemudahan registrasi/login — tanpa pilihan role.
   - Dynamic System Settings (system_settings) untuk batas kuota Free & Pro Tier via Redis Cache, termasuk batas jumlah Klien yang bisa diundang per project.
   - **Client Invitation by Email**: Arsitek meng-assign email Klien ke project (`project_clients`); tidak ada lagi link publik read-only. Klien wajib login & lolos validasi email-matching sebelum bisa mengakses apa pun terkait project.
   - **In-App Real-time Chat**: percakapan umum Arsitek↔Klien per project, terpisah dari pin comment spasial, dengan otorisasi akses yang identik dengan pin comment.
   - SaaS Billing dengan Payment Gateway (Midtrans) via Webhook Handler yang menulis ke tabel `transactions` dan `subscriptions` secara sinkron.
   - Background Queue untuk Draco Compression menggunakan gltf-pipeline di server.
   - Client Revision Limit dinamis per project (`projects.max_revisions_allowed` & `current_revision_count`) dengan gatekeeper 403.
   - **Apple-Style Scrollytelling Landing Page**: estetika Apple Pro (obsidian `#000000`, frosted glass, display typography bold, bento grid) dipadukan dengan storytelling sekuensial (pinned product journey, scrubbing problem-to-solution, layered UI mockup animation) tanpa WebGL demi FCP < 1.0s.

[FORMAT]
Sajikan output secara terstruktur dalam format Markdown:
1. Alur Arsitektur Sistem:
   - Authentication & Google OAuth Flow (tanpa role picker)
   - **Client Invitation & Access Validation Flow** (assign email → email matching saat login → 403 jika tidak match)
   - **Real-time Chat Flow** (persist message → broadcast via Reverb → channel authorization)
   - Payment Webhook
   - Quota Validation (termasuk kuota jumlah Klien yang diundang)
   - Revision Limit Gatekeeper
   - Dynamic Storage Handling
   - File Upload Queue
   - 3D Viewer
   - **Apple Scrollytelling Flow** (pinned container, scrubbed progress → UI mockup layer translation, fast SSR FCP)
2. Draf Struktur Folder DDD di Laravel 13 (termasuk `Domains/Chat`, `Domains/Project/Actions` untuk invitation) & Feature-Driven di Vue 3 (termasuk `features/chat`, `features/landing`).
3. Rekomendasi Struktur Endpoint API / Route Laravel 13 (Termasuk Auth/OAuth, Client Invitation endpoints, Chat endpoints & channel broadcasting, Webhook, dan System Settings).
