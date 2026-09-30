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
                    <span class="text-sm font-semibold tracking-tight text-white line-clamp-1">{{ project.title || 'AETHER 3D Viewer' }}</span>
                    <span class="rounded bg-rose-500/20 border border-rose-500/30 px-2 py-0.5 text-[10px] font-mono text-rose-300 hidden sm:inline">
                        3D VIEWER
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

                <!-- Tambah Pin -->
                <button
                    type="button"
                    @click="handleTambahPinButton"
                    class="flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-all shadow-md"
                    :class="interactionMode === 'pin'
                        ? 'bg-emerald-500 text-black ring-2 ring-emerald-300'
                        : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>{{ interactionMode === 'pin' ? 'Klik di Model!' : '+ Tambah Pin' }}</span>
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

        </div>
    </div>
</template>
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CheckCircle2,
    Eye,
    EyeOff,
    Focus,
    GripVertical,
    Hand,
    MapPin,
    Maximize2,
    MessageSquare,
    Minimize2,
    Pencil,
    PinOff,
    Plus,
    RotateCw,
    Send,
    Sparkles,
    Trash2,
    X,
} from '@lucide/vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { useRaycast } from '@/composables/useRaycast';
import RevisionBadge from '@/components/RevisionBadge.vue';
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
}

const page = usePage();
const project = computed(() => (page.props.project ?? {}) as {
    id: string;
    user_id?: string;
    title: string;
    file_path: string;
    current_revision_count: number;
    max_revisions_allowed: number;
    comments?: Comment[];
});

const viewerContainer = ref<HTMLDivElement | null>(null);
const comments = ref<Comment[]>([]);
const isLoading = ref(true);
const modelError = ref('');

// UI States
const interactionMode = ref<'rotate' | 'pan' | 'pin'>('rotate');
const showAnnotations = ref(true);
const isDrawerOpen = ref(true);
const activeCommentId = ref<string | null>(null);
const limitWarning = ref('');

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
        };

        window.localStorage.setItem(viewerPreferencesKey(), JSON.stringify(preferences));
    } catch {
        // Ignore unavailable browser storage, such as private browsing restrictions.
    }
}

watch([isDrawerOpen, showAnnotations, userBoxOffsets], persistViewerPreferences, { deep: true });

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



// Three.js variables
let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let controls: OrbitControls | null = null;
let model: THREE.Object3D | null = null;
let animationFrame: number | null = null;
let resizeObserver: ResizeObserver | null = null;
const markerGroup = new THREE.Group();

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
            scene?.add(model);
            frameModel();
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
    updateProjections();
}

function render(): void {
    if (renderer === null || scene === null || camera === null) {
        return;
    }

    if (controls) {
        controls.autoRotate = autoRotate.value;
        controls.autoRotateSpeed = 1.2;
        controls.update();
    }
    renderer.render(scene, camera);
    updateProjections();
    animationFrame = window.requestAnimationFrame(render);
}

function initializeViewer(): void {
    if (viewerContainer.value === null) {
        return;
    }

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x06070a);

    // Studio Lighting
    scene.add(new THREE.AmbientLight(0xffffff, 1.8));

    const keyLight = new THREE.DirectionalLight(0xffffff, 2.5);
    keyLight.position.set(8, 12, 10);
    scene.add(keyLight);

    const fillLight = new THREE.DirectionalLight(0x93c5fd, 1.2);
    fillLight.position.set(-8, 6, -8);
    scene.add(fillLight);

    scene.add(markerGroup);

    camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;

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
    if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
    }

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
