# 📁 SaaS Web 3D Directory & Folder Structure

Dokumen ini adalah acuan resmi struktur folder untuk backend (Laravel 13 DDD), frontend (Nuxt 3 Feature-Driven), dan berkas panduan AI.

> **v2.4 Changelog:** Menambahkan `Domains/Chat` (real-time messaging), `Domains/Project/Actions` untuk Client Invitation, `Http/Middleware` untuk validasi akses Klien, `features/chat`, serta struktur landing page scrollytelling + sample 3D showcase publik.

---

```text
my-saas-3d-project/
├── .github/                   # CI/CD Workflows (GitHub Actions)
│
├── guide/                     # 📂 FOLDER PANDUAN & KOORDINASI AI
│   ├── AI_INSTRUCTIONS.md     # System prompt & aturan wajib AI Coding
│   ├── PRD.md                 # Product Requirement Document (v2.4)
│   ├── RTCF.md                # Arsitektur teknis & stack spec
│   ├── RISE.md                # Framework draf prompt & tugas
│   ├── DATABASE_SCHEMA.md     # Dokumentasi tabel, kolom, UUID, ERD (v2.4)
│   ├── PROGRESS_TRACKER.md    # Log & status pengerjaan fitur (Auto-update oleh AI)
│   └── FOLDER_STRUCTURE.md    # Referensi peta direktori projek ini
│
├── backend/                   # 🐘 LARAVEL 13 (DOMAIN-DRIVEN DESIGN)
│   ├── app/
│   │   ├── Domains/           # 🎯 BOUNDED CONTEXTS LOGIKA BISNIS
│   │   │   ├── Auth/
│   │   │   │   ├── Actions/            # Business Logic / Use Cases
│   │   │   │   │                       # MatchInvitedClientEmailAction.php (link user_id ke project_clients saat login)
│   │   │   │   ├── Controllers/        # API Controllers
│   │   │   │   ├── DTOs/               # Data Transfer Objects
│   │   │   │   ├── Models/             # User.php
│   │   │   │   └── Requests/           # LoginRequest.php, RegisterRequest.php
│   │   │   │
│   │   │   ├── SystemConfig/           # Konfigurasi Kuota Global & Setting SaaS (termasuk backoffice Super Admin)
│   │   │   │   ├── Actions/            # UpdateQuotaAction.php, GetSettingsAction.php
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Models/             # SystemSetting.php
│   │   │   │   ├── Repositories/       # SystemSettingRepository.php (Redis Cache)
│   │   │   │   └── Requests/           # UpdateSettingRequest.php
│   │   │   │
│   │   │   ├── Project/                # Manajemen Model 3D, Storage & Client Invitation
│   │   │   │   ├── Actions/            # CreateProjectAction.php, ProcessDracoAction.php,
│   │   │   │   │                       # InviteClientAction.php, RevokeClientAccessAction.php,
│   │   │   │   │                       # ValidateProjectClientAccessAction.php
│   │   │   │   ├── Controllers/        # ProjectController.php, ProjectClientController.php
│   │   │   │   ├── Models/             # Project.php, ProjectVersion.php, ProjectClient.php
│   │   │   │   ├── Notifications/      # ClientInvitationNotification.php (Mailable undangan)
│   │   │   │   ├── Jobs/               # CompressGlbJob.php (Queue Worker)
│   │   │   │   └── Requests/           # StoreProjectRequest.php, InviteClientRequest.php
│   │   │   │
│   │   │   ├── Comment/                # Spatial 3D Pin Annotation
│   │   │   │   ├── Actions/            # CreatePinCommentAction.php
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Models/             # Comment.php
│   │   │   │   └── Requests/           # StoreCommentRequest.php
│   │   │   │
│   │   │   ├── Chat/                   # 🆕 Real-time Messaging Arsitek ↔ Klien per Project
│   │   │   │   ├── Actions/            # SendChatMessageAction.php, MarkMessageReadAction.php
│   │   │   │   ├── Controllers/        # ChatController.php (index/store histori pesan)
│   │   │   │   ├── Events/             # ChatMessageSent.php (implements ShouldBroadcast)
│   │   │   │   ├── Models/             # ChatMessage.php
│   │   │   │   └── Requests/           # SendChatMessageRequest.php
│   │   │   │
│   │   │   └── Billing/                # Integrasi Midtrans SaaS & Subscription
│   │   │       ├── Actions/            # CreateSnapTokenAction.php, HandleWebhookAction.php
│   │   │       ├── Controllers/
│   │   │       ├── Models/             # Transaction.php, Subscription.php
│   │   │       └── Requests/
│   │   │
│   │   ├── Http/
│   │   │   └── Middleware/             # Role/Permission, QuotaCheck, RevisionLimitCheck,
│   │   │                                # ProjectClientAccessMiddleware.php (🆕 validasi email undangan vs user login)
│   │   └── Providers/                  # BroadcastServiceProvider.php (registrasi routes/channels.php)
│   │
│   ├── database/
│   │   ├── factories/                  # ProjectFactory.php, CommentFactory.php, SubscriptionFactory.php,
│   │   │                                # ProjectClientFactory.php, ChatMessageFactory.php
│   │   ├── migrations/                 # PostgreSQL UUID Table Migrations (termasuk project_clients, chat_messages)
│   │   └── seeders/                    # SystemSettingSeeder.php, DatabaseSeeder.php
│   │
│   ├── routes/
│   │   ├── api.php                     # Route V1 API per domain
│   │   ├── channels.php                # 🆕 Broadcasting channel authorization (private channel project.{id}.chat)
│   │   └── web.php
│   ├── config/
│   │   └── reverb.php                  # 🆕 Konfigurasi Laravel Reverb (WebSocket server)
│   └── storage/
│
├── frontend/                  # 🟢 NUXT 3 / VUE 3 (FEATURE-DRIVEN)
│   ├── assets/                # CSS, Tailwind, WebGL Textures/Shaders
│   │   ├── css/                # main.css, tailwind.css, theme.css
│   │   ├── images/             # Hero banners, logo, favicon
│   │   └── models/             # 🆕 sample-showcase.glb (Draco-compressed, demo publik landing page)
│   │
│   ├── components/            # UI Reusable Components
│   │   ├── ui/                 # Base UI (Button, Modal, Card, Navbar, Footer)
│   │   └── landing/            # Landing Page Components (Hero, FeatureCard, PricingTable)
│   │       └── scrollytelling/ # 🆕 ScrollytellingSection.vue, StorySceneCanvas.vue, ScrollProgressDots.vue
│   │
│   ├── features/              # 🧩 FEATURE-BASED MODULES
│   │   ├── viewer-3d/         # Engine Three.js / TresJS
│   │   │   ├── components/    # Canvas3D.vue, PinMarker.vue, OrbitControls.vue, RevisionBadge.vue
│   │   │   ├── composables/   # useRaycaster.ts, useThreeScene.ts
│   │   │   └── utils/         # dracoLoader.ts
│   │   ├── projects/          # Dashboard Project Management
│   │   │   ├── components/    # ProjectList.vue, InviteClientModal.vue (🆕), ClientAccessList.vue (🆕)
│   │   │   └── composables/   # useProjectClients.ts (🆕 kelola undangan & status pending/accepted)
│   │   ├── comments/          # Sidebar Feedback & Thread List
│   │   ├── chat/              # 🆕 Real-time Chat Arsitek ↔ Klien
│   │   │   ├── components/    # ChatWindow.vue, ChatBubble.vue, ChatInput.vue, TypingIndicator.vue
│   │   │   └── composables/   # useChat.ts (kirim/terima pesan), useEcho.ts (koneksi Reverb/Echo)
│   │   ├── billing/           # Pricing Table & Midtrans Snap Pop-up
│   │   └── landing/           # 🆕 Landing Page Scrollytelling Engine
│   │       ├── components/    # HeroScrollScene.vue, NarrativeStep.vue
│   │       └── composables/   # useScrollytelling.ts (GSAP ScrollTrigger + kamera TresJS reaktif terhadap progres scroll)
│   │
│   ├── composables/           # Shared Composables (useAuth.ts, useApi.ts)
│   ├── layouts/               # default.vue (Landing Page), dashboard.vue, viewer.vue
│   ├── pages/                 # Routing Nuxt
│   │   ├── index.vue          # 🏠 Landing Page Scrollytelling (SSR Enabled)
│   │   ├── pricing.vue        # Pricing & Benefit Page
│   │   ├── demo.vue           # Interactive 3D Demo Preview (publik, sample model)
│   │   ├── login.vue          # Mendukung query `?invited_email=` prefill dari undangan
│   │   ├── register.vue
│   │   ├── dashboard/
│   │   │   ├── index.vue
│   │   │   └── settings.vue   # Backoffice Control Panel Owner/Admin
│   │   └── p/
│   │       └── [token].vue    # Halaman akses project via undangan — WAJIB auth guard +
│   │                            # validasi email (`project_clients`) sebelum render Viewer/Chat/Comment
│   ├── middleware/             # 🆕 auth.ts, project-client-access.client.ts (guard route p/[token])
│   ├── plugins/                 # 🆕 echo.client.ts (init Laravel Echo + Reverb connector)
│   ├── stores/                 # Pinia Stores (useProjectStore.ts, useCommentStore.ts, useChatStore.ts 🆕)
│   └── nuxt.config.ts
│
└── docker-compose.yml         # Dev Environment (PostgreSQL, Redis, Reverb, MinIO/R2 Mock)
```
