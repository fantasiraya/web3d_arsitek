# 📊 SaaS Web 3D Progress Tracker

> **CATATAN UNTUK AI:** File ini dikelola dan diperbarui secara otomatis oleh AI setiap kali sebuah menu, modul, atau fitur selesai dikerjakan dan berhasil dijalankan.

---

## 🚦 Status Legend
- 🔴 **Pending:** Belum dikerjakan.
- 🟡 **In Progress:** Sedang dalam proses koding/refactoring.
- 🟢 **Completed:** Selesai, lulus tes, dan berfungsi dengan baik.

---

## 📋 Feature & Menu Checklist

### 1. Identity & Auth Domain (`Domains/Auth`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Email & Password Register/Login (tanpa pilihan role) | Backend & Frontend | 🔴 Pending | v2.4: form register TIDAK boleh ada field pilih role |
| Google OAuth / One-Tap Login | Backend (`socialite`) & Frontend | 🔴 Pending | |
| Sanctum Token Generation | Backend API | 🔴 Pending | |
| Role & Permission Authorization | Backend Middleware | 🔴 Pending | Hanya untuk `super_admin` via Spatie; Arsitek/Klien bukan Spatie role |
| Client Invitation Email Matching on Login | Backend `Domains/Auth` | 🔴 Pending | v2.4: cocokkan email login dengan `project_clients.email`, update status `accepted` |
| Dual-Capacity Account Support (Arsitek + Klien 1 akun) | Backend / QA | 🔴 Pending | v2.4: pastikan tidak ada logic yang mencegah 1 akun jadi Arsitek & Klien bersamaan |

### 2. Platform Config Domain (`Domains/SystemConfig`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `system_settings` | Database | 🟢 Completed | Selesai & teruji (2026-09-17) |
| Dynamic Config Seeder | Backend | 🟢 Completed | Quota Free & Pro Tier, default client revisions (2026-09-17) |
| System Settings Caching | Redis | 🔴 Pending | Cache key: `system_settings_all` |
| Admin Settings Control Panel | Frontend Dashboard | 🔴 Pending | Interface update kuota sistem |

### 3. Project & Storage Domain (`Domains/Project`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `projects` | Database | 🟢 Completed | UUID PK, max_revisions_allowed & current_revision_count (2026-09-17) |
| Create Project Action & Quota Check | Backend DDD | 🟢 Completed | Validasi kuota ke Redis (2026-09-17) |
| File Upload Handler (Local/R2) | Backend Storage | 🟢 Completed | Support `.glb` & ProjectVersion (2026-09-17) |
| Background Draco Queue Worker | Queue Worker | 🟢 Completed | `DracoCompressionJob` dgn `gltf-pipeline` (2026-09-17) |
| Database Migration `project_clients` | Database | 🟢 Completed | v2.4: unique `(project_id, email)`, status, invited_by (2026-09-17) |
| Client Invitation Action (`InviteClientAction`) | Backend DDD | 🟢 Completed | v2.4: assign email Klien + trigger notification (2026-09-17) |
| Client Access Validation Middleware | Backend DDD | 🟢 Completed | v2.4: `ProjectClientAccessMiddleware` (2026-09-17) |
| Revoke Client Access Action | Backend DDD | 🟢 Completed | v2.4: Arsitek cabut akses Klien kapan saja (2026-09-17) |
| Invitation Email Notification (Mailable) | Backend | 🟢 Completed | v2.4: `ClientInvitationNotification` (2026-09-17) |
| Client Access List UI (Dashboard) | Frontend Dashboard | 🟢 Completed | v2.4: lihat status pending/accepted per Klien, modal kelola & tombol revoke (2026-09-17) |
| Project Versioning Migration (`project_versions`) | Database | 🟢 Completed | Support multi-revisi .glb & history log (2026-09-17) |
| Custom Client Revision Setting | Backend / Dashboard | 🟢 Completed | Arsitek bisa set batas revisi khusus per project saat buat proyek (2026-09-17) |
| Revision Limit Enforcement Middleware | Backend DDD | 🟢 Completed | `RevisionLimitEnforcementMiddleware` (2026-09-17) |

### 4. 3D Viewer & Annotation Domain (`Domains/Comment`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| WebGL 3D Viewport Rendering | Frontend (TresJS) | 🔴 Pending | Orbit controls |
| Raycaster Intersect & Coordinates | Frontend Vue 3 | 🔴 Pending | Vector X, Y, Z & Normal |
| Database Migration `comments` | Database | 🟢 Completed | Spatial vector fields X, Y, Z & Normal (2026-09-17) |
| Pin Comment Marker Overlay | Frontend | 🔴 Pending | Marker di koordinat 3D |
| Auth Wall + Invitation Wall for Commenting | Frontend / Auth | 🔴 Pending | v2.4: bukan cuma auth wall — juga wajib lolos `project_clients` accepted |
| Revision Counter Increment Logic | Backend DDD | 🔴 Pending | Increment `projects.current_revision_count` saat pin root baru dibuat client |
| Revision Counter & Status Badge UI | Frontend Viewer | 🔴 Pending | Menampilkan counter revisi (cth: "Revisi 2 dari 3") & badge peringatan jika kuota habis |

### 5. Chat & Communication Domain (`Domains/Chat`) 🆕 [v2.4]
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `chat_messages` | Database | 🟢 Completed | UUID PK, index `(project_id, created_at)` (2026-09-17) |
| Laravel Reverb Setup (WebSocket Server) | Backend Infra | 🟢 Completed | `laravel/reverb`, konfigurasi `config/reverb.php` (2026-09-17) |
| Channel Authorization (`routes/channels.php`) | Backend DDD | 🔴 Pending | Private channel `project.{project_id}.chat`, otorisasi sama seperti `ProjectClientAccessMiddleware` |
| Send Chat Message Action & Event | Backend DDD | 🔴 Pending | `SendChatMessageAction` + `ChatMessageSent` (persist-then-broadcast) |
| Chat History Endpoint (Pagination) | Backend API | 🔴 Pending | `ChatController@index` |
| Mark as Read Action | Backend DDD | 🔴 Pending | Update `read_at` |
| Frontend Chat Window UI | Frontend (`features/chat`) | 🔴 Pending | `ChatWindow.vue`, `ChatBubble.vue`, `ChatInput.vue` |
| Laravel Echo Client Setup | Frontend | 🔴 Pending | `plugins/echo.client.ts`, composable `useEcho.ts` |
| Typing Indicator & Read Receipt | Frontend / Backend | 🔴 Pending | P1 — Should Have |

### 6. Billing & Subscription Domain (`Domains/Billing`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `transactions` | Database | 🟢 Completed | Order ID TRX-YYYYMMDD, UUID PK (2026-09-17) |
| Database Migration `subscriptions` | Database | 🟢 Completed | Riwayat status langganan, relasi ke `transactions` (2026-09-17) |
| Midtrans Snap Integration | Backend / Gateway | 🔴 Pending | `midtrans/midtrans-php` |
| Payment Webhook Handler | Backend Webhook | 🔴 Pending | Auto-update `subscriptions` & cache `users.subscription_status` menjadi `pro` (dual-write, lihat AI_INSTRUCTIONS Section G) |
| Invoice & Billing History | Frontend Dashboard | 🔴 Pending | |

### 7. Testing & Data Seeding
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| `SystemSettingSeeder` | Database Seeder | 🟢 Completed | Seed nilai default kuota Free & Pro Tier (2026-09-17) |
| Model Factories (User, Project, Comment, Subscription) | Database Factory | 🟢 Completed | Support UUID generation (2026-09-17) |
| Model Factories (ProjectClient, ChatMessage) 🆕 | Database Factory | 🟢 Completed | v2.4 (2026-09-17) |
| Form Requests Validation | Domain Requests | 🔴 Pending | Validasi payload per Domain |

### 8. Automated Testing & Quality Assurance (Post-MVP)
| Test Suite | Target Coverage | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Auth Domain Unit & Feature Tests | `Domains/Auth` | � Completed | Test Login, Register, Google OAuth, Invitation Email Matching (2026-09-17) |
| SystemConfig Unit Tests | `Domains/SystemConfig` | 🟢 Completed | Test Quota Retrieval & Redis Cache (2026-09-17) |
| Project Domain Unit Tests | `Domains/Project` | 🟢 Completed | Test Upload, Quota Exceeded, Draco Job (2026-09-17) |
| Client Invitation & Access Control Tests 🆕 | `Domains/Project` & `Domains/Auth` | 🟢 Completed | v2.4: test undang email, test 403 saat email tidak match, test revoke (2026-09-17) |
| Revision Limit & Gatekeeper Tests | `Domains/Project` & `Domains/Comment` | 🟢 Completed | Test pemblokiran revisi ke-N jika kuota habis, test increment counter (2026-09-17) |
| Comment Spatial Pin Unit Tests | `Domains/Comment` | 🔴 Pending | Test Vector Coordinates & Raycaster |
| Chat Domain Feature Tests 🆕 | `Domains/Chat` | 🔴 Pending | v2.4: test broadcasting, channel authorization, test non-invited user ditolak |
| Billing & Webhook Feature Tests | `Domains/Billing` | 🔴 Pending | Test Midtrans Signature, Status Updates & sinkronisasi `subscriptions` |

### 9. Landing Page & Marketing Domain (Frontend Nuxt 3)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Hero Section & CTA | Frontend (SSR) | 🔴 Pending | Landing Page Index |
| Scrollytelling Engine (GSAP ScrollTrigger) 🆕 | Frontend (`features/landing`) | 🔴 Pending | v2.4: `useScrollytelling.ts`, pinned section per narasi |
| Sample 3D Model Showcase (Publik, No Login) 🆕 | Frontend (TresJS) | 🔴 Pending | v2.4: `assets/models/sample-showcase.glb`, lazy-mount via IntersectionObserver |
| Interactive 3D Demo Preview | Frontend (TresJS) | 🔴 Pending | Demo tanpa login, terintegrasi scrollytelling |
| Dynamic Pricing Table | Frontend | 🔴 Pending | Fetch data kuota dari API |
| SEO Meta Tags & OpenGraph | Frontend Nuxt | 🔴 Pending | Support social media preview |

---

## 📝 Activity Logs
- **2026-09-16:** Inisialisasi struktur dokumen guide (`PRD.md`, `RTCF.md`, `RISE.md`, `DATABASE_SCHEMA.md`, `AI_INSTRUCTIONS.md`, `PROGRESS_TRACKER.md`).
- **2026-09-16:** Penambahan fitur *Client Revision Limit & Counter Display* untuk proteksi arsitek dari revisi berlebihan.
- **2026-09-16:** Sinkronisasi dokumen v2.3 — menambahkan kolom `max_revisions_allowed` & `current_revision_count` ke `projects`, menambahkan tabel `subscriptions`, memperbaiki referensi domain `Domains/Admin` → `Domains/SystemConfig` di PRD, memperbaiki bug tabel ganda di section 8, dan menstandarkan seluruh file guide ke format `.md`.
- **2026-09-17:** Sinkronisasi dokumen v2.4 — (1) Landing page diubah ke **Scrollytelling Experience** dengan sample 3D showcase publik; (2) Ditambahkan **Domain baru `Domains/Chat`** untuk real-time messaging Arsitek↔Klien via Laravel Reverb; (3) Model akses dirombak total dari share-link publik read-only menjadi **Client Invitation by Email** (tabel baru `project_clients`, middleware `ProjectClientAccessMiddleware`); (4) Diklarifikasi **Role & Account Model**: tidak ada pilihan role saat registrasi, Arsitek adalah kapabilitas default, Klien adalah status per-project turunan dari `project_clients`, dan satu akun dapat merangkap Arsitek + Klien sekaligus (dual-capacity). Diperbarui: `PRD.md`, `DATABASE_SCHEMA.md`, `FOLDER_STRUCTURE.md`, `AI_INSTRUCTIONS.md` (Section H/I/J baru), `RTCF.md`, `RISE.md`.
- **2026-09-17:** Pengerjaan Fondasi Arsitektur DDD & Database Migrations (v2.4) — instalasi package (`socialite`, `reverb`, `spatie/laravel-permission`, `midtrans`, `predis`), pembuatan dan eksekusi seluruh migrasi database tabel UUID (`users`, `system_settings`, `projects`, `project_versions`, `project_clients`, `comments`, `chat_messages`, `transactions`, `subscriptions`), pembuatan Eloquent Domain Models di `app/Domains/`, Factories dengan dukungan UUID, dan seeder `SystemSettingSeeder` dengan verifikasi 47 Pest tests lulus 100%.
