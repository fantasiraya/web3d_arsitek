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
| Database Migration `system_settings` | Database | 🔴 Pending | |
| Dynamic Config Seeder | Backend | 🔴 Pending | Quota Free Tier (Max 2 Project, 15MB) & Pro Tier (Max 100 Project, 100MB), + `free_tier_max_invited_clients` (5) & `pro_tier_max_invited_clients` (unlimited) |
| System Settings Caching | Redis | 🔴 Pending | Cache key: `system_settings_all` |
| Admin Settings Control Panel | Frontend Dashboard | 🔴 Pending | Interface update kuota sistem |

### 3. Project & Storage Domain (`Domains/Project`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `projects` | Database | 🔴 Pending | UUID Primary Key, `max_revisions_allowed` & `current_revision_count` columns |
| Create Project Action & Quota Check | Backend DDD | 🔴 Pending | Validasi kuota ke Redis |
| File Upload Handler (Local/R2) | Backend Storage | 🔴 Pending | Support `.glb` |
| Background Draco Queue Worker | Queue Worker | 🔴 Pending | `gltf-pipeline` |
| Database Migration `project_clients` | Database | 🔴 Pending | v2.4: unique `(project_id, email)`, kolom `status`, `invited_by` |
| Client Invitation Action (`InviteClientAction`) | Backend DDD | 🔴 Pending | v2.4: assign email Klien + trigger `ClientInvitationNotification` |
| Client Access Validation Middleware | Backend DDD | 🔴 Pending | v2.4: `ProjectClientAccessMiddleware` — ganti total model share-link publik |
| Revoke Client Access Action | Backend DDD | 🔴 Pending | v2.4: Arsitek cabut akses Klien kapan saja |
| Invitation Email Notification (Mailable) | Backend | 🔴 Pending | v2.4: berisi link `/p/{share_token}` + email pre-fill login |
| Client Access List UI (Dashboard) | Frontend Dashboard | 🔴 Pending | v2.4: lihat status pending/accepted per Klien, tombol revoke |
| Project Versioning Migration (`project_versions`) | Database | 🔴 Pending | Support multi-revisi .glb & history log |
| Custom Client Revision Setting | Backend / Dashboard | 🔴 Pending | Arsitek bisa set batas revisi khusus per project (misal: 3x) |
| Revision Limit Enforcement Middleware | Backend DDD | 🔴 Pending | Blokir upload & kirim feedback jika `current_revision_count >= max_revisions_allowed` |

### 4. 3D Viewer & Annotation Domain (`Domains/Comment`)
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| WebGL 3D Viewport Rendering | Frontend (TresJS) | 🔴 Pending | Orbit controls |
| Raycaster Intersect & Coordinates | Frontend Vue 3 | 🔴 Pending | Vector X, Y, Z & Normal |
| Database Migration `comments` | Database | 🔴 Pending | Spatial vector fields |
| Pin Comment Marker Overlay | Frontend | 🔴 Pending | Marker di koordinat 3D |
| Auth Wall + Invitation Wall for Commenting | Frontend / Auth | 🔴 Pending | v2.4: bukan cuma auth wall — juga wajib lolos `project_clients` accepted |
| Revision Counter Increment Logic | Backend DDD | 🔴 Pending | Increment `projects.current_revision_count` saat pin root baru dibuat client |
| Revision Counter & Status Badge UI | Frontend Viewer | 🔴 Pending | Menampilkan counter revisi (cth: "Revisi 2 dari 3") & badge peringatan jika kuota habis |

### 5. Chat & Communication Domain (`Domains/Chat`) 🆕 [v2.4]
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Database Migration `chat_messages` | Database | 🔴 Pending | UUID PK, index `(project_id, created_at)` |
| Laravel Reverb Setup (WebSocket Server) | Backend Infra | 🔴 Pending | `laravel/reverb`, konfigurasi `config/reverb.php` |
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
| Database Migration `transactions` | Database | 🔴 Pending | Order ID TRX-YYYYMMDD |
| Database Migration `subscriptions` | Database | 🔴 Pending | Riwayat status langganan, relasi ke `transactions` |
| Midtrans Snap Integration | Backend / Gateway | 🔴 Pending | `midtrans/midtrans-php` |
| Payment Webhook Handler | Backend Webhook | 🔴 Pending | Auto-update `subscriptions` & cache `users.subscription_status` menjadi `pro` (dual-write, lihat AI_INSTRUCTIONS Section G) |
| Invoice & Billing History | Frontend Dashboard | 🔴 Pending | |

### 7. Testing & Data Seeding
| Menu / Fitur | Scope | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| `SystemSettingSeeder` | Database Seeder | 🔴 Pending | Seed nilai default kuota Free & Pro Tier |
| Model Factories (User, Project, Comment, Subscription) | Database Factory | 🔴 Pending | Support UUID generation |
| Model Factories (ProjectClient, ChatMessage) 🆕 | Database Factory | 🔴 Pending | v2.4 |
| Form Requests Validation | Domain Requests | 🔴 Pending | Validasi payload per Domain |

### 8. Automated Testing & Quality Assurance (Post-MVP)
| Test Suite | Target Coverage | Status | Catatan & Tgl Selesai |
| :--- | :--- | :---: | :--- |
| Auth Domain Unit & Feature Tests | `Domains/Auth` | 🔴 Pending | Test Login, Register, Google OAuth, Invitation Email Matching |
| SystemConfig Unit Tests | `Domains/SystemConfig` | 🔴 Pending | Test Quota Retrieval & Redis Cache |
| Project Domain Unit Tests | `Domains/Project` | 🔴 Pending | Test Upload, Quota Exceeded, Draco Job |
| Client Invitation & Access Control Tests 🆕 | `Domains/Project` & `Domains/Auth` | 🔴 Pending | v2.4: test undang email, test 403 saat email tidak match, test revoke |
| Revision Limit & Gatekeeper Tests | `Domains/Project` & `Domains/Comment` | 🔴 Pending | Test pemblokiran revisi ke-N jika kuota habis, test increment counter |
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
