const EMAIL = /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i;
const PHONE = /(?:\+?\s*6\s*0|0)\s*1\d(?:[\s-]?\d){7,8}|\+\d{1,3}[\s-]?(?:\d[\s-]?){7,14}\d/;
const WEBSITE = /(?:https?:\/\/|www\.)[^\s<>]+/i;

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

async function readText(blob) {
    if (typeof window.Tesseract?.recognize !== 'function') {
        return '';
    }

    try {
        const result = await window.Tesseract.recognize(blob, 'eng', { logger: () => {} });

        return (result?.data?.text || '').trim();
    } catch {
        return '';
    }
}

function boot() {
    const root = document.getElementById('card-capture');
    if (! root) {
        return;
    }

    const video = document.getElementById('card-video');
    const canvas = document.getElementById('card-canvas');
    const preview = document.getElementById('card-preview');
    const shutter = document.getElementById('shutter');
    const retake = document.getElementById('card-retake');
    const fileInput = document.getElementById('card-file');
    const status = document.getElementById('card-scan-status');
    const storeUrl = root.dataset.store;
    const token = root.dataset.csrf;
    let stream = null;
    let busy = false;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const showPreview = (url) => {
        preview.src = url;
        preview.hidden = false;
        video.hidden = true;
        retake.hidden = false;
        shutter.hidden = true;
    };

    const showLive = () => {
        preview.hidden = true;
        preview.removeAttribute('src');
        video.hidden = false;
        retake.hidden = true;
        shutter.hidden = false;
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
            setStatus('Align the card, then tap the shutter.');
        } catch {
            setStatus('Camera permission was denied. You can still pick a photo from Album.');
        }
    };

    const captureFrame = () => new Promise((resolve, reject) => {
        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 720;
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        if (! context) {
            reject(new Error('canvas'));
            return;
        }
        context.drawImage(video, 0, 0, width, height);
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
            } else {
                reject(new Error('blob'));
            }
        }, 'image/jpeg', 0.86);
    });

    const upload = async (blob) => {
        if (busy) {
            return;
        }
        busy = true;
        showPreview(URL.createObjectURL(blob));
        setStatus('Reading the card…');

        const ocrText = await readText(blob);
        const fields = parseOcr(ocrText);
        const body = new FormData();
        body.append('image', blob, 'card.jpg');
        body.append('ocr_text', ocrText);
        Object.entries(fields).forEach(([key, value]) => {
            if (value) {
                body.append(key, value);
            }
        });

        setStatus('Sending to the desk…');

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: csrfHeaders(token),
                body,
                credentials: 'same-origin',
            });
            if (! response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || 'Upload failed.');
            }
            setStatus('Saved on the desk. Photograph the next card.');
            window.setTimeout(() => {
                showLive();
                setStatus('Align the next card, then tap the shutter.');
            }, 1200);
        } catch (error) {
            setStatus(error.message || 'Could not send that photo. Try again.');
            retake.hidden = false;
        } finally {
            busy = false;
        }
    };

    shutter?.addEventListener('click', async () => {
        try {
            const blob = await captureFrame();
            await upload(blob);
        } catch {
            setStatus('Could not capture that frame. Try Album instead.');
        }
    });

    retake?.addEventListener('click', () => {
        showLive();
        setStatus('Align the card, then tap the shutter.');
    });

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        fileInput.value = '';
        if (file) {
            await upload(file);
        }
    });

    startCamera();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
