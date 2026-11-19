const EMAIL = /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i;
const PHONE = /(?:\+?\s*6\s*0|0)\s*1\d(?:[\s-]?\d){7,8}|\+\d{1,3}[\s-]?(?:\d[\s-]?){7,14}\d/;
const WEBSITE = /(?:https?:\/\/|www\.)[^\s<>]+/i;

const OCR_MAX_EDGE = 2000;
const OCR_ROTATIONS = [0, 180, 90, 270];

export function parseOcr(text) {
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

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image'));
        image.src = src;
    });
}

export function rotatedCanvas(image, rotation, maxEdge) {
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
 * Greyscale plus a 2–98% contrast stretch, which lifts faint print on glossy cards.
 */
function enhancedCanvas(image, rotation) {
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
        const value = Math.round((data[i] * 0.299) + (data[i + 1] * 0.587) + (data[i + 2] * 0.114));
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

function waitForTesseract(timeoutMs = 30000) {
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

let workerPromise = null;

export function ocrWorker() {
    if (! workerPromise) {
        workerPromise = waitForTesseract().then(async (Tesseract) => {
            if (! Tesseract) {
                return null;
            }

            const worker = await Tesseract.createWorker('eng', 1, { logger: () => {} });
            await worker.setParameters({
                tessedit_pageseg_mode: '11',
                preserve_interword_spaces: '1',
            });

            return worker;
        }).catch(() => {
            workerPromise = null;

            return null;
        });
    }

    return workerPromise;
}

/**
 * Reads the card upright first and only turns it when the text looks like noise,
 * so a card photographed upside down or sideways still reads.
 *
 * @returns {Promise<{rotation: number, text: string, image: HTMLImageElement}|null>}
 */
export async function readCard(source) {
    const worker = await ocrWorker();
    if (! worker) {
        return null;
    }

    const image = await loadImage(source);
    let best = null;

    for (const rotation of OCR_ROTATIONS) {
        const canvas = enhancedCanvas(image, rotation);
        if (! canvas) {
            continue;
        }

        const { data } = await worker.recognize(canvas);
        const text = (data?.text || '').trim();
        const confidence = Number(data?.confidence || 0);
        const words = (text.match(/[A-Za-z]{3,}/g) || []).length;
        const score = confidence + (words * 3);

        if (! best || score > best.score) {
            best = { rotation, text, score };
        }

        if (confidence >= 60 && words >= 3) {
            break;
        }
    }

    return best ? { rotation: best.rotation, text: best.text, image } : null;
}
