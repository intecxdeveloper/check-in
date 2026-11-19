const EMAIL = /[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i;
const PHONE = /(?:\+?\s*6\s*0|0)\s*1\d(?:[\s-]?\d){7,8}|\+\d{1,3}[\s-]?(?:\d[\s-]?){7,14}\d/;
const WEBSITE = /(?:https?:\/\/|www\.)[^\s<>]+/i;

const OCR_MAX_EDGE = 2000;
const OCR_ROTATIONS = [0, 180, 90, 270];

const NOT_A_NAME = /\b(sdn|bhd|berhad|ltd|limited|inc|corp|corporation|company|enterprise|group|solutions?|services?|technolog\w*|consult\w*|holdings?|resources|systems?|digital|global|international|academy|university|institute|agency|jalan|jln|road|street|taman|lorong|persiaran|selangor|kuala|lumpur|malaysia|johor|penang|sabah|sarawak|tel|fax|mobile|phone|email|www|http|director|manager|engineer|officer|executive|consultant|founder|president|head|lead|ceo|cto|cfo|coo|senior|assistant|specialist|analyst|department|division|marketing|sales|business|development)\b/i;
const HONORIFIC = /^(?:dr|ir|ts|prof|dato'?|datuk|datin|tan sri|puan|pn|encik|en|mr|mrs|ms|haji|hj|hajah)\.?\s+/i;
const MALAY_LINK = /\b(bin|binti|bt|a\/l|a\/p)\b/i;
const GENERIC_EMAIL = /^(info|sales|admin|contact|enquir\w*|hello|marketing|support|hr|office|mail|general|service|cs|team)$/i;

function titleCase(text) {
    return text.toLowerCase().replace(/(^|[\s'\-])([a-z])/g, (match, lead, letter) => `${lead}${letter.toUpperCase()}`);
}

function cleanNameLine(line) {
    return line
        .replace(/[|_~*=<>{}[\]"“”]/g, ' ')
        .replace(/^[^A-Za-z]+|[^A-Za-z.]+$/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

function nameFromEmail(email) {
    const local = (email.split('@')[0] || '').replace(/\d+/g, '');
    const parts = local.split(/[._\-]+/).filter((part) => part.length >= 2);
    if (parts.length < 2 || parts.length > 4 || parts.some((part) => GENERIC_EMAIL.test(part))) {
        return '';
    }

    return titleCase(parts.join(' '));
}

/**
 * Picks the most name-like line. Names are usually the largest print on a card,
 * so taller OCR lines score higher, as do lines that match the email address.
 *
 * @param {{text: string, height: number}[]} lines
 */
function guessName(lines, fields) {
    const emailLocal = (fields.email.split('@')[0] || '').toLowerCase().replace(/[^a-z]/g, '');
    const tallest = Math.max(1, ...lines.map((line) => line.height || 0));
    let best = null;

    lines.forEach((line, position) => {
        const clean = cleanNameLine(line.text);
        if (! clean || clean.length > 48 || /\d|@|\.com|\.my|www|https?/i.test(clean) || NOT_A_NAME.test(clean)) {
            return;
        }
        if ([fields.company_name, fields.job_title].includes(line.text.trim())) {
            return;
        }

        let bare = clean;
        while (HONORIFIC.test(bare)) {
            bare = bare.replace(HONORIFIC, '');
        }
        const words = bare.split(/\s+/).filter(Boolean);
        if (words.length < 2 || words.length > 6) {
            return;
        }
        if (words.some((word) => ! /^[A-Za-z][A-Za-z.'\-/@]*$/.test(word))) {
            return;
        }
        if (words.some((word) => ! MALAY_LINK.test(word) && word.replace(/[^A-Za-z]/g, '').length >= 2 && ! /[aeiouy]/i.test(word))) {
            return;
        }
        const letters = bare.replace(/[^A-Za-z]/g, '').length;
        if (letters / bare.length < 0.75 || words.every((word) => word.length <= 2)) {
            return;
        }

        const matchesEmail = emailLocal !== '' && words.some((word) => word.length >= 3 && emailLocal.includes(word.toLowerCase().replace(/[^a-z]/g, '')));
        const capitalised = words.every((word) => MALAY_LINK.test(word) || /^[A-Z]/.test(word));
        if (! capitalised && ! matchesEmail) {
            return;
        }

        let score = ((line.height || 0) / tallest) * 4;
        score += (1 - (position / Math.max(1, lines.length))) * 1.5;
        if (matchesEmail) {
            score += 5;
        }
        if (capitalised) {
            score += 1.5;
        }
        if (MALAY_LINK.test(clean) || HONORIFIC.test(clean)) {
            score += 2;
        }

        if (! best || score > best.score) {
            best = { score, name: clean };
        }
    });

    if (best) {
        return /^[A-Z\s.'\-/@]+$/.test(best.name) ? titleCase(best.name) : best.name;
    }

    return fields.email ? nameFromEmail(fields.email) : '';
}

/**
 * @param {string} text
 * @param {{text: string, height: number}[]} [lines]
 */
export function parseOcr(text, lines = []) {
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

    const measured = lines.length > 0
        ? lines.filter((line) => line.text.trim() !== '')
        : text.split(/\n+/).map((line) => ({ text: line, height: 0 })).filter((line) => line.text.trim() !== '');

    for (const { text: raw } of measured) {
        const line = raw.trim();
        if (! fields.company_name && /\b(sdn\.?\s*bhd\.?|berhad|bhd\.?|ltd\.?|limited|inc\.?|pte\.?\s*ltd|plt)\b/i.test(line)) {
            fields.company_name = line;
        }
        if (! fields.job_title && /\b(ceo|cto|cfo|coo|director|manager|engineer|officer|consultant|founder|president|head|lead|executive|specialist|analyst|architect|designer|coordinator|chairman|partner)\b/i.test(line)) {
            fields.job_title = line;
        }
        if (! fields.address && /\d+.+\b(jalan|jln|road|street|taman|lorong|persiaran|selangor|kuala|malaysia)\b/i.test(line)) {
            fields.address = line;
        }
    }

    fields.full_name = guessName(measured, fields);

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
 * @returns {Promise<{rotation: number, text: string, lines: {text: string, height: number}[], image: HTMLImageElement}|null>}
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
            const lines = (data?.lines || []).map((line) => ({
                text: (line.text || '').trim(),
                height: Math.max(0, (line.bbox?.y1 ?? 0) - (line.bbox?.y0 ?? 0)),
            }));
            best = { rotation, text, score, lines };
        }

        if (confidence >= 60 && words >= 3) {
            break;
        }
    }

    return best ? { rotation: best.rotation, text: best.text, lines: best.lines, image } : null;
}
