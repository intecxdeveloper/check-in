const EMAIL = /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i;
const PHONE = /(?:\+?\s*6\s*0|0)\s*1\d(?:[\s-]?\d){7,8}|\+\d{1,3}[\s-]?(?:\d[\s-]?){7,14}\d/;
const WEBSITE = /(?:https?:\/\/|www\.)[^\s<>]+/i;

const SAMPLE_WIDTH = 160;
const SAMPLE_HEIGHT = 92;
const STABLE_FRAMES = 6;
const MOTION_LIMIT = 14;
const CARD_MARGIN = 0.06;
const OCR_MAX_EDGE = 2000;
const OCR_ROTATIONS = [0, 180, 90, 270];

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

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image'));
        image.src = src;
    });
}

function luma(data, index) {
    return (data[index] * 0.299) + (data[index + 1] * 0.587) + (data[index + 2] * 0.114);
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

function rotatedCanvas(image, rotation, maxEdge) {
    const sourceWidth = image.naturalWidth || image.width;
    const sourceHeight = image.naturalHeight || image.height;
    const scale = Math.min(2.5, maxEdge / Math.max(sourceWidth, sourceHeight, 1));
    const width = Math.max(1, Math.round(sourceWidth * scale));
    const height = Math.max(1, Math.round(sourceHeight * scale));
    const quarterTurn = rotation % 180 !== 0;
    const canvas = document.createElement('canvas');
    canvas.width = quarterTurn ? height : width;
    canvas.height = quarterTurn ? width : height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (! context) {
        return null;
    }

    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate((rotation * Math.PI) / 180);
    context.drawImage(image, -width / 2, -height / 2, width, height);

    return canvas;
}

/**
 * Greyscale plus a 2–98% contrast stretch done on pixels, because canvas
 * `filter` is ignored by iOS Safari.
 */
function ocrCanvas(image, rotation) {
    const canvas = rotatedCanvas(image, rotation, OCR_MAX_EDGE);
    const context = canvas?.getContext('2d', { willReadFrequently: true });
    if (! canvas || ! context) {
        return null;
    }

    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    const { data } = pixels;
    const histogram = new Uint32Array(256);
    const total = canvas.width * canvas.height;

    for (let i = 0; i < data.length; i += 4) {
        const value = Math.round(luma(data, i));
        data[i] = value;
        histogram[value] += 1;
    }

    let low = 0;
    let high = 255;
    let running = 0;
    for (let value = 0; value < 256; value += 1) {
        running += histogram[value];
        if (running >= total * 0.02) {
            low = value;
            break;
        }
    }
    running = 0;
    for (let value = 255; value >= 0; value -= 1) {
        running += histogram[value];
        if (running >= total * 0.02) {
            high = value;
            break;
        }
    }

    const range = Math.max(1, high - low);
    for (let i = 0; i < data.length; i += 4) {
        const stretched = Math.max(0, Math.min(255, ((data[i] - low) * 255) / range));
        data[i] = stretched;
        data[i + 1] = stretched;
        data[i + 2] = stretched;
    }
    context.putImageData(pixels, 0, 0);

    return canvas;
}

function waitForTesseract(timeoutMs = 20000) {
    return new Promise((resolve) => {
        const started = Date.now();
        const check = () => {
            if (window.Tesseract?.createWorker) {
                resolve(window.Tesseract);
            } else if (Date.now() - started > timeoutMs) {
                resolve(null);
            } else {
                window.setTimeout(check, 200);
            }
        };
        check();
    });
}

let ocrWorkerPromise = null;

function ocrWorker() {
    if (! ocrWorkerPromise) {
        ocrWorkerPromise = waitForTesseract().then(async (Tesseract) => {
            if (! Tesseract) {
                return null;
            }

            const worker = await Tesseract.createWorker('eng', 1, { logger: () => {} });
            await worker.setParameters({
                tessedit_pageseg_mode: '11',
                preserve_interword_spaces: '1',
            });

            return worker;
        }).catch(() => null);
    }

    return ocrWorkerPromise;
}

function scoreText(text, confidence) {
    const words = (text.match(/[A-Za-z]{3,}/g) || []).length;

    return { words, score: confidence + (words * 3) };
}

/**
 * Tries the card upright first and only turns it when the text looks like noise,
 * so a card held upside down or sideways still reads.
 */
async function readCard(source) {
    const worker = await ocrWorker();
    if (! worker) {
        return null;
    }

    const image = await loadImage(source);
    let best = null;

    for (const rotation of OCR_ROTATIONS) {
        const canvas = ocrCanvas(image, rotation);
        if (! canvas) {
            continue;
        }

        const { data } = await worker.recognize(canvas);
        const text = (data?.text || '').trim();
        const confidence = Number(data?.confidence || 0);
        const { words, score } = scoreText(text, confidence);

        if (! best || score > best.score) {
            best = { rotation, text, score };
        }

        if (confidence >= 60 && words >= 3) {
            break;
        }
    }

    return best ? { ...best, image } : null;
}

function analyzeFrame(video, region, scratch, previous) {
    if (! region) {
        return null;
    }

    const context = scratch.getContext('2d', { willReadFrequently: true });
    if (! context) {
        return null;
    }

    scratch.width = SAMPLE_WIDTH;
    scratch.height = SAMPLE_HEIGHT;
    context.drawImage(video, region.sx, region.sy, region.sw, region.sh, 0, 0, SAMPLE_WIDTH, SAMPLE_HEIGHT);

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
    const ocrStatus = document.getElementById('card-ocr-status');
    const storeUrl = root.dataset.store;
    const token = root.dataset.csrf;
    const scratch = document.createElement('canvas');
    const ocrQueue = [];
    let stream = null;
    let busy = false;
    let watching = false;
    let armed = true;
    let stableCount = 0;
    let previousSample = null;
    let watchTimer = null;
    let ocrBusy = false;
    let cardsRead = 0;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const setOcrStatus = (message) => {
        if (ocrStatus) {
            ocrStatus.textContent = message;
        }
    };

    const setReady = (ready) => {
        frame?.classList.toggle('is-ready', ready);
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

    const sendOcr = async (ocrUrl, source) => {
        const result = await readCard(source);
        if (! result) {
            setOcrStatus('Text reader did not load. Photos still save; type details on the desk.');

            return;
        }

        const body = { ocr_text: result.text, ...parseOcr(result.text) };
        if (result.rotation !== 0) {
            const upright = rotatedCanvas(result.image, result.rotation, 1600);
            if (upright) {
                body.image_base64 = upright.toDataURL('image/jpeg', 0.9);
            }
        }

        await fetch(ocrUrl, {
            method: 'PATCH',
            headers: {
                ...csrfHeaders(token),
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        cardsRead += 1;
        const name = body.full_name || body.company_name;
        setOcrStatus(name
            ? `Read: ${name}`
            : `Card ${cardsRead} saved. No clear text, so check it on the desk.`);
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
        setOcrStatus(ocrQueue.length > 0 ? `Reading text… ${ocrQueue.length + 1} cards waiting` : 'Reading text…');
        try {
            await sendOcr(job.ocrUrl, job.source);
        } catch {
            setOcrStatus('Could not read that card. The photo is still on the desk.');
        } finally {
            ocrBusy = false;
            pumpOcr();
        }
    };

    const captureFrame = () => {
        const region = frameRegion(video, frame) ?? {
            sx: 0,
            sy: 0,
            sw: video.videoWidth || 1280,
            sh: video.videoHeight || 720,
        };
        canvas.width = Math.max(1, Math.round(region.sw));
        canvas.height = Math.max(1, Math.round(region.sh));
        const context = canvas.getContext('2d');
        if (! context) {
            throw new Error('canvas');
        }
        context.drawImage(video, region.sx, region.sy, region.sw, region.sh, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/jpeg', 0.9);
    };

    const upload = async (imageBase64) => {
        busy = true;
        armed = false;
        stableCount = 0;
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

        const sample = analyzeFrame(video, frameRegion(video, frame), scratch, previousSample);
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
                try {
                    upload(captureFrame());
                } catch {
                    setStatus('Could not capture that frame. Hold the card steady.');
                    busy = false;
                    armed = true;
                    setReady(false);
                }
            }

            return;
        }

        stableCount = 0;
        setReady(false);
        setStatus(sample.aligned ? 'Keep the card still.' : 'Lay the card flat inside the frame.');
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
                    width: { ideal: 2560 },
                    height: { ideal: 1440 },
                },
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            watching = true;
            setStatus('Lay the card flat inside the frame. It captures by itself.');
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
    ocrWorker();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
