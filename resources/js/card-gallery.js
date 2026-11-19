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

    const applyFeed = (incoming) => {
        const previousLatest = cards[0]?.id ?? 0;
        cards = incoming;
        if (cards.length > 0 && (index >= cards.length || incoming[0]?.id !== previousLatest)) {
            index = 0;
        }
        if (incoming[0]?.id > previousLatest && status) {
            status.textContent = 'New card received';
        }
        render();
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
