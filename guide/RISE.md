> **v2.4 Note:** File ini adalah versi terkonversi & disinkronkan dari `RISE.txt` awal, ditambahkan langkah-langkah kode untuk Client Invitation, Real-time Chat, dan Scrollytelling Landing Page.

[ROLE]
Bertindaklah sebagai Senior Tech Lead spesialis Laravel 13 (Domain-Driven Design / DDD) dan Vue 3 / Nuxt 3 (Feature-Driven Architecture) dengan keahlian Three.js/TresJS.

[INPUT]
Mengacu pada PRD v2.4 "SaaS Web 3D Architecture Presentation" beserta kebutuhan spesifikasi teknis terkini:
- Backend: Laravel 13 (DDD), PostgreSQL, Redis, Storage Abstraction S3-Compatible (Local Disk dev / Cloudflare R2 prod), spatie/laravel-permission (khusus `super_admin`), spatie/laravel-medialibrary, midtrans/midtrans-php, laravel/socialite, laravel/reverb.
- Frontend: Vue 3 / Nuxt 3 (Feature-Driven), TresJS / Three.js, Pinia, @vueuse/core, lucide-vue-next, vue3-google-signin, gsap (ScrollTrigger), laravel-echo.
- Model Akun: TIDAK ada pilihan role saat registrasi. Arsitek = kapabilitas default akun. Klien = status per-project via `project_clients`. Dual-capacity 1 akun didukung native.

[STEPS]
1. Tuliskan skema migration PostgreSQL untuk tabel `users` (dengan kolom `google_id`, `avatar` — TANPA kolom `role`), `system_settings`, `subscriptions` (relasi ke `transactions`), `projects` (dengan kolom `max_revisions_allowed`, `current_revision_count`), `project_versions`, `project_clients` (undangan Klien: `email`, `user_id` nullable, `status`, `invited_by`), `chat_messages` (`project_id`, `sender_id`, `message`, `read_at`), dan `comments`.
2. Tuliskan Controller & Service Laravel 13 `GoogleAuthController` pada `Domains/Auth` untuk menangani otentikasi Google OAuth via `laravel/socialite`, meng-generate Sanctum Token, DAN menjalankan pencocokan email terhadap `project_clients` pending (update jadi `accepted` bila cocok) — TANPA field pemilihan role di request.
3. Tuliskan Action Class Laravel 13 `CreateProjectAction` pada `Domains/Project` yang memvalidasi batasan kuota dinamis dari `system_settings` via Redis Cache sebelum mengunggah file.
4. Buatkan Webhook Controller & Service Handler di `Domains/Billing` untuk menangani notifikasi pembayaran dari Midtrans (Settlement) dan otomatis meng-update tabel `subscriptions` beserta cache `users.subscription_status` menjadi `pro` (dual-write, lihat AI_INSTRUCTIONS Section G).
5. Tuliskan Composable Vue 3 `useRaycaster.ts` pada struktur `features/viewer-3d` untuk menangkap koordinat interseksi 3D (X, Y, Z) dan Vector Normal saat objek diklik.
6. Tuliskan Middleware/Action `RevisionLimitEnforcement` pada `Domains/Comment` yang mengecek `projects.current_revision_count` terhadap `max_revisions_allowed` sebelum mengizinkan client membuat pin komentar baru, dan melempar `403 RevisionLimitExceededException` sesuai format JSON di `AI_INSTRUCTIONS.md` Section F.
7. 🆕 Tuliskan Action Class `InviteClientAction` pada `Domains/Project` yang menerima email Klien, membuat/update baris `project_clients` (status `pending`), mengecek kuota `free_tier_max_invited_clients`/`pro_tier_max_invited_clients` via Redis Cache, dan mengirim `ClientInvitationNotification`. Sertakan juga `RevokeClientAccessAction`.
8. 🆕 Tuliskan Middleware `ProjectClientAccessMiddleware` pada `app/Http/Middleware` yang memvalidasi: request diteruskan HANYA jika user login adalah owner project ATAU punya baris `project_clients` dengan `status = 'accepted'` untuk project tsb; selain itu lempar `403 Forbidden` sesuai format JSON di `AI_INSTRUCTIONS.md` Section H.
9. 🆕 Tuliskan Domain `Chat` lengkap: `SendChatMessageAction`, Event `ChatMessageSent` (implements `ShouldBroadcast`, private channel `project.{project_id}.chat`), `ChatController` (index histori + store), dan isi `routes/channels.php` untuk otorisasi channel (memakai validasi yang sama seperti Section H.3 di `AI_INSTRUCTIONS.md`).
10. 🆕 Tuliskan Composable Vue 3 `useChat.ts` (kirim pesan via API + dengarkan event lewat Echo) dan `useEcho.ts` (inisialisasi koneksi Reverb) pada `features/chat`.
11. 🆕 Tuliskan Composable Vue 3 `useScrollytelling.ts` pada `features/landing` yang menggunakan GSAP `ScrollTrigger` untuk mem-pin section narasi landing page dan menggerakkan kamera TresJS pada canvas sample 3D publik (`assets/models/sample-showcase.glb`) mengikuti progres scroll (0–1), termasuk logic lazy-mount canvas via `IntersectionObserver` agar tidak membebani SSR/FCP.

[EXPECTATION]
Output berupa contoh kode modular, rapi, aman, dan siap pakai (Developer-Ready / Production-Grade) tanpa penjelasan teori yang bertele-tele. Setiap kode yang menyentuh akses project (viewer, comment, chat) WAJIB melalui validasi `project_clients`/`ProjectClientAccessMiddleware` — dilarang membuat jalur akses publik tanpa login.
