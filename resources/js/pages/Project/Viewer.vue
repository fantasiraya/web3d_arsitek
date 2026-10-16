<template>
    <div class="relative h-screen w-screen overflow-hidden bg-[#06070a] text-white flex flex-col select-none">

        <!-- ── TOP APP BAR ── -->
        <header class="relative z-30 flex h-14 shrink-0 items-center justify-between border-b border-white/10 bg-black/80 px-4 sm:px-6 backdrop-blur-2xl">
            <div class="flex items-center gap-3">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10 hover:text-white"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Dashboard</span>
                </Link>

                <div class="h-4 w-px bg-white/10 hidden sm:block"></div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold tracking-tight text-white line-clamp-1">{{ project.title || 'PitchArch Viewer' }}</span>
                    <span class="rounded bg-rose-500/20 border border-rose-500/30 px-2 py-0.5 text-[10px] font-mono text-rose-300 hidden sm:inline">
                        3D VIEWER
                    </span>
                    <span v-if="isAdminViewer" class="rounded bg-amber-500/20 border border-amber-500/30 px-2 py-0.5 text-[10px] font-mono text-amber-300 hidden sm:inline">
                        ADMIN READ-ONLY
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <!-- Revision Badge -->
                <RevisionBadge :project="project" :current-count="comments.length" />

                <!-- Pin count toggle -->
                <button
                    type="button"
                    @click="isDrawerOpen = !isDrawerOpen"
                    class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                >
                    <MessageSquare class="h-3.5 w-3.5 text-rose-400" />
                    <span>{{ comments.length }} Pin</span>
                </button>

                <!-- Fullscreen -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                >
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-neutral-300" />
                    <span class="hidden sm:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>

                <!-- Booklet PDF button -->
                <Link
                    :href="`/projects/${project.id}/booklet`"
                    class="flex items-center gap-1.5 rounded-full border border-orange-500/30 bg-orange-600/15 hover:bg-orange-600/30 px-3 py-1.5 text-xs font-semibold text-orange-300 transition shadow-sm"
                    title="Buka Lembar Kerja & Booklet PDF Standar Studio Arsitek"
                >
                    <Layers class="h-3.5 w-3.5 text-orange-400" />
                    <span class="hidden sm:inline">Booklet PDF</span>
                </Link>

                <!-- RAB button — muncul jika ada dokumen RAB yang visible -->
                <div v-if="hasVisibleRab" class="relative">
                    <button
                        type="button"
                        @click="showRabPanel = !showRabPanel; isChatOpen = false"
                        class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                        :class="showRabPanel && 'border-emerald-500/50 bg-emerald-500/10 text-emerald-300'"
                    >
                        <ClipboardList class="h-3.5 w-3.5" :class="showRabPanel ? 'text-emerald-400' : 'text-emerald-300'" />
                        <span class="hidden sm:inline">RAB</span>
                        <span class="inline-flex items-center justify-center rounded-full bg-emerald-500/80 px-1.5 text-[9px] font-bold text-white min-w-[16px]">
                            {{ rabDocuments.length }}
                        </span>
                    </button>

                    <!-- RAB dropdown panel -->
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-1"
                    >
                        <div
                            v-if="showRabPanel"
                            class="absolute right-0 top-10 z-50 w-80 rounded-2xl border border-white/15 bg-black/95 backdrop-blur-2xl shadow-2xl overflow-hidden"
                        >
                            <!-- Header -->
                            <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
                                <div class="flex items-center gap-2">
                                    <ClipboardList class="h-4 w-4 text-emerald-400" />
                                    <span class="text-sm font-semibold text-white">Rencana Anggaran Biaya</span>
                                </div>
                                <button @click="showRabPanel = false" class="text-neutral-500 hover:text-white transition-colors">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <!-- RAB document list -->
                            <div class="divide-y divide-white/5 max-h-80 overflow-y-auto">
                                <Link
                                    v-for="doc in rabDocuments"
                                    :key="doc.id"
                                    :href="`/projects/${project.id}/rab/${doc.id}`"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-white/5 transition-colors group"
                                >
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-white truncate">{{ doc.title }}</p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span :class="[
                                                'text-[10px] font-medium px-1.5 py-0.5 rounded-full border',
                                                doc.status === 'final'
                                                    ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
                                                    : 'bg-amber-500/20 text-amber-400 border-amber-500/30',
                                            ]">
                                                {{ doc.status === 'final' ? 'Final' : 'Draft' }}
                                            </span>
                                            <span class="text-[10px] text-neutral-500">{{ doc.items_count }} item</span>
                                            <span class="text-[10px] font-semibold text-emerald-400">
                                                {{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(doc.total) }}
                                            </span>
                                        </div>
                                    </div>
                                    <ExternalLink class="h-3.5 w-3.5 text-neutral-600 group-hover:text-emerald-400 transition-colors shrink-0" />
                                </Link>
                            </div>

                            <!-- Footer — owner bisa ke halaman RAB project -->
                            <div v-if="isOwner" class="border-t border-white/10 px-4 py-2.5">
                                <Link
                                    :href="`/projects/${project.id}/rab`"
                                    class="flex items-center justify-center gap-1.5 text-xs text-neutral-400 hover:text-white transition-colors"
                                >
                                    <ClipboardList class="h-3.5 w-3.5" />
                                    Kelola semua dokumen RAB
                                </Link>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Galeri Capture toggle -->
                <button
                    type="button"
                    @click="isGalleryOpen = !isGalleryOpen; isChatOpen = false; isDrawerOpen = false; showRabPanel = false"
                    class="relative flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                    :class="isGalleryOpen && 'border-amber-500/50 bg-amber-500/10 text-amber-300'"
                    title="Galeri Hasil Ambil Gambar 3D"
                >
                    <Image class="h-3.5 w-3.5" :class="isGalleryOpen ? 'text-amber-400' : 'text-neutral-300'" />
                    <span class="hidden sm:inline">Galeri</span>
                    <span
                        v-if="captures.length > 0"
                        class="inline-flex items-center justify-center rounded-full bg-amber-500/80 px-1.5 text-[9px] font-bold text-white min-w-[16px]"
                    >
                        {{ captures.length }}
                    </span>
                </button>

                <!-- Chat toggle — hanya untuk owner dan client, bukan admin viewer -->
                <button
                    v-if="!isAdminViewer"
                    type="button"
                    @click="isChatOpen = !isChatOpen; isDrawerOpen = false; isGalleryOpen = false"
                    class="relative flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                    :class="isChatOpen && 'border-indigo-500/50 bg-indigo-500/10 text-indigo-300'"
                >
                    <MessageCircle class="h-3.5 w-3.5" :class="isChatOpen ? 'text-indigo-400' : 'text-indigo-300'" />
                    <span class="hidden sm:inline">Chat</span>
                    <span
                        v-if="chatUnread > 0"
                        class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-500 text-[9px] font-bold text-white"
                    >
                        {{ chatUnread > 9 ? '9+' : chatUnread }}
                    </span>
                </button>
            </div>
        </header>

        <!-- ── MAIN VIEWPORT ── -->
        <div class="relative flex-1 w-full overflow-hidden">

            <!-- Three.js Canvas -->
            <div
                ref="viewerContainer"
                class="absolute inset-0 h-full w-full"
                :class="{
                    'cursor-grab active:cursor-grabbing': interactionMode === 'rotate',
                    'cursor-move': interactionMode === 'pan',
                    'cursor-crosshair': interactionMode === 'pin',
                }"
                @pointerdown="onPointerDown"
                @pointerup="onPointerUp"
            ></div>

            <!-- ── Camera Shutter Flash Effect & Loading Indicator ── -->
            <Transition
                enter-active-class="transition-opacity duration-75 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-300 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isShutterFlashing"
                    class="pointer-events-none absolute inset-0 z-40 bg-white/40 backdrop-blur-[1px]"
                ></div>
            </Transition>

            <!-- Floating Snapshot Capture Toast -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 -translate-y-2 scale-95"
            >
                <div
                    v-if="isCapturingSnapshot"
                    class="pointer-events-none absolute top-16 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2.5 px-4 py-2 rounded-full bg-black/85 border border-rose-500/40 text-white shadow-2xl backdrop-blur-md"
                >
                    <span class="h-3.5 w-3.5 rounded-full border-2 border-rose-400 border-t-transparent animate-spin"></span>
                    <span class="text-xs font-medium text-rose-200">Mengambil snapshot 3D resolusi tinggi...</span>
                </div>
            </Transition>

            <!-- Loading Overlay -->
            <div
                v-if="isLoading"
                class="pointer-events-none absolute inset-0 z-40 flex flex-col items-center justify-center bg-black/85 backdrop-blur-md text-center p-6"
            >
                <div class="relative flex items-center justify-center">
                    <div class="h-16 w-16 rounded-full border-2 border-rose-500/20 border-t-rose-500 animate-spin"></div>
                    <Sparkles class="absolute h-6 w-6 text-rose-400 animate-pulse" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-white">Memuat Model 3D Arsitektur...</h3>
                <p class="mt-1 text-xs text-neutral-400 max-w-sm">Menguraikan geometri mesh, tekstur PBR, dan pencahayaan fotometrik.</p>
                <div class="mt-4 h-1.5 w-48 rounded-full bg-white/10 overflow-hidden">
                    <div class="h-full bg-rose-500 rounded-full transition-all duration-300" :style="{ width: `${loadingProgress || 5}%` }"></div>
                </div>
            </div>

            <!-- Error Overlay -->
            <div
                v-if="modelError"
                class="pointer-events-none absolute inset-0 z-40 flex flex-col items-center justify-center bg-black/90 backdrop-blur-md text-center p-6"
            >
                <div class="rounded-full bg-rose-500/20 p-3 mb-3">
                    <X class="h-6 w-6 text-rose-400" />
                </div>
                <p class="font-semibold text-base text-white">{{ modelError }}</p>
                <p class="text-xs text-neutral-400 mt-1 max-w-sm">Pastikan file model .glb tersedia di storage.</p>
            </div>

            <!-- ── TOOLBAR TOP-LEFT: Controls ── -->
            <div class="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-1.5 rounded-2xl border border-white/15 bg-black/80 p-1.5 shadow-2xl backdrop-blur-xl">
                <span class="text-[11px] font-mono text-neutral-400 px-2 hidden sm:inline">KONTROL:</span>
                <button
                    type="button"
                    @click="setInteractionMode('rotate')"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="interactionMode === 'rotate' ? 'bg-white text-black shadow-md font-semibold' : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                >
                    <RotateCw class="h-3.5 w-3.5" />
                    <span>Putar</span>
                </button>
                <button
                    type="button"
                    @click="setInteractionMode('pan')"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="interactionMode === 'pan' ? 'bg-white text-black shadow-md font-semibold' : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                >
                    <Hand class="h-3.5 w-3.5" />
                    <span>Geser</span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol preset kamera -->
                <button
                    type="button"
                    @click="isPresetPanelOpen = !isPresetPanelOpen; isChatOpen = false; isDrawerOpen = false"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isPresetPanelOpen
                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Preset Sudut Kamera"
                >
                    <Bookmark class="h-3.5 w-3.5" :class="isPresetPanelOpen ? 'text-amber-400' : ''" />
                    <span>Preset</span>
                    <span v-if="cameraPresets.length > 0" class="ml-0.5 text-[10px] font-mono px-1 rounded"
                          :class="isPresetPanelOpen ? 'bg-amber-500/20 text-amber-300' : 'bg-white/10 text-neutral-400'">
                        {{ cameraPresets.length }}
                    </span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol Clipping -->
                <button
                    type="button"
                    @click="isClipPanelOpen = !isClipPanelOpen; isPresetPanelOpen = false; isChatOpen = false; isDrawerOpen = false"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isClipPanelOpen || clippingEnabled
                        ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Sectioning / Clipping Tool"
                >
                    <Scissors class="h-3.5 w-3.5" :class="clippingEnabled ? 'text-cyan-400' : ''" />
                    <span>Potong</span>
                    <span v-if="clippingEnabled" class="ml-0.5 h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol Cahaya / Matahari -->
                <button
                    type="button"
                    @click="isSunPanelOpen = !isSunPanelOpen; isClipPanelOpen = false; isPresetPanelOpen = false; isChatOpen = false"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isSunPanelOpen
                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Simulasi Pencahayaan Real-time Matahari"
                >
                    <Sun v-if="!isNight" class="h-3.5 w-3.5 text-amber-400" />
                    <Moon v-else class="h-3.5 w-3.5 text-indigo-400" />
                    <span>Cahaya</span>
                    <span class="ml-0.5 text-[10px] font-mono px-1 rounded bg-white/10 text-neutral-300">
                        {{ formattedTime }}
                    </span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol Studio HD (Render Post-Processing) -->
                <button
                    type="button"
                    @click="isEnhancedRenderingActive = !isEnhancedRenderingActive"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isEnhancedRenderingActive
                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shadow-sm'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Toggle Render Studio HD (GTAO Ambient Occlusion & Bloom)"
                >
                    <Sparkles class="h-3.5 w-3.5" :class="isEnhancedRenderingActive ? 'text-emerald-400 animate-pulse' : 'text-neutral-400'" />
                    <span>Studio HD</span>
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="isEnhancedRenderingActive ? 'bg-emerald-400 shadow-[0_0_8px_#34d399]' : 'bg-neutral-600'"
                    ></span>
                </button>

                <!-- Tombol Ambil Gambar (Hanya Pemilik Proyek) -->
                <template v-if="isOwner">
                    <div class="h-4 w-px bg-white/15 mx-0.5"></div>
                    <button
                        type="button"
                        :disabled="isCapturingSnapshot"
                        @click="openCaptureModal"
                        class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all shadow-sm"
                        :class="isCapturingSnapshot
                            ? 'bg-rose-600/40 text-rose-200 border border-rose-400/60 cursor-wait animate-pulse'
                            : 'bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-rose-500/30'"
                        title="Ambil Gambar dari Sudut Kamera Saat Ini"
                    >
                        <Loader2 v-if="isCapturingSnapshot" class="h-3.5 w-3.5 text-rose-300 animate-spin" />
                        <Camera v-else class="h-3.5 w-3.5 text-rose-400" />
                        <span>{{ isCapturingSnapshot ? 'Mengambil...' : 'Ambil Gambar' }}</span>
                    </button>
                </template>
            </div>

            <!-- ── TOOLBAR BOTTOM-CENTER ── -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex flex-wrap items-center justify-center gap-1.5 sm:gap-2 rounded-full border border-white/15 bg-black/85 p-1.5 sm:p-2 shadow-2xl backdrop-blur-2xl max-w-[95vw]">

                <!-- Reset/Pusatkan -->
                <button
                    type="button"
                    @click="resetModelView"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all"
                    title="Pusatkan kembali model 3D"
                >
                    <Focus class="h-3.5 w-3.5 text-rose-400" />
                    <span>Pusatkan</span>
                </button>

                <!-- Fullscreen -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all"
                >
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-neutral-300" />
                    <span class="hidden md:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>

                <div class="h-4 w-px bg-white/20 mx-0.5 hidden sm:block"></div>

                <!-- Auto Orbit -->
                <button
                    type="button"
                    @click="autoRotate = !autoRotate"
                    class="rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium transition-all"
                    :class="autoRotate ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:bg-white/10 hover:text-white'"
                    title="Rotasi otomatis"
                >
                    <span>Auto-Orbit</span>
                </button>

                <!-- Tambah Pin — hanya untuk owner dan client, bukan admin viewer -->
                <button
                    v-if="!isAdminViewer"
                    type="button"
                    @click="handleTambahPinButton"
                    class="flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-all shadow-md"
                    :class="interactionMode === 'pin'
                        ? 'bg-emerald-500 text-black ring-2 ring-emerald-300'
                        : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>{{ interactionMode === 'pin' ? 'Klik di Model!' : 'Tambah Pin' }}</span>
                </button>

                <!-- Toggle Annotations -->
                <button
                    type="button"
                    @click="showAnnotations = !showAnnotations"
                    class="rounded-full p-2 text-neutral-300 hover:bg-white/10 hover:text-white transition-colors"
                    :title="showAnnotations ? 'Sembunyikan Pin' : 'Tampilkan Pin'"
                >
                    <Eye class="h-4 w-4" :class="showAnnotations ? 'text-rose-400' : 'text-neutral-500'" />
                </button>
            </div>

            <!-- Pin mode active banner -->
            <div
                v-if="interactionMode === 'pin'"
                class="absolute top-[72px] left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-black shadow-2xl animate-pulse"
            >
                <MapPin class="h-3.5 w-3.5" />
                <span>Mode Pin Aktif — Klik pada geometri model 3D untuk menambahkan catatan revisi!</span>
            </div>

            <!-- Revision limit warning -->
            <div
                v-if="limitWarning"
                class="absolute top-[72px] left-1/2 -translate-x-1/2 z-30 flex items-center gap-2 rounded-full border border-rose-500/40 bg-black/90 px-4 py-2 text-xs text-rose-200 shadow-2xl backdrop-blur-2xl"
            >
                <AlertTriangle class="h-4 w-4 text-rose-400 shrink-0 animate-bounce" />
                <span>{{ limitWarning }}</span>
                <button @click="limitWarning = ''" class="ml-2 rounded p-0.5 hover:bg-white/10 text-rose-300">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Tips hint -->
            <div class="absolute bottom-4 left-4 z-20 pointer-events-none hidden md:flex items-center gap-2 rounded-full bg-black/60 border border-white/10 px-3 py-1.5 text-[11px] text-neutral-400 backdrop-blur-xl">
                <span>💡 <strong class="text-neutral-300">Tips:</strong> Klik Kiri Drag = Putar | Klik Kanan Drag = Geser | Scroll = Zoom</span>
            </div>

            <!-- ── SVG LEADER LINES ── -->
            <svg v-if="showAnnotations" class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible">
                <defs>
                    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.5" />
                    </filter>
                    <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ef4444" />
                        <stop offset="100%" stop-color="#f43f5e" />
                    </linearGradient>
                    <linearGradient id="pendingGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#3b82f6" />
                        <stop offset="100%" stop-color="#6366f1" />
                    </linearGradient>
                </defs>

                <g v-for="(item, idx) in visibleProjectedComments" :key="'line-' + item.id">
                    <circle :cx="item.screenX" :cy="item.screenY" r="5" fill="#ef4444" stroke="#ffffff" stroke-width="2" filter="url(#shadow)" />
                    <circle v-if="activeCommentId === item.id" :cx="item.screenX" :cy="item.screenY" r="5" fill="none" stroke="#f43f5e" stroke-width="1.5">
                        <animate attributeName="r" from="5" to="16" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                    <path :d="getLeaderLinePath(item.screenX, item.screenY, item.anchorX, item.anchorY, item.isRightSide)" fill="none" stroke="url(#lineGrad)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4,3" />
                    <circle :cx="item.anchorX" :cy="item.anchorY" r="3.5" fill="#ef4444" />
                </g>

                <g v-if="pendingPin && pendingPin.isVisible">
                    <circle :cx="pendingPin.screenX" :cy="pendingPin.screenY" r="6" fill="#3b82f6" stroke="#ffffff" stroke-width="2" filter="url(#shadow)" />
                    <circle :cx="pendingPin.screenX" :cy="pendingPin.screenY" r="6" fill="none" stroke="#3b82f6" stroke-width="2">
                        <animate attributeName="r" from="6" to="18" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                    <path :d="getLeaderLinePath(pendingPin.screenX, pendingPin.screenY, pendingPin.anchorX, pendingPin.anchorY, pendingPin.isRightSide)" fill="none" stroke="url(#pendingGrad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <circle :cx="pendingPin.anchorX" :cy="pendingPin.anchorY" r="4" fill="#3b82f6" />
                </g>
            </svg>

            <!-- ── HTML CALLOUT CARDS ── -->
            <div v-if="showAnnotations" class="pointer-events-none absolute inset-0 z-10 overflow-hidden">

                <!-- Existing comment cards (draggable) -->
                <div
                    v-for="(item, idx) in visibleProjectedComments"
                    :key="'card-' + item.id"
                    :style="{ transform: `translate3d(${item.cardX}px, ${item.cardY}px, 0)` }"
                    class="pointer-events-auto absolute top-0 left-0 w-[280px] max-w-[85vw] touch-none"
                >
                    <div
                        @click="activeCommentId = item.id"
                        :class="[
                            'group rounded-2xl border p-3.5 shadow-2xl backdrop-blur-2xl transition-colors animate-fadeIn',
                            activeCommentId === item.id
                                ? 'border-rose-500/50 bg-black/95 ring-2 ring-rose-500/20'
                                : 'border-white/15 bg-black/85 hover:border-white/25',
                        ]"
                    >
                        <!-- Header drag handle -->
                        <div
                            class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 mb-2 select-none cursor-grab active:cursor-grabbing touch-none"
                            @pointerdown="startDrag(item.id, $event)"
                            @touchstart="startTouchDrag(item.id, $event)"
                        >
                            <div class="flex items-center gap-1.5 min-w-0">
                                <GripVertical class="h-4 w-4 text-neutral-500 hover:text-neutral-300 shrink-0" />
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-500/20 text-[10px] font-bold text-rose-400">
                                    {{ comments.length - idx }}
                                </span>
                                <span class="text-xs font-semibold text-white truncate">{{ item.user?.name ?? 'Reviewer' }}</span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0" @pointerdown.stop @touchstart.stop>
                                <button v-if="canEditComment(item) && editingCommentId !== item.id && unpinningCommentId !== item.id" @click.stop="startEditing(item, $event)" class="rounded p-1 text-neutral-500 hover:bg-white/10 hover:text-white transition" title="Edit">
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button v-if="canEditComment(item) && unpinningCommentId !== item.id" @click.stop="promptUnpin(item.id, $event)" class="rounded p-1 text-neutral-500 hover:bg-rose-500/20 hover:text-rose-400 transition" title="Hapus pin">
                                    <PinOff class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Unpin confirm -->
                        <div v-if="unpinningCommentId === item.id" class="rounded-2xl bg-rose-950/50 border border-rose-500/30 p-2.5 text-xs space-y-2" @pointerdown.stop @touchstart.stop>
                            <p class="flex items-center gap-1.5 text-rose-200 font-semibold"><PinOff class="h-3.5 w-3.5 text-rose-400" /> Hapus pin ini?</p>
                            <p v-if="unpinError" class="text-rose-400 text-[11px]">{{ unpinError }}</p>
                            <div class="flex justify-end gap-1.5">
                                <button @click.stop="cancelUnpin($event)" class="rounded-lg px-2 py-1 text-neutral-300 hover:bg-white/10">Batal</button>
                                <button @click.stop="executeUnpin(item.id)" :disabled="isUnpinning" class="flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 font-semibold text-white hover:bg-rose-500 disabled:opacity-50">
                                    <Trash2 class="h-3 w-3" />{{ isUnpinning ? 'Menghapus...' : 'Ya, Hapus' }}
                                </button>
                            </div>
                        </div>

                        <!-- Read -->
                        <div v-else-if="editingCommentId !== item.id">
                            <p class="text-xs text-neutral-200 leading-relaxed break-words line-clamp-4">{{ item.content }}</p>
                            <div class="mt-3 flex items-center justify-between text-[10px] pt-2 border-t border-white/10">
                                <button
                                    v-if="!isAdminViewer"
                                    type="button"
                                    @click.stop="toggleResolved(item.id)"
                                    :disabled="togglingResolvedId === item.id"
                                    class="font-medium transition-colors disabled:opacity-50"
                                    :class="item.status === 'resolved' ? 'text-emerald-400 hover:text-emerald-300' : 'text-amber-400 hover:text-amber-300'"
                                >
                                    {{ togglingResolvedId === item.id ? 'Menyimpan...' : item.status === 'resolved' ? '✓ Selesai (Resolved)' : '○ Tandai Selesai' }}
                                </button>
                                <span
                                    class="text-[9px] font-mono px-1.5 py-0.5 rounded"
                                    :class="item.status === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                                >
                                    {{ item.status === 'resolved' ? 'RESOLVED' : 'OPEN' }}
                                </span>
                            </div>
                        </div>

                        <!-- Edit -->
                        <div v-else class="space-y-2" @pointerdown.stop @touchstart.stop>
                            <textarea v-model="editCommentText" rows="3" class="w-full resize-none rounded-xl border border-white/15 bg-black/80 p-2 text-xs text-white placeholder-neutral-500 focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500" autofocus></textarea>
                            <p v-if="editCommentError" class="text-[11px] text-rose-400">{{ editCommentError }}</p>
                            <div class="flex justify-end gap-1.5">
                                <button @click.stop="cancelEditing($event)" class="rounded-lg px-2 py-1 text-neutral-400 hover:bg-white/10">Batal</button>
                                <button @click.stop="saveEditing(item.id)" :disabled="isSavingEdit" class="flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-500 disabled:opacity-50">
                                    <Check class="h-3 w-3" />{{ isSavingEdit ? 'Menyimpan...' : 'Simpan' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending pin form (draggable) -->
                <div
                    v-if="pendingPin && pendingPin.isVisible"
                    :style="{ transform: `translate3d(${pendingPin.cardX}px, ${pendingPin.cardY}px, 0)` }"
                    class="pointer-events-auto absolute top-0 left-0 w-[320px] max-w-[90vw] touch-none"
                >
                    <div class="rounded-2xl border-2 border-blue-500 bg-black/95 p-3.5 shadow-2xl backdrop-blur-2xl ring-4 ring-blue-500/20">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10 select-none cursor-grab active:cursor-grabbing" @pointerdown="startDrag('pending', $event)" @touchstart="startTouchDrag('pending', $event)">
                            <div class="flex items-center gap-1.5">
                                <GripVertical class="h-4 w-4 text-blue-400" />
                                <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                <span class="text-xs font-semibold text-white">Tulis Catatan Revisi</span>
                            </div>
                            <button @click="cancelPendingPin" class="rounded p-1 text-neutral-400 hover:bg-white/10 hover:text-white" @pointerdown.stop @touchstart.stop>
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <form @submit.prevent="submitComment" class="mt-2.5 space-y-2.5" @pointerdown.stop @touchstart.stop>
                            <textarea ref="newCommentInputRef" v-model="newCommentText" placeholder="Tulis feedback revisi atau catatan arsitektur..." rows="3" required class="w-full resize-none rounded-xl border border-white/15 bg-black/80 p-2 text-xs text-white placeholder-neutral-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                            <p v-if="submitCommentError" class="text-[11px] text-rose-400">{{ submitCommentError }}</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="cancelPendingPin" class="rounded-lg px-2.5 py-1 text-xs text-neutral-400 hover:bg-white/10">Batal</button>
                                <button type="submit" :disabled="isSubmittingComment || !newCommentText.trim()" class="flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                    <Send class="h-3 w-3" />{{ isSubmittingComment ? 'Menyimpan...' : 'Simpan Pin' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ── PANEL KANAN: Spatial Comments Thread (sama persis dengan Showcase) ── -->
            <aside
                v-if="isDrawerOpen"
                class="absolute top-4 right-4 bottom-20 z-20 w-80 max-w-[90vw] rounded-3xl border border-white/15 bg-black/85 p-5 shadow-2xl backdrop-blur-2xl flex flex-col justify-between overflow-hidden"
            >
                <div class="flex flex-col min-h-0">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3 flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <MessageSquare class="h-4 w-4 text-rose-400" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white">
                                Catatan Revisi ({{ comments.length }})
                            </h3>
                        </div>
                        <button type="button" @click="isDrawerOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Scrollable comment list -->
                    <div class="mt-4 space-y-2.5 overflow-y-auto pr-1 flex-1">
                        <div v-if="comments.length === 0" class="py-6 text-center text-xs text-neutral-500 space-y-3">
                            <p>Belum ada pin catatan. Klik tombol "+ Tambah Pin" dan klik pada model untuk menambahkan.</p>
                            <button @click="handleTambahPinButton" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1.5 text-xs font-medium text-emerald-300 hover:bg-emerald-500/30 transition">
                                <Plus class="h-3.5 w-3.5" />Tambah Pin Pertama
                            </button>
                        </div>

                        <div
                            v-for="comment in comments"
                            :key="comment.id"
                            @click="focusComment(comment)"
                            class="rounded-2xl border p-3 cursor-pointer transition-all duration-200"
                            :class="activeCommentId === comment.id
                                ? 'border-rose-500/50 bg-rose-500/10 shadow-lg'
                                : 'border-white/10 bg-white/[0.02] hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-rose-500/20 text-[10px] font-bold text-rose-400">
                                        {{ comments.indexOf(comment) + 1 }}
                                    </span>
                                    <span class="font-semibold text-white truncate max-w-[130px]">{{ comment.user?.name ?? 'Reviewer' }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button v-if="canEditComment(comment)" @click.stop="focusAndEdit(comment)" class="rounded p-0.5 text-neutral-500 hover:text-white transition" title="Edit">
                                        <Pencil class="h-3 w-3" />
                                    </button>
                                    <button v-if="canEditComment(comment)" @click.stop="promptUnpinFromDrawer(comment)" class="rounded p-0.5 text-neutral-500 hover:text-rose-400 transition" title="Hapus pin">
                                        <PinOff class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <p class="mt-1.5 text-xs text-neutral-300 line-clamp-2">{{ comment.content }}</p>

                            <!-- Inline unpin confirm in drawer -->
                            <div v-if="unpinningCommentId === comment.id" class="mt-2 rounded-xl bg-rose-950/60 p-2 border border-rose-500/30 text-[11px] space-y-1.5" @click.stop>
                                <p class="text-rose-200 font-medium">Hapus permanen pin ini?</p>
                                <div class="flex justify-end gap-1.5">
                                    <button @click.stop="cancelUnpin($event)" class="rounded px-2 py-0.5 text-neutral-300 hover:text-white">Batal</button>
                                    <button @click.stop="executeUnpin(comment.id)" :disabled="isUnpinning" class="rounded bg-rose-600 px-2 py-0.5 text-white font-medium hover:bg-rose-500 disabled:opacity-50">
                                        {{ isUnpinning ? 'Menghapus...' : 'Ya, Hapus' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Tandai Selesai di drawer -->
                            <div class="mt-2 flex items-center justify-between">
                                <button
                                    v-if="!isAdminViewer"
                                    type="button"
                                    @click.stop="toggleResolved(comment.id)"
                                    :disabled="togglingResolvedId === comment.id"
                                    class="text-[10px] font-medium transition-colors disabled:opacity-50"
                                    :class="comment.status === 'resolved' ? 'text-emerald-400 hover:text-emerald-300' : 'text-amber-400 hover:text-amber-300'"
                                >
                                    {{ togglingResolvedId === comment.id ? 'Menyimpan...' : comment.status === 'resolved' ? '✓ Selesai' : '○ Tandai Selesai' }}
                                </button>
                                <span
                                    class="text-[9px] font-mono px-1.5 py-0.5 rounded"
                                    :class="comment.status === 'resolved' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                                >
                                    {{ comment.status === 'resolved' ? 'RESOLVED' : 'OPEN' }}
                                </span>
                            </div>

                            <div class="mt-2 text-[10px] text-neutral-500 font-mono">
                                ({{ comment.position_x.toFixed(1) }}, {{ comment.position_y.toFixed(1) }}, {{ comment.position_z.toFixed(1) }})
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer hint — sama persis Showcase -->
                <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3 text-xs text-neutral-400 flex-shrink-0">
                    <div class="flex items-center gap-1.5 text-rose-400 font-medium text-[11px] mb-1">
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        <span>Raycasting Spasial 3D</span>
                    </div>
                    <p class="text-[10px] text-neutral-400 leading-tight">
                        Klik "+ Tambah Pin" lalu klik titik mana saja pada geometri model 3D untuk menambahkan catatan revisi.
                    </p>
                </div>
            </aside>

            <!-- ── CAMERA PRESET PANEL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 -translate-x-4 scale-95"
            >
                <div
                    v-if="isPresetPanelOpen"
                    class="absolute top-16 left-4 z-30 w-72 max-w-[90vw] rounded-2xl border border-white/15 bg-black/90 backdrop-blur-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Bookmark class="h-4 w-4 text-amber-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Preset Kamera</span>
                            <span class="text-[10px] font-mono text-neutral-500">{{ cameraPresets.length }}/8</span>
                        </div>
                        <button type="button" @click="isPresetPanelOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Preset list -->
                    <div class="max-h-52 overflow-y-auto">
                        <div v-if="cameraPresets.length === 0" class="flex flex-col items-center justify-center py-6 gap-2 text-center px-4">
                            <Bookmark class="h-7 w-7 text-neutral-700" />
                            <p class="text-xs text-neutral-500 leading-relaxed">
                                Belum ada preset. Atur sudut kamera lalu simpan sebagai preset.
                            </p>
                        </div>
                        <button
                            v-for="preset in cameraPresets"
                            :key="preset.id"
                            type="button"
                            class="w-full flex items-center justify-between gap-2 px-4 py-2.5 text-left hover:bg-white/5 transition-colors group border-b border-white/5 last:border-0"
                            @click="applyPreset(preset)"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 border border-amber-500/20">
                                    <Bookmark class="h-3 w-3 text-amber-400" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-white truncate">{{ preset.name }}</p>
                                    <p class="text-[10px] font-mono text-neutral-600 truncate">
                                        {{ preset.position.x.toFixed(1) }}, {{ preset.position.y.toFixed(1) }}, {{ preset.position.z.toFixed(1) }}
                                    </p>
                                </div>
                            </div>
                            <!-- Hapus — hanya owner -->
                            <button
                                v-if="isOwner"
                                type="button"
                                class="shrink-0 opacity-0 group-hover:opacity-100 rounded p-1 text-neutral-500 hover:text-rose-400 hover:bg-rose-500/10 transition-all"
                                :disabled="isDeletingPresetId === preset.id"
                                title="Hapus preset"
                                @click.stop="deletePreset(preset.id)"
                            >
                                <Trash2 class="h-3 w-3" />
                            </button>
                        </button>
                    </div>

                    <!-- Save new preset — hanya owner -->
                    <div v-if="isOwner" class="border-t border-white/10 p-3 space-y-2">
                        <p class="text-[10px] font-mono text-neutral-600 uppercase tracking-wider">Simpan sudut saat ini sebagai preset</p>
                        <div class="flex gap-2">
                            <input
                                ref="presetInputRef"
                                v-model="newPresetName"
                                type="text"
                                placeholder="Nama preset..."
                                maxlength="80"
                                class="flex-1 h-8 rounded-lg border border-white/10 bg-white/5 px-2.5 text-xs text-white placeholder:text-neutral-600 focus:border-amber-500/50 focus:outline-none focus:ring-1 focus:ring-amber-500/20"
                                @keydown.enter="savePreset"
                            />
                            <button
                                type="button"
                                :disabled="isSavingPreset || !newPresetName.trim() || cameraPresets.length >= 8"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 border border-amber-500/30 text-amber-400 hover:bg-amber-500/30 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                title="Simpan preset"
                                @click="savePreset"
                            >
                                <BookmarkPlus v-if="!isSavingPreset" class="h-3.5 w-3.5" />
                                <span v-else class="h-3.5 w-3.5 block rounded-full border-2 border-amber-400/30 border-t-amber-400 animate-spin"></span>
                            </button>
                        </div>
                        <p v-if="presetError" class="text-[10px] text-rose-400">{{ presetError }}</p>
                        <p v-if="cameraPresets.length >= 8" class="text-[10px] text-neutral-600">Maks 8 preset tercapai.</p>
                    </div>
                </div>
            </Transition>

            <!-- ── CLIPPING / SECTIONING PANEL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 -translate-x-4 scale-95"
            >
                <div
                    v-if="isClipPanelOpen"
                    class="absolute top-16 left-4 z-30 w-72 max-w-[90vw] rounded-2xl border border-white/15 bg-black/90 backdrop-blur-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Scissors class="h-4 w-4 text-cyan-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Sectioning Tool</span>
                            <span v-if="clippingEnabled" class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        </div>
                        <button type="button" @click="isClipPanelOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-4 space-y-4">

                        <!-- Enable toggle -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-white">Aktifkan Potongan</p>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Tampilkan interior tanpa mengubah model</p>
                            </div>
                            <button
                                type="button"
                                @click="clippingEnabled = !clippingEnabled"
                                class="relative h-6 w-11 rounded-full transition-all duration-200 focus:outline-none"
                                :class="clippingEnabled ? 'bg-cyan-500' : 'bg-white/15'"
                            >
                                <span
                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-200"
                                    :class="clippingEnabled ? 'left-[22px]' : 'left-0.5'"
                                ></span>
                            </button>
                        </div>

                        <!-- Axis selector -->
                        <div class="space-y-1.5">
                            <p class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Arah Potongan</p>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="ax in (['x', 'y', 'z'] as const)"
                                    :key="ax"
                                    type="button"
                                    @click="clippingAxis = ax"
                                    class="flex-1 h-8 rounded-lg text-xs font-mono font-semibold uppercase transition-all"
                                    :class="clippingAxis === ax
                                        ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40'
                                        : 'bg-white/5 text-neutral-400 hover:bg-white/10 hover:text-white border border-transparent'"
                                >
                                    {{ ax === 'x' ? '← X →' : ax === 'y' ? '↑ Y ↓' : '↙ Z ↗' }}
                                </button>
                            </div>
                            <p class="text-[10px] text-neutral-600">
                                {{ clippingAxis === 'y' ? 'Potongan horizontal — tampilkan lantai per lantai' :
                                   clippingAxis === 'x' ? 'Potongan vertikal kiri-kanan' :
                                   'Potongan vertikal depan-belakang' }}
                            </p>
                        </div>

                        <!-- Slider posisi -->
                        <div class="space-y-2" :class="!clippingEnabled && 'opacity-40 pointer-events-none'">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Posisi Bidang</p>
                                <span class="text-[11px] font-mono text-cyan-400">{{ clippingValue.toFixed(2) }}m</span>
                            </div>
                            <input
                                type="range"
                                :min="clippingRange.min"
                                :max="clippingRange.max"
                                :step="clippingRange.step"
                                v-model.number="clippingValue"
                                class="w-full h-1.5 rounded-full appearance-none cursor-pointer bg-white/10 accent-cyan-500"
                            />
                            <div class="flex justify-between text-[10px] font-mono text-neutral-600">
                                <span>{{ clippingRange.min.toFixed(1) }}m</span>
                                <span>{{ clippingRange.max.toFixed(1) }}m</span>
                            </div>
                        </div>

                        <!-- Flip direction -->
                        <div class="flex items-center justify-between" :class="!clippingEnabled && 'opacity-40 pointer-events-none'">
                            <p class="text-xs text-neutral-400">Balik arah potongan</p>
                            <button
                                type="button"
                                @click="clippingFlip = !clippingFlip"
                                class="relative h-6 w-11 rounded-full transition-all duration-200"
                                :class="clippingFlip ? 'bg-cyan-500' : 'bg-white/15'"
                            >
                                <span
                                    class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-200"
                                    :class="clippingFlip ? 'left-[22px]' : 'left-0.5'"
                                ></span>
                            </button>
                        </div>

                        <!-- Reset button -->
                        <button
                            type="button"
                            @click="resetClipping"
                            class="w-full h-8 rounded-xl border border-white/10 bg-white/5 text-xs text-neutral-400 hover:bg-white/10 hover:text-white transition-all"
                        >
                            Reset Potongan
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- ── SUN & LIGHTING PANEL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 -translate-x-4 scale-95"
            >
                <div
                    v-if="isSunPanelOpen"
                    class="absolute top-16 left-4 z-30 w-80 max-w-[92vw] rounded-2xl border border-white/15 bg-black/90 backdrop-blur-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Sun v-if="!isNight" class="h-4 w-4 text-amber-400" />
                            <Moon v-else class="h-4 w-4 text-indigo-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Pencahayaan Matahari</span>
                        </div>
                        <button type="button" @click="isSunPanelOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-4 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Presets (Pagi, Siang, Sore, Malam) -->
                        <div>
                            <p class="text-[11px] font-medium text-neutral-400 uppercase tracking-wider mb-2">Preset Waktu</p>
                            <div class="grid grid-cols-4 gap-1.5">
                                <button
                                    type="button"
                                    @click="setPreset('pagi')"
                                    class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl border text-xs font-medium transition-all"
                                    :class="activePreset === 'pagi'
                                        ? 'bg-amber-500/25 border-amber-500/50 text-amber-300'
                                        : 'bg-white/5 border-white/10 text-neutral-300 hover:bg-white/10 hover:text-white'"
                                >
                                    <Sunrise class="h-4 w-4 text-amber-400" />
                                    <span class="text-[10px]">Pagi</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setPreset('siang')"
                                    class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl border text-xs font-medium transition-all"
                                    :class="activePreset === 'siang'
                                        ? 'bg-amber-500/25 border-amber-500/50 text-amber-300'
                                        : 'bg-white/5 border-white/10 text-neutral-300 hover:bg-white/10 hover:text-white'"
                                >
                                    <Sun class="h-4 w-4 text-amber-300" />
                                    <span class="text-[10px]">Siang</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setPreset('sore')"
                                    class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl border text-xs font-medium transition-all"
                                    :class="activePreset === 'sore'
                                        ? 'bg-orange-500/25 border-orange-500/50 text-orange-300'
                                        : 'bg-white/5 border-white/10 text-neutral-300 hover:bg-white/10 hover:text-white'"
                                >
                                    <Sunset class="h-4 w-4 text-orange-400" />
                                    <span class="text-[10px]">Sore</span>
                                </button>
                                <button
                                    type="button"
                                    @click="setPreset('malam')"
                                    class="flex flex-col items-center justify-center gap-1 py-2 px-1 rounded-xl border text-xs font-medium transition-all"
                                    :class="activePreset === 'malam'
                                        ? 'bg-indigo-500/25 border-indigo-500/50 text-indigo-300'
                                        : 'bg-white/5 border-white/10 text-neutral-300 hover:bg-white/10 hover:text-white'"
                                >
                                    <Moon class="h-4 w-4 text-indigo-400" />
                                    <span class="text-[10px]">Malam</span>
                                </button>
                            </div>
                        </div>

                        <!-- Clock & Slider -->
                        <div class="rounded-xl border border-white/10 bg-white/5 p-3 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-neutral-400">Jam Simulasi</span>
                                <span class="text-base font-mono font-bold text-white bg-black/60 px-2 py-0.5 rounded-lg border border-white/10">
                                    {{ formattedTime }}
                                </span>
                            </div>

                            <input
                                type="range"
                                min="0"
                                max="23.9"
                                step="0.1"
                                :value="currentHour"
                                @input="onHourSliderChange"
                                class="w-full accent-amber-400 h-1.5 bg-white/20 rounded-lg cursor-pointer"
                            />

                            <!-- Status night / lamps -->
                            <div class="flex items-center justify-between text-[11px] pt-1">
                                <span v-if="isNight" class="text-indigo-300 flex items-center gap-1">
                                    <span>🌙 Mode Malam</span>
                                    <span v-if="lampNodes.length > 0" class="text-amber-300 font-medium">({{ lampNodes.length }} Lampu Nyala)</span>
                                </span>
                                <span v-else class="text-amber-300 flex items-center gap-1">
                                    <span>☀️ Matahari Aktif</span>
                                    <span class="text-neutral-400 text-[10px]">({{ (sunAltitude * 180 / Math.PI).toFixed(0) }}°)</span>
                                </span>
                                <span class="text-[10px] text-neutral-500">Geser untuk bayangan</span>
                            </div>
                        </div>

                        <!-- Project Settings Section (Architect only) -->
                        <div v-if="isOwner" class="border-t border-white/10 pt-3 space-y-3">
                            <button
                                type="button"
                                @click="showSunGeoSettings = !showSunGeoSettings"
                                class="w-full flex items-center justify-between text-xs font-semibold text-neutral-300 hover:text-white"
                            >
                                <span class="flex items-center gap-1.5">
                                    <Compass class="h-3.5 w-3.5 text-amber-400" />
                                    <span>Lokasi & Arah Utara</span>
                                </span>
                                <span class="text-[10px] text-amber-400 font-mono">{{ showSunGeoSettings ? 'Tutup ▲' : 'Atur ▼' }}</span>
                            </button>

                            <div v-if="showSunGeoSettings" class="space-y-3 pt-1 text-xs">
                                <!-- Arah Utara -->
                                <div class="space-y-1">
                                    <div class="flex justify-between text-[11px]">
                                        <span class="text-neutral-400">Rotasi Arah Utara:</span>
                                        <span class="font-mono text-amber-400">{{ northAngle }}°</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="0"
                                        max="360"
                                        step="1"
                                        v-model.number="northAngle"
                                        @input="updateSunPosition"
                                        class="w-full accent-amber-400 h-1.5 bg-white/20 rounded-lg cursor-pointer"
                                    />
                                </div>

                                <!-- Latitude / Longitude -->
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[10px] text-neutral-400 block mb-0.5">Latitude</label>
                                        <input
                                            type="number"
                                            step="0.0001"
                                            v-model.number="latitude"
                                            @change="updateSunPosition"
                                            class="w-full h-7 rounded-lg border border-white/10 bg-white/5 px-2 text-xs text-white focus:outline-none focus:border-amber-400"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-[10px] text-neutral-400 block mb-0.5">Longitude</label>
                                        <input
                                            type="number"
                                            step="0.0001"
                                            v-model.number="longitude"
                                            @change="updateSunPosition"
                                            class="w-full h-7 rounded-lg border border-white/10 bg-white/5 px-2 text-xs text-white focus:outline-none focus:border-amber-400"
                                        />
                                    </div>
                                </div>

                                <!-- Tanggal -->
                                <div>
                                    <label class="text-[10px] text-neutral-400 block mb-0.5">Tanggal Simulasi</label>
                                    <input
                                        type="date"
                                        v-model="solarDate"
                                        @change="updateSunPosition"
                                        class="w-full h-7 rounded-lg border border-white/10 bg-white/5 px-2 text-xs text-white focus:outline-none focus:border-amber-400"
                                    />
                                </div>

                                <!-- Tombol Simpan Pengaturan Project -->
                                <button
                                    type="button"
                                    :disabled="isSavingSunSettings"
                                    @click="saveSunSettings"
                                    class="w-full py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-300 hover:bg-amber-500/30 font-medium transition-all disabled:opacity-50 text-xs flex items-center justify-center gap-1.5"
                                >
                                    <Check v-if="!isSavingSunSettings" class="h-3.5 w-3.5" />
                                    <span v-else class="h-3 w-3 rounded-full border-2 border-amber-400 border-t-transparent animate-spin"></span>
                                    <span>Simpan ke Proyek</span>
                                </button>
                                <p v-if="sunSettingsMessage" class="text-[10px] text-emerald-400 text-center">{{ sunSettingsMessage }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── FLOATING BANNER: ARAHKAN KAMERA 3D UNTUK FRAME 2 ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-8 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-8 scale-95"
            >
                <div
                    v-if="isSettingFrameB"
                    class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-wrap items-center gap-3 p-3 px-5 rounded-2xl bg-neutral-950/95 border border-purple-500/50 shadow-[0_0_30px_rgba(168,85,247,0.3)] backdrop-blur-xl"
                >
                    <div class="flex items-center gap-2.5 text-xs text-purple-200">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-purple-500"></span>
                        </span>
                        <span class="font-bold text-white">Mode Walkthrough:</span>
                        <span>Putar / arahkan kamera 3D ke posisi akhir yang diinginkan</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="isCapturingFrameB"
                            @click="finishNavigatingForFrameB"
                            class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-semibold shadow-lg transition-all disabled:opacity-50"
                        >
                            <Loader2 v-if="isCapturingFrameB" class="h-3.5 w-3.5 animate-spin" />
                            <Check v-else class="h-3.5 w-3.5" />
                            <span>{{ isCapturingFrameB ? 'Mengunci...' : 'Kunci Frame 2 (Akhir)' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="cancelNavigatingForFrameB"
                            class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-neutral-300 text-xs font-medium transition"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- ── CAPTURE MODAL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="isCaptureModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 md:p-6 bg-black/85 backdrop-blur-xl overflow-y-auto"
                    @click.self="closeCaptureModal"
                >
                    <div class="relative w-full max-w-6xl my-auto rounded-3xl border border-white/15 bg-neutral-950/95 shadow-2xl overflow-hidden flex flex-col lg:flex-row max-h-[92vh]">
                        <!-- SEBELAH KIRI: GAMBAR PREVIEW .GLB + KONTROL ENGINE & DOWNLOAD PREVIEW REFERENCE -->
                        <div class="lg:w-[54%] p-5 sm:p-6 flex flex-col justify-between space-y-4 bg-black/60 border-b lg:border-b-0 lg:border-r border-white/10 overflow-y-auto">
                            <!-- Header Kiri -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Camera v-if="capturePromptMode === 'image'" class="h-4 w-4 text-rose-400" />
                                    <Film v-else class="h-4 w-4 text-purple-400" />
                                    <span class="text-xs font-semibold text-white tracking-wide uppercase">
                                        {{ capturePromptMode === 'video' ? 'Preview Tangkapan Video' : 'Preview Tangkapan 3D' }}
                                    </span>
                                </div>

                                <!-- Switcher Frame Awal / Akhir jika mode Video Two-Point -->
                                <div v-if="isTwoPointActive" class="flex items-center gap-1 p-0.5 rounded-xl bg-purple-950/60 border border-purple-500/30 text-[11px]">
                                    <button
                                        type="button"
                                        @click="captureActiveFrameTab = 'frame_a'"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1"
                                        :class="captureActiveFrameTab === 'frame_a' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        <span>1️⃣ Frame 1 (Awal)</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="captureActiveFrameTab = 'frame_b'"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1"
                                        :class="captureActiveFrameTab === 'frame_b' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        <span>2️⃣ Frame 2 (Akhir)</span>
                                        <span v-if="captureFrameBDataUrl" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    </button>
                                </div>

                                <!-- Engine Selector Switcher (Standard vs Path-Trace) jika mode image / standard -->
                                <div v-else class="flex items-center gap-1 p-0.5 rounded-xl bg-white/10 border border-white/10 text-[11px]">
                                    <button
                                        type="button"
                                        @click="renderEngine = 'standard'; refreshCaptureSnapshot()"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all"
                                        :class="renderEngine === 'standard' ? 'bg-white text-black font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        Studio HD
                                    </button>
                                    <button
                                        type="button"
                                        @click="renderEngine = 'pathtrace'"
                                        class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1"
                                        :class="renderEngine === 'pathtrace' ? 'bg-gradient-to-r from-emerald-400 to-teal-400 text-black font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        <span>Path-Tracing</span>
                                        <span class="text-[9px] px-1 rounded bg-black/20 font-mono">PRO</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Preview Image Canvas Screen -->
                            <div class="relative w-full aspect-video rounded-2xl overflow-hidden border border-white/15 bg-black flex items-center justify-center shadow-2xl">
                                <!-- FRAME 1 / SINGLE FRAME PREVIEW -->
                                <template v-if="captureActiveFrameTab === 'frame_a' || !isTwoPointActive">
                                    <img
                                        v-if="capturePreviewDataUrl"
                                        :src="capturePreviewDataUrl"
                                        alt="Capture Preview"
                                        class="h-full w-full object-contain"
                                    />
                                    <div v-else class="flex flex-col items-center gap-2 text-neutral-500">
                                        <span class="h-6 w-6 rounded-full border-2 border-rose-400 border-t-transparent animate-spin"></span>
                                        <span class="text-xs">Mengambil snapshot canvas...</span>
                                    </div>

                                    <!-- Overlay Badge Sudut & Waktu -->
                                    <div class="absolute bottom-2.5 left-2.5 flex items-center gap-1.5 pointer-events-none">
                                        <span class="px-2.5 py-0.5 rounded-full bg-black/80 border border-white/15 text-[10px] font-medium text-white backdrop-blur">
                                            {{ isTwoPointActive ? 'Frame 1: ' + (captureRoomName || 'Posisi Awal') : (captureRoomName || 'Sudut Bebas') }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full bg-black/80 border border-white/15 text-[10px] font-mono text-amber-300 backdrop-blur">
                                            {{ captureHour }}
                                        </span>
                                        <span v-if="renderEngine === 'pathtrace'" class="px-2 py-0.5 rounded-full bg-emerald-500 text-black text-[9px] font-bold font-mono">
                                            PATH-TRACED
                                        </span>
                                    </div>
                                </template>

                                <!-- FRAME 2 (POSISI AKHIR) PREVIEW -->
                                <template v-else>
                                    <div v-if="captureFrameBDataUrl" class="h-full w-full relative flex items-center justify-center">
                                        <img
                                            :src="captureFrameBDataUrl"
                                            alt="Frame B Preview"
                                            class="h-full w-full object-contain"
                                        />
                                        <div class="absolute bottom-2.5 left-2.5 flex items-center gap-1.5 pointer-events-none">
                                            <span class="px-2.5 py-0.5 rounded-full bg-purple-950/80 border border-purple-500/30 text-[10px] font-medium text-purple-200 backdrop-blur">
                                                Frame 2: {{ captureFrameBRoomName || 'Posisi Akhir' }}
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full bg-black/80 border border-white/15 text-[10px] font-mono text-amber-300 backdrop-blur">
                                                {{ captureFrameBHour }}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="clearFrameB"
                                            class="absolute top-2.5 right-2.5 px-2 py-1 rounded-lg bg-black/70 hover:bg-red-500/80 border border-white/20 text-[10px] text-white transition flex items-center gap-1 backdrop-blur"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                            <span>Hapus Frame 2</span>
                                        </button>
                                    </div>

                                    <!-- Placeholder jika Frame B belum diset -->
                                    <div v-else class="p-6 text-center space-y-3 flex flex-col items-center justify-center">
                                        <div class="p-3 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400">
                                            <Clapperboard class="h-6 w-6" />
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold text-white">Frame 2 (Posisi Akhir) Belum Ditentukan</h4>
                                            <p class="text-[10px] text-neutral-400 max-w-xs mt-1">
                                                Tentukan posisi sudut kamera kedua untuk menghasilkan transisi video sinematik yang mulus.
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
                                            <button
                                                type="button"
                                                @click="startNavigatingForFrameB"
                                                class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold shadow flex items-center gap-1.5 transition"
                                            >
                                                <Compass class="h-3.5 w-3.5" />
                                                <span>Arahkan Kamera di Canvas</span>
                                            </button>
                                            <button
                                                type="button"
                                                :disabled="isCapturingFrameB"
                                                @click="setFrameBFromCurrentView"
                                                class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-neutral-200 text-xs font-medium border border-white/15 transition flex items-center gap-1.5 disabled:opacity-50"
                                            >
                                                <Loader2 v-if="isCapturingFrameB" class="h-3 w-3 animate-spin text-purple-400" />
                                                <span>{{ isCapturingFrameB ? 'Mengunci...' : 'Kunci Sudut Saat Ini' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Path-Tracing Overlay -->
                                <div
                                    v-if="isPathTracing"
                                    class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-black/80 backdrop-blur-sm p-4 text-center space-y-2"
                                >
                                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono">
                                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
                                        <span v-if="isCompilingBvh">BVH: {{ bvhProgress }}%</span>
                                        <span v-else>Sampel: {{ currentSample }}/{{ ptTotalSamples }} ({{ ptProgressPercent }}%)</span>
                                    </div>
                                    <div class="w-full max-w-xs h-1.5 rounded-full bg-white/10 overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400" :style="{ width: `${isCompilingBvh ? bvhProgress : ptProgressPercent}%` }"></div>
                                    </div>
                                    <button type="button" @click="cancelPathTracing" class="px-2.5 py-1 text-[11px] text-rose-300 bg-rose-500/20 hover:bg-rose-500/30 border border-rose-500/30 rounded-lg">
                                        Batal
                                    </button>
                                </div>
                            </div>

                            <!-- Path-Tracing Sampling Controller (jika mode pathtrace) -->
                            <div v-if="renderEngine === 'pathtrace' && capturePromptMode === 'image'" class="p-2.5 rounded-xl border border-teal-500/30 bg-teal-950/20 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1 text-[11px]">
                                    <span class="text-neutral-400">Kualitas:</span>
                                    <button type="button" @click="pathTraceTargetSamples = 15" class="px-2 py-0.5 rounded" :class="pathTraceTargetSamples === 15 ? 'bg-teal-500 text-black font-semibold' : 'bg-white/10 text-neutral-300'">15</button>
                                    <button type="button" @click="pathTraceTargetSamples = 30" class="px-2 py-0.5 rounded" :class="pathTraceTargetSamples === 30 ? 'bg-teal-500 text-black font-semibold' : 'bg-white/10 text-neutral-300'">30</button>
                                    <button type="button" @click="pathTraceTargetSamples = 60" class="px-2 py-0.5 rounded" :class="pathTraceTargetSamples === 60 ? 'bg-teal-500 text-black font-semibold' : 'bg-white/10 text-neutral-300'">60</button>
                                </div>
                                <button
                                    type="button"
                                    :disabled="isPathTracing"
                                    @click="startPathTraceRender"
                                    class="px-3 py-1 rounded-lg bg-teal-500 text-black text-xs font-semibold hover:brightness-110 disabled:opacity-50"
                                >
                                    {{ isPathTracing ? 'Merender...' : 'Mulai Render' }}
                                </button>
                            </div>

                            <!-- Download Aset Gambar / Video untuk AI -->
                            <div class="p-3 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                                <!-- DOWNLOAD MODE VIDEO TWO-POINT -->
                                <template v-if="isTwoPointActive">
                                    <div class="text-left">
                                        <p class="text-xs font-semibold text-white flex items-center gap-1.5">
                                            <Download class="h-3.5 w-3.5 text-purple-400" />
                                            <span>Download Frame Aset Video untuk AI</span>
                                        </p>
                                        <p class="text-[10px] text-neutral-400">
                                            Unggah <b>Frame 1</b> ke slot First Frame dan <b>Frame 2</b> ke slot Last Frame di Runway Gen-3 atau Kling AI.
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                        <button
                                            type="button"
                                            :disabled="!capturePreviewDataUrl"
                                            @click="downloadCurrentCapture"
                                            class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-neutral-200 text-black text-xs font-semibold transition-all shadow disabled:opacity-40"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                            <span>Download Frame 1 (Awal)</span>
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="!captureFrameBDataUrl"
                                            @click="downloadFrameB"
                                            class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition-all shadow disabled:opacity-40"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                            <span>{{ captureFrameBDataUrl ? 'Download Frame 2 (Akhir)' : 'Frame 2 Belum Diset' }}</span>
                                        </button>
                                    </div>
                                </template>

                                <!-- DOWNLOAD MODE FOTO / STANDARD -->
                                <template v-else>
                                    <div class="text-left">
                                        <p class="text-xs font-semibold text-white flex items-center gap-1.5">
                                            <Download class="h-3.5 w-3.5 text-rose-400" />
                                            <span>Download Aset Gambar untuk AI</span>
                                        </p>
                                        <p class="text-[10px] text-neutral-400">
                                            Gunakan gambar warna untuk Image Reference (Midjourney/Flux/Runway) atau Depth Map untuk ControlNet agar geometri 100% presisi.
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                        <button
                                            type="button"
                                            :disabled="!capturePreviewDataUrl"
                                            @click="downloadCurrentCapture"
                                            class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-neutral-200 text-black text-xs font-semibold transition-all shadow disabled:opacity-40"
                                            title="Download tangkapan layar 3D warna penuh"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                            <span>Gambar Warna (RGB)</span>
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="!capturePreviewDataUrl"
                                            @click="downloadDepthMap"
                                            class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-neutral-800 hover:bg-neutral-700 text-amber-300 border border-white/15 text-xs font-semibold transition-all shadow disabled:opacity-40"
                                            title="Download peta kedalaman hitam-putih untuk ControlNet"
                                        >
                                            <Layers class="h-3.5 w-3.5 text-amber-400" />
                                            <span>Depth Map (ControlNet)</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- SEBELAH KANAN: STATUS BADGES + UNIVERSAL AI PROMPT (VISION AI) + ACTIONS -->
                        <div class="lg:w-[46%] p-5 sm:p-6 flex flex-col justify-between space-y-4 bg-neutral-900/70 overflow-y-auto">
                            <!-- Header Kanan & Close Button -->
                            <div class="flex items-start justify-between gap-3 border-b border-white/10 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <Sparkles class="h-4 w-4 text-indigo-400" />
                                        <h3 class="text-sm font-semibold text-white tracking-tight">AI Prompt Generator</h3>
                                        <span
                                            class="text-[9px] font-mono px-1.5 py-0.5 rounded border"
                                            :class="promptSource === 'gemini-vision'
                                                ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                                : 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30'"
                                        >
                                            {{ promptSource === 'gemini-vision' ? '✨ Vision AI' : '⚡ Spasial 3D' }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-neutral-400 mt-0.5">
                                        Pilih tipe output AI agar hasil render foto maupun video sinematik 100% akurat.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="closeCaptureModal"
                                    class="p-1.5 rounded-xl text-neutral-400 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0"
                                >
                                    <X class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- PRIMARY MODE SWITCHER: FOTO vs VIDEO -->
                            <div class="flex items-center p-1 rounded-2xl bg-black/60 border border-white/10 gap-1">
                                <button
                                    type="button"
                                    @click="capturePromptMode = 'image'; syncPromptWithCurrentState()"
                                    class="flex-1 py-1.5 px-3 rounded-xl text-xs font-semibold transition-all flex items-center justify-center gap-2"
                                    :class="capturePromptMode === 'image' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
                                >
                                    <Camera class="h-3.5 w-3.5" />
                                    <span>📷 Foto Render</span>
                                </button>
                                <button
                                    type="button"
                                    @click="capturePromptMode = 'video'; syncPromptWithCurrentState()"
                                    class="flex-1 py-1.5 px-3 rounded-xl text-xs font-semibold transition-all flex items-center justify-center gap-2"
                                    :class="capturePromptMode === 'video' ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
                                >
                                    <Film class="h-3.5 w-3.5" />
                                    <span>🎬 Video Sinematik</span>
                                </button>
                            </div>

                            <!-- JIKA MODE VIDEO: GAYA KAMERA (MULTI-SELECT), DURASI, KECEPATAN, AMBIENCE & LENSA -->
                            <div v-if="capturePromptMode === 'video'" class="space-y-3">
                                <!-- 1. Gaya Gerakan Kamera (Bisa Multipel) -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold uppercase tracking-wider text-purple-300">Gaya Kamera:</span>
                                            <span class="text-[9px] px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-200 border border-purple-500/30">
                                                {{ captureVideoMotions.length }} Dipilih (Bisa Multipel)
                                            </span>
                                        </div>
                                        <span class="font-mono text-[10px] text-neutral-300 truncate max-w-[200px]" :title="getSelectedMotionsLabel()">
                                            {{ getSelectedMotionsLabel() }}
                                        </span>
                                    </div>

                                    <!-- Grid of 15 Camera Movements -->
                                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-1 p-1 rounded-xl bg-black/40 border border-white/10 text-[10px]">
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('two_point')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('two_point') ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Transisi kamera dari posisi awal ke posisi akhir"
                                        >
                                            <span>🎯 2 Titik</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('push_in')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('push_in') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera bergerak maju masuk ke ruangan"
                                        >
                                            <span>🚶 Push-In</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('pull_out')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('pull_out') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera mundur mengungkap fasad bangunan"
                                        >
                                            <span>🔙 Pull-Out</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('orbit_left')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('orbit_left') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera mengitari bangunan searah jarum jam"
                                        >
                                            <span>🛸 Orbit Kiri</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('orbit_right')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('orbit_right') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera mengitari bangunan berlawanan jarum jam"
                                        >
                                            <span>🔄 Orbit Kanan</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('pan_left')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('pan_left') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Geser kamera ke kiri"
                                        >
                                            <span>⬅️ Pan Kiri</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('pan_right')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('pan_right') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Geser kamera ke kanan"
                                        >
                                            <span>➡️ Pan Kanan</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('crane_up')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('crane_up') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera naik vertikal ke atas"
                                        >
                                            <span>🏗️ Crane Naik</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('crane_down')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('crane_down') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera turun vertikal mendekati lantai"
                                        >
                                            <span>⬇️ Crane Turun</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('fpv_drone')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('fpv_drone') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Penerbangan drone FPV mulus melintasi bukaan ruangan"
                                        >
                                            <span>🛩️ FPV Drone</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('spiral_ascend')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('spiral_ascend') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera naik melingkar spiral mengitari bangunan"
                                        >
                                            <span>🌀 Spiral Naik</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('tilt_up')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('tilt_up') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Sudut kamera menengadah ke atas dari lantai"
                                        >
                                            <span>📐 Tilt Up</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('tilt_down')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('tilt_down') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Sudut kamera menunduk ke bawah dari plafon"
                                        >
                                            <span>📐 Tilt Down</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('timelapse')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('timelapse') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Kamera statis dengan bayangan matahari bergerak cepat"
                                        >
                                            <span>🌅 Timelapse</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="toggleVideoMotion('static_subtle')"
                                            class="py-1 px-1 rounded-lg text-center font-medium transition-all flex items-center justify-center gap-1"
                                            :class="captureVideoMotions.includes('static_subtle') ? 'bg-purple-600 text-white shadow font-semibold border border-purple-400/40' : 'text-neutral-400 hover:text-white hover:bg-white/5'"
                                            title="Tripod statis dengan micro-parallax alami"
                                        >
                                            <span>📹 Tripod Parallax</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- 2. Parameter Standar AI Video: Durasi & Kecepatan Kamera -->
                                <div class="grid grid-cols-2 gap-2">
                                    <!-- Durasi Video Standar -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-semibold uppercase tracking-wider text-neutral-400">Durasi Video AI:</span>
                                            <span class="font-mono text-purple-300 font-semibold">{{ captureVideoDuration }}</span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-1 p-0.5 rounded-xl bg-black/40 border border-white/10 text-[10px]">
                                            <button
                                                type="button"
                                                @click="captureVideoDuration = '4s'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoDuration === '4s' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="4 Detik (Looping / Reels)"
                                            >
                                                4 Detik
                                            </button>
                                            <button
                                                type="button"
                                                @click="captureVideoDuration = '5s'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoDuration === '5s' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="5 Detik (Standar Runway Gen-3 & Kling)"
                                            >
                                                5s (Standar)
                                            </button>
                                            <button
                                                type="button"
                                                @click="captureVideoDuration = '10s'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoDuration === '10s' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="10 Detik (Extended Kling / Sora)"
                                            >
                                                10s (Long)
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Kecepatan Kamera (Pace) -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-semibold uppercase tracking-wider text-neutral-400">Kecepatan Kamera:</span>
                                            <span class="font-mono text-purple-300 font-semibold">
                                                {{ captureVideoPace === 'slow' ? '🐢 Slow (0.5x)' : (captureVideoPace === 'dynamic' ? '⚡ Dinamis' : '🚶 Normal') }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-1 p-0.5 rounded-xl bg-black/40 border border-white/10 text-[10px]">
                                            <button
                                                type="button"
                                                @click="captureVideoPace = 'slow'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoPace === 'slow' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="Slow & Majestic (Terbaik untuk Arsitektur)"
                                            >
                                                Slow 0.5x
                                            </button>
                                            <button
                                                type="button"
                                                @click="captureVideoPace = 'normal'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoPace === 'normal' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="Normal Walking Pace 1.0x"
                                            >
                                                Normal
                                            </button>
                                            <button
                                                type="button"
                                                @click="captureVideoPace = 'dynamic'; syncPromptWithCurrentState()"
                                                class="py-1 px-1 rounded-lg text-center font-medium transition-all"
                                                :class="captureVideoPace === 'dynamic' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                                title="Dynamic Drone Speed"
                                            >
                                                Dinamis
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Suasana Cuaca & Lensa Sinematik -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <!-- Suasana Cuaca & Ambience -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-semibold uppercase tracking-wider text-neutral-400">Suasana Cuaca / Ambience:</span>
                                        </div>
                                        <select
                                            v-model="captureVideoMood"
                                            @change="syncPromptWithCurrentState()"
                                            class="w-full py-1.5 px-2 rounded-xl bg-black/50 border border-white/10 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-400"
                                        >
                                            <option value="scene">☀️ Sesuai Model 3D (Jam &amp; Matahari)</option>
                                            <option value="golden_hour">🌇 Golden Hour Sore (Bayangan Panjang)</option>
                                            <option value="overcast">☁️ Overcast Lembut (Jepang / Nordik)</option>
                                            <option value="rainy">🌧️ Hujan &amp; Refleksi Basah (Mewah)</option>
                                            <option value="night_luxury">🌃 Malam Mewah (Warm Spotlights &amp; LED)</option>
                                            <option value="misty_morning">🌫️ Kabut Pagi &amp; Sunbeams (Mist)</option>
                                        </select>
                                    </div>

                                    <!-- Gaya Lensa Kamera -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="font-semibold uppercase tracking-wider text-neutral-400">Profil Lensa Kamera:</span>
                                        </div>
                                        <select
                                            v-model="captureVideoLens"
                                            @change="syncPromptWithCurrentState()"
                                            class="w-full py-1.5 px-2 rounded-xl bg-black/50 border border-white/10 text-[11px] text-neutral-200 focus:outline-none focus:border-purple-400"
                                        >
                                            <option value="anamorphic_35mm">🎬 35mm Anamorphic (Film Hollywood 24fps)</option>
                                            <option value="wide_16mm">🏛️ 16mm Ultra-Wide (Garis Vertikal Lurus)</option>
                                            <option value="macro_50mm">🔍 50mm Bokeh (Detail Tekstur Material)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- 4. Target Video AI Platform & Anti-Warping Toggle -->
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-semibold uppercase tracking-wider text-neutral-400">Target AI Video:</span>
                                        <label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] text-purple-300 hover:text-white">
                                            <input
                                                type="checkbox"
                                                v-model="captureVideoStrictGeometry"
                                                @change="syncPromptWithCurrentState()"
                                                class="rounded border-white/20 bg-black/40 text-purple-600 focus:ring-0 h-3 w-3"
                                            />
                                            <span>Kunci Geometri 3D (Anti-Warping)</span>
                                        </label>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 p-1 rounded-xl bg-black/40 border border-white/10">
                                        <button
                                            type="button"
                                            @click="captureVideoTarget = 'runway'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureVideoTarget === 'runway' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Runway
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureVideoTarget = 'kling'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureVideoTarget === 'kling' ? 'bg-indigo-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Kling AI
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureVideoTarget = 'luma'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureVideoTarget === 'luma' ? 'bg-pink-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Luma
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureVideoTarget = 'universal'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureVideoTarget === 'universal' ? 'bg-teal-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Universal
                                        </button>
                                    </div>
                                </div>

                                <!-- Platform Tips Banner -->
                                <div class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-[11px] text-purple-200 leading-relaxed flex items-start gap-2">
                                    <Info class="h-4 w-4 text-purple-400 shrink-0 mt-0.5" />
                                    <div v-if="captureVideoTarget === 'runway'">
                                        <span class="font-semibold text-white">Panduan Runway Gen-3 Alpha:</span>
                                        <span v-if="isTwoPointActive"> Unggah Frame 1 ke slot <b>First Frame</b> dan Frame 2 ke slot <b>Last Frame</b>.</span>
                                        <span v-else> Unggah gambar snapshot ini ke Runway.</span>
                                        Salin prompt di bawah untuk pergerakan kamera sinematik tanpa distorsi bentuk arsitektur.
                                    </div>
                                    <div v-else-if="captureVideoTarget === 'kling'">
                                        <span class="font-semibold text-white">Panduan Kling AI:</span>
                                        <span v-if="isTwoPointActive"> Aktifkan opsi <b>Start &amp; End Frame</b> pada menu kamera Kling AI.</span>
                                        <span v-else> Paste prompt di bawah ini pada menu Text-to-Video / Image-to-Video Kling AI.</span>
                                    </div>
                                    <div v-else-if="captureVideoTarget === 'luma'">
                                        <span class="font-semibold text-white">Panduan Luma Dream Machine:</span> Gunakan Frame 1 sebagai input gambar, lalu paste prompt untuk mengarahkan kamera 3D.
                                    </div>
                                    <div v-else>
                                        <span class="font-semibold text-white">Format Universal Video AI:</span> Kompatibel dengan Sora, Hailuo/Minimax, Pika, dan VidU.
                                    </div>
                                </div>
                            </div>

                            <!-- JIKA MODE FOTO: MODEL AI SYNTAX SWITCHER TABS -->
                            <template v-else>
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-semibold uppercase tracking-wider text-neutral-400">Target Model AI:</span>
                                        <span class="font-mono text-indigo-300">
                                            {{ captureSelectedSyntax === 'midjourney' ? 'Midjourney v6.1 (--iw 2.0)' : (captureSelectedSyntax === 'sdxl' ? 'SDXL / Fooocus / ControlNet' : (captureSelectedSyntax === 'flux' ? 'Flux.1 / Krea.ai' : 'Universal Prompt')) }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1 p-1 rounded-xl bg-black/40 border border-white/10">
                                        <button
                                            type="button"
                                            @click="captureSelectedSyntax = 'universal'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureSelectedSyntax === 'universal' ? 'bg-indigo-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Universal
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureSelectedSyntax = 'midjourney'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureSelectedSyntax === 'midjourney' ? 'bg-blue-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Midjourney
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureSelectedSyntax = 'sdxl'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureSelectedSyntax === 'sdxl' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            SDXL / ControlNet
                                        </button>
                                        <button
                                            type="button"
                                            @click="captureSelectedSyntax = 'flux'; syncPromptWithCurrentState()"
                                            class="py-1 px-1.5 rounded-lg text-center text-[10px] sm:text-[11px] font-medium transition-all"
                                            :class="captureSelectedSyntax === 'flux' ? 'bg-emerald-600 text-white shadow font-semibold' : 'text-neutral-400 hover:text-white'"
                                        >
                                            Flux / Krea
                                        </button>
                                    </div>
                                </div>

                                <!-- Model-Specific Recommendation Tip Banner -->
                                <div v-if="captureSelectedSyntax === 'midjourney'" class="p-2.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-[11px] text-blue-200 leading-relaxed flex items-start gap-2">
                                    <Info class="h-4 w-4 text-blue-400 shrink-0 mt-0.5" />
                                    <div>
                                        <span class="font-semibold text-white">Panduan Midjourney v6:</span> Unggah gambar preview ke Discord, salin link gambarnya di awal prompt. Parameter <code class="px-1 py-0.2 rounded bg-blue-900/60 font-mono text-amber-300">--iw 2.0</code> (Image Weight maksimal) otomatis ditambahkan untuk mengunci geometri 3D.
                                    </div>
                                </div>

                                <div v-else-if="captureSelectedSyntax === 'sdxl'" class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-[11px] text-purple-200 leading-relaxed space-y-1">
                                    <div class="flex items-center gap-1.5 font-semibold text-white">
                                        <Sliders class="h-3.5 w-3.5 text-purple-400" />
                                        <span>Panduan Stable Diffusion / Fooocus:</span>
                                    </div>
                                    <p class="text-[10px] text-neutral-300">
                                        • <b>Img2Img Denoising:</b> Set di angka <span class="text-amber-300 font-bold font-mono">0.35 - 0.45</span> (jangan >0.5 agar bentuk tidak berubah).<br>
                                        • <b>ControlNet:</b> Unggah file <b>Depth Map</b> (Preprocessor: Depth/None, Weight: 1.0) untuk akurasi dinding 100%.
                                    </p>
                                </div>

                                <div v-else-if="captureSelectedSyntax === 'flux'" class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-200 leading-relaxed flex items-start gap-2">
                                    <Info class="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" />
                                    <div>
                                        <span class="font-semibold text-white">Panduan Flux.1 &amp; Krea.ai:</span> Gunakan mode <b>Image-to-Image / Sketch to Render</b> dengan <b>AI Strength 30% - 40%</b> agar warna material dan proporsi asli tidak diganti.
                                    </div>
                                </div>
                            </template>

                            <!-- Detected Feature Status Badges -->
                            <div class="space-y-1.5">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 block">Status Fitur Terdeteksi:</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(badge, bIdx) in detectedSceneBadges"
                                        :key="bIdx"
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-medium border flex items-center gap-1 shadow-sm"
                                        :class="badge.type === 'mode'
                                            ? (badge.active ? 'bg-amber-500/15 text-amber-300 border-amber-500/30 font-semibold' : 'bg-white/5 text-neutral-300 border-white/10')
                                            : badge.type === 'time'
                                            ? 'bg-orange-500/15 text-orange-300 border-orange-500/30 font-mono'
                                            : badge.type === 'angle'
                                            ? 'bg-blue-500/15 text-blue-300 border-blue-500/30'
                                            : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'"
                                    >
                                        {{ badge.label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Prompt Box & Copy CTA -->
                            <div class="flex-1 flex flex-col p-3.5 rounded-2xl border border-indigo-500/30 bg-indigo-950/20 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-semibold text-white">
                                            {{ capturePromptMode === 'video' ? 'Video AI Prompt' : (captureSelectedSyntax === 'midjourney' ? 'Midjourney Command' : (captureSelectedSyntax === 'sdxl' ? 'Positive Prompt' : (captureSelectedSyntax === 'flux' ? 'Flux Prompt' : 'Universal Prompt'))) }}
                                        </span>
                                        <span v-if="isGeneratingVisionPrompt" class="text-[10px] text-amber-300 font-mono animate-pulse flex items-center gap-1">
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-ping"></span>
                                            <span>Menganalisis Vision AI...</span>
                                        </span>
                                        <span v-else-if="hasVisionAiGenerated" class="text-[9px] font-semibold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                                            <Sparkles class="h-2.5 w-2.5 text-emerald-400" />
                                            <span>Vision AI Aktif</span>
                                        </span>
                                        <span v-else class="text-[9px] font-medium px-1.5 py-0.5 rounded bg-white/10 text-neutral-400 border border-white/10">
                                            Draft 3D (0 Token)
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <!-- Regenerate / Re-roll Button (active when already generated) -->
                                        <button
                                            v-if="hasVisionAiGenerated"
                                            type="button"
                                            :disabled="isGeneratingVisionPrompt"
                                            @click="requestAiPrompt"
                                            class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] text-neutral-400 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-40"
                                            title="Generate ulang prompt dengan Vision AI"
                                        >
                                            <RefreshCw class="h-3 w-3" :class="isGeneratingVisionPrompt ? 'animate-spin' : ''" />
                                            <span>{{ isGeneratingVisionPrompt ? 'Proses...' : 'Re-roll' }}</span>
                                        </button>

                                        <!-- Copy Formatted Prompt Button -->
                                        <button
                                            type="button"
                                            @click="copySelectedCapturePrompt"
                                            class="flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all shadow-md"
                                            :class="copiedPromptModal
                                                ? 'bg-emerald-500 text-black'
                                                : (capturePromptMode === 'video' ? 'bg-purple-600 hover:bg-purple-500 text-white' : (captureSelectedSyntax === 'midjourney' ? 'bg-blue-600 hover:bg-blue-500 text-white' : (captureSelectedSyntax === 'sdxl' ? 'bg-purple-600 hover:bg-purple-500 text-white' : 'bg-indigo-600 hover:bg-indigo-500 text-white')))"
                                            :title="`Salin prompt ke clipboard`"
                                        >
                                            <Check v-if="copiedPromptModal" class="h-3.5 w-3.5" />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                            <span>{{ copiedPromptModal ? 'Tersalin!' : (capturePromptMode === 'video' ? 'Copy Video Prompt' : (captureSelectedSyntax === 'midjourney' ? 'Copy Midjourney' : (captureSelectedSyntax === 'sdxl' ? 'Copy Positive' : 'Copy Prompt'))) }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- PRIMARY ACTION BUTTON: Dapatkan Prompt dengan Vision AI (On-demand) -->
                                <button
                                    v-if="!hasVisionAiGenerated"
                                    type="button"
                                    :disabled="isGeneratingVisionPrompt"
                                    @click="requestAiPrompt"
                                    class="w-full py-2 px-3 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all shadow-md group disabled:opacity-50"
                                    :class="capturePromptMode === 'video'
                                        ? 'bg-gradient-to-r from-purple-600 via-pink-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white border border-purple-400/30 hover:shadow-purple-500/25'
                                        : 'bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white border border-indigo-400/30 hover:shadow-indigo-500/25'"
                                >
                                    <Sparkles class="h-4 w-4 text-amber-300" :class="isGeneratingVisionPrompt ? 'animate-spin' : 'group-hover:scale-110 transition-transform'" />
                                    <span>
                                        {{ isGeneratingVisionPrompt ? 'Menganalisis Snapshot 3D...' : (capturePromptMode === 'video' ? '✨ Dapatkan Prompt Video dengan Vision AI' : '✨ Dapatkan Prompt dengan Vision AI') }}
                                    </span>
                                </button>

                                <!-- Textarea Editor for Prompt -->
                                <textarea
                                    v-model="capturePrompt"
                                    rows="5"
                                    placeholder="Menghasilkan prompt arsitektur dari Gemini Vision AI..."
                                    class="w-full flex-1 min-h-[105px] rounded-xl border border-white/10 bg-black/50 p-2.5 text-xs text-neutral-200 placeholder:text-neutral-500 focus:outline-none focus:border-indigo-400 leading-relaxed font-sans resize-y"
                                ></textarea>

                                <!-- Midjourney Parameters Indicator -->
                                <div v-if="captureSelectedSyntax === 'midjourney'" class="p-2 rounded-xl bg-blue-950/40 border border-blue-500/30 flex items-center justify-between text-[10px]">
                                    <span class="text-neutral-400">Parameter otomatis ditambahkan saat disalin:</span>
                                    <span class="font-mono text-amber-300 font-semibold bg-black/50 px-2 py-0.5 rounded border border-blue-500/30">--iw 2.0 --ar 16:9 --style raw --v 6.1</span>
                                </div>

                                <!-- SDXL Negative Prompt Section -->
                                <div v-if="captureSelectedSyntax === 'sdxl'" class="space-y-1 pt-1 border-t border-purple-500/20">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-rose-300">Negative Prompt (Wajib di SDXL):</span>
                                        <button
                                            type="button"
                                            @click="copyNegativePrompt"
                                            class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 border border-rose-500/30 transition-all"
                                            title="Salin Negative Prompt"
                                        >
                                            <Check v-if="copiedNegativeModal" class="h-2.5 w-2.5" />
                                            <Copy v-else class="h-2.5 w-2.5" />
                                            <span>{{ copiedNegativeModal ? 'Tersalin!' : 'Copy Negative' }}</span>
                                        </button>
                                    </div>
                                    <textarea
                                        v-model="defaultNegativePrompt"
                                        rows="2"
                                        class="w-full rounded-xl border border-rose-500/30 bg-black/60 p-2 text-[10px] text-rose-200/90 font-mono leading-relaxed focus:outline-none focus:border-rose-400"
                                    ></textarea>
                                </div>

                                <!-- Video AI Negative Prompt Section (Anti-Warping & Anti-Distorsi) -->
                                <div v-if="capturePromptMode === 'video'" class="space-y-1 pt-1 border-t border-purple-500/20">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-purple-300">Negative Prompt Video (Anti-Warping):</span>
                                        <button
                                            type="button"
                                            @click="copyVideoNegativePrompt"
                                            class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 hover:bg-purple-500/30 border border-purple-500/30 transition-all"
                                            title="Salin Negative Prompt Video"
                                        >
                                            <Check v-if="copiedVideoNegativeModal" class="h-2.5 w-2.5" />
                                            <Copy v-else class="h-2.5 w-2.5" />
                                            <span>{{ copiedVideoNegativeModal ? 'Tersalin!' : 'Copy Negative Video' }}</span>
                                        </button>
                                    </div>
                                    <textarea
                                        v-model="defaultVideoNegativePrompt"
                                        rows="2"
                                        class="w-full rounded-xl border border-purple-500/30 bg-black/60 p-2 text-[10px] text-purple-200/90 font-mono leading-relaxed focus:outline-none focus:border-purple-400"
                                    ></textarea>
                                </div>

                                <p class="text-[10px] text-neutral-400 flex items-center justify-between pt-0.5">
                                    <span>💡 Dapat diedit bebas sebelum disalin atau disimpan.</span>
                                    <span class="font-mono text-neutral-500">{{ capturePrompt.length }} karakter</span>
                                </p>
                            </div>

                            <!-- Settings: Kode, Label Ruangan, Resolusi -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                <div>
                                    <label class="text-[10px] font-medium text-amber-300 block mb-1">
                                        Kode Snapshot
                                    </label>
                                    <input
                                        type="text"
                                        v-model="captureCode"
                                        placeholder="Contoh: R-01"
                                        class="w-full h-8 rounded-xl border border-amber-500/30 bg-amber-500/10 px-2.5 text-xs font-mono font-bold text-amber-300 placeholder:text-neutral-500 focus:outline-none focus:border-amber-400"
                                        title="Kode referensi unik untuk denah 2D dan booklet (contoh: R-01, EXT-01)"
                                    />
                                </div>
                                <div>
                                    <label class="text-[10px] font-medium text-neutral-400 block mb-1">
                                        Label Ruangan
                                        <span v-if="drawingRooms.length > 0" class="text-orange-400 font-mono text-[9px] ml-1">(Sinkron Denah 2D)</span>
                                    </label>
                                    <input
                                        type="text"
                                        v-model="captureRoomName"
                                        list="drawing-room-options"
                                        placeholder="Contoh: Dapur & Meja Makan"
                                        class="w-full h-8 rounded-xl border border-white/10 bg-white/5 px-2.5 text-xs text-white placeholder:text-neutral-600 focus:outline-none focus:border-rose-400"
                                    />
                                    <datalist id="drawing-room-options">
                                        <option v-for="r in drawingRooms" :key="r.id" :value="r.name">{{ r.name }}</option>
                                    </datalist>
                                </div>
                                <div>
                                    <label class="text-[10px] font-medium text-neutral-400 block mb-1">Resolusi Simpan</label>
                                    <select
                                        v-model="captureResolution"
                                        @change="renderEngine === 'standard' && refreshCaptureSnapshot()"
                                        class="w-full h-8 rounded-xl border border-white/10 bg-white/5 px-2 text-xs text-white focus:outline-none focus:border-rose-400"
                                    >
                                        <option value="viewport" class="bg-neutral-900 text-white">Viewport</option>
                                        <option value="1080p" class="bg-neutral-900 text-white">1080p Full HD</option>
                                        <option value="4k" class="bg-neutral-900 text-white">4K Ultra HD</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Owner Action & Gallery Save -->
                            <div class="pt-2 border-t border-white/10 space-y-2">
                                <div v-if="isOwner" class="flex items-center justify-between px-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-xs">
                                    <span class="text-[11px] text-neutral-300">Tampilkan ke Klien di Galeri</span>
                                    <button
                                        type="button"
                                        @click="captureVisibleToClients = !captureVisibleToClients"
                                        class="relative h-5 w-9 rounded-full transition-all duration-200"
                                        :class="captureVisibleToClients ? 'bg-emerald-500' : 'bg-white/15'"
                                    >
                                        <span
                                            class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all duration-200"
                                            :class="captureVisibleToClients ? 'left-[18px]' : 'left-0.5'"
                                        ></span>
                                    </button>
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <div v-if="isOwner && quotaInfo" class="text-[10px] text-neutral-400">
                                        <span>Kuota Galeri: </span>
                                        <span class="font-semibold text-white">{{ quotaInfo.current_count }} / {{ quotaInfo.max_captures ?? '∞' }}</span>
                                    </div>
                                    <div v-else></div>

                                    <button
                                        v-if="isOwner"
                                        type="button"
                                        :disabled="isPathTracing || isSavingCapture || !capturePreviewDataUrl"
                                        @click="saveCaptureToGallery"
                                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 text-xs font-semibold text-white hover:bg-rose-500 transition-all shadow-md disabled:opacity-40"
                                    >
                                        <Check v-if="!isSavingCapture" class="h-3.5 w-3.5" />
                                        <span v-else class="h-3.5 w-3.5 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
                                        <span>Simpan ke Galeri</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── CAPTURE GALLERY DRAWER ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-full"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-full"
            >
                <div
                    v-if="isGalleryOpen"
                    class="absolute top-14 right-0 bottom-0 z-40 w-96 max-w-[95vw] border-l border-white/15 bg-neutral-950/95 backdrop-blur-2xl shadow-2xl flex flex-col"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 shrink-0">
                        <div class="flex items-center gap-2">
                            <Image class="h-4 w-4 text-amber-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Galeri Gambar 3D</span>
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-white/10 text-neutral-300">
                                {{ filteredCaptures.length }}
                            </span>
                        </div>
                        <button type="button" @click="isGalleryOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Quota Bar (Architect only) -->
                    <div v-if="isOwner && quotaInfo" class="px-4 py-2.5 bg-white/[0.04] border-b border-white/10 flex items-center justify-between text-xs shrink-0">
                        <div class="flex items-center gap-1.5 text-neutral-300">
                            <Sparkles class="h-3.5 w-3.5 text-amber-400" />
                            <span>Kuota Gambar:</span>
                            <span class="font-bold text-white">
                                {{ quotaInfo.current_count }} / {{ quotaInfo.max_captures ?? '∞' }}
                            </span>
                        </div>
                        <span
                            v-if="quotaInfo.max_captures !== null"
                            class="text-[10px] font-mono px-2 py-0.5 rounded-full"
                            :class="quotaInfo.current_count >= quotaInfo.max_captures
                                ? 'bg-rose-500/20 text-rose-300 font-bold border border-rose-500/30'
                                : 'bg-white/10 text-neutral-300'"
                        >
                            {{ quotaInfo.current_count >= quotaInfo.max_captures ? 'Kuota Penuh' : `Sisa ${Math.max(0, quotaInfo.max_captures - quotaInfo.current_count)}` }}
                        </span>
                    </div>

                    <!-- Filter Bar -->
                    <div class="p-3 border-b border-white/5 grid grid-cols-3 gap-1.5 shrink-0">
                        <select
                            v-model="galleryCodeGroupFilter"
                            class="h-7 rounded-lg border border-amber-500/30 bg-amber-500/10 px-1.5 text-[11px] font-mono text-amber-300 focus:outline-none focus:border-amber-400"
                            title="Filter pengelompokan kode snapshot"
                        >
                            <option value="all" class="bg-neutral-900 text-white">Semua Kode</option>
                            <option value="R" class="bg-neutral-900 text-white">R-* (Interior)</option>
                            <option value="EXT" class="bg-neutral-900 text-white">EXT-* (Eksterior)</option>
                            <option value="CUSTOM" class="bg-neutral-900 text-white">Kustom</option>
                        </select>

                        <select
                            v-model="galleryRoomFilter"
                            class="h-7 rounded-lg border border-white/10 bg-white/5 px-1.5 text-[11px] text-white focus:outline-none focus:border-amber-400"
                        >
                            <option value="all" class="bg-neutral-900 text-white">Semua Ruangan</option>
                            <option
                                v-for="room in uniqueRooms"
                                :key="room"
                                :value="room"
                                class="bg-neutral-900 text-white"
                            >
                                {{ room }}
                            </option>
                        </select>

                        <select
                            v-model="galleryHourFilter"
                            class="h-7 rounded-lg border border-white/10 bg-white/5 px-1.5 text-[11px] text-white focus:outline-none focus:border-amber-400"
                        >
                            <option value="all" class="bg-neutral-900 text-white">Semua Jam</option>
                            <option
                                v-for="hour in uniqueHours"
                                :key="hour"
                                :value="hour"
                                class="bg-neutral-900 text-white"
                            >
                                {{ hour }}
                            </option>
                        </select>
                    </div>

                    <!-- Gallery Content List -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-4">
                        <div v-if="filteredCaptures.length === 0" class="flex flex-col items-center justify-center py-16 gap-3 text-center px-4">
                            <Camera class="h-10 w-10 text-neutral-700" />
                            <p class="text-xs text-neutral-400 leading-relaxed max-w-[220px]">
                                <template v-if="isOwner">
                                    Belum ada gambar tersimpan. Klik tombol <span class="text-rose-400 font-semibold">"Ambil Gambar"</span> di toolbar untuk menyimpan hasil render ruangan dan jam.
                                </template>
                                <template v-else>
                                    Belum ada gambar render yang dibagikan untuk proyek ini.
                                </template>
                            </p>
                        </div>

                        <div
                            v-for="capture in filteredCaptures"
                            :key="capture.id"
                            class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden group hover:border-white/20 transition-all shadow-md"
                        >
                            <!-- Thumbnail with click-to-preview -->
                            <div
                                class="relative aspect-video w-full bg-black cursor-pointer overflow-hidden"
                                @click="activeLightboxCapture = capture"
                            >
                                <img
                                    :src="capture.url"
                                    :alt="capture.room_name"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-80 group-hover:opacity-100 transition-opacity flex items-end justify-between p-2.5">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span v-if="capture.code" class="px-1.5 py-0.5 rounded bg-amber-500/90 text-black text-[10px] font-mono font-bold shrink-0 shadow">
                                            {{ capture.code }}
                                        </span>
                                        <span class="text-xs font-semibold text-white drop-shadow truncate">{{ capture.room_name }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/30 text-[10px] font-mono text-amber-300 shrink-0 ml-1">
                                        {{ capture.captured_hour }}
                                    </span>
                                </div>
                            </div>

                            <!-- Prompt AI Section in Gallery Card -->
                            <div class="px-3 py-2 border-t border-white/5 space-y-1.5 bg-black/20">
                                <div class="flex items-center justify-between text-[11px]">
                                    <div class="flex items-center gap-1.5 text-neutral-400">
                                        <Sparkles class="h-3 w-3 text-indigo-400" />
                                        <span class="font-medium text-neutral-300">Prompt AI:</span>
                                        <span
                                            v-if="promptSaveStatus[capture.id] === 'saving'"
                                            class="text-[10px] text-amber-400 animate-pulse font-mono"
                                        >
                                            Menyimpan...
                                        </span>
                                        <span
                                            v-else-if="promptSaveStatus[capture.id] === 'saved'"
                                            class="text-[10px] text-emerald-400 font-mono"
                                        >
                                            Tersimpan ✓
                                        </span>
                                        <span
                                            v-else-if="promptSaveStatus[capture.id] === 'error'"
                                            class="text-[10px] text-rose-400 font-mono"
                                        >
                                            Gagal simpan
                                        </span>
                                    </div>

                                    <button
                                        v-if="capture.prompt"
                                        type="button"
                                        @click.stop="copyCapturePrompt(capture)"
                                        class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium transition-all"
                                        :class="copiedCaptureId === capture.id
                                            ? 'bg-emerald-500 text-black font-semibold'
                                            : 'bg-white/10 hover:bg-white/20 text-neutral-300 hover:text-white'"
                                        title="Salin Prompt ke Clipboard"
                                    >
                                        <Check v-if="copiedCaptureId === capture.id" class="h-2.5 w-2.5" />
                                        <Copy v-else class="h-2.5 w-2.5" />
                                        <span>{{ copiedCaptureId === capture.id ? 'Tersalin' : 'Salin' }}</span>
                                    </button>
                                </div>

                                <!-- Editable textarea for Owner with Auto-Save on Blur -->
                                <textarea
                                    v-if="isOwner"
                                    v-model="capture.prompt"
                                    @blur="autoSaveCapturePrompt(capture)"
                                    rows="2"
                                    placeholder="Tulis prompt AI di sini (otomatis tersimpan saat klik di luar)..."
                                    class="w-full rounded-xl border border-white/10 bg-black/40 p-2 text-[11px] text-neutral-300 placeholder:text-neutral-600 focus:outline-none focus:border-indigo-400 leading-relaxed font-sans resize-y"
                                ></textarea>
                                <!-- Read-only text for Client -->
                                <p
                                    v-else-if="capture.prompt"
                                    class="text-[11px] text-neutral-400 line-clamp-3 bg-black/30 p-2 rounded-xl border border-white/5 leading-relaxed"
                                >
                                    {{ capture.prompt }}
                                </p>
                            </div>

                            <!-- Footer actions -->
                            <div class="p-3 flex items-center justify-between text-xs">
                                <!-- Visibility toggle (Architect only) -->
                                <div v-if="isOwner" class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="toggleCaptureVisibility(capture)"
                                        class="flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-medium transition-all"
                                        :class="capture.is_visible_to_clients
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                                            : 'bg-white/5 text-neutral-400 border border-white/10'"
                                        title="Ubah visibilitas ke klien"
                                    >
                                        <Eye v-if="capture.is_visible_to_clients" class="h-3 w-3" />
                                        <EyeOff v-else class="h-3 w-3" />
                                        <span>{{ capture.is_visible_to_clients ? 'Tampil Klien' : 'Privat' }}</span>
                                    </button>
                                </div>
                                <span v-else class="text-[10px] text-neutral-500">
                                    {{ new Date(capture.created_at).toLocaleDateString('id-ID') }}
                                </span>

                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click.stop="downloadCaptureFile(capture)"
                                        class="p-1.5 rounded-lg text-neutral-300 hover:text-white hover:bg-white/10 transition-colors"
                                        title="Unduh PNG"
                                    >
                                        <Download class="h-3.5 w-3.5" />
                                    </button>

                                    <button
                                        v-if="isOwner"
                                        type="button"
                                        @click="deleteCapture(capture)"
                                        class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors"
                                        title="Hapus gambar"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── LIGHTBOX MODAL ── -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="activeLightboxCapture"
                    class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 md:p-6 bg-black/90 backdrop-blur-xl overflow-y-auto"
                    @click.self="activeLightboxCapture = null"
                >
                    <!-- Modal Container (2-Column on Desktop / Stacked on Mobile) -->
                    <div class="relative w-full max-w-6xl max-h-[92vh] flex flex-col lg:flex-row bg-neutral-950/95 border border-white/15 rounded-3xl shadow-2xl overflow-hidden backdrop-blur-2xl my-auto">
                        
                        <!-- Left Stage: Large 3D Snapshot Viewer -->
                        <div class="relative flex-1 flex flex-col items-center justify-center bg-black/70 p-4 sm:p-6 min-h-[280px] sm:min-h-[380px] lg:min-h-[500px] select-none">
                            
                            <!-- Floating Nav Arrows (Prev / Next) -->
                            <button
                                v-if="filteredCaptures.length > 1"
                                type="button"
                                @click.stop="prevLightboxCapture"
                                class="absolute left-3 top-1/2 -translate-y-1/2 z-10 p-2.5 rounded-full bg-black/60 hover:bg-black/90 text-white/80 hover:text-white border border-white/15 backdrop-blur-md transition-all shadow-lg hover:scale-105 active:scale-95"
                                title="Gambar Sebelumnya (Arrow Left)"
                            >
                                <ChevronLeft class="h-5 w-5" />
                            </button>

                            <button
                                v-if="filteredCaptures.length > 1"
                                type="button"
                                @click.stop="nextLightboxCapture"
                                class="absolute right-3 top-1/2 -translate-y-1/2 z-10 p-2.5 rounded-full bg-black/60 hover:bg-black/90 text-white/80 hover:text-white border border-white/15 backdrop-blur-md transition-all shadow-lg hover:scale-105 active:scale-95"
                                title="Gambar Selanjutnya (Arrow Right)"
                            >
                                <ChevronRight class="h-5 w-5" />
                            </button>

                            <!-- Centered Responsive Image -->
                            <img
                                :src="activeLightboxCapture.url"
                                :alt="activeLightboxCapture.room_name"
                                class="max-h-[46vh] lg:max-h-[80vh] w-auto max-w-full rounded-2xl border border-white/10 shadow-2xl object-contain transition-all duration-200"
                            />

                            <!-- Image Bottom Floating Badges -->
                            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between pointer-events-none">
                                <div class="flex items-center gap-1.5 pointer-events-auto">
                                    <span class="px-2.5 py-1 rounded-full bg-black/75 border border-white/15 text-[11px] font-medium text-white backdrop-blur">
                                        {{ activeLightboxCapture.room_name }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/25 border border-amber-500/40 text-amber-300 font-mono text-[10px] backdrop-blur">
                                        {{ activeLightboxCapture.captured_hour }}
                                    </span>
                                </div>

                                <div v-if="filteredCaptures.length > 1" class="px-2.5 py-1 rounded-full bg-black/75 border border-white/15 text-[10px] text-neutral-300 font-mono backdrop-blur">
                                    {{ currentLightboxIndex + 1 }} / {{ filteredCaptures.length }}
                                </div>
                            </div>
                        </div>

                        <!-- Right Sidebar: Inspector, AI Prompt, Metadata & Actions -->
                        <div class="w-full lg:w-[420px] flex flex-col justify-between border-t lg:border-t-0 lg:border-l border-white/10 bg-neutral-900/80 p-5 space-y-4 overflow-y-auto max-h-[50vh] lg:max-h-[92vh]">
                            
                            <!-- Header: Title, Close button & Meta pills -->
                            <div class="space-y-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-if="isOwner"
                                                type="text"
                                                v-model="activeLightboxCapture.code"
                                                @blur="autoSaveCaptureCode(activeLightboxCapture)"
                                                placeholder="Kode (R-01)"
                                                class="w-20 px-2 py-0.5 text-xs font-mono font-bold text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-lg focus:outline-none focus:border-amber-400"
                                                title="Ubah kode identifikasi gambar (contoh: R-01, EXT-01)"
                                            />
                                            <span v-else-if="activeLightboxCapture.code" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-amber-500/20 border border-amber-500/30 text-amber-300">
                                                {{ activeLightboxCapture.code }}
                                            </span>
                                            <input
                                                v-if="isOwner"
                                                type="text"
                                                list="drawing-room-options"
                                                v-model="activeLightboxCapture.room_name"
                                                @blur="autoSaveCaptureRoomName(activeLightboxCapture)"
                                                class="text-base font-semibold text-white tracking-tight bg-transparent hover:bg-white/10 focus:bg-black/60 border border-transparent hover:border-white/20 focus:border-indigo-400 rounded-lg px-2 py-0.5 focus:outline-none transition max-w-[180px]"
                                                title="Klik untuk mengubah label ruangan (Sinkron Denah 2D)"
                                            />
                                            <h3 v-else class="text-base font-semibold text-white tracking-tight">
                                                {{ activeLightboxCapture.room_name }}
                                            </h3>
                                            <span
                                                v-if="isOwner"
                                                class="px-2 py-0.5 rounded text-[10px] font-medium border"
                                                :class="activeLightboxCapture.is_visible_to_clients
                                                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
                                                    : 'bg-neutral-800 text-neutral-400 border-white/10'"
                                            >
                                                {{ activeLightboxCapture.is_visible_to_clients ? 'Tampil ke Klien' : 'Privat' }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-neutral-400 mt-0.5">
                                            Ditangkap pada jam <span class="text-amber-300 font-mono font-medium">{{ activeLightboxCapture.captured_hour }}</span>
                                            <span v-if="activeLightboxCapture.width && activeLightboxCapture.height" class="text-neutral-500"> • {{ activeLightboxCapture.width }}×{{ activeLightboxCapture.height }}px</span>
                                            <span class="text-neutral-500"> • {{ (activeLightboxCapture.file_size_bytes / 1024).toFixed(0) }} KB</span>
                                        </p>
                                    </div>

                                    <!-- Sticky Close button -->
                                    <button
                                        type="button"
                                        @click="activeLightboxCapture = null"
                                        class="p-2 rounded-xl text-neutral-400 hover:text-white hover:bg-white/10 transition-colors flex-shrink-0"
                                        title="Tutup (Esc)"
                                    >
                                        <X class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Model AI Syntax Switcher Tabs for Lightbox -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-semibold uppercase tracking-wider text-neutral-400">Target Model AI:</span>
                                    <span class="font-mono text-indigo-300">
                                        {{ lightboxSelectedSyntax === 'midjourney' ? 'Midjourney v6.1' : (lightboxSelectedSyntax === 'sdxl' ? 'SDXL / ControlNet' : (lightboxSelectedSyntax === 'flux' ? 'Flux.1 / Krea' : 'Universal')) }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-4 gap-1 p-1 rounded-xl bg-black/40 border border-white/10">
                                    <button
                                        type="button"
                                        @click="lightboxSelectedSyntax = 'universal'"
                                        class="py-1 px-1 rounded-lg text-center text-[10px] font-medium transition-all"
                                        :class="lightboxSelectedSyntax === 'universal' ? 'bg-indigo-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        Universal
                                    </button>
                                    <button
                                        type="button"
                                        @click="lightboxSelectedSyntax = 'midjourney'"
                                        class="py-1 px-1 rounded-lg text-center text-[10px] font-medium transition-all"
                                        :class="lightboxSelectedSyntax === 'midjourney' ? 'bg-blue-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        Midjourney
                                    </button>
                                    <button
                                        type="button"
                                        @click="lightboxSelectedSyntax = 'sdxl'"
                                        class="py-1 px-1 rounded-lg text-center text-[10px] font-medium transition-all"
                                        :class="lightboxSelectedSyntax === 'sdxl' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        SDXL
                                    </button>
                                    <button
                                        type="button"
                                        @click="lightboxSelectedSyntax = 'flux'"
                                        class="py-1 px-1 rounded-lg text-center text-[10px] font-medium transition-all"
                                        :class="lightboxSelectedSyntax === 'flux' ? 'bg-emerald-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                                    >
                                        Flux
                                    </button>
                                </div>
                            </div>

                            <!-- AI Prompt Section -->
                            <div class="flex-1 flex flex-col p-4 rounded-2xl border border-indigo-500/25 bg-indigo-950/20 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Sparkles class="h-4 w-4 text-indigo-400" />
                                        <span class="text-xs font-semibold text-white">
                                            {{ lightboxSelectedSyntax === 'midjourney' ? 'Prompt Midjourney' : (lightboxSelectedSyntax === 'sdxl' ? 'Positive Prompt SDXL' : (lightboxSelectedSyntax === 'flux' ? 'Prompt Flux' : 'Prompt Arsitektur')) }}
                                        </span>
                                        <span
                                            v-if="promptSaveStatus[activeLightboxCapture.id] === 'saving'"
                                            class="text-[10px] text-amber-400 animate-pulse font-mono"
                                        >
                                            Menyimpan...
                                        </span>
                                        <span
                                            v-else-if="promptSaveStatus[activeLightboxCapture.id] === 'saved'"
                                            class="text-[10px] text-emerald-400 font-mono"
                                        >
                                            Tersimpan ✓
                                        </span>
                                        <span
                                            v-else-if="promptSaveStatus[activeLightboxCapture.id] === 'error'"
                                            class="text-[10px] text-rose-400 font-mono"
                                        >
                                            Gagal simpan
                                        </span>
                                    </div>

                                    <button
                                        v-if="activeLightboxCapture.prompt"
                                        type="button"
                                        @click="copyLightboxSelectedPrompt(activeLightboxCapture)"
                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all shadow-sm"
                                        :class="copiedCaptureId === activeLightboxCapture.id
                                            ? 'bg-emerald-500 text-black'
                                            : (lightboxSelectedSyntax === 'midjourney' ? 'bg-blue-600 hover:bg-blue-500 text-white' : (lightboxSelectedSyntax === 'sdxl' ? 'bg-purple-600 hover:bg-purple-500 text-white' : 'bg-indigo-600 hover:bg-indigo-500 text-white'))"
                                        :title="`Salin prompt format ${lightboxSelectedSyntax}`"
                                    >
                                        <Check v-if="copiedCaptureId === activeLightboxCapture.id" class="h-3 w-3" />
                                        <Copy v-else class="h-3 w-3" />
                                        <span>{{ copiedCaptureId === activeLightboxCapture.id ? 'Tersalin!' : (lightboxSelectedSyntax === 'midjourney' ? 'Copy Midjourney' : (lightboxSelectedSyntax === 'sdxl' ? 'Copy Positive' : 'Salin Prompt')) }}</span>
                                    </button>
                                </div>

                                <!-- Editable textarea for Owner with Auto-Save on Blur -->
                                <textarea
                                    v-if="isOwner"
                                    v-model="activeLightboxCapture.prompt"
                                    @blur="autoSaveCapturePrompt(activeLightboxCapture)"
                                    rows="5"
                                    placeholder="Edit prompt AI di sini... Otomatis tersimpan ke server saat klik di luar."
                                    class="w-full flex-1 min-h-[105px] rounded-xl border border-white/10 bg-black/50 p-3 text-xs text-neutral-200 placeholder:text-neutral-600 focus:outline-none focus:border-indigo-400 leading-relaxed font-sans resize-y"
                                ></textarea>

                                <!-- Read-only text for Client -->
                                <div
                                    v-else-if="activeLightboxCapture.prompt"
                                    class="p-3 rounded-xl bg-black/40 border border-white/5 text-xs text-neutral-300 leading-relaxed max-h-[180px] overflow-y-auto"
                                >
                                    {{ activeLightboxCapture.prompt }}
                                </div>

                                <p v-else class="text-[11px] text-neutral-500 italic p-2">
                                    Belum ada prompt AI untuk gambar ini.
                                </p>

                                <!-- Midjourney Parameters Indicator -->
                                <div v-if="lightboxSelectedSyntax === 'midjourney' && activeLightboxCapture.prompt" class="p-2 rounded-xl bg-blue-950/40 border border-blue-500/30 flex items-center justify-between text-[10px]">
                                    <span class="text-neutral-400">Parameter otomatis saat disalin:</span>
                                    <span class="font-mono text-amber-300 font-semibold bg-black/50 px-2 py-0.5 rounded border border-blue-500/30">--iw 2.0 --ar 16:9 --style raw --v 6.1</span>
                                </div>

                                <!-- SDXL Negative Prompt Section -->
                                <div v-if="lightboxSelectedSyntax === 'sdxl' && activeLightboxCapture.prompt" class="space-y-1 pt-1 border-t border-purple-500/20">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-rose-300">Negative Prompt:</span>
                                        <button
                                            type="button"
                                            @click="copyNegativePrompt"
                                            class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/20 text-rose-300 hover:bg-rose-500/30 border border-rose-500/30 transition-all"
                                            title="Salin Negative Prompt"
                                        >
                                            <Check v-if="copiedNegativeModal" class="h-2.5 w-2.5" />
                                            <Copy v-else class="h-2.5 w-2.5" />
                                            <span>{{ copiedNegativeModal ? 'Tersalin!' : 'Copy Negative' }}</span>
                                        </button>
                                    </div>
                                    <textarea
                                        v-model="defaultNegativePrompt"
                                        rows="2"
                                        class="w-full rounded-xl border border-rose-500/30 bg-black/60 p-2 text-[10px] text-rose-200/90 font-mono leading-relaxed focus:outline-none focus:border-rose-400"
                                    ></textarea>
                                </div>

                                <p class="text-[10px] text-neutral-400 flex items-center justify-between pt-1">
                                    <span>💡 Siap disalin ke Midjourney / ControlNet / SD / Flux.</span>
                                    <span v-if="activeLightboxCapture.prompt" class="font-mono text-neutral-500">
                                        {{ activeLightboxCapture.prompt.length }} karakter
                                    </span>
                                </p>
                            </div>

                            <!-- Footer Actions: Download, Visibility, Delete -->
                            <div class="space-y-2 pt-2 border-t border-white/10">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="downloadCaptureFile(activeLightboxCapture)"
                                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white text-black font-semibold text-xs hover:bg-neutral-200 transition-all shadow-md active:scale-[0.99]"
                                        title="Unduh PNG Resolusi Penuh"
                                    >
                                        <Download class="h-4 w-4" />
                                        <span>Unduh PNG Resolusi Penuh</span>
                                    </button>

                                    <button
                                        v-if="isOwner"
                                        type="button"
                                        @click="deleteCapture(activeLightboxCapture)"
                                        class="p-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 transition-all"
                                        title="Hapus gambar ini"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Visibility Toggle for Owner -->
                                <div v-if="isOwner" class="flex items-center justify-between px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                                    <span class="text-[11px] text-neutral-300">Visibilitas Gambar ke Klien</span>
                                    <button
                                        type="button"
                                        @click="toggleCaptureVisibility(activeLightboxCapture)"
                                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-medium transition-all"
                                        :class="activeLightboxCapture.is_visible_to_clients
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                                            : 'bg-white/10 text-neutral-400 border border-white/10'"
                                    >
                                        <Eye v-if="activeLightboxCapture.is_visible_to_clients" class="h-3 w-3" />
                                        <EyeOff v-else class="h-3 w-3" />
                                        <span>{{ activeLightboxCapture.is_visible_to_clients ? 'Bisa Dilihat Klien' : 'Hanya Pemilik' }}</span>
                                    </button>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </Transition>

            <!-- ── CHAT PANEL (realtime) ── -->
            <ChatPanel
                ref="chatPanelRef"
                :project-id="project.id"
                :visible="isChatOpen"
                @close="isChatOpen = false"
            />

        </div>
    </div>
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Bookmark,
    BookmarkPlus,
    Camera,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clapperboard,
    ClipboardList,
    Film,
    Compass,
    Copy,
    Cpu,
    Download,
    Eye,
    EyeOff,
    ExternalLink,
    Focus,
    Globe,
    GripVertical,
    Hand,
    Image,
    Info,
    Layers,
    Loader2,
    Lock,
    MapPin,
    Maximize2,
    MessageCircle,
    MessageSquare,
    Minimize2,
    Moon,
    Pencil,
    PinOff,
    Play,
    Plus,
    RefreshCw,
    RotateCw,
    Scissors,
    Send,
    Sliders,
    Sparkles,
    Sun,
    Sunrise,
    Sunset,
    Trash2,
    Unlock,
    X,
} from '@lucide/vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { useRaycast } from '@/composables/useRaycast';
import { useSunSimulation } from '@/composables/useSunSimulation';
import { usePostProcessing } from '@/composables/usePostProcessing';
import { usePathTracer } from '@/composables/usePathTracer';
import RevisionBadge from '@/components/RevisionBadge.vue';
import ChatPanel from '@/components/ChatPanel.vue';
import {
    index as commentIndex,
    store as storeComment,
} from '@/routes/projects/comments';

interface Comment {
    id: string;
    project_id?: string;
    user_id?: string;
    content: string;
    position_x: number;
    position_y: number;
    position_z: number;
    status?: string;
    user?: {
        id?: string;
        name: string;
    };
    created_at?: string;
}

interface ProjectedComment {
    id: string;
    user_id?: string;
    content: string;
    status?: string;
    user?: { id?: string; name: string };
    screenX: number;
    screenY: number;
    anchorX: number;
    anchorY: number;
    cardX: number;
    cardY: number;
    isRightSide: boolean;
    isVisible: boolean;
}

interface ViewerPreferences {
    drawerOpen: boolean;
    showAnnotations: boolean;
    boxOffsets: Record<string, { dx: number; dy: number }>;
    clipping: {
        enabled: boolean;
        axis: 'x' | 'y' | 'z';
        value: number;
        flip: boolean;
    };
}

const page = usePage();
const project = computed(() => (page.props.project ?? {}) as {
    id: string;
    user_id?: string;
    title: string;
    file_path: string;
    current_revision_count: number;
    max_revisions_allowed: number;
    latitude?: number;
    longitude?: number;
    north_angle?: number;
    solar_date?: string;
    comments?: Comment[];
});

const viewerContainer = ref<HTMLDivElement | null>(null);
const comments = ref<Comment[]>([]);
const isLoading = ref(true);
const modelError = ref('');

// Three.js variables
let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let model: THREE.Object3D | null = null;
let animationFrame: number | null = null;
let resizeObserver: ResizeObserver | null = null;
const markerGroup = new THREE.Group();

// UI States
const interactionMode = ref<'rotate' | 'pan' | 'pin'>('rotate');
const showAnnotations = ref(true);
const isDrawerOpen = ref(true);
const activeCommentId = ref<string | null>(null);
const limitWarning = ref('');

// ── Clipping / Sectioning state ──────────────────────────
const clippingEnabled  = ref(false);
const clippingAxis     = ref<'x' | 'y' | 'z'>('y');
const clippingValue    = ref(3.5);   // posisi bidang potong (unit world)
const clippingFlip     = ref(false); // arah potongan dibalik
const isClipPanelOpen  = ref(false);

// Range slider per axis (bisa disesuaikan setelah model load)
const clippingRange = ref<{ min: number; max: number; step: number }>({
    min:  -10,
    max:   20,
    step:  0.1,
});

// ── Chat state ──────────────────────────────────────────
const isChatOpen = ref(false);
const chatPanelRef = ref<InstanceType<typeof ChatPanel> | null>(null);
const chatUnread = computed(() => chatPanelRef.value?.unreadCount ?? 0);

// ── Camera Preset state ──────────────────────────────────
interface CameraPreset {
    id: string;
    name: string;
    position: { x: number; y: number; z: number };
    target:   { x: number; y: number; z: number };
    sort_order: number;
    created_by: string;
}

const cameraPresets       = ref<CameraPreset[]>([]);
const isPresetPanelOpen   = ref(false);
const isSavingPreset      = ref(false);
const isDeletingPresetId  = ref<string | null>(null);
const newPresetName       = ref('');
const presetError         = ref('');
const presetInputRef      = ref<HTMLInputElement | null>(null);

// ── Sun & Lighting Simulation ────────────────────────────
const isSunPanelOpen      = ref(false);
const showSunGeoSettings  = ref(false);
const isSavingSunSettings = ref(false);
const sunSettingsMessage  = ref('');

const {
    currentHour,
    latitude,
    longitude,
    northAngle,
    solarDate,
    sunAltitude,
    sunAzimuth,
    isNight,
    formattedTime,
    activePreset,
    lampNodes,
    initSunLighting,
    scanLampObjects,
    updateSunPosition,
    setPreset,
    setHour,
    updateSettings: updateSunSettingsState,
} = useSunSimulation();

// ── Enhanced Post-Processing (GTAO, Bloom & HD Render) ──
const {
    initPostProcessing,
    renderScene: renderWithPostProcessing,
    setSize: setPostProcessingSize,
    renderHighQualitySnapshot,
    dispose: disposePostProcessing,
    isEnhancedRenderingActive,
    isAoEnabled,
    isBloomEnabled,
    aoIntensity,
    bloomStrength,
} = usePostProcessing();

// ── Tingkat 2: True Path-Tracing Pro (three-gpu-pathtracer) ──
const {
    isPathTracing,
    isCompilingBvh,
    bvhProgress,
    currentSample,
    totalSamples: ptTotalSamples,
    progressPercent: ptProgressPercent,
    pathTraceError,
    renderPathTracedImage,
    cancelPathTracing,
    disposePathTracer,
} = usePathTracer();

const renderEngine = ref<'standard' | 'pathtrace'>('standard');
const pathTraceTargetSamples = ref<number>(30);

async function startPathTraceRender() {
    captureError.value = '';
    if (!renderer || !scene || !camera) {
        captureError.value = 'Viewer 3D belum siap. Silakan tunggu hingga model 3D selesai dimuat.';
        return;
    }
    try {
        const result = await renderPathTracedImage({
            scene,
            camera,
            renderer,
            targetResolution: captureResolution.value,
            maxSamples: pathTraceTargetSamples.value,
            container: viewerContainer.value,
            onFrameUpdate: (dataUrl) => {
                capturePreviewDataUrl.value = dataUrl;
            },
        });
        if (result) {
            capturePreviewDataUrl.value = result;
        }
    } catch (err: unknown) {
        console.error('[PathTracer] Error:', err);
        captureError.value = err instanceof Error ? err.message : 'Gagal menjalankan render Path-Tracing.';
    }
}

function closeCaptureModal() {
    if (isPathTracing.value) {
        cancelPathTracing();
    }
    isCaptureModalOpen.value = false;
}

function onHourSliderChange(e: Event) {
    const val = parseFloat((e.target as HTMLInputElement).value);
    setHour(val);
}

async function saveSunSettings() {
    isSavingSunSettings.value = true;
    sunSettingsMessage.value = '';
    try {
        const res = await fetch(`/projects/${project.value.id}/sun-settings`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                latitude: latitude.value,
                longitude: longitude.value,
                north_angle: northAngle.value,
                solar_date: solarDate.value,
            }),
        });
        const data = await res.json();
        if (res.ok) {
            sunSettingsMessage.value = 'Pengaturan berhasil disimpan!';
            setTimeout(() => { sunSettingsMessage.value = ''; }, 3000);
        } else {
            sunSettingsMessage.value = data.message || 'Gagal menyimpan pengaturan.';
        }
    } catch {
        sunSettingsMessage.value = 'Koneksi error.';
    } finally {
        isSavingSunSettings.value = false;
    }
}

// ── Captures & Gallery ───────────────────────────────────
interface ProjectCaptureItem {
    id: string;
    code?: string | null;
    project_id: string;
    drawing_sheet_id?: string | null;
    room_element_id?: string | null;
    user_id: string;
    title: string | null;
    room_name: string;
    captured_hour: string;
    file_path: string;
    ai_rendered_path?: string | null;
    ai_rendered_url?: string | null;
    file_size_bytes: number;
    width: number;
    height: number;
    is_visible_to_clients: boolean;
    prompt?: string | null;
    url: string;
    created_at: string;
}

const captures = ref<ProjectCaptureItem[]>([]);
const quotaInfo = ref<{ max_captures: number | null; current_count: number; can_capture: boolean } | null>(null);
const isCaptureModalOpen = ref(false);
const isCapturingSnapshot = ref(false);
const isShutterFlashing = ref(false);
const isCapturingFrameB = ref(false);
const isGalleryOpen = ref(false);
const activeLightboxCapture = ref<ProjectCaptureItem | null>(null);

const selectedPresetForCapture = ref<string>('current');
const captureCode = ref('');
const captureRoomName = ref('Sudut Bebas');
const captureHour = ref('12:00');
const captureTitle = ref('');
const captureResolution = ref<'viewport' | '1080p' | '4k'>('1080p');
const captureVisibleToClients = ref(false);
const capturePreviewDataUrl = ref('');
const isSavingCapture = ref(false);
const captureError = ref('');
const galleryCodeGroupFilter = ref('all');

// ── AI Prompt States, Scene 3D Analysis & Auto-Save ──
const capturePrompt = ref('');
const copiedPromptModal = ref(false);
const promptSaveStatus = ref<Record<string, 'idle' | 'saving' | 'saved' | 'error'>>({});
const copiedCaptureId = ref<string | null>(null);

// ── Video Prompt & Multi-Camera Motion States ──
const capturePromptMode = ref<'image' | 'video'>('image');
const captureVideoMotions = ref<string[]>(['push_in']);
const captureVideoDuration = ref<'4s' | '5s' | '10s'>('5s');
const captureVideoPace = ref<'slow' | 'normal' | 'dynamic'>('slow');
const captureVideoMood = ref<'scene' | 'golden_hour' | 'overcast' | 'rainy' | 'night_luxury' | 'misty_morning'>('scene');
const captureVideoLens = ref<'anamorphic_35mm' | 'wide_16mm' | 'macro_50mm'>('anamorphic_35mm');
const captureVideoStrictGeometry = ref(true);
const captureVideoTarget = ref<'runway' | 'kling' | 'luma' | 'universal'>('runway');
const defaultVideoNegativePrompt = ref('deformed architecture, morphing walls, bending columns, warped windows, flickering geometry, sudden camera cuts, jerky movement, erratic zoom, floating artifacts, distorted proportions, low framerate, jittery shadows');
const copiedVideoNegativeModal = ref(false);

const isTwoPointActive = computed(() => capturePromptMode.value === 'video' && captureVideoMotions.value.includes('two_point'));

const videoMotionDict: Record<string, string> = {
    two_point: '2 Titik',
    push_in: 'Push-In',
    pull_out: 'Pull-Out',
    orbit_left: 'Orbit Kiri',
    orbit_right: 'Orbit Kanan',
    pan_left: 'Pan Kiri',
    pan_right: 'Pan Kanan',
    crane_up: 'Crane Naik',
    crane_down: 'Crane Turun',
    fpv_drone: 'FPV Drone',
    spiral_ascend: 'Spiral Naik',
    tilt_up: 'Tilt Up',
    tilt_down: 'Tilt Down',
    timelapse: 'Timelapse',
    static_subtle: 'Tripod Parallax',
};

function toggleVideoMotion(key: string) {
    const idx = captureVideoMotions.value.indexOf(key);
    if (idx > -1) {
        if (captureVideoMotions.value.length > 1) {
            captureVideoMotions.value.splice(idx, 1);
        }
    } else {
        captureVideoMotions.value.push(key);
    }
    syncPromptWithCurrentState();
}

function getSelectedMotionsLabel(): string {
    if (!captureVideoMotions.value.length) return 'Belum dipilih';
    return captureVideoMotions.value.map((k: string) => videoMotionDict[k] || k).join(' + ');
}

function copyVideoNegativePrompt() {
    if (!defaultVideoNegativePrompt.value) return;
    navigator.clipboard.writeText(defaultVideoNegativePrompt.value);
    copiedVideoNegativeModal.value = true;
    setTimeout(() => {
        copiedVideoNegativeModal.value = false;
    }, 2000);
}

// Two-Point Transition Frames (Frame Awal & Frame Akhir)
const captureActiveFrameTab = ref<'frame_a' | 'frame_b'>('frame_a');
const captureFrameBDataUrl = ref<string | null>(null);
const captureFrameBRoomName = ref<string>('');
const captureFrameBHour = ref<string>('');
const isSettingFrameB = ref(false);

interface Analyzed3DView {
    labelId: string;
    subjectEn: string;
    materialsEn: string[];
    shotTypeEn: string;
    perspectiveEn: string;
    distance: number;
}

const ARCHITECTURAL_DICTIONARY = [
    {
        keywords: ['sofa', 'couch', 'lounge', 'seating', 'armchair', 'ottoman', 'daybed', 'kursi_santai'],
        labelId: 'Sofa & Ruang Santai',
        subjectEn: 'contemporary designer lounge sofa and seating area',
        defaultMaterials: ['tactile woven fabric upholstery', 'warm ambient finishes'],
    },
    {
        keywords: ['kitchen', 'dapur', 'island', 'countertop', 'sink', 'cooktop', 'kitchenset', 'stove', 'kompor', 'pantry'],
        labelId: 'Dapur & Kitchen Island',
        subjectEn: 'minimalist modern kitchen island and bespoke architectural cabinetry',
        defaultMaterials: ['monolithic stone countertop', 'matte cabinet surfaces'],
    },
    {
        keywords: ['table', 'meja', 'dining', 'desk', 'meja_makan', 'meja_kerja'],
        labelId: 'Meja & Area Makan',
        subjectEn: 'sculptural dining table and curated interior setting',
        defaultMaterials: ['solid hardwood surface', 'architectural metal base'],
    },
    {
        keywords: ['chair', 'kursi', 'stool', 'barstool'],
        labelId: 'Kursi & Tempat Duduk',
        subjectEn: 'modern architectural chairs with refined silhouettes',
        defaultMaterials: ['ergonomic molded frames', 'matte finishes'],
    },
    {
        keywords: ['bed', 'kasur', 'ranjang', 'bedroom', 'headboard', 'matras', 'kamar_tidur'],
        labelId: 'Tempat Tidur & Kamar Tidur',
        subjectEn: 'serene master bedroom suite with bespoke upholstered bed and headboard',
        defaultMaterials: ['crisp linen bedding', 'natural wood accents'],
    },
    {
        keywords: ['window', 'jendela', 'glass', 'kaca', 'glazing', 'curtainwall', 'vitrage', 'fenster'],
        labelId: 'Bukaan Kaca & Jendela',
        subjectEn: 'floor-to-ceiling architectural glass glazing and panoramic window frames',
        defaultMaterials: ['reflective clear float glass', 'powder-coated aluminum frames'],
    },
    {
        keywords: ['door', 'pintu', 'portal', 'entrance', 'entry', 'kusen'],
        labelId: 'Pintu & Akses Masuk',
        subjectEn: 'grand architectural entrance door and entryway portal',
        defaultMaterials: ['solid timber door panel', 'refined metal hardware'],
    },
    {
        keywords: ['stair', 'tangga', 'step', 'railing', 'balustrade', 'handrail', 'tread', 'riser'],
        labelId: 'Tangga & Railing Minimalis',
        subjectEn: 'cantilevered floating staircase with minimalist architectural railing',
        defaultMaterials: ['structural steel', 'natural timber steps'],
    },
    {
        keywords: ['wall', 'dinding', 'partition', 'partisi', 'panel', 'cladding', 'fasad', 'facade', 'bata', 'brick'],
        labelId: 'Dinding & Panel Aksen',
        subjectEn: 'architectural feature wall with textured paneling and crisp geometric planes',
        defaultMaterials: ['textured architectural surface', 'recessed shadow lines'],
    },
    {
        keywords: ['floor', 'lantai', 'tile', 'tiles', 'ubin', 'parket', 'parquet', 'carpet', 'karpet', 'paving', 'asphalt', 'aspal'],
        labelId: 'Lantai & Permukaan Pijakan',
        subjectEn: 'seamless expansive architectural flooring and floor finishes',
        defaultMaterials: ['large-format tiles', 'smooth polished surface'],
    },
    {
        keywords: ['roof', 'atap', 'ceiling', 'plafon', 'canopy', 'kanopi', 'soffit', 'genteng', 'truss'],
        labelId: 'Plafon & Struktur Atap',
        subjectEn: 'geometric architectural ceiling design with recessed indirect lighting cove',
        defaultMaterials: ['smooth matte ceiling', 'integrated architectural reveals'],
    },
    {
        keywords: ['pool', 'kolam', 'swimming', 'water', 'air', 'jacuzzi'],
        labelId: 'Kolam Renang & Fitur Air',
        subjectEn: 'crisp modern swimming pool with tranquil reflective water surfaces',
        defaultMaterials: ['shimmering turquoise water', 'natural stone coping edges'],
    },
    {
        keywords: ['garden', 'taman', 'plant', 'tanaman', 'tree', 'pohon', 'landscape', 'grass', 'rumput', 'foliage', 'vegetation'],
        labelId: 'Taman & Lanskap Hijau',
        subjectEn: 'lush landscaped courtyard garden with sculpted tropical greenery',
        defaultMaterials: ['vibrant architectural foliage', 'natural stone pathways'],
    },
    {
        keywords: ['lamp', 'lampu', 'light', 'lighting', 'chandelier', 'pendant', 'sconce', 'spotlight'],
        labelId: 'Pencahayaan & Fitting Lampu',
        subjectEn: 'sculptural architectural lighting fixture and warm ambient luminescence',
        defaultMaterials: ['brushed brass housing', 'frosted diffuse glass'],
    },
    {
        keywords: ['cabinet', 'lemari', 'wardrobe', 'shelf', 'shelving', 'rak', 'credenza', 'cupboard', 'storage'],
        labelId: 'Lemari & Rak Built-in',
        subjectEn: 'integrated custom joinery and minimalist architectural shelving',
        defaultMaterials: ['natural wood veneer', 'seamless flush doors'],
    },
    {
        keywords: ['bath', 'toilet', 'shower', 'bathtub', 'wastafel', 'kamar_mandi', 'kloset', 'vanity'],
        labelId: 'Kamar Mandi & Sanitair',
        subjectEn: 'spa-like luxury bathroom suite with monolithic stone finishes',
        defaultMaterials: ['honed marble surfaces', 'matte black fixtures'],
    },
    {
        keywords: ['balcony', 'balkon', 'terrace', 'teras', 'patio', 'deck', 'porch', 'veranda'],
        labelId: 'Balkon & Teras Terbuka',
        subjectEn: 'open-air architectural terrace and balcony deck framing outdoor views',
        defaultMaterials: ['weather-resistant timber decking', 'frameless glass balustrade'],
    },
    {
        keywords: ['column', 'tiang', 'pilar', 'beam', 'balok', 'kolom', 'structure', 'pillar'],
        labelId: 'Kolom & Elemen Struktural',
        subjectEn: 'monolithic architectural columns and exposed structural concrete geometry',
        defaultMaterials: ['smooth architectural concrete', 'clean chamfered edges'],
    },
];

function analyzeSceneFromCamera(): Analyzed3DView | null {
    if (!camera || !scene) return null;

    const raycaster = new THREE.Raycaster();
    const sampleCoords = [
        new THREE.Vector2(0, 0),         // center
        new THREE.Vector2(-0.12, 0),     // slight left
        new THREE.Vector2(0.12, 0),      // slight right
        new THREE.Vector2(0, -0.12),     // slight bottom
        new THREE.Vector2(0, 0.12),      // slight top
    ];

    const targetObjects = model ? [model] : scene.children;
    let closestHit: THREE.Intersection | null = null;

    for (const coord of sampleCoords) {
        raycaster.setFromCamera(coord, camera);
        const intersects = raycaster.intersectObjects(targetObjects, true);
        const valid = intersects.find((i) => {
            let cur: THREE.Object3D | null = i.object;
            while (cur) {
                if (cur.userData?.isMarker) return false;
                cur = cur.parent;
            }
            return (i.object as THREE.Mesh).isMesh !== false;
        });

        if (valid) {
            closestHit = valid;
            break;
        }
    }

    if (!closestHit) {
        const dir = new THREE.Vector3();
        camera.getWorldDirection(dir);
        const isLookingDown = dir.y < -0.3;
        const isLookingUp = dir.y > 0.3;

        return {
            labelId: isLookingDown ? 'Tinjauan Atas (Elevated)' : (isLookingUp ? 'Sudut Rendah Heroic' : 'Perspektif Bebas'),
            subjectEn: isLookingDown
                ? 'overall architectural massing and spatial layout from an elevated vantage point'
                : 'contemporary architectural structure against the open sky',
            materialsEn: ['mixed architectural finishes', 'crisp exterior facade'],
            shotTypeEn: isLookingDown ? 'elevated isometric architectural overview, 35mm lens' : 'wide architectural perspective, 24mm lens',
            perspectiveEn: isLookingDown ? 'high-angle bird’s eye perspective' : 'atmospheric architectural framing',
            distance: 12,
        };
    }

    const hitObject = closestHit.object;
    const hitPoint = closestHit.point;
    const distance = camera.position.distanceTo(hitPoint);

    // Collect names from object and up hierarchy (up to 5 levels)
    const hierarchyNames: string[] = [];
    let curObj: THREE.Object3D | null = hitObject;
    while (curObj && curObj !== scene && hierarchyNames.length < 5) {
        if (curObj.name && curObj.name.trim().length > 0) {
            hierarchyNames.push(curObj.name);
        }
        curObj = curObj.parent;
    }

    // Material analysis
    const detectedMaterials: string[] = [];
    const matNames: string[] = [];
    const rawMaterial = (hitObject as THREE.Mesh).material;
    const materialsList = Array.isArray(rawMaterial) ? rawMaterial : (rawMaterial ? [rawMaterial] : []);

    for (const m of materialsList) {
        if (m.name) matNames.push(m.name);

        const mNameLower = (m.name || '').toLowerCase();
        if (mNameLower.includes('wood') || mNameLower.includes('kayu') || mNameLower.includes('timber') || mNameLower.includes('oak') || mNameLower.includes('walnut') || mNameLower.includes('plywood')) {
            detectedMaterials.push('warm textured natural timber wood grain');
        } else if (mNameLower.includes('concrete') || mNameLower.includes('beton') || mNameLower.includes('semen') || mNameLower.includes('cement')) {
            detectedMaterials.push('smooth cast-in-place architectural concrete with subtle pores');
        } else if (mNameLower.includes('marble') || mNameLower.includes('marmer') || mNameLower.includes('granite') || mNameLower.includes('travertine') || mNameLower.includes('stone') || mNameLower.includes('batu')) {
            detectedMaterials.push('luxurious polished marble and veined natural stone');
        } else if (mNameLower.includes('glass') || mNameLower.includes('kaca') || mNameLower.includes('glazing')) {
            detectedMaterials.push('reflective clear float architectural glass');
        } else if (mNameLower.includes('metal') || mNameLower.includes('aluminum') || mNameLower.includes('steel') || mNameLower.includes('baja') || mNameLower.includes('chrome') || mNameLower.includes('brass')) {
            detectedMaterials.push('precision matte dark metallic framing and trim');
        } else if (mNameLower.includes('brick') || mNameLower.includes('bata')) {
            detectedMaterials.push('tactile architectural brickwork with clean mortar joints');
        } else if (mNameLower.includes('tile') || mNameLower.includes('keramik') || mNameLower.includes('porcelain')) {
            detectedMaterials.push('refined large-format ceramic tile surfaces');
        } else if (mNameLower.includes('fabric') || mNameLower.includes('linen') || mNameLower.includes('leather') || mNameLower.includes('kain')) {
            detectedMaterials.push('tactile woven upholstery and soft leather finishes');
        } else if (mNameLower.includes('asphalt') || mNameLower.includes('aspal')) {
            detectedMaterials.push('clean dark architectural pavement');
        }

        const std = m as THREE.MeshStandardMaterial;
        if (std.metalness && std.metalness > 0.45 && !detectedMaterials.some(d => d.includes('metallic'))) {
            detectedMaterials.push('brushed metallic accents');
        }
        if (std.roughness !== undefined && std.roughness < 0.15 && !detectedMaterials.some(d => d.includes('polished') || d.includes('reflective'))) {
            detectedMaterials.push('high-gloss reflective finish');
        }
        if (m.transparent || m.opacity < 0.95) {
            if (!detectedMaterials.some(d => d.includes('glass'))) {
                detectedMaterials.push('translucent architectural glazing');
            }
        }
    }

    // Match architectural category
    const searchString = [...hierarchyNames, ...matNames].join(' ').toLowerCase();
    let matchedCategory = ARCHITECTURAL_DICTIONARY.find(cat =>
        cat.keywords.some(kw => searchString.includes(kw))
    );

    // If no keyword match, inspect surface orientation
    let labelId = matchedCategory?.labelId;
    let subjectEn = matchedCategory?.subjectEn;

    if (!matchedCategory) {
        const rawNormal = closestHit.face?.normal ?? new THREE.Vector3(0, 1, 0);
        const normalMatrix = new THREE.Matrix3().getNormalMatrix(hitObject.matrixWorld);
        const worldNormal = rawNormal.clone().applyMatrix3(normalMatrix).normalize();

        if (worldNormal.y > 0.65) {
            labelId = 'Lantai & Permukaan Datar';
            subjectEn = 'expansive architectural floor plane and horizontal surface';
        } else if (worldNormal.y < -0.65) {
            labelId = 'Plafon & Bidang Atas';
            subjectEn = 'architectural ceiling soffit and overhead planes';
        } else {
            labelId = 'Dinding & Elemen Fasad';
            subjectEn = 'geometric architectural wall plane and volumetric facade';
        }
    }

    if (detectedMaterials.length === 0) {
        if (matchedCategory?.defaultMaterials) {
            detectedMaterials.push(...matchedCategory.defaultMaterials);
        } else {
            detectedMaterials.push('clean architectural materials with natural textures');
        }
    }

    // Shot type & Perspective based on distance
    let shotTypeEn = 'eye-level medium perspective shot, 35mm lens, balanced one-point framing';
    let perspectiveEn = 'human-scale eye-level composition, highlighting architectural proportion';

    if (distance < 2.5) {
        shotTypeEn = 'tactile close-up macro detail shot, 50mm f/1.8 lens with shallow depth of field';
        perspectiveEn = 'intimate close-up perspective capturing delicate material textures and craftsmanship';
    } else if (distance >= 2.5 && distance < 7.0) {
        shotTypeEn = 'eye-level medium architectural perspective, 35mm lens, balanced one-point perspective';
        perspectiveEn = 'human-scale eye-level composition, highlighting architectural proportion and interior flow';
    } else if (distance >= 7.0 && distance < 16.0) {
        shotTypeEn = 'wide-angle spatial perspective shot, 24mm architectural tilt-shift lens, perfectly vertical lines';
        perspectiveEn = 'expansive wide-angle composition showcasing full volumetric space and architectural rhythm';
    } else {
        shotTypeEn = 'sweeping panoramic architectural vista, 18mm lens, majestic exterior composition';
        perspectiveEn = 'commanding panoramic perspective revealing the full building massing and landscape context';
    }

    // Camera pitch angle
    const dir = new THREE.Vector3();
    camera.getWorldDirection(dir);
    if (dir.y < -0.35) {
        perspectiveEn += ', elevated high-angle viewpoint looking downward with isometric clarity';
    } else if (dir.y > 0.35) {
        perspectiveEn += ', dramatic low-angle hero perspective looking upward emphasizing towering architectural scale';
    } else {
        perspectiveEn += ', straight-on rectilinear architectural framing with strict vertical alignment';
    }

    return {
        labelId: labelId!,
        subjectEn: subjectEn!,
        materialsEn: Array.from(new Set(detectedMaterials)),
        shotTypeEn,
        perspectiveEn,
        distance,
    };
}

function generatePromptForCurrentView(providedAnalysis?: Analyzed3DView | null): string {
    const analysis = providedAnalysis !== undefined ? providedAnalysis : analyzeSceneFromCamera();

    if (capturePromptMode.value === 'video') {
        const motionPrompts: Record<string, string> = {
            two_point: 'Smooth cinematic camera transition traveling steadily from starting camera viewpoint to ending viewpoint',
            push_in: 'Slow cinematic push-in walkthrough moving smoothly forward into the interior space',
            pull_out: 'Slow cinematic pull-out revealing the full architectural facade and contextual exterior',
            orbit_left: 'Smooth continuous 360-degree circular drone orbit flying clockwise around the architectural structure',
            orbit_right: 'Smooth continuous 360-degree circular drone orbit flying counter-clockwise around the architectural structure',
            pan_left: 'Smooth lateral cinematic panning shot to the left revealing spatial depth and interior transitions',
            pan_right: 'Smooth lateral cinematic panning shot to the right showcasing the expansive room volume',
            crane_up: 'Slow vertical crane pedestal ascending upwards towards the architectural ceiling structure',
            crane_down: 'Smooth vertical crane pedestal descending gracefully towards ground floor living space',
            fpv_drone: 'Dynamic continuous FPV drone flight gliding smoothly through architectural openings and hallways',
            spiral_ascend: 'Smooth cinematic spiral ascending shot rotating upwards around the structural building column',
            tilt_up: 'Slow deliberate camera tilt up from floor finish revealing double-height ceiling and skylights',
            tilt_down: 'Slow cinematic camera tilt down from clerestory ceiling to curated interior floor arrangement',
            timelapse: 'Static fixed camera angle with dynamic architectural sunlight and shadow timelapse progression',
            static_subtle: 'Static tripod framing with subtle natural camera breathing and micro-parallax',
        };

        const selectedMotions = captureVideoMotions.value.length > 0 ? captureVideoMotions.value : ['push_in'];
        const motionDescriptions = selectedMotions.map(k => motionPrompts[k] || k);
        const compoundMotion = motionDescriptions.join(', combined with ');

        const durationText = captureVideoDuration.value === '4s'
            ? '4-second quick seamless loop'
            : captureVideoDuration.value === '10s'
            ? '10-second extended showcase'
            : '5-second standard generation';

        const paceText = captureVideoPace.value === 'slow'
            ? 'slow majestic 0.5x camera pace'
            : captureVideoPace.value === 'dynamic'
            ? 'dynamic fluid pacing'
            : 'natural steady walking pace';

        const moodMap: Record<string, string> = {
            scene: 'authentic lighting matching reference 3D scene',
            golden_hour: 'golden hour sunset with warm low-angle sunbeams and soft elongated shadows',
            overcast: 'soft diffused overcast daylight with subtle natural ambient shadows',
            rainy: 'moody rainy atmosphere with reflective wet ground and subtle raindrops',
            night_luxury: 'luxurious nighttime architectural showcase with warm interior spotlights and subtle linear LED lighting',
            misty_morning: 'misty morning sunrise with ethereal sunbeams piercing through the space',
        };
        const moodText = moodMap[captureVideoMood.value] || moodMap.scene;

        const lensMap: Record<string, string> = {
            anamorphic_35mm: '35mm anamorphic cinema lens, subtle lens flare, natural depth of field, 24fps cinematic motion blur',
            wide_16mm: '16mm architectural wide-angle lens, perfectly straight vertical lines, zero barrel distortion',
            macro_50mm: '50mm prime lens, beautiful shallow depth of field, tactile architectural material focus',
        };
        const lensText = lensMap[captureVideoLens.value] || lensMap.anamorphic_35mm;

        const strictGeometryText = captureVideoStrictGeometry.value
            ? 'Maintain 100% rigid architectural geometry, zero morphing walls, zero flickering surfaces, perfectly straight architectural vertical lines.'
            : 'Photorealistic architectural videography.';

        const targetGuidance = captureVideoTarget.value === 'runway'
            ? 'Runway Gen-3 Alpha camera direction: zero structural morphing, realistic interior materials, rigid spatial structure.'
            : captureVideoTarget.value === 'kling'
            ? 'Kling AI video generation: preserve architectural geometry and lighting consistency across all frames.'
            : captureVideoTarget.value === 'luma'
            ? 'Luma Dream Machine camera move: photorealistic architectural videography, realistic scale.'
            : 'Cinematic architectural videography, steady camera movement, photorealistic 8k render.';

        const subjectName = analysis?.subjectEn || captureRoomName.value || 'architectural space';
        return `Cinematic 4K architectural video (${durationText}, ${paceText}). ${compoundMotion}. Subject: ${subjectName}. Environment: ${moodText}. Optics: ${lensText}. ${strictGeometryText} ${targetGuidance}`;
    }

    const mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image.';

    const hourStr = captureHour.value || '12:00';
    const hourNum = parseFloat(hourStr.replace(':', '.'));

    let lightingContext = 'crisp bright mid-day sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    if (hourNum >= 5 && hourNum < 8.5) {
        lightingContext = 'directional morning sunlight from east casting elongated architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    } else if (hourNum >= 8.5 && hourNum < 15) {
        lightingContext = 'crisp bright mid-day overhead sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    } else if (hourNum >= 15 && hourNum < 18.5) {
        lightingContext = 'directional afternoon sunlight from west casting defined architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    } else if (hourNum >= 18.5 && hourNum < 20.5) {
        lightingContext = 'twilight ambient illumination with interior warm spotlights and linear LED accents, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    } else {
        lightingContext = 'nighttime architectural lighting with interior ceiling spotlights and linear LED accents, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    }

    const renderStyle = renderEngine.value === 'pathtrace'
        ? 'ultra-realistic path-traced raytracing, physically accurate global illumination, sub-surface light bounce, 8k resolution, architectural showcase'
        : 'photorealistic architectural photography, ambient occlusion crevices, raytraced reflections, Hasselblad H6D-100c, f/8, 8k resolution, award-winning architectural showcase';

    const projectContext = project.value.title ? `for ${project.value.title}` : '';

    const isClip = clippingEnabled.value;
    const clipAxis = clippingAxis.value || 'y';
    const clipVal = clippingValue.value;
    let clipContext = 'unclipped solid complete architectural geometry';
    if (isClip) {
        clipContext = clipAxis === 'y'
            ? `architectural vertical section cut through Y-axis (${clipVal.toFixed(1)}m) exposing floor levels and room layouts`
            : (clipAxis === 'x'
                ? `architectural longitudinal cross-section cut along X-axis (${clipVal.toFixed(1)}m) exposing internal heights`
                : `architectural transverse section cut along Z-axis (${clipVal.toFixed(1)}m)`);
    }

    if (analysis) {
        const subjectPhrase = analysis.subjectEn;
        const materialsPhrase = analysis.materialsEn.length > 0
            ? `Preserve visible material palette: ${analysis.materialsEn.slice(0, 4).join(', ')}.`
            : 'Preserve authentic visible surface colors and textures without altering materials.';
        const roomContext = captureRoomName.value && captureRoomName.value !== 'Sudut Bebas' && captureRoomName.value !== analysis.labelId
            ? `in ${captureRoomName.value}`
            : '';

        return `${mandatoryPrefix} Viewport capture of ${subjectPhrase} ${roomContext} ${projectContext}. Section state: ${clipContext}. Camera framing: ${analysis.perspectiveEn}, ${analysis.shotTypeEn}. Lighting: ${lightingContext}. ${materialsPhrase} ${renderStyle}, highly detailed architecture, cinematic composition.`;
    }

    const room = captureRoomName.value || 'Ruangan Arsitektur';
    return `${mandatoryPrefix} Viewport capture of ${room} ${projectContext}. Section state: ${clipContext}. Lighting: ${lightingContext}. Preserve authentic visible surface colors and textures. ${renderStyle}, highly detailed interior architecture, cinematic composition, award-winning architectural showcase.`;
}

type TargetModelSyntax = 'universal' | 'midjourney' | 'sdxl' | 'flux';
const captureSelectedSyntax = ref<TargetModelSyntax>('universal');
const lightboxSelectedSyntax = ref<TargetModelSyntax>('universal');

const defaultNegativePrompt = ref(
    'deformed architecture, crooked lines, altered geometry, wrong colors, color cast, extra walls, distorted perspective, oversaturated, blurry, low resolution, bad render artifacts, cartoon 3d look, watermark, signature'
);
const copiedNegativeModal = ref(false);

const formattedCapturePrompt = computed(() => {
    const raw = capturePrompt.value || '';
    if (capturePromptMode.value === 'video') {
        return raw;
    }
    if (captureSelectedSyntax.value === 'midjourney') {
        return `${raw} --iw 2.0 --ar 16:9 --style raw --v 6.1`;
    }
    if (captureSelectedSyntax.value === 'flux') {
        return `Photorealistic architectural capture. ${raw}. Shot on Hasselblad H6D-100c, 35mm lens, f/8, 8k resolution, neutral white balance.`;
    }
    return raw;
});

function copyPromptText(text?: string | null) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedPromptModal.value = true;
    setTimeout(() => {
        copiedPromptModal.value = false;
    }, 2000);
}

function copySelectedCapturePrompt() {
    copyPromptText(formattedCapturePrompt.value);
}

function copyNegativePrompt() {
    navigator.clipboard.writeText(defaultNegativePrompt.value);
    copiedNegativeModal.value = true;
    setTimeout(() => {
        copiedNegativeModal.value = false;
    }, 2000);
}

function getLightboxFormattedPrompt(capture: ProjectCaptureItem, syntax: TargetModelSyntax): string {
    const raw = capture.prompt || '';
    if (syntax === 'midjourney') {
        return `${raw} --iw 2.0 --ar 16:9 --style raw --v 6.1`;
    }
    if (syntax === 'flux') {
        return `Photorealistic architectural capture. ${raw}. Shot on Hasselblad H6D-100c, 35mm lens, f/8, 8k resolution, neutral white balance.`;
    }
    return raw;
}

function copyLightboxSelectedPrompt(capture: ProjectCaptureItem) {
    const text = getLightboxFormattedPrompt(capture, lightboxSelectedSyntax.value);
    navigator.clipboard.writeText(text);
    copiedCaptureId.value = capture.id;
    setTimeout(() => {
        if (copiedCaptureId.value === capture.id) {
            copiedCaptureId.value = null;
        }
    }, 2000);
}

function downloadDepthMap() {
    if (!renderer || !scene || !camera) return;

    const origBackground = scene.background;
    const origOverride = scene.overrideMaterial;
    const origNear = camera.near;
    const origFar = camera.far;

    try {
        const dist = controls ? camera.position.distanceTo(controls.target) : 10;
        camera.near = Math.max(0.1, dist * 0.1);
        camera.far = Math.max(5, dist * 2.5);
        camera.updateProjectionMatrix();

        scene.overrideMaterial = new THREE.MeshDepthMaterial();
        scene.background = new THREE.Color(0x000000);

        renderer.render(scene, camera);
        const depthDataUrl = renderer.domElement.toDataURL('image/png');

        if (depthDataUrl) {
            const a = document.createElement('a');
            a.href = depthDataUrl;
            const roomSlug = (captureRoomName.value || 'snapshot').replace(/\s+/g, '-').toLowerCase();
            const timeSlug = (captureHour.value || '12-00').replace(':', '-');
            a.download = `controlnet-depth-${roomSlug}-${timeSlug}.png`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    } catch (err) {
        console.error('Failed to generate depth map:', err);
    } finally {
        camera.near = origNear;
        camera.far = origFar;
        camera.updateProjectionMatrix();
        scene.overrideMaterial = origOverride;
        scene.background = origBackground;
        renderer.render(scene, camera);
    }
}

function copyCapturePrompt(capture: ProjectCaptureItem) {
    if (!capture.prompt) return;
    navigator.clipboard.writeText(capture.prompt);
    copiedCaptureId.value = capture.id;
    setTimeout(() => {
        if (copiedCaptureId.value === capture.id) {
            copiedCaptureId.value = null;
        }
    }, 2000);
}

async function autoSaveCapturePrompt(capture: ProjectCaptureItem) {
    if (!isOwner.value) return;
    const captureId = capture.id;
    promptSaveStatus.value[captureId] = 'saving';

    try {
        const res = await fetch(`/projects/${project.value.id}/captures/${captureId}/prompt`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                prompt: capture.prompt ?? '',
            }),
        });

        if (res.ok) {
            promptSaveStatus.value[captureId] = 'saved';
            setTimeout(() => {
                if (promptSaveStatus.value[captureId] === 'saved') {
                    promptSaveStatus.value[captureId] = 'idle';
                }
            }, 2500);
        } else {
            promptSaveStatus.value[captureId] = 'error';
        }
    } catch {
        promptSaveStatus.value[captureId] = 'error';
    }
}

const galleryRoomFilter = ref('all');
const galleryHourFilter = ref('all');

const uniqueRooms = computed(() => {
    return Array.from(new Set(captures.value.map(c => c.room_name).filter(Boolean)));
});

const uniqueHours = computed(() => {
    return Array.from(new Set(captures.value.map(c => c.captured_hour).filter(Boolean)));
});

const filteredCaptures = computed(() => {
    return captures.value.filter(c => {
        if (galleryCodeGroupFilter.value !== 'all') {
            const code = (c.code || '').toUpperCase();
            if (galleryCodeGroupFilter.value === 'R' && !code.startsWith('R-')) {
                return false;
            }
            if (galleryCodeGroupFilter.value === 'EXT' && !code.startsWith('EXT-')) {
                return false;
            }
            if (galleryCodeGroupFilter.value === 'CUSTOM' && (code.startsWith('R-') || code.startsWith('EXT-') || !code)) {
                return false;
            }
        }
        if (galleryRoomFilter.value !== 'all' && c.room_name !== galleryRoomFilter.value) {
            return false;
        }
        if (galleryHourFilter.value !== 'all' && c.captured_hour !== galleryHourFilter.value) {
            return false;
        }
        return true;
    });
});

async function loadCaptures() {
    try {
        const res = await fetch(`/projects/${project.value.id}/captures`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            const data = await res.json();
            captures.value = data.data ?? [];
            quotaInfo.value = data.quota ?? null;
        }
    } catch (err) {
        console.error('[Captures] load error:', err);
    }
}

interface SceneTechnicalMetadata {
    room_name: string;
    hour: string;
    lighting_desc: string;
    clipping_enabled: boolean;
    clipping_axis: 'x' | 'y' | 'z';
    clipping_value: number;
    clipping_desc: string;
    camera_perspective: string;
    camera_distance: number;
    shot_type: string;
    camera_fov: number;
    detected_object: string;
    detected_materials: string[];
    project_title: string;
    render_engine: 'standard' | 'pathtrace';
    detected_object_id: string;
    cut_keywords: string;
    angle_keywords: string;
    lighting_keywords: string;
}

const isGeneratingVisionPrompt = ref(false);
const promptSource = ref<'gemini-vision' | 'local-spatial-3d'>('local-spatial-3d');
const detectedSceneBadges = ref<{ label: string; type: 'mode' | 'time' | 'angle' | 'focus'; active?: boolean }[]>([]);

const cachedAiPrompts = ref<Record<string, string>>({});
const hasVisionAiGenerated = ref(false);

function getPromptCacheKey(): string {
    if (capturePromptMode.value === 'video') {
        const motionsKey = [...captureVideoMotions.value].sort().join('+');
        const hasEnd = captureFrameBDataUrl.value ? 'with_end' : 'no_end';
        return `video_${motionsKey}_${captureVideoDuration.value}_${captureVideoPace.value}_${captureVideoMood.value}_${captureVideoLens.value}_${captureVideoStrictGeometry.value ? 'strict' : 'free'}_${captureVideoTarget.value}_${hasEnd}`;
    }
    return `image_${captureSelectedSyntax.value}`;
}

function syncPromptWithCurrentState() {
    const key = getPromptCacheKey();
    if (cachedAiPrompts.value[key]) {
        capturePrompt.value = cachedAiPrompts.value[key];
        promptSource.value = 'gemini-vision';
        hasVisionAiGenerated.value = true;
    } else {
        capturePrompt.value = generatePromptForCurrentView();
        promptSource.value = 'local-spatial-3d';
        hasVisionAiGenerated.value = false;
    }
}

async function requestAiPrompt() {
    await triggerVisionAiPromptGeneration();
}

function extractSceneMetadata(): SceneTechnicalMetadata {
    const analysis = analyzeSceneFromCamera();

    // 1. Potongan / Clipping Status
    const isClip = clippingEnabled.value;
    const clipAxis = clippingAxis.value || 'y';
    const clipVal = clippingValue.value;
    let clippingDesc = 'Solid intact architectural massing, unclipped complete geometry';
    let clipLabel = '[Mode: Geometri Utuh]';

    if (isClip) {
        if (clipAxis === 'y') {
            clippingDesc = `Architectural vertical section cut through Y-axis (height: ${clipVal.toFixed(1)}m), exposed internal floor levels, room volumes and interior circulation layout`;
            clipLabel = `[Mode: Potong Sumbu Y (Denah/Lantai)]`;
        } else if (clipAxis === 'x') {
            clippingDesc = `Architectural longitudinal cross-section cut along X-axis (offset: ${clipVal.toFixed(1)}m), exposed interior profile revealing room heights and structural slabs`;
            clipLabel = `[Mode: Potong Sumbu X (Longitudinal)]`;
        } else {
            clippingDesc = `Architectural transverse section cut along Z-axis (offset: ${clipVal.toFixed(1)}m), exposed architectural cross-section showing structural depth`;
            clipLabel = `[Mode: Potong Sumbu Z (Transversal)]`;
        }
    }

    // 2. Pencahayaan / Jam
    const hourStr = captureHour.value || '12:00';
    const hourNum = parseFloat(hourStr.replace(':', '.'));
    let lightingDesc = 'crisp bright mid-day sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
    let timeLabel = `[Waktu: ${hourStr} - Siang Terang]`;

    if (hourNum >= 5 && hourNum < 8.5) {
        lightingDesc = 'directional morning sunlight from east casting elongated architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
        timeLabel = `[Waktu: ${hourStr} - Pagi Sunrise]`;
    } else if (hourNum >= 8.5 && hourNum < 15) {
        lightingDesc = 'crisp bright mid-day overhead sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
        timeLabel = `[Waktu: ${hourStr} - Siang Hari]`;
    } else if (hourNum >= 15 && hourNum < 18.5) {
        lightingDesc = 'directional afternoon sunlight from west casting defined architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
        timeLabel = `[Waktu: ${hourStr} - Golden Hour]`;
    } else if (hourNum >= 18.5 && hourNum < 20.5) {
        lightingDesc = 'twilight ambient illumination with interior warm spotlights and linear LED accents, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
        timeLabel = `[Waktu: ${hourStr} - Senja Blue Hour]`;
    } else {
        lightingDesc = 'nighttime architectural lighting with interior ceiling spotlights and linear LED accents, neutral white balance, accurate true-to-life surface colors without heavy color tinting';
        timeLabel = `[Waktu: ${hourStr} - Malam Hari]`;
    }

    // 3. Kamera & Sudut
    let cameraPerspective = analysis?.perspectiveEn || 'straight-on rectilinear architectural framing';
    let shotType = analysis?.shotTypeEn || 'eye-level medium perspective shot, 35mm lens';
    let cameraDistance = analysis?.distance ?? 5;
    let angleLabel = '[Sudut: Eye-Level 35mm]';

    if (camera) {
        const dir = new THREE.Vector3();
        camera.getWorldDirection(dir);
        if (dir.y < -0.35) {
            angleLabel = "[Sudut: Elevated Bird's Eye]";
        } else if (dir.y > 0.35) {
            angleLabel = '[Sudut: Low-Angle Heroic]';
        } else if (cameraDistance < 2.5) {
            angleLabel = '[Sudut: Close-Up Detail 50mm]';
        } else if (cameraDistance > 10) {
            angleLabel = '[Sudut: Wide-Angle 24mm]';
        }
    }

    // 4. Fokus Objek & Material
    const detectedObject = analysis?.subjectEn || 'architectural space';
    const detectedObjectId = analysis?.labelId || 'Objek Arsitektur';
    const detectedMaterials = analysis?.materialsEn || [];
    const focusLabel = `[Fokus: ${detectedObjectId}]`;

    // 5. Update UI badges
    detectedSceneBadges.value = [
        { label: clipLabel, type: 'mode', active: isClip },
        { label: timeLabel, type: 'time' },
        { label: angleLabel, type: 'angle' },
        { label: focusLabel, type: 'focus' },
    ];

    const angleKeywords = `${cameraPerspective}, ${shotType}`;
    const cutKeywords = clippingDesc;
    const lightingKeywords = lightingDesc;

    return {
        room_name: captureRoomName.value || detectedObjectId,
        hour: hourStr,
        lighting_desc: lightingDesc,
        clipping_enabled: isClip,
        clipping_axis: clipAxis,
        clipping_value: clipVal,
        clipping_desc: clippingDesc,
        camera_perspective: cameraPerspective,
        camera_distance: Number(cameraDistance.toFixed(1)),
        shot_type: shotType,
        camera_fov: camera?.fov ?? 45,
        detected_object: detectedObject,
        detected_materials: detectedMaterials,
        project_title: project.value.title || 'Architectural Project',
        render_engine: renderEngine.value,
        detected_object_id: detectedObjectId,
        cut_keywords: cutKeywords,
        angle_keywords: angleKeywords,
        lighting_keywords: lightingKeywords,
    };
}

async function triggerVisionAiPromptGeneration() {
    isGeneratingVisionPrompt.value = true;
    const meta = extractSceneMetadata();

    try {
        const payload: Record<string, any> = {
            image: capturePreviewDataUrl.value,
            prompt_type: capturePromptMode.value,
            camera_motions: captureVideoMotions.value,
            duration: captureVideoDuration.value,
            camera_pace: captureVideoPace.value,
            ambient_mood: captureVideoMood.value,
            lens_style: captureVideoLens.value,
            strict_geometry: captureVideoStrictGeometry.value,
            video_target: captureVideoTarget.value,
            scene_metadata: meta,
        };

        if (capturePromptMode.value === 'video' && captureFrameBDataUrl.value) {
            payload.end_image = captureFrameBDataUrl.value;
        }

        const res = await fetch(`/projects/${project.value.id}/captures/generate-prompt`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.prompt) {
                capturePrompt.value = data.prompt;
                promptSource.value = data.source ? 'gemini-vision' : 'local-spatial-3d';
                const key = getPromptCacheKey();
                cachedAiPrompts.value[key] = data.prompt;
                hasVisionAiGenerated.value = Boolean(data.source);
            }
        }
    } catch (err) {
        console.error('[Vision AI] Generate prompt error:', err);
    } finally {
        isGeneratingVisionPrompt.value = false;
    }
}

async function setFrameBFromCurrentView() {
    if (isCapturingFrameB.value) return;
    isCapturingFrameB.value = true;
    isShutterFlashing.value = true;
    setTimeout(() => {
        isShutterFlashing.value = false;
    }, 280);

    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 60));

    try {
        captureFrameBDataUrl.value = captureCanvasSnapshot(captureResolution.value);
        const meta = extractSceneMetadata();
        captureFrameBRoomName.value = meta.detected_object_id || 'Posisi Akhir';
        captureFrameBHour.value = formattedTime.value;
        captureActiveFrameTab.value = 'frame_b';
        syncPromptWithCurrentState();
    } finally {
        isCapturingFrameB.value = false;
    }
}

function startNavigatingForFrameB() {
    isSettingFrameB.value = true;
    isCaptureModalOpen.value = false;
}

async function finishNavigatingForFrameB() {
    if (isCapturingFrameB.value) return;
    isCapturingFrameB.value = true;
    isShutterFlashing.value = true;
    setTimeout(() => {
        isShutterFlashing.value = false;
    }, 280);

    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 60));

    try {
        captureFrameBDataUrl.value = captureCanvasSnapshot(captureResolution.value);
        const meta = extractSceneMetadata();
        captureFrameBRoomName.value = meta.detected_object_id || 'Posisi Akhir';
        captureFrameBHour.value = formattedTime.value;
        isSettingFrameB.value = false;
        isCaptureModalOpen.value = true;
        captureActiveFrameTab.value = 'frame_b';
        syncPromptWithCurrentState();
    } finally {
        isCapturingFrameB.value = false;
    }
}

function cancelNavigatingForFrameB() {
    isSettingFrameB.value = false;
    isCaptureModalOpen.value = true;
}

function clearFrameB() {
    captureFrameBDataUrl.value = null;
    captureFrameBRoomName.value = '';
    captureActiveFrameTab.value = 'frame_a';
    syncPromptWithCurrentState();
}

function downloadFrameB() {
    if (!captureFrameBDataUrl.value) return;
    const a = document.createElement('a');
    a.href = captureFrameBDataUrl.value;
    const roomSlug = (captureFrameBRoomName.value || 'frame-b').replace(/\s+/g, '-').toLowerCase();
    const timeSlug = (captureFrameBHour.value || '12-00').replace(':', '-');
    a.download = `pitcharch-${roomSlug}-last-frame-${timeSlug}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function captureCanvasSnapshot(targetRes: 'viewport' | '1080p' | '4k'): string {
    return renderHighQualitySnapshot(targetRes, viewerContainer.value);
}

function refreshCaptureSnapshot() {
    capturePreviewDataUrl.value = captureCanvasSnapshot(captureResolution.value);
}

async function openCaptureModal() {
    if (!isOwner.value) return;
    if (isCapturingSnapshot.value) return;
    isCapturingSnapshot.value = true;
    isShutterFlashing.value = true;

    // Durasi kilatan shutter kamera
    setTimeout(() => {
        isShutterFlashing.value = false;
    }, 280);

    // Yield control sejenak agar browser merender spinner loading pada tombol & efek shutter
    await nextTick();
    await new Promise(resolve => setTimeout(resolve, 60));

    try {
        selectedPresetForCapture.value = 'current';
        captureHour.value = formattedTime.value;
        captureTitle.value = '';
        captureError.value = '';
        captureVisibleToClients.value = false;
        renderEngine.value = 'standard';
        cachedAiPrompts.value = {};
        hasVisionAiGenerated.value = false;

        // 1. Bekukan snapshot preview canvas seketika
        refreshCaptureSnapshot();

        // 2. Ekstrak status fitur aplikasi (kamera, potongan, cahaya, objek)
        const meta = extractSceneMetadata();
        captureRoomName.value = meta.detected_object_id || 'Sudut Bebas';
        const isExt = /tampak|depan|eksterior|exterior|fasad|belakang|samping/i.test(captureRoomName.value);
        const prefix = isExt ? 'EXT-' : 'R-';
        const count = captures.value.filter(c => (c.code || '').toUpperCase().startsWith(prefix)).length + 1;
        captureCode.value = `${prefix}${String(count).padStart(2, '0')}`;

        // 3. Set prompt lokal instan (0 token) agar user leluasa memilih foto/video
        syncPromptWithCurrentState();
        isCaptureModalOpen.value = true;
    } catch (err) {
        console.error('[Capture] Error taking snapshot:', err);
    } finally {
        isCapturingSnapshot.value = false;
    }
}

function onCapturePresetChange() {
    cachedAiPrompts.value = {};
    hasVisionAiGenerated.value = false;

    if (selectedPresetForCapture.value === 'current') {
        refreshCaptureSnapshot();
        const meta = extractSceneMetadata();
        captureRoomName.value = meta.detected_object_id || 'Sudut Bebas';
        const isExt = /tampak|depan|eksterior|exterior|fasad|belakang|samping/i.test(captureRoomName.value);
        const prefix = isExt ? 'EXT-' : 'R-';
        const count = captures.value.filter(c => (c.code || '').toUpperCase().startsWith(prefix)).length + 1;
        captureCode.value = `${prefix}${String(count).padStart(2, '0')}`;
        syncPromptWithCurrentState();
        return;
    }

    const preset = cameraPresets.value.find(p => p.id === selectedPresetForCapture.value);
    if (preset) {
        captureRoomName.value = preset.name;
        const isExt = /tampak|depan|eksterior|exterior|fasad|belakang|samping/i.test(preset.name);
        const prefix = isExt ? 'EXT-' : 'R-';
        const count = captures.value.filter(c => (c.code || '').toUpperCase().startsWith(prefix)).length + 1;
        captureCode.value = `${prefix}${String(count).padStart(2, '0')}`;
        applyPreset(preset);

        setTimeout(() => {
            refreshCaptureSnapshot();
            extractSceneMetadata();
            syncPromptWithCurrentState();
        }, 350);
    }
}

function downloadCurrentCapture() {
    if (!capturePreviewDataUrl.value) return;
    const a = document.createElement('a');
    a.href = capturePreviewDataUrl.value;
    const roomSlug = (captureRoomName.value || 'snapshot').replace(/\s+/g, '-').toLowerCase();
    const timeSlug = (captureHour.value || '12-00').replace(':', '-');
    a.download = `pitcharch-${roomSlug}-${timeSlug}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function downloadCaptureFile(capture?: ProjectCaptureItem | null) {
    if (!capture?.id) return;

    const downloadUrl = `/projects/${project.value.id}/captures/${capture.id}/download`;

    // Gunakan hidden iframe agar halaman 3D tidak reload / berpindah sama sekali
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = 'none';
    iframe.style.opacity = '0';
    iframe.style.pointerEvents = 'none';
    iframe.src = downloadUrl;
    document.body.appendChild(iframe);

    setTimeout(() => {
        if (document.body.contains(iframe)) {
            document.body.removeChild(iframe);
        }
    }, 10000);
}

async function saveCaptureToGallery() {
    if (!capturePreviewDataUrl.value) return;

    isSavingCapture.value = true;
    captureError.value = '';

    const matchedRoom = drawingRooms.value.find(r => r.name.toLowerCase() === (captureRoomName.value || '').trim().toLowerCase());

    const payload = {
        image: capturePreviewDataUrl.value,
        code: captureCode.value || null,
        room_name: captureRoomName.value || 'Sudut Bebas',
        drawing_sheet_id: matchedRoom?.drawing_sheet_id || null,
        room_element_id: matchedRoom?.id || null,
        captured_hour: captureHour.value || '12:00',
        title: captureTitle.value || null,
        width: captureResolution.value === '1080p' ? 1920 : (renderer?.domElement.width ?? 1280),
        height: captureResolution.value === '1080p' ? 1080 : (renderer?.domElement.height ?? 720),
        is_visible_to_clients: captureVisibleToClients.value,
        prompt: capturePrompt.value || null,
    };

    try {
        const res = await fetch(`/projects/${project.value.id}/captures`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (res.ok) {
            captures.value.unshift(data.data);
            if (quotaInfo.value) {
                quotaInfo.value.current_count++;
            }
            isCaptureModalOpen.value = false;
        } else {
            captureError.value = data.message || 'Gagal menyimpan gambar.';
        }
    } catch {
        captureError.value = 'Koneksi error saat menyimpan gambar.';
    } finally {
        isSavingCapture.value = false;
    }
}

async function toggleCaptureVisibility(capture: ProjectCaptureItem) {
    const nextState = !capture.is_visible_to_clients;
    try {
        const res = await fetch(`/projects/${project.value.id}/captures/${capture.id}/toggle-visibility`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ is_visible_to_clients: nextState }),
        });
        if (res.ok) {
            capture.is_visible_to_clients = nextState;
        }
    } catch (err) {
        console.error('[Captures] toggle error:', err);
    }
}

async function autoSaveCaptureCode(capture: ProjectCaptureItem) {
    if (!isOwner.value) return;
    try {
        await fetch(`/projects/${project.value.id}/captures/${capture.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                code: capture.code,
            }),
        });
    } catch (err) {
        console.error('[Captures] update code error:', err);
    }
}

async function autoSaveCaptureRoomName(capture: ProjectCaptureItem) {
    if (!isOwner.value) return;
    try {
        await fetch(`/projects/${project.value.id}/captures/${capture.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                room_name: capture.room_name,
            }),
        });
    } catch (err) {
        console.error('[Captures] update room_name error:', err);
    }
}

async function deleteCapture(capture: ProjectCaptureItem) {
    if (!confirm(`Hapus gambar "${capture.room_name} (${capture.captured_hour})"?`)) return;

    try {
        const res = await fetch(`/projects/${project.value.id}/captures/${capture.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            captures.value = captures.value.filter(c => c.id !== capture.id);
            if (quotaInfo.value && quotaInfo.value.current_count > 0) {
                quotaInfo.value.current_count--;
            }
            if (activeLightboxCapture.value?.id === capture.id) {
                activeLightboxCapture.value = null;
            }
        }
    } catch (err) {
        console.error('[Captures] delete error:', err);
    }
}

// ── Lightbox Navigation & Keyboard Controls ──
const currentLightboxIndex = computed(() => {
    if (!activeLightboxCapture.value) return 0;
    return filteredCaptures.value.findIndex(c => c.id === activeLightboxCapture.value?.id);
});

function nextLightboxCapture() {
    if (!activeLightboxCapture.value) return;
    const list = filteredCaptures.value;
    if (list.length === 0) return;
    const idx = list.findIndex(c => c.id === activeLightboxCapture.value?.id);
    if (idx !== -1 && idx < list.length - 1) {
        activeLightboxCapture.value = list[idx + 1];
    } else {
        activeLightboxCapture.value = list[0];
    }
}

function prevLightboxCapture() {
    if (!activeLightboxCapture.value) return;
    const list = filteredCaptures.value;
    if (list.length === 0) return;
    const idx = list.findIndex(c => c.id === activeLightboxCapture.value?.id);
    if (idx > 0) {
        activeLightboxCapture.value = list[idx - 1];
    } else {
        activeLightboxCapture.value = list[list.length - 1];
    }
}

function onLightboxKeyDown(e: KeyboardEvent) {
    if (!activeLightboxCapture.value) return;
    const activeEl = document.activeElement;
    if (activeEl && (activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'INPUT')) {
        if (e.key === 'Escape') {
            (activeEl as HTMLElement).blur();
        }
        return;
    }

    if (e.key === 'Escape') {
        activeLightboxCapture.value = null;
    } else if (e.key === 'ArrowRight') {
        nextLightboxCapture();
    } else if (e.key === 'ArrowLeft') {
        prevLightboxCapture();
    }
}

watch(activeLightboxCapture, (val) => {
    if (val) {
        window.addEventListener('keydown', onLightboxKeyDown);
    } else {
        window.removeEventListener('keydown', onLightboxKeyDown);
    }
});

// Apakah user adalah arsitek pemilik project
const isOwner = computed(() => {
    const uid = (page.props.auth as any)?.user?.id;
    return uid && project.value.user_id === uid;
});

// Apakah user adalah super admin yang buka viewer untuk review saja (read-only)
const isAdminViewer = computed(() => {
    return (page.props.auth as any)?.user?.is_admin === true && !isOwner.value;
});

// Dokumen RAB yang boleh dilihat user ini (dikirim dari ViewerController)
interface RabDocSummary {
    id: string;
    title: string;
    status: 'draft' | 'final';
    is_visible_to_clients: boolean;
    total: number;
    source: string;
    items_count: number;
}
const rabDocuments = computed(() => (page.props.rabDocuments ?? []) as RabDocSummary[]);
const hasVisibleRab = computed(() => rabDocuments.value.length > 0);
const showRabPanel  = ref(false);

const drawingRooms = computed(() => (page.props.drawingRooms ?? []) as Array<{ id: string; drawing_sheet_id: string; name: string; raw_text: string }>);

// Lerp target — sama polanya dengan framing model
let presetLerpTarget: { pos: THREE.Vector3; tgt: THREE.Vector3 } | null = null;

async function loadCameraPresets(): Promise<void> {
    try {
        const res = await fetch(`/projects/${project.value.id}/camera-presets`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (res.ok) {
            const data = await res.json();
            cameraPresets.value = data.data ?? [];
        }
    } catch (err) {
        console.error('[CameraPresets] load error:', err);
    }
}

function applyPreset(preset: CameraPreset): void {
    if (!camera || !controls) return;
    presetLerpTarget = {
        pos: new THREE.Vector3(preset.position.x, preset.position.y, preset.position.z),
        tgt: new THREE.Vector3(preset.target.x,   preset.target.y,   preset.target.z),
    };
}

async function savePreset(): Promise<void> {
    if (!camera || !controls || !newPresetName.value.trim()) return;

    isSavingPreset.value = true;
    presetError.value = '';

    const payload = {
        name:       newPresetName.value.trim(),
        position_x: camera.position.x,
        position_y: camera.position.y,
        position_z: camera.position.z,
        target_x:   controls.target.x,
        target_y:   controls.target.y,
        target_z:   controls.target.z,
    };

    try {
        const res = await fetch(`/projects/${project.value.id}/camera-presets`, {
            method: 'POST',
            headers: {
                'Content-Type':   'application/json',
                Accept:           'application/json',
                'X-CSRF-TOKEN':   (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (res.ok) {
            cameraPresets.value.push(data.data);
            newPresetName.value = '';
        } else {
            presetError.value = data.message ?? 'Gagal menyimpan preset.';
        }
    } catch {
        presetError.value = 'Koneksi error saat menyimpan preset.';
    } finally {
        isSavingPreset.value = false;
    }
}

async function deletePreset(presetId: string): Promise<void> {
    isDeletingPresetId.value = presetId;
    try {
        const res = await fetch(`/projects/${project.value.id}/camera-presets/${presetId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            cameraPresets.value = cameraPresets.value.filter(p => p.id !== presetId);
        }
    } catch {
        // silent fail
    } finally {
        isDeletingPresetId.value = null;
    }
}

// ── Showcase-compatible state ──
const autoRotate = ref(false);
const isFullscreen = ref(false);
const loadingProgress = ref(0);

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
        isFullscreen.value = true;
    } else {
        document.exitFullscreen().catch(() => {});
        isFullscreen.value = false;
    }
}

function handleFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
}

function resetModelView() {
    frameModel();
}

// Auto-rotate: sync with controls in render loop (handled in render())

// Edit comment states
const editingCommentId = ref<string | null>(null);
const editCommentText = ref('');
const isSavingEdit = ref(false);
const editCommentError = ref('');

// Unpin / Delete comment states
const unpinningCommentId = ref<string | null>(null);
const isUnpinning = ref(false);
const unpinError = ref('');


// Draggable box offsets
const userBoxOffsets = ref<Record<string, { dx: number; dy: number }>>({});
const pendingPinOffset = ref<{ dx: number; dy: number }>({ dx: 0, dy: 0 });

function viewerPreferencesKey(): string {
    return `project-viewer:${project.value.id}:preferences`;
}

function isValidBoxOffset(value: unknown): value is { dx: number; dy: number } {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const offset = value as { dx?: unknown; dy?: unknown };

    return Number.isFinite(offset.dx) && Number.isFinite(offset.dy);
}

function restoreViewerPreferences(): void {
    try {
        const storedPreferences = window.localStorage.getItem(viewerPreferencesKey());
        if (!storedPreferences) {
            return;
        }

        const preferences = JSON.parse(storedPreferences) as Partial<ViewerPreferences>;

        if (typeof preferences.drawerOpen === 'boolean') {
            isDrawerOpen.value = preferences.drawerOpen;
        }

        if (typeof preferences.showAnnotations === 'boolean') {
            showAnnotations.value = preferences.showAnnotations;
        }

        if (typeof preferences.boxOffsets === 'object' && preferences.boxOffsets !== null) {
            userBoxOffsets.value = Object.fromEntries(
                Object.entries(preferences.boxOffsets).filter(([, offset]) => isValidBoxOffset(offset))
            );
        }

        // Restore clipping state
        if (preferences.clipping && typeof preferences.clipping === 'object') {
            const c = preferences.clipping;
            if (typeof c.enabled === 'boolean') clippingEnabled.value = c.enabled;
            if (c.axis === 'x' || c.axis === 'y' || c.axis === 'z') clippingAxis.value = c.axis;
            if (typeof c.value === 'number' && Number.isFinite(c.value)) clippingValue.value = c.value;
            if (typeof c.flip === 'boolean') clippingFlip.value = c.flip;
        }
    } catch {
        // Ignore unavailable or malformed browser storage.
    }
}

function persistViewerPreferences(): void {
    try {
        const preferences: ViewerPreferences = {
            drawerOpen: isDrawerOpen.value,
            showAnnotations: showAnnotations.value,
            boxOffsets: userBoxOffsets.value,
            clipping: {
                enabled: clippingEnabled.value,
                axis:    clippingAxis.value,
                value:   clippingValue.value,
                flip:    clippingFlip.value,
            },
        };

        window.localStorage.setItem(viewerPreferencesKey(), JSON.stringify(preferences));
    } catch {
        // Ignore unavailable browser storage, such as private browsing restrictions.
    }
}

watch([isDrawerOpen, showAnnotations, userBoxOffsets], persistViewerPreferences, { deep: true });

// Watch clipping state — persist + apply ke renderer segera
watch([clippingEnabled, clippingAxis, clippingValue, clippingFlip], () => {
    applyClippingToRenderer();
    persistViewerPreferences();
});

// Update range saat axis berganti
watch(clippingAxis, () => {
    updateClippingRangeFromModel();
    applyClippingToRenderer();
});

// ── Clipping / Sectioning functions ──────────────────────
function applyClippingToRenderer(): void {
    if (!renderer) return;

    if (!clippingEnabled.value) {
        renderer.clippingPlanes = [];
        renderer.localClippingEnabled = false;
        return;
    }

    renderer.localClippingEnabled = true;

    // Tentukan normal vektor berdasarkan axis + flip
    const sign = clippingFlip.value ? 1 : -1;
    let normal: THREE.Vector3;
    switch (clippingAxis.value) {
        case 'x': normal = new THREE.Vector3(sign, 0, 0); break;
        case 'z': normal = new THREE.Vector3(0, 0, sign); break;
        default:  normal = new THREE.Vector3(0, sign, 0); break; // 'y'
    }

    // constant = jarak bidang dari origin (negatif = arah normal)
    renderer.clippingPlanes = [
        new THREE.Plane(normal, clippingValue.value * sign * -1),
    ];
}

function resetClipping(): void {
    clippingEnabled.value = false;
    clippingAxis.value    = 'y';
    clippingValue.value   = 3.5;
    clippingFlip.value    = false;
}

// Update range slider saat model selesai load (bounding box dari model)
function updateClippingRangeFromModel(): void {
    if (!model) return;
    const box  = new THREE.Box3().setFromObject(model);
    const size = box.getSize(new THREE.Vector3());
    const min  = box.min;
    const max  = box.max;

    // Range berdasarkan axis yang aktif
    switch (clippingAxis.value) {
        case 'x':
            clippingRange.value = { min: Math.floor(min.x) - 1, max: Math.ceil(max.x) + 1, step: 0.05 };
            break;
        case 'z':
            clippingRange.value = { min: Math.floor(min.z) - 1, max: Math.ceil(max.z) + 1, step: 0.05 };
            break;
        default: // y
            clippingRange.value = { min: Math.floor(min.y) - 0.5, max: Math.ceil(max.y) + 0.5, step: 0.05 };
            // Set nilai awal ke tengah bangunan jika belum pernah disimpan
            if (clippingValue.value === 3.5) {
                clippingValue.value = parseFloat(((min.y + max.y) / 2).toFixed(2));
            }
    }
}

let isDraggingBox = false;
let dragTargetId: string | null = null;
let dragStartPointer = { x: 0, y: 0 };
let dragInitialOffset = { dx: 0, dy: 0 };

// Pending pin state (when user clicks to add a comment)
const pendingPin = ref<{
    x: number;
    y: number;
    z: number;
    normal?: { x: number; y: number; z: number };
    screenX: number;
    screenY: number;
    anchorX: number;
    anchorY: number;
    cardX: number;
    cardY: number;
    isRightSide: boolean;
    isVisible: boolean;
} | null>(null);

const newCommentText = ref('');
const newCommentInputRef = ref<HTMLTextAreaElement | null>(null);
const isSubmittingComment = ref(false);
const submitCommentError = ref('');

function focusNewCommentInput() {
    nextTick(() => {
        newCommentInputRef.value?.focus({ preventScroll: true });
    });
    setTimeout(() => {
        newCommentInputRef.value?.focus({ preventScroll: true });
    }, 50);
}

// Check if current user can edit a comment
function canEditComment(comment: { user_id?: string; user?: { id?: string } }): boolean {
    const currentUserId = (page.props.auth as any)?.user?.id;
    if (!currentUserId) return false;
    return (
        comment.user_id === currentUserId ||
        comment.user?.id === currentUserId ||
        project.value.user_id === currentUserId
    );
}

let activePointerId: number | null = null;
let activeDragTarget: HTMLElement | null = null;

// Start drag for comment card or pending pin via PointerEvent
function startDrag(targetId: string, event: PointerEvent) {
    if (event.button !== 0 && event.pointerType === 'mouse') return;

    event.stopPropagation();
    event.preventDefault();

    isDraggingBox = true;
    dragTargetId = targetId;
    dragStartPointer = { x: event.clientX, y: event.clientY };
    activePointerId = event.pointerId;

    activeDragTarget = event.currentTarget as HTMLElement | null;
    if (activeDragTarget && activeDragTarget.setPointerCapture) {
        try {
            activeDragTarget.setPointerCapture(event.pointerId);
        } catch (e) {
            // ignore
        }
    }

    if (targetId === 'pending') {
        dragInitialOffset = { ...pendingPinOffset.value };
    } else {
        dragInitialOffset = { ...(userBoxOffsets.value[targetId] || { dx: 0, dy: 0 }) };
    }

    window.addEventListener('pointermove', onBoxDragMove, { passive: false });
    window.addEventListener('pointerup', onBoxDragEnd);
    window.addEventListener('pointercancel', onBoxDragEnd);
}

function onBoxDragMove(event: PointerEvent) {
    if (!isDraggingBox || !dragTargetId) return;

    event.preventDefault();
    event.stopPropagation();

    const deltaX = event.clientX - dragStartPointer.x;
    const deltaY = event.clientY - dragStartPointer.y;

    if (dragTargetId === 'pending') {
        pendingPinOffset.value = {
            dx: dragInitialOffset.dx + deltaX,
            dy: dragInitialOffset.dy + deltaY,
        };
    } else {
        userBoxOffsets.value[dragTargetId] = {
            dx: dragInitialOffset.dx + deltaX,
            dy: dragInitialOffset.dy + deltaY,
        };
    }

    updateProjections();
}

function onBoxDragEnd() {
    if (activeDragTarget && activePointerId !== null) {
        try {
            activeDragTarget.releasePointerCapture(activePointerId);
        } catch (e) {
            // ignore
        }
    }
    activeDragTarget = null;
    activePointerId = null;
    isDraggingBox = false;
    dragTargetId = null;

    window.removeEventListener('pointermove', onBoxDragMove);
    window.removeEventListener('pointerup', onBoxDragEnd);
    window.removeEventListener('pointercancel', onBoxDragEnd);
}

// Dedicated mobile touch drag handler for 100% reliability on touchscreens
function startTouchDrag(targetId: string, event: TouchEvent) {
    if (event.touches.length !== 1) return;
    const touch = event.touches[0];

    event.stopPropagation();
    event.preventDefault();

    isDraggingBox = true;
    dragTargetId = targetId;
    dragStartPointer = { x: touch.clientX, y: touch.clientY };

    if (targetId === 'pending') {
        dragInitialOffset = { ...pendingPinOffset.value };
    } else {
        dragInitialOffset = { ...(userBoxOffsets.value[targetId] || { dx: 0, dy: 0 }) };
    }

    const onTouchMove = (e: TouchEvent) => {
        if (!isDraggingBox || !dragTargetId || e.touches.length !== 1) return;
        e.preventDefault();
        e.stopPropagation();

        const t = e.touches[0];
        const deltaX = t.clientX - dragStartPointer.x;
        const deltaY = t.clientY - dragStartPointer.y;

        if (dragTargetId === 'pending') {
            pendingPinOffset.value = {
                dx: dragInitialOffset.dx + deltaX,
                dy: dragInitialOffset.dy + deltaY,
            };
        } else {
            userBoxOffsets.value[dragTargetId] = {
                dx: dragInitialOffset.dx + deltaX,
                dy: dragInitialOffset.dy + deltaY,
            };
        }

        updateProjections();
    };

    const onTouchEnd = () => {
        isDraggingBox = false;
        dragTargetId = null;
        window.removeEventListener('touchmove', onTouchMove);
        window.removeEventListener('touchend', onTouchEnd);
        window.removeEventListener('touchcancel', onTouchEnd);
    };

    window.addEventListener('touchmove', onTouchMove, { passive: false });
    window.addEventListener('touchend', onTouchEnd);
    window.addEventListener('touchcancel', onTouchEnd);
}

// Edit comment actions
function startEditing(comment: { id: string; content: string }, event?: Event) {
    if (event) {
        event.stopPropagation();
    }
    editingCommentId.value = comment.id;
    editCommentText.value = comment.content;
    editCommentError.value = '';
    activeCommentId.value = comment.id;
}

function cancelEditing(event?: Event) {
    if (event) {
        event.stopPropagation();
    }
    editingCommentId.value = null;
    editCommentText.value = '';
    editCommentError.value = '';
}

function saveEditing(commentId: string) {
    if (!editCommentText.value.trim() || isSavingEdit.value) return;

    isSavingEdit.value = true;
    editCommentError.value = '';

    router.patch(
        `/projects/${project.value.id}/comments/${commentId}`,
        {
            content: editCommentText.value.trim(),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                const target = comments.value.find((c) => c.id === commentId);
                if (target) {
                    target.content = editCommentText.value.trim();
                }
                editingCommentId.value = null;
                editCommentText.value = '';
                updateProjections();
            },
            onError: (errs) => {
                editCommentError.value =
                    errs.content || 'Gagal memperbarui komentar.';
            },
            onFinish: () => {
                isSavingEdit.value = false;
            },
        }
    );
}

function focusAndEdit(comment: Comment) {
    focusComment(comment);
    startEditing(comment);
}

// Unpin comment actions
function promptUnpin(commentId: string, event?: Event) {
    if (event) {
        event.stopPropagation();
    }
    unpinningCommentId.value = commentId;
    unpinError.value = '';
    activeCommentId.value = commentId;
}

function cancelUnpin(event?: Event) {
    if (event) {
        event.stopPropagation();
    }
    unpinningCommentId.value = null;
    unpinError.value = '';
}

function executeUnpin(commentId: string) {
    if (isUnpinning.value) return;

    isUnpinning.value = true;
    unpinError.value = '';

    router.delete(`/projects/${project.value.id}/comments/${commentId}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            comments.value = comments.value.filter((c) => c.id !== commentId);
            delete userBoxOffsets.value[commentId];

            if (comments.value.length < (project.value.max_revisions_allowed || 3)) {
                limitWarning.value = '';
            }

            if (activeCommentId.value === commentId) {
                activeCommentId.value = null;
            }
            if (editingCommentId.value === commentId) {
                editingCommentId.value = null;
            }
            unpinningCommentId.value = null;

            renderCommentMarkers();
            updateProjections();
        },
        onError: (errs) => {
            unpinError.value =
                errs?.message || 'Gagal melepas pin dan menghapus komentar.';
        },
        onFinish: () => {
            isUnpinning.value = false;
        },
    });
}

function promptUnpinFromDrawer(comment: Comment) {
    focusComment(comment);
    promptUnpin(comment.id);
}

// Toggle resolved status — save to DB
const togglingResolvedId = ref<string | null>(null);

async function toggleResolved(commentId: string): Promise<void> {
    if (togglingResolvedId.value) return;
    togglingResolvedId.value = commentId;

    try {
        const response = await fetch(
            `/projects/${project.value.id}/comments/${commentId}/resolve`,
            {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (response.ok) {
            const data = await response.json();
            const target = comments.value.find(c => c.id === commentId);
            if (target) {
                target.status = data.data?.status;
            }
            updateProjections();
        }
    } catch (err) {
        console.error('Failed to toggle resolved:', err);
    } finally {
        togglingResolvedId.value = null;
    }
}



// 2D Projection state
const projectedComments = ref<ProjectedComment[]>([]);
const visibleProjectedComments = computed(() =>
    projectedComments.value.filter((item) => item.isVisible)
);

// Drag vs Click detection
let pointerDownPos = { x: 0, y: 0 };
let pointerDownTime = 0;

function onPointerDown(e: MouseEvent) {
    pointerDownPos = { x: e.clientX, y: e.clientY };
    pointerDownTime = Date.now();
}

async function onPointerUp(e: MouseEvent) {
    const dist = Math.hypot(e.clientX - pointerDownPos.x, e.clientY - pointerDownPos.y);

    // If mouse moved more than 5px, it's a drag (rotate or pan), NOT a pin click
    if (dist > 5) {
        return;
    }

    // Only respond to left-clicks for pin drop
    if (e.button !== 0) {
        return;
    }

    // In pan mode, user is panning; don't pin unless in pin mode or rotate mode
    if (interactionMode.value === 'pan') {
        return;
    }

    await handlePinClick(e);
}

function setInteractionMode(mode: 'rotate' | 'pan' | 'pin') {
    if (mode === 'pin') {
        const maxLimit = project.value.max_revisions_allowed || 3;
        if (comments.value.length >= maxLimit) {
            limitWarning.value = `Batas revisi maksimal (${maxLimit} pin) telah tercapai. Hapus atau unpin komentar yang ada jika ingin menambahkan revisi baru.`;
            return;
        }
    }
    interactionMode.value = mode;
    limitWarning.value = '';
    if (!controls) return;

    if (mode === 'pan') {
        // Desktop mouse: Left click pans
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.PAN,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.ROTATE,
        };
        // Mobile touch: 1 finger pans
        controls.touches = {
            ONE: THREE.TOUCH.PAN,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    } else if (mode === 'rotate') {
        // Desktop mouse: Left click rotates (Orbit), Right click pans
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.ROTATE,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.PAN,
        };
        // Mobile touch: 1 finger rotates
        controls.touches = {
            ONE: THREE.TOUCH.ROTATE,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    } else if (mode === 'pin') {
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.ROTATE,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.PAN,
        };
        controls.touches = {
            ONE: THREE.TOUCH.ROTATE,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    }
}

function createPendingPin(
    hit: { x: number; y: number; z: number; normal?: { x: number; y: number; z: number } },
    screenX: number,
    screenY: number,
    rect: DOMRect
) {
    // Reset pending offset & always ensure clean empty input on new pin drop
    pendingPinOffset.value = { dx: 0, dy: 0 };
    newCommentText.value = '';
    submitCommentError.value = '';
    editingCommentId.value = null;
    editCommentError.value = '';

    const defaultIsRight = screenX < rect.width * 0.6;
    const cardWidth = 320;
    const defaultDx = defaultIsRight ? 60 : -350;
    const cardX = Math.max(10, Math.min(rect.width - cardWidth - 10, screenX + defaultDx));
    const cardY = Math.max(20, Math.min(rect.height - 180, screenY - 40));
    const isRightSide = cardX + cardWidth * 0.5 >= screenX;
    const anchorX = isRightSide ? cardX : cardX + cardWidth;
    const anchorY = cardY + 24;

    pendingPin.value = {
        x: hit.x,
        y: hit.y,
        z: hit.z,
        normal: hit.normal,
        screenX,
        screenY,
        anchorX,
        anchorY,
        cardX,
        cardY,
        isRightSide,
        isVisible: true,
    };

    updateProjections();
    focusNewCommentInput();
}

function handleTambahPinButton(): void {
    const maxLimit = project.value.max_revisions_allowed || 3;
    if (comments.value.length >= maxLimit) {
        limitWarning.value = `Batas revisi maksimal (${maxLimit} pin) telah tercapai. Hapus atau unpin komentar yang ada jika ingin menambahkan revisi baru.`;
        return;
    }

    setInteractionMode('pin');

    if (pendingPin.value) {
        focusNewCommentInput();
    }
}

async function handlePinClick(event: MouseEvent): Promise<void> {
    if (!camera || !scene || !viewerContainer.value) return;

    const maxLimit = project.value.max_revisions_allowed || 3;
    if (comments.value.length >= maxLimit) {
        limitWarning.value = `Batas revisi maksimal (${maxLimit} pin) telah tercapai. Hapus atau unpin komentar yang ada jika ingin menambahkan revisi baru.`;
        return;
    }

    const hit = await useRaycast(event, camera, scene);
    if (!hit) {
        return;
    }

    const rect = viewerContainer.value.getBoundingClientRect();
    const screenX = event.clientX - rect.left;
    const screenY = event.clientY - rect.top;

    createPendingPin(hit, screenX, screenY, rect);
}

function cancelPendingPin() {
    pendingPin.value = null;
    pendingPinOffset.value = { dx: 0, dy: 0 };
    newCommentText.value = '';
    submitCommentError.value = '';
}

async function submitComment(): Promise<void> {
    if (!pendingPin.value || !newCommentText.value.trim()) return;

    const maxLimit = project.value.max_revisions_allowed || 3;
    if (comments.value.length >= maxLimit) {
        submitCommentError.value = `Batas revisi maksimal (${maxLimit} pin) telah tercapai. Hapus atau unpin komentar yang ada jika ingin menambahkan revisi baru.`;
        return;
    }

    isSubmittingComment.value = true;
    submitCommentError.value = '';

    const payload = {
        content: newCommentText.value.trim(),
        position_x: pendingPin.value.x,
        position_y: pendingPin.value.y,
        position_z: pendingPin.value.z,
        normal_x: pendingPin.value.normal?.x ?? 0,
        normal_y: pendingPin.value.normal?.y ?? 0,
        normal_z: pendingPin.value.normal?.z ?? 0,
    };

    router.post(storeComment.url(project.value.id), payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: async (pageData: any) => {
            pendingPin.value = null;
            pendingPinOffset.value = { dx: 0, dy: 0 };
            newCommentText.value = '';

            // 1. Immediately apply fresh comments from Inertia's back() response if present
            const freshComments = pageData?.props?.project?.comments;
            if (Array.isArray(freshComments) && freshComments.length > 0) {
                comments.value = [...freshComments];
                renderCommentMarkers();
                updateProjections();
            }

            // 2. Always force fetch to guarantee sync with database
            await loadComments(true);

            // 3. Switch back to rotate mode after placing a pin
            if (interactionMode.value === 'pin') {
                setInteractionMode('rotate');
            }

            // 4. Ensure drawer is open so the user immediately sees the newly pinned note
            isDrawerOpen.value = true;
        },
        onError: (errs) => {
            submitCommentError.value = errs.content || 'Gagal menyimpan komentar pin.';
        },
        onFinish: () => {
            isSubmittingComment.value = false;
        },
    });
}

function getLeaderLinePath(
    fromX: number,
    fromY: number,
    toX: number,
    toY: number,
    isRightSide: boolean
): string {
    // Architectural elbow leader line: start at pin -> knee corner -> anchor at card
    const landingLength = 24;
    const kneeX = isRightSide ? toX - landingLength : toX + landingLength;
    return `M ${fromX} ${fromY} L ${kneeX} ${toY} L ${toX} ${toY}`;
}

function updateProjections(): void {
    if (!viewerContainer.value || !camera) return;

    const width = viewerContainer.value.clientWidth;
    const height = viewerContainer.value.clientHeight;
    if (width === 0 || height === 0) return;

    const tempVec = new THREE.Vector3();

    // Update pending pin position with custom user drag offset
    if (pendingPin.value) {
        tempVec.set(pendingPin.value.x, pendingPin.value.y, pendingPin.value.z);
        tempVec.project(camera);
        const isVisible = tempVec.z < 1.0;
        const screenX = (tempVec.x * 0.5 + 0.5) * width;
        const screenY = (-tempVec.y * 0.5 + 0.5) * height;

        const pOffset = pendingPinOffset.value;
        const cardWidth = 320;
        const defaultIsRight = screenX < width * 0.6;
        const defaultDx = defaultIsRight ? 60 : -350;
        const defaultDy = -40;

        const cardX = screenX + defaultDx + pOffset.dx;
        const cardY = screenY + defaultDy + pOffset.dy;

        const cardCenterX = cardX + cardWidth * 0.5;
        const isRightSide = cardCenterX >= screenX;
        const anchorX = isRightSide ? cardX : cardX + cardWidth;
        const anchorY = cardY + 24;

        pendingPin.value.screenX = screenX;
        pendingPin.value.screenY = screenY;
        pendingPin.value.cardX = cardX;
        pendingPin.value.cardY = cardY;
        pendingPin.value.anchorX = anchorX;
        pendingPin.value.anchorY = anchorY;
        pendingPin.value.isRightSide = isRightSide;
        pendingPin.value.isVisible = isVisible;
    }

    // Update existing comments positions with custom user drag offset
    const list: ProjectedComment[] = [];
    for (let i = 0; i < comments.value.length; i++) {
        const c = comments.value[i];
        tempVec.set(c.position_x, c.position_y, c.position_z);
        tempVec.project(camera);
        const isVisible = tempVec.z < 1.0;
        const screenX = (tempVec.x * 0.5 + 0.5) * width;
        const screenY = (-tempVec.y * 0.5 + 0.5) * height;

        const userOffset = userBoxOffsets.value[c.id] || { dx: 0, dy: 0 };
        const cardWidth = 280;
        const defaultIsRight = screenX < width * 0.6;
        const defaultDx = defaultIsRight ? 50 : -310;
        const defaultDy = -35;

        const cardX = screenX + defaultDx + userOffset.dx;
        const cardY = screenY + defaultDy + userOffset.dy;

        const cardCenterX = cardX + cardWidth * 0.5;
        const isRightSide = cardCenterX >= screenX;
        const anchorX = isRightSide ? cardX : cardX + cardWidth;
        const anchorY = cardY + 24;

        list.push({
            id: c.id,
            user_id: c.user_id,
            content: c.content,
            status: c.status,
            user: c.user,
            screenX,
            screenY,
            anchorX,
            anchorY,
            cardX,
            cardY,
            isRightSide,
            isVisible,
        });
    }

    projectedComments.value = list;
}

function focusComment(comment: Comment) {
    activeCommentId.value = comment.id;
    if (!controls || !camera) return;

    // Smoothly focus on this pin coordinate
    const targetPos = new THREE.Vector3(
        comment.position_x,
        comment.position_y,
        comment.position_z
    );

    controls.target.lerp(targetPos, 0.8);
    controls.update();
}

async function loadComments(forceFetch = false): Promise<void> {
    const proj = project.value;
    if (!proj || !proj.id) return;

    if (!forceFetch && Array.isArray(proj.comments) && proj.comments.length > 0 && comments.value.length === 0) {
        comments.value = [...proj.comments];
    } else {
        try {
            const response = await fetch(commentIndex.url(proj.id), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data = await response.json();
                comments.value = Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : []);
            } else if (Array.isArray(proj.comments)) {
                comments.value = [...proj.comments];
            }
        } catch (err) {
            console.error('Failed to load comments:', err);
            if (Array.isArray(proj.comments)) {
                comments.value = [...proj.comments];
            }
        }
    }

    renderCommentMarkers();
    updateProjections();
}

function renderCommentMarkers(): void {
    markerGroup.clear();

    for (const comment of comments.value) {
        const marker = new THREE.Mesh(
            new THREE.SphereGeometry(0.045, 16, 16),
            new THREE.MeshStandardMaterial({
                color: 0xef4444,
                emissive: 0x7f1d1d,
                roughness: 0.3,
                metalness: 0.5,
            })
        );
        marker.position.set(
            Number(comment.position_x),
            Number(comment.position_y),
            Number(comment.position_z)
        );
        marker.userData = { isMarker: true, commentId: comment.id };
        markerGroup.add(marker);
    }
}

function frameModel(): void {
    if (model === null || camera === null || controls === null) {
        return;
    }

    const bounds = new THREE.Box3().setFromObject(model);
    const center = bounds.getCenter(new THREE.Vector3());
    const size = bounds.getSize(new THREE.Vector3());
    const distance = Math.max(size.length() * 0.9, 1.2);

    camera.position.set(
        center.x + distance * 0.8,
        center.y + distance * 0.6,
        center.z + distance * 0.8
    );
    camera.near = distance / 100;
    camera.far = distance * 100;
    camera.updateProjectionMatrix();

    controls.target.copy(center);
    controls.update();
}

function loadModel(): void {
    const proj = project.value;
    if (scene === null || typeof proj?.file_path !== 'string') {
        modelError.value = 'Path file model 3D tidak tersedia.';
        isLoading.value = false;
        return;
    }

    const loader = new GLTFLoader();
    const modelUrl = `/storage/${proj.file_path}`;

    loader.load(
        modelUrl,
        (gltf: { scene: any }) => {
            model = gltf.scene;
            model.traverse((child: any) => {
                if (child.isMesh) {
                    child.castShadow = true;
                    child.receiveShadow = true;
                }
            });
            scene?.add(model);
            scanLampObjects(model);
            frameModel();
            updateClippingRangeFromModel();
            applyClippingToRenderer();
            isLoading.value = false;
        },
        undefined,
        () => {
            modelError.value =
                'Model 3D tidak dapat dimuat. Pastikan file GLB tersedia di storage.';
            isLoading.value = false;
        }
    );
}

function resizeRenderer(): void {
    if (renderer === null || camera === null || viewerContainer.value === null) {
        return;
    }

    const { clientWidth: width, clientHeight: height } = viewerContainer.value;
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    setPostProcessingSize(width, height);
    updateProjections();
}

function render(): void {
    if (renderer === null || scene === null || camera === null) {
        return;
    }

    if (isPathTracing.value) {
        animationFrame = window.requestAnimationFrame(render);
        return;
    }

    if (controls) {
        controls.autoRotate = autoRotate.value;
        controls.autoRotateSpeed = 1.2;

        // Smooth lerp ke preset kamera yang dipilih
        if (presetLerpTarget) {
            camera.position.lerp(presetLerpTarget.pos, 0.06);
            controls.target.lerp(presetLerpTarget.tgt, 0.06);
            // Selesai saat jarak < 0.05
            if (camera.position.distanceTo(presetLerpTarget.pos) < 0.05) {
                camera.position.copy(presetLerpTarget.pos);
                controls.target.copy(presetLerpTarget.tgt);
                presetLerpTarget = null;
            }
        }

        controls.update();
    }
    renderWithPostProcessing();
    updateProjections();
    animationFrame = window.requestAnimationFrame(render);
}

function initializeViewer(): void {
    if (viewerContainer.value === null) {
        return;
    }

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x06070a);

    // Dynamic Sun & Sky Lighting (SunCalc + DirectionalLight with shadows & ambient)
    initSunLighting(scene, {
        latitude: (project.value as any)?.latitude ?? -6.2088,
        longitude: (project.value as any)?.longitude ?? 106.8456,
        north_angle: (project.value as any)?.north_angle ?? 0,
        solar_date: (project.value as any)?.solar_date,
    });

    scene.add(markerGroup);

    camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: false,
        powerPreference: 'high-performance',
        preserveDrawingBuffer: true,
    });
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;

    // Inisialisasi Post-Processing Composer (GTAO + Bloom + Tone Mapping)
    const initW = viewerContainer.value.clientWidth || 1280;
    const initH = viewerContainer.value.clientHeight || 720;
    initPostProcessing(renderer, scene, camera, initW, initH);

    viewerContainer.value.appendChild(renderer.domElement);
    renderer.domElement.classList.add('h-full', 'w-full');

    // OrbitControls with dynamic rotation & screen-space panning
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.enablePan = true;
    controls.screenSpacePanning = true; // Allows natural pan up/down/left/right
    controls.panSpeed = 1.0;
    controls.rotateSpeed = 0.8;

    // Apply interaction mode configuration (both mouseButtons and touches)
    setInteractionMode(interactionMode.value);

    resizeObserver = new ResizeObserver(resizeRenderer);
    resizeObserver.observe(viewerContainer.value);
    resizeRenderer();
    render();
    loadModel();
}

onMounted(async () => {
    restoreViewerPreferences();
    initializeViewer();
    loadCameraPresets();
    loadCaptures();
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    const proj = project.value;
    if (Array.isArray(proj?.comments) && proj.comments.length > 0) {
        comments.value = [...proj.comments];
        renderCommentMarkers();
        updateProjections();
    } else {
        await loadComments(true);
    }
});

onBeforeUnmount(() => {
    onBoxDragEnd();
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    window.removeEventListener('keydown', onLightboxKeyDown);
    if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
    }

    disposePostProcessing();
    disposePathTracer();
    resizeObserver?.disconnect();
    controls?.dispose();
    renderer?.dispose();
});
</script>

<style scoped>
canvas { touch-action: none; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
