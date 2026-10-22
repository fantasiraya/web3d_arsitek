type CaptureTask = {
    id: string;
    run: () => void;
};

const captureQueue: CaptureTask[] = [];
let activeCaptureId: string | null = null;

export function enqueueAutoCapture(id: string, run: () => void) {
    if (activeCaptureId === id || captureQueue.some((t) => t.id === id)) {
        return;
    }
    captureQueue.push({ id, run });
    processCaptureQueue();
}

function processCaptureQueue() {
    if (activeCaptureId !== null || captureQueue.length === 0) return;
    const nextTask = captureQueue.shift();
    if (!nextTask) return;
    activeCaptureId = nextTask.id;
    nextTask.run();
}

export function releaseAutoCapture(id: string) {
    if (activeCaptureId === id) {
        activeCaptureId = null;
        setTimeout(() => {
            processCaptureQueue();
        }, 120);
    }
}

export function removeFromCaptureQueue(id: string) {
    const idx = captureQueue.findIndex((t) => t.id === id);
    if (idx !== -1) {
        captureQueue.splice(idx, 1);
    }
    if (activeCaptureId === id) {
        releaseAutoCapture(id);
    }
}

