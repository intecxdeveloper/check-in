const CARD_MARGIN = 0.06;
const UPLOAD_MAX_EDGE = 1600;
const UPLOAD_QUALITY = 0.85;

function csrfHeaders(token) {
    return {
        'X-CSRF-TOKEN': token,
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image'));
        image.src = src;
    });
}

/**
 * Draws a region of a video or image into a JPEG capped at UPLOAD_MAX_EDGE,
 * keeping uploads small enough for proxy body limits.
 */
function toUploadDataUrl(canvas, source, region) {
    const scale = Math.min(1, UPLOAD_MAX_EDGE / Math.max(region.sw, region.sh, 1));
    canvas.width = Math.max(1, Math.round(region.sw * scale));
    canvas.height = Math.max(1, Math.round(region.sh * scale));
    const context = canvas.getContext('2d');
    if (! context) {
        throw new Error('canvas');
    }
    context.drawImage(source, region.sx, region.sy, region.sw, region.sh, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', UPLOAD_QUALITY);
}

/**
 * Maps the on-screen guide frame to video pixels. The video is shown with
 * object-fit: cover, so the visible area is a scaled, centred slice of the stream.
 */
function frameRegion(video, frame) {
    const videoWidth = video.videoWidth;
    const videoHeight = video.videoHeight;
    if (! videoWidth || ! videoHeight || ! frame) {
        return null;
    }

    const view = video.getBoundingClientRect();
    const box = frame.getBoundingClientRect();
    if (! view.width || ! view.height || ! box.width || ! box.height) {
        return null;
    }

    const scale = Math.max(view.width / videoWidth, view.height / videoHeight);
    const offsetX = (view.width - (videoWidth * scale)) / 2;
    const offsetY = (view.height - (videoHeight * scale)) / 2;
    const padX = box.width * CARD_MARGIN;
    const padY = box.height * CARD_MARGIN;

    const sx = Math.max(0, (box.left - padX - view.left - offsetX) / scale);
    const sy = Math.max(0, (box.top - padY - view.top - offsetY) / scale);
    const sw = Math.min(videoWidth - sx, (box.width + (padX * 2)) / scale);
    const sh = Math.min(videoHeight - sy, (box.height + (padY * 2)) / scale);

    return { sx, sy, sw, sh };
}

function uploadErrorMessage(status, payload) {
    if (status === 413) {
        return 'The photo was too large for the server. Try again.';
    }
    if (status === 404) {
        return 'This scan link has expired. Scan the new QR on the desk.';
    }
    if (status === 419) {
        return 'The page timed out. Reload and try again.';
    }
    if (status === 429) {
        return 'Too many photos at once. Wait a moment.';
    }

    return payload.message || `Upload failed (${status}). Try again.`;
}

function boot() {
    const root = document.getElementById('card-capture');
    if (! root) {
        return;
    }

    const video = document.getElementById('card-video');
    const canvas = document.getElementById('card-canvas');
    const preview = document.getElementById('card-preview');
    const frame = document.querySelector('.card-scan-frame');
    const shutter = document.getElementById('card-shutter');
    const fileInput = document.getElementById('card-file');
    const status = document.getElementById('card-scan-status');
    const counter = document.getElementById('card-scan-count');
    const storeUrl = root.dataset.store;
    const token = root.dataset.csrf;
    let stream = null;
    let busy = false;
    let saved = 0;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const setBusy = (value) => {
        busy = value;
        if (shutter) {
            shutter.disabled = value || ! stream;
        }
    };

    const showPreview = (url) => {
        preview.src = url;
        preview.hidden = false;
        video.hidden = true;
    };

    const showLive = () => {
        preview.hidden = true;
        preview.removeAttribute('src');
        video.hidden = false;
    };

    const upload = async (imageBase64) => {
        setBusy(true);
        showPreview(imageBase64);
        setStatus('Saving the photo…');

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    ...csrfHeaders(token),
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ image_base64: imageBase64 }),
            });
            if (! response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(uploadErrorMessage(response.status, payload));
            }
            saved += 1;
            if (counter) {
                counter.textContent = `${saved} saved`;
            }
            setStatus('Saved. Place the next card and tap the shutter.');
            window.setTimeout(() => {
                showLive();
                setBusy(false);
            }, 450);
        } catch (error) {
            setStatus(error instanceof TypeError
                ? 'No connection to the desk. Check Wi-Fi and try again.'
                : (error.message || 'Could not send that photo. Try again.'));
            window.setTimeout(() => {
                showLive();
                setBusy(false);
            }, 1800);
        }
    };

    const snap = () => {
        if (busy || video.hidden || ! video.videoWidth) {
            return;
        }
        try {
            const region = frameRegion(video, frame) ?? {
                sx: 0,
                sy: 0,
                sw: video.videoWidth,
                sh: video.videoHeight,
            };
            upload(toUploadDataUrl(canvas, video, region));
        } catch {
            setStatus('Could not capture that frame. Try again.');
            setBusy(false);
        }
    };

    const startCamera = async () => {
        if (! navigator.mediaDevices?.getUserMedia) {
            setStatus('This browser cannot open the camera. Use Album instead.');

            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            setBusy(false);
            setStatus('Fit the card inside the frame, then tap the shutter.');
        } catch {
            setStatus('Camera permission was denied. You can still pick a photo from Album.');
        }
    };

    shutter?.addEventListener('click', snap);

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        fileInput.value = '';
        if (! file || busy) {
            return;
        }
        const url = URL.createObjectURL(file);
        try {
            const image = await loadImage(url);
            await upload(toUploadDataUrl(canvas, image, {
                sx: 0,
                sy: 0,
                sw: image.naturalWidth,
                sh: image.naturalHeight,
            }));
        } catch {
            setStatus('That photo could not be opened. Try another one.');
        } finally {
            URL.revokeObjectURL(url);
        }
    });

    window.addEventListener('pagehide', () => {
        stream?.getTracks().forEach((track) => track.stop());
    });

    startCamera();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
