<template>
    <div class="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100">
        <!-- Top Navigation Bar -->
        <header class="flex h-14 shrink-0 items-center justify-between border-b border-slate-800 bg-slate-900/90 px-4 backdrop-blur-md z-20">
            <div class="flex items-center gap-3">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Dashboard</span>
                </Link>
                <div class="h-4 w-px bg-slate-700"></div>
                <div>
                    <h1 class="text-sm font-semibold text-white line-clamp-1">
                        {{ project.title || '3D Architecture Viewer' }}
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Revision Badge (Reactive with comments.length) -->
                <RevisionBadge :project="project" :current-count="comments.length" />

                <!-- Comment count indicator -->
                <button
                    @click="isDrawerOpen = !isDrawerOpen"
                    class="flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-slate-700"
                >
                    <MessageSquare class="h-3.5 w-3.5 text-rose-400" />
                    <span>{{ comments.length }} Pin</span>
                </button>
            </div>
        </header>

        <!-- Main 3D Viewport Area -->
        <div class="relative min-h-0 flex-1 overflow-hidden">
            <!-- Three.js Canvas Container -->
            <div
                ref="viewerContainer"
                class="relative h-full w-full select-none"
                :class="{
                    'cursor-grab': interactionMode === 'rotate',
                    'cursor-move': interactionMode === 'pan',
                    'cursor-crosshair': interactionMode === 'pin',
                }"
                @pointerdown="onPointerDown"
                @pointerup="onPointerUp"
            ></div>

            <!-- Loading & Error Overlays -->
            <div
                v-if="isLoading"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-slate-950/80 backdrop-blur-xs text-sm text-white"
            >
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-rose-500 border-t-transparent mb-3"></div>
                <p class="font-medium">Memuat model 3D arsitektur...</p>
                <p class="text-xs text-slate-400 mt-1">Mengoptimalkan tekstur dan geometri</p>
            </div>

            <div
                v-if="modelError"
                class="absolute inset-0 z-30 flex flex-col items-center justify-center p-6 text-center text-sm text-rose-300 bg-slate-950/90"
            >
                <div class="rounded-full bg-rose-500/20 p-3 text-rose-400 mb-3">
                    <X class="h-6 w-6" />
                </div>
                <p class="font-semibold text-base">{{ modelError }}</p>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">
                    Pastikan file model .glb tersedia dan dapat diakses.
                </p>
            </div>

            <!-- Floating Viewport Control Toolbar -->
            <div class="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-900/85 p-1.5 shadow-2xl backdrop-blur-md">
                <!-- Rotate Mode Button -->
                <button
                    @click="setInteractionMode('rotate')"
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition',
                        interactionMode === 'rotate'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                    ]"
                    title="Putar kamera secara dinamis (Orbit)"
                >
                    <RotateCw class="h-3.5 w-3.5" />
                    <span>Putar</span>
                </button>

                <!-- Pan Mode Button -->
                <button
                    @click="setInteractionMode('pan')"
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition',
                        interactionMode === 'pan'
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                    ]"
                    title="Geser kamera ke kanan, kiri, atas, bawah (Pan)"
                >
                    <Hand class="h-3.5 w-3.5" />
                    <span>Geser</span>
                </button>

                <!-- Pin Mode Button (Tambah Pin) -->
                <button
                    @click="handleTambahPinButton"
                    :class="[
                        'flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition',
                        interactionMode === 'pin'
                            ? 'bg-rose-600 text-white shadow-sm ring-2 ring-rose-500/30'
                            : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                    ]"
                    title="Tekan untuk menambah pin komentar pada objek 3D"
                >
                    <MapPin class="h-3.5 w-3.5 text-rose-400" />
                    <span>Tambah Pin</span>
                </button>

                <div class="h-4 w-px bg-slate-700 my-auto"></div>

                <!-- Toggle Annotations -->
                <button
                    @click="showAnnotations = !showAnnotations"
                    :class="[
                        'flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium transition',
                        showAnnotations ? 'text-slate-200 hover:bg-slate-800' : 'text-slate-500 hover:bg-slate-800',
                    ]"
                    :title="showAnnotations ? 'Sembunyikan garis & catatan' : 'Tampilkan garis & catatan'"
                >
                    <Eye v-if="showAnnotations" class="h-3.5 w-3.5" />
                    <EyeOff v-else class="h-3.5 w-3.5" />
                </button>

                <!-- Reset Camera -->
                <button
                    @click="frameModel"
                    class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
                    title="Pusatkan kembali model 3D di layar"
                >
                    <Maximize2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Revision Limit Alert Banner -->
            <div
                v-if="limitWarning"
                class="absolute top-4 left-1/2 -translate-x-1/2 z-30 flex items-center gap-2 rounded-xl border border-rose-500/60 bg-slate-900/95 px-4 py-2 text-xs text-rose-200 shadow-2xl backdrop-blur-md"
            >
                <AlertTriangle class="h-4 w-4 text-rose-400 shrink-0 animate-bounce" />
                <span>{{ limitWarning }}</span>
                <button
                    @click="limitWarning = ''"
                    class="ml-2 rounded p-0.5 hover:bg-slate-800 text-rose-300"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Help Hint Badge -->
            <div class="absolute bottom-4 left-4 z-20 pointer-events-none hidden md:flex items-center gap-2 rounded-lg bg-slate-900/70 border border-slate-800/80 px-3 py-1.5 text-[11px] text-slate-400 backdrop-blur-xs">
                <span>💡 <strong>Tips Kontrol:</strong> Klik Kiri Drag = Putar | Klik Kanan Drag = Geser (Pan) | Scroll = Zoom</span>
            </div>

            <!-- SVG LEADER LINES OVERLAY -->
            <svg
                v-if="showAnnotations"
                class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible"
            >
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

                <!-- Render Leader Lines for Existing Comments -->
                <g v-for="(item, idx) in visibleProjectedComments" :key="'line-' + item.id">
                    <!-- Surface contact point dot -->
                    <circle
                        :cx="item.screenX"
                        :cy="item.screenY"
                        r="5"
                        fill="#ef4444"
                        stroke="#ffffff"
                        stroke-width="2"
                        filter="url(#shadow)"
                    />
                    <!-- Outer pulsating wave on active pin -->
                    <circle
                        v-if="activeCommentId === item.id"
                        :cx="item.screenX"
                        :cy="item.screenY"
                        r="5"
                        fill="none"
                        stroke="#f43f5e"
                        stroke-width="1.5"
                    >
                        <animate attributeName="r" from="5" to="16" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>

                    <!-- Connected Leader Line (Pin Point -> Knee Corner -> Comment Box Anchor) -->
                    <path
                        :d="getLeaderLinePath(item.screenX, item.screenY, item.anchorX, item.anchorY, item.isRightSide)"
                        fill="none"
                        stroke="url(#lineGrad)"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-dasharray="4,3"
                    />

                    <!-- Anchor dot at the comment card edge -->
                    <circle
                        :cx="item.anchorX"
                        :cy="item.anchorY"
                        r="3.5"
                        fill="#ef4444"
                    />
                </g>

                <!-- Leader Line for Pending Pin Input -->
                <g v-if="pendingPin && pendingPin.isVisible">
                    <circle
                        :cx="pendingPin.screenX"
                        :cy="pendingPin.screenY"
                        r="6"
                        fill="#3b82f6"
                        stroke="#ffffff"
                        stroke-width="2"
                        filter="url(#shadow)"
                    />
                    <circle
                        :cx="pendingPin.screenX"
                        :cy="pendingPin.screenY"
                        r="6"
                        fill="none"
                        stroke="#3b82f6"
                        stroke-width="2"
                    >
                        <animate attributeName="r" from="6" to="18" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                    <path
                        :d="getLeaderLinePath(pendingPin.screenX, pendingPin.screenY, pendingPin.anchorX, pendingPin.anchorY, pendingPin.isRightSide)"
                        fill="none"
                        stroke="url(#pendingGrad)"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle
                        :cx="pendingPin.anchorX"
                        :cy="pendingPin.anchorY"
                        r="4"
                        fill="#3b82f6"
                    />
                </g>
            </svg>

            <!-- HTML CALLOUTS OVERLAY -->
            <div
                v-if="showAnnotations"
                class="pointer-events-none absolute inset-0 z-10 overflow-hidden"
            >
                <!-- Render Existing Comment Callouts (Draggable & Editable) -->
                <div
                    v-for="(item, idx) in visibleProjectedComments"
                    :key="'card-' + item.id"
                    :style="{
                        transform: `translate3d(${item.cardX}px, ${item.cardY}px, 0)`,
                    }"
                    class="pointer-events-auto absolute top-0 left-0 w-[280px] max-w-[85vw] touch-none"
                >
                    <div
                        @click="activeCommentId = item.id"
                        :class="[
                            'group rounded-xl border p-3 shadow-xl backdrop-blur-md transition-colors',
                            activeCommentId === item.id
                                ? 'border-rose-500 bg-slate-900/95 ring-2 ring-rose-500/30'
                                : 'border-slate-700/80 bg-slate-900/85 hover:border-slate-600 hover:bg-slate-900/95',
                        ]"
                    >
                        <!-- Author & Pin Header + Drag Handle + Edit Trigger -->
                        <div
                            class="flex items-center justify-between gap-2 border-b border-slate-800 pb-2 mb-2 select-none cursor-grab active:cursor-grabbing touch-none"
                            @pointerdown="startDrag(item.id, $event)"
                            @touchstart="startTouchDrag(item.id, $event)"
                            title="Tahan & geser untuk memindahkan kotak komentar"
                        >
                            <div class="flex items-center gap-1.5 min-w-0">
                                <GripVertical class="h-4 w-4 text-slate-400 hover:text-slate-200 shrink-0" />
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-500/20 text-[10px] font-bold text-rose-400">
                                    #{{ comments.length - idx }}
                                </span>
                                <span class="text-xs font-semibold text-white truncate">
                                    {{ item.user?.name ?? 'Reviewer' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1 shrink-0" @pointerdown.stop @touchstart.stop>
                                <!-- Edit Button -->
                                <button
                                    v-if="canEditComment(item) && editingCommentId !== item.id && unpinningCommentId !== item.id"
                                    @click.stop="startEditing(item, $event)"
                                    class="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white transition"
                                    title="Edit teks komentar ini"
                                >
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <!-- Unpin Button -->
                                <button
                                    v-if="canEditComment(item) && unpinningCommentId !== item.id"
                                    @click.stop="promptUnpin(item.id, $event)"
                                    class="rounded p-1 text-slate-400 hover:bg-rose-950/60 hover:text-rose-400 transition"
                                    title="Lepas pin & hapus komentar dari database"
                                >
                                    <PinOff class="h-3 w-3" />
                                </button>
                                <span class="text-[10px] text-slate-400">
                                    Pin 3D
                                </span>
                            </div>
                        </div>

                        <!-- Unpin Confirmation Mode -->
                        <div
                            v-if="unpinningCommentId === item.id"
                            class="rounded-lg bg-rose-950/50 border border-rose-500/40 p-2.5 text-xs space-y-2 touch-auto"
                            @pointerdown.stop
                            @touchstart.stop
                        >
                            <div class="flex items-center gap-1.5 text-rose-200 font-semibold">
                                <PinOff class="h-3.5 w-3.5 text-rose-400 shrink-0" />
                                <span>Lepas pin & hapus komentar?</span>
                            </div>
                            <p class="text-[11px] text-rose-200/75 leading-relaxed">
                                Pin ini dan data komentarnya akan dihapus permanen dari database.
                            </p>
                            <p v-if="unpinError" class="text-[11px] font-medium text-rose-400">
                                {{ unpinError }}
                            </p>
                            <div class="flex items-center justify-end gap-1.5 pt-1">
                                <button
                                    type="button"
                                    @click.stop="cancelUnpin($event)"
                                    class="rounded-md px-2 py-1 text-xs text-slate-300 hover:bg-slate-800 hover:text-white transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    @click.stop="executeUnpin(item.id)"
                                    :disabled="isUnpinning"
                                    class="flex items-center gap-1 rounded-md bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 disabled:opacity-50 transition"
                                >
                                    <Trash2 class="h-3 w-3" />
                                    <span>{{ isUnpinning ? 'Menghapus...' : 'Ya, Unpin' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Read Mode -->
                        <div v-else-if="editingCommentId !== item.id">
                            <p class="text-xs text-slate-200 leading-relaxed break-words line-clamp-4">
                                {{ item.content }}
                            </p>
                        </div>

                        <!-- Edit Mode -->
                        <div v-else class="space-y-2 touch-auto" @pointerdown.stop @touchstart.stop>
                            <textarea
                                v-model="editCommentText"
                                rows="3"
                                class="w-full resize-none rounded-lg border border-slate-700 bg-slate-950 p-2 text-xs text-white placeholder-slate-500 focus:border-rose-500 focus:outline-none focus:ring-1 focus:ring-rose-500"
                                placeholder="Edit feedback atau catatan revisi..."
                                autofocus
                            ></textarea>

                            <p v-if="editCommentError" class="text-[11px] font-medium text-rose-400">
                                {{ editCommentError }}
                            </p>

                            <div class="flex items-center justify-end gap-1.5 pt-0.5">
                                <button
                                    type="button"
                                    @click.stop="cancelEditing($event)"
                                    class="rounded-lg px-2 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-white"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    @click.stop="saveEditing(item.id)"
                                    :disabled="isSavingEdit || !editCommentText.trim()"
                                    class="flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 disabled:opacity-50"
                                >
                                    <Check class="h-3 w-3" />
                                    <span>{{ isSavingEdit ? 'Menyimpan...' : 'Simpan' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PENDING PIN COMMENT INPUT FORM (Draggable & Always Empty on Initial Drop) -->
                <div
                    v-if="pendingPin && pendingPin.isVisible"
                    :style="{
                        transform: `translate3d(${pendingPin.cardX}px, ${pendingPin.cardY}px, 0)`,
                    }"
                    class="pointer-events-auto absolute top-0 left-0 w-[320px] max-w-[90vw] touch-none"
                >
                    <div class="rounded-xl border-2 border-blue-500 bg-slate-900/95 p-3.5 shadow-2xl backdrop-blur-md ring-4 ring-blue-500/20">
                        <div
                            class="flex items-center justify-between pb-2 border-b border-slate-800 select-none cursor-grab active:cursor-grabbing touch-none"
                            @pointerdown="startDrag('pending', $event)"
                            @touchstart="startTouchDrag('pending', $event)"
                            title="Tahan & geser untuk memindahkan form pin"
                        >
                            <div class="flex items-center gap-1.5">
                                <GripVertical class="h-4 w-4 text-blue-400 shrink-0" />
                                <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                <span class="text-xs font-semibold text-white">Tulis Komentar Pin</span>
                                <span class="text-[10px] text-blue-400 font-normal hidden xs:inline">(Bisa digeser)</span>
                            </div>
                            <button
                                @click="cancelPendingPin"
                                class="rounded p-1 text-slate-400 hover:bg-slate-800 hover:text-white"
                                title="Batalkan pin"
                                @pointerdown.stop
                                @touchstart.stop
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <form @submit.prevent="submitComment" class="mt-2.5 space-y-2.5 touch-auto" @pointerdown.stop @touchstart.stop>
                            <textarea
                                ref="newCommentInputRef"
                                v-model="newCommentText"
                                placeholder="Tulis feedback revisi atau catatan arsitektur untuk titik ini..."
                                rows="3"
                                required
                                class="w-full resize-none rounded-lg border border-slate-700 bg-slate-950 p-2 text-xs text-white placeholder-slate-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            ></textarea>

                            <p v-if="submitCommentError" class="text-[11px] font-medium text-rose-400">
                                {{ submitCommentError }}
                            </p>

                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="cancelPendingPin"
                                    class="rounded-lg px-2.5 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-white"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="isSubmittingComment || !newCommentText.trim()"
                                    class="flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50"
                                >
                                    <Send class="h-3 w-3" />
                                    <span>{{ isSubmittingComment ? 'Menyimpan...' : 'Simpan Pin' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Collapsible Comments Panel -->
        <div
            v-if="isDrawerOpen"
            class="border-t border-slate-800 bg-slate-900 px-4 py-3 transition-all z-20 max-h-52 overflow-y-auto"
        >
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <MessageSquare class="h-4 w-4 text-rose-400" />
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">
                        Daftar Catatan Pin ({{ comments.length }})
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="handleTambahPinButton"
                        class="flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-rose-500 shadow-sm"
                        title="Tambah Pin Komentar"
                    >
                        <MapPin class="h-3 w-3" />
                        <span>Tambah Pin</span>
                    </button>
                    <button
                        @click="isDrawerOpen = false"
                        class="text-xs text-slate-400 hover:text-white"
                    >
                        Sembunyikan
                    </button>
                </div>
            </div>

            <div v-if="comments.length === 0" class="py-4 text-center text-xs text-slate-500 space-y-2">
                <p>Belum ada pin anotasi pada model ini. Klik pada objek 3D atau tombol di bawah untuk menambahkan catatan revisi.</p>
                <button
                    @click="handleTambahPinButton"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600/90 hover:bg-rose-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition"
                >
                    <MapPin class="h-3.5 w-3.5" />
                    <span>Tambah Pin Baru</span>
                </button>
            </div>

            <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(comment, index) in comments"
                    :key="comment.id"
                    @click="focusComment(comment)"
                    :class="[
                        'group flex cursor-pointer flex-col justify-between rounded-lg border p-2.5 text-xs transition',
                        activeCommentId === comment.id
                            ? 'border-rose-500 bg-slate-800/90'
                            : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-800/40',
                    ]"
                >
                    <div>
                        <div class="flex items-center justify-between font-medium">
                            <span class="text-white line-clamp-1">{{ comment.user?.name ?? 'Reviewer' }}</span>
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-if="canEditComment(comment)"
                                    @click.stop="focusAndEdit(comment)"
                                    class="rounded p-0.5 text-slate-400 hover:text-white transition"
                                    title="Edit komentar ini"
                                >
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button
                                    v-if="canEditComment(comment)"
                                    @click.stop="promptUnpinFromDrawer(comment)"
                                    class="rounded p-0.5 text-slate-400 hover:text-rose-400 transition"
                                    title="Lepas pin & hapus komentar dari database"
                                >
                                    <PinOff class="h-3 w-3" />
                                </button>
                                <span class="text-[10px] text-rose-400 font-semibold">#{{ comments.length - index }}</span>
                            </div>
                        </div>
                        <p class="mt-1 text-slate-300 line-clamp-2">{{ comment.content }}</p>

                        <!-- Drawer inline unpin confirmation -->
                        <div
                            v-if="unpinningCommentId === comment.id"
                            class="mt-2 rounded-md bg-rose-950/60 p-2 border border-rose-500/40 text-[11px] space-y-1.5"
                            @click.stop
                        >
                            <p class="text-rose-200 font-medium">Hapus permanen komentar pin ini?</p>
                            <div class="flex items-center justify-end gap-1.5">
                                <button
                                    type="button"
                                    @click.stop="cancelUnpin($event)"
                                    class="rounded px-2 py-0.5 text-slate-300 hover:text-white transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    @click.stop="executeUnpin(comment.id)"
                                    :disabled="isUnpinning"
                                    class="rounded bg-rose-600 px-2 py-0.5 font-medium text-white hover:bg-rose-500 disabled:opacity-50 transition"
                                >
                                    {{ isUnpinning ? 'Menghapus...' : 'Ya, Hapus' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[10px] text-slate-500">
                        <span>({{ comment.position_x.toFixed(2) }}, {{ comment.position_y.toFixed(2) }}, {{ comment.position_z.toFixed(2) }})</span>
                        <span class="text-rose-400 hover:underline">Lihat di 3D</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Eye,
    EyeOff,
    GripVertical,
    Hand,
    MapPin,
    Maximize2,
    MessageSquare,
    Pencil,
    PinOff,
    RotateCw,
    Send,
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

    controls?.update();
    renderer.render(scene, camera);
    updateProjections();
    animationFrame = window.requestAnimationFrame(render);
}

function initializeViewer(): void {
    if (viewerContainer.value === null) {
        return;
    }

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x020617);

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
    initializeViewer();
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
    if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
    }

    resizeObserver?.disconnect();
    controls?.dispose();
    renderer?.dispose();
});
</script>

<style scoped>
/* Scoped adjustments for crisp WebGL rendering */
canvas {
    touch-action: none;
}
</style>
