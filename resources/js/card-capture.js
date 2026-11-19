const EMAIL = /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i;
const PHONE = /(?:\+?\s*6\s*0|0)\s*1\d(?:[\s-]?\d){7,8}|\+\d{1,3}[\s-]?(?:\d[\s-]?){7,14}\d/;
const WEBSITE = /(?:https?:\/\/|www\.)[^\s<>]+/i;

const SAMPLE_WIDTH = 160;
const SAMPLE_HEIGHT = 90;
const STABLE_FRAMES = 6;
const MOTION_LIMIT = 14;
const FRAME_X = 0.07;
const FRAME_Y = 0.18;
const FRAME_W = 0.86;
const FRAME_H = 0.64;

function parseOcr(text) {
    const fields = {
        full_name: '',
        job_title: '',
        company_name: '',
        email: '',
        phone: '',
        website: '',
        address: '',
    };
    const email = text.match(EMAIL);
    const phone = text.match(PHONE);
    const website = text.match(WEBSITE);

    if (email) {
        fields.email = email[0].toLowerCase();
    }
    if (phone) {
        fields.phone = phone[0].replace(/[^\d+]/g, '');
    }
    if (website) {
        const url = website[0].replace(/[.,);]+$/, '');
        fields.website = /^https?:/i.test(url) ? url : `https://${url}`;
    }

    const lines = text.split(/\n+/).map((line) => line.trim()).filter(Boolean);
    for (const line of lines) {
        if (! fields.company_name && /\b(sdn\.?\s*bhd\.?|berhad|ltd\.?|limited|inc\.?)\b/i.test(line)) {
            fields.company_name = line;
        }
        if (! fields.job_title && /\b(ceo|cto|director|manager|engineer|officer|consultant|founder|president|head|lead)\b/i.test(line)) {
            fields.job_title = line;
        }
        if (! fields.address && /\d+.+\b(jalan|jln|road|street|selangor|kuala|malaysia)\b/i.test(line)) {
            fields.address = line;
        }
    }

    for (const line of lines) {
        const words = line.split(/\s+/);
        if (words.length >= 2 && words.length <= 5 && ! /\d|@/.test(line) && line !== fields.company_name && line !== fields.job_title) {
            fields.full_name = line;
            break;
        }
    }

    return fields;
}

function csrfHeaders(token) {
    return {
        'X-CSRF-TOKEN': token,
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function dataUrlFromBlob(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(new Error('read'));
        reader.readAsDataURL(blob);
    });
}

function enhanceForOcr(source) {
    return createImageBitmap(source).then((bitmap) => {
        const scale = Math.min(2, 1400 / Math.max(bitmap.width, 1));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const context = canvas.getContext('2d');
        if (! context) {
            bitmap.close();

            return source;
        }
        context.filter = 'grayscale(1) contrast(1.45) brightness(1.08)';
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        return canvas;
    }).catch(() => source);
}

let ocrWorkerPromise = null;

function ocrWorker() {
    const Tesseract = window.Tesseract;
    if (! Tesseract?.createWorker) {
        return null;
    }

    if (! ocrWorkerPromise) {
        ocrWorkerPromise = Tesseract.createWorker('eng', 1).then(async (worker) => {
            await worker.setParameters({ tessedit_pageseg_mode: '6' });

            return worker;
        });
    }

    return ocrWorkerPromise;
}

async function readText(source) {
    const Tesseract = window.Tesseract;
    if (! Tesseract) {
        return '';
    }

    const enhanced = await enhanceForOcr(source);

    try {
        const worker = await ocrWorker();
        if (worker) {
            const result = await worker.recognize(enhanced);

            return (result?.data?.text || '').trim();
        }

        if (typeof Tesseract.recognize === 'function') {
            const result = await Tesseract.recognize(enhanced, 'eng');

            return (result?.data?.text || '').trim();
        }
    } catch {
        return '';
    }

    return '';
}

function luma(data, index) {
    return (data[index] * 0.299) + (data[index + 1] * 0.587) + (data[index + 2] * 0.114);
}

function analyzeFrame(video, scratch, previous) {
    const width = video.videoWidth;
    const height = video.videoHeight;
    if (! width || ! height) {
        return null;
    }

    const context = scratch.getContext('2d', { willReadFrequently: true });
    if (! context) {
        return null;
    }

    scratch.width = SAMPLE_WIDTH;
    scratch.height = SAMPLE_HEIGHT;
    context.drawImage(
        video,
        width * FRAME_X,
        height * FRAME_Y,
        width * FRAME_W,
        height * FRAME_H,
        0,
        0,
        SAMPLE_WIDTH,
        SAMPLE_HEIGHT,
    );

    const { data } = context.getImageData(0, 0, SAMPLE_WIDTH, SAMPLE_HEIGHT);
    const pixels = SAMPLE_WIDTH * SAMPLE_HEIGHT;
    let sum = 0;
    let sumSq = 0;
    let edge = 0;
    let motion = 0;

    for (let i = 0; i < data.length; i += 4) {
        const value = luma(data, i);
        sum += value;
        sumSq += value * value;
    }

    for (let y = 1; y < SAMPLE_HEIGHT - 1; y += 2) {
        for (let x = 1; x < SAMPLE_WIDTH - 1; x += 2) {
            const index = ((y * SAMPLE_WIDTH) + x) * 4;
            const center = luma(data, index);
            const right = luma(data, index + 4);
            const down = luma(data, ((y + 1) * SAMPLE_WIDTH + x) * 4);
            edge += Math.abs(center - right) + Math.abs(center - down);
        }
    }

    if (previous) {
        for (let i = 0; i < data.length; i += 16) {
            motion += Math.abs(luma(data, i) - luma(previous, i));
        }
        motion /= (data.length / 16);
    } else {
        motion = MOTION_LIMIT + 1;
    }

    const mean = sum / pixels;
    const variance = (sumSq / pixels) - (mean * mean);
    const sharp = edge / pixels;

    return {
        mean,
        variance,
        sharp,
        motion,
        data,
        aligned: mean > 50 && mean < 220 && variance > 280 && sharp > 4.2,
        still: motion < MOTION_LIMIT,
        empty: mean < 42 || variance < 90,
    };
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
    const fileInput = document.getElementById('card-file');
    const status = document.getElementById('card-scan-status');
    const storeUrl = root.dataset.store;
    const token = root.dataset.csrf;
    const scratch = document.createElement('canvas');
    let stream = null;
    let busy = false;
    let watching = false;
    let armed = true;
    let stableCount = 0;
    let previousSample = null;
    let watchTimer = null;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const setReady = (ready) => {
        frame?.classList.toggle('is-ready', ready);
        root.classList.toggle('is-locked', ready);
    };

    const showPreview = (url) => {
        preview.src = url;
        preview.hidden = false;
        video.hidden = true;
        setReady(false);
    };

    const showLive = () => {
        preview.hidden = true;
        preview.removeAttribute('src');
        video.hidden = false;
    };

    const ocrQueue = [];
    let ocrBusy = false;

    const sendOcr = async (ocrUrl, source) => {
        const ocrText = await readText(source);
        const fields = parseOcr(ocrText);
        await fetch(ocrUrl, {
            method: 'PATCH',
            headers: {
                ...csrfHeaders(token),
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ocr_text: ocrText, ...fields }),
        });
    };

    const pumpOcr = async () => {
        if (ocrBusy) {
            return;
        }
        const job = ocrQueue.shift();
        if (! job) {
            return;
        }
        ocrBusy = true;
        try {
            await sendOcr(job.ocrUrl, job.source);
        } catch {
            // The photo is already on the desk; staff can type what OCR missed.
        } finally {
            ocrBusy = false;
            pumpOcr();
        }
    };

    const captureFrame = () => new Promise((resolve, reject) => {
        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 720;
        canvas.width = Math.max(1, Math.round(width * FRAME_W));
        canvas.height = Math.max(1, Math.round(height * FRAME_H));
        const context = canvas.getContext('2d');
        if (! context) {
            reject(new Error('canvas'));
            return;
        }
        context.drawImage(
            video,
            width * FRAME_X,
            height * FRAME_Y,
            width * FRAME_W,
            height * FRAME_H,
            0,
            0,
            canvas.width,
            canvas.height,
        );
        resolve(canvas.toDataURL('image/jpeg', 0.88));
    });

    const upload = async (imageBase64, previewUrl = imageBase64) => {
        busy = true;
        armed = false;
        stableCount = 0;
        showPreview(previewUrl);
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
                throw new Error(payload.message || 'Upload failed.');
            }
            const payload = await response.json();
            if (payload.ocr_url) {
                ocrQueue.push({ ocrUrl: payload.ocr_url, source: imageBase64 });
                pumpOcr();
            }
            setStatus('Saved. Lift the card, then hold the next one.');
            window.setTimeout(() => {
                showLive();
                busy = false;
                setStatus('Waiting for the next card.');
            }, 450);
        } catch (error) {
            setStatus(error.message || 'Could not send that photo. Hold the card in the frame again.');
            showLive();
            busy = false;
            armed = true;
        }
    };

    const watch = () => {
        if (busy || video.hidden || ! watching) {
            return;
        }

        const sample = analyzeFrame(video, scratch, previousSample);
        if (! sample) {
            return;
        }
        previousSample = sample.data;

        if (! armed) {
            if (sample.empty || ! sample.aligned) {
                armed = true;
                stableCount = 0;
                setStatus('Hold the next card in the frame.');
            }
            return;
        }

        if (sample.aligned && sample.still) {
            stableCount += 1;
            setReady(true);
            setStatus('Hold still…');
            if (stableCount >= STABLE_FRAMES) {
                busy = true;
                armed = false;
                captureFrame().then(upload).catch(() => {
                    setStatus('Could not capture that frame. Hold the card steady.');
                    busy = false;
                    armed = true;
                    setReady(false);
                });
            }
            return;
        }

        stableCount = 0;
        setReady(false);
        if (sample.aligned) {
            setStatus('Keep the card still.');
        } else {
            setStatus('Fill the frame with the card.');
        }
    };

    const startCamera = async () => {
        if (! navigator.mediaDevices?.getUserMedia) {
            setStatus('This browser cannot open the camera. Use Album instead.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 } },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            watching = true;
            setStatus('Fill the frame with the card. It captures by itself.');
            watchTimer = window.setInterval(watch, 120);
        } catch {
            setStatus('Camera permission was denied. You can still pick a photo from Album.');
        }
    };

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        fileInput.value = '';
        if (! file || busy) {
            return;
        }
        const dataUrl = await dataUrlFromBlob(file);
        await upload(dataUrl);
    });

    window.addEventListener('pagehide', () => {
        if (watchTimer) {
            window.clearInterval(watchTimer);
        }
        stream?.getTracks().forEach((track) => track.stop());
    });

    startCamera();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
