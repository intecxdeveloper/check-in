import { ocrWorker, parseOcr, readCard, rotatedCanvas } from './card-ocr';

const TABLE_FIELDS = ['full_name', 'job_title', 'company_name', 'email', 'phone', 'website', 'address'];

function parseCards(raw) {
    try {
        const parsed = JSON.parse(raw || '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function drawQr(canvas, url) {
    const draw = () => {
        if (typeof window.QRCode?.toCanvas !== 'function') {
            return false;
        }

        window.QRCode.toCanvas(canvas, url, {
            width: 220,
            margin: 1,
            color: {
                dark: '#00254e',
                light: '#ffffff',
            },
        }, (error) => {
            if (error) {
                canvas.replaceWith(fallbackImage(url));
            }
        });

        return true;
    };

    if (draw()) {
        return;
    }

    let attempts = 0;
    const timer = setInterval(() => {
        attempts += 1;
        if (draw() || attempts >= 20) {
            clearInterval(timer);
            if (typeof window.QRCode?.toCanvas !== 'function') {
                canvas.replaceWith(fallbackImage(url));
            }
        }
    }, 150);
}

function fallbackImage(url) {
    const img = document.createElement('img');
    img.width = 220;
    img.height = 220;
    img.alt = 'QR code for the phone camera';
    img.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=${encodeURIComponent(url)}`;

    return img;
}

function createCoverFlow(root) {
    const stage = root.querySelector('[data-stage]');
    const empty = root.querySelector('[data-empty]');
    const count = root.querySelector('[data-count]');
    const prev = root.querySelector('[data-prev]');
    const next = root.querySelector('[data-next]');
    const caption = document.getElementById('card-gallery-caption');
    const status = document.getElementById('card-gallery-status');
    let cards = parseCards(root.dataset.cards);
    let index = 0;
    let startX = 0;

    const render = () => {
        stage.querySelectorAll('.card-coverflow-item').forEach((node) => node.remove());

        if (cards.length === 0) {
            empty.hidden = false;
            if (caption) {
                caption.hidden = true;
            }
            if (count) {
                count.textContent = '';
            }
            return;
        }

        empty.hidden = true;
        cards.forEach((card, i) => {
            const article = document.createElement('article');
            article.className = 'card-coverflow-item';
            article.style.setProperty('--offset', String(i - index));
            article.dataset.active = i === index ? 'true' : 'false';
            const img = document.createElement('img');
            img.src = card.image_url || '';
            img.alt = card.full_name || 'Business card';
            article.append(img);
            article.addEventListener('click', () => {
                index = i;
                render();
            });
            stage.append(article);
        });

        const current = cards[index];
        if (count) {
            count.textContent = `${index + 1} / ${cards.length}`;
        }
        if (caption && current) {
            caption.hidden = false;
            caption.innerHTML = '';
            const title = document.createElement('p');
            title.className = 'card-coverflow-name';
            title.textContent = current.full_name || 'Unnamed card';
            const meta = document.createElement('p');
            meta.className = 'card-coverflow-meta';
            meta.textContent = [current.job_title, current.company_name, current.email, current.phone]
                .filter(Boolean)
                .join(' · ');
            caption.append(title, meta);
        }
    };

    const go = (delta) => {
        if (cards.length === 0) {
            return;
        }
        index = (index + delta + cards.length) % cards.length;
        render();
    };

    prev?.addEventListener('click', () => go(-1));
    next?.addEventListener('click', () => go(1));

    root.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            go(-1);
        }
        if (event.key === 'ArrowRight') {
            go(1);
        }
    });
    root.tabIndex = 0;

    stage.addEventListener('pointerdown', (event) => {
        startX = event.clientX;
    });
    stage.addEventListener('pointerup', (event) => {
        const delta = event.clientX - startX;
        if (delta > 40) {
            go(-1);
        } else if (delta < -40) {
            go(1);
        }
    });

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const ocrQueue = [];
    const ocrSeen = new Set();
    let ocrBusy = false;
    let unseenRows = 0;

    const fillTableRow = (card) => {
        const form = document.querySelector(`[data-card-row="${card.id}"]`);
        if (! form) {
            unseenRows += 1;

            return;
        }
        TABLE_FIELDS.forEach((name) => {
            const input = form.elements.namedItem(name);
            if (input && ! input.value && card[name]) {
                input.value = card[name];
            }
        });
    };

    const replaceCard = (updated) => {
        cards = cards.map((card) => (card.id === updated.id ? updated : card));
        render();
    };

    const saveOcr = async (card) => {
        const result = await readCard(card.image_url);
        if (! result) {
            throw new Error('reader');
        }
        const body = { ocr_text: result.text, ...parseOcr(result.text) };
        if (result.rotation !== 0) {
            const upright = rotatedCanvas(result.image, result.rotation, 1600);
            if (upright) {
                body.image_base64 = upright.toDataURL('image/jpeg', 0.88);
            }
        }

        const response = await fetch(card.ocr_url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': root.dataset.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        if (! response.ok) {
            throw new Error(String(response.status));
        }
        const payload = await response.json();
        if (payload.card) {
            if (result.rotation !== 0 && payload.card.image_url) {
                payload.card.image_url = `${payload.card.image_url}?v=${Date.now()}`;
            }
            replaceCard(payload.card);
            fillTableRow(payload.card);
        }

        return payload.card;
    };

    const pumpOcr = async () => {
        if (ocrBusy) {
            return;
        }
        const card = ocrQueue.shift();
        if (! card) {
            return;
        }
        ocrBusy = true;
        setStatus(`Reading card text… ${ocrQueue.length + 1} left`);
        try {
            const updated = await saveOcr(card);
            const name = updated?.full_name || updated?.company_name;
            setStatus(name ? `Read: ${name}` : 'No clear text on that card. Type it in the table.');
            if (ocrQueue.length === 0 && unseenRows > 0) {
                setStatus(`${name ? `Read: ${name}. ` : ''}Reload to edit ${unseenRows === 1 ? 'the new card' : `${unseenRows} new cards`} in the table.`);
            }
        } catch (error) {
            ocrSeen.delete(card.id);
            setStatus(error.message === 'reader'
                ? 'Text reader did not load. Photos are saved; it will retry.'
                : 'Could not read a card. It will retry shortly.');
        } finally {
            ocrBusy = false;
            pumpOcr();
        }
    };

    const queueOcr = (list) => {
        list.forEach((card) => {
            if (card.ocr_url && card.image_url && ! ocrSeen.has(card.id)) {
                ocrSeen.add(card.id);
                ocrQueue.push(card);
            }
        });
        if (ocrQueue.length > 0) {
            pumpOcr();
        }
    };

    const applyFeed = (incoming) => {
        const previousLatest = cards[0]?.id ?? 0;
        const cacheBusted = new Map(cards.map((card) => [card.id, card.image_url]));
        cards = incoming.map((card) => {
            const known = cacheBusted.get(card.id);

            return known && known.startsWith(card.image_url || '\u0000') ? { ...card, image_url: known } : card;
        });
        if (cards.length > 0 && (index >= cards.length || incoming[0]?.id !== previousLatest)) {
            index = 0;
        }
        if (incoming[0]?.id > previousLatest) {
            setStatus('New card received');
        }
        render();
        queueOcr(incoming);
    };

    const poll = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(root.dataset.feed, {
                headers: { Accept: 'application/json' },
            });
            if (! response.ok) {
                return;
            }
            const payload = await response.json();
            if (Array.isArray(payload.cards)) {
                applyFeed(payload.cards);
            }
        } catch {
            // Keep the last good gallery if the desk Wi-Fi blips.
        }
    };

    render();
    ocrWorker();
    queueOcr(cards);
    const timer = setInterval(poll, 2500);
    window.addEventListener('beforeunload', () => clearInterval(timer));
}

const gallery = document.getElementById('card-gallery');
const qrCanvas = document.getElementById('card-qr');

if (qrCanvas && gallery?.dataset.scanUrl) {
    drawQr(qrCanvas, gallery.dataset.scanUrl);
}

if (gallery) {
    createCoverFlow(gallery);
}
