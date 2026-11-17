const CLAIM_STORAGE_KEY = 'expo-claim-code';

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function readStoredClaimCode() {
    try {
        return sessionStorage.getItem(CLAIM_STORAGE_KEY) || '';
    } catch {
        return '';
    }
}

export function storeGiftClaimCode(code) {
    if (! code) {
        return;
    }

    try {
        sessionStorage.setItem(CLAIM_STORAGE_KEY, code);
    } catch {
        // Private mode can block sessionStorage; the form still works.
    }
}

export function createGiftWheel(root) {
    const modal = root.querySelector('#gift-modal');
    const form = root.querySelector('#gift-form');
    const wheel = root.querySelector('#gift-wheel');
    const claimInput = root.querySelector('#gift-claim-code');
    const statusEl = root.querySelector('#gift-status');
    const resultEl = root.querySelector('#gift-result');
    const spinButton = root.querySelector('#gift-spin');
    const prizes = JSON.parse(root.dataset.giftPrizes || '[]');
    const spinUrl = root.dataset.giftSpinUrl || '/api/gift-spin';
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let busy = false;
    let currentTurns = 0;

    if (! modal || ! form || ! wheel || prizes.length === 0) {
        return { open() {} };
    }

    paintWheel(wheel, prizes);

    const openers = [root.querySelector('#gift-open'), root.querySelector('#gift-open-mobile')];
    openers.forEach((button) => {
        button?.addEventListener('click', () => open());
    });

    root.querySelector('#gift-close')?.addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! modal.classList.contains('hidden')) {
            close();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await spin();
    });

    claimInput?.addEventListener('input', () => {
        claimInput.value = claimInput.value.toUpperCase();
    });

    function open() {
        if (claimInput && ! claimInput.value) {
            claimInput.value = readStoredClaimCode();
        }
        resultEl?.classList.add('hidden');
        setStatus('');
        modal.classList.remove('hidden');
        claimInput?.focus();
    }

    function close() {
        if (busy) {
            return;
        }
        modal.classList.add('hidden');
    }

    function setStatus(message, isError = false) {
        if (! statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.toggle('is-error', isError);
    }

    async function spin() {
        if (busy) {
            return;
        }

        const claimCode = (claimInput?.value || '').trim().toUpperCase();
        if (! claimCode) {
            setStatus('Enter the claim code from the check-in receipt.', true);
            claimInput?.focus();
            return;
        }

        busy = true;
        spinButton.disabled = true;
        resultEl?.classList.add('hidden');
        setStatus('Spinning…');

        try {
            const payload = await window.expoFetch(spinUrl, {
                method: 'POST',
                body: JSON.stringify({ claim_code: claimCode }),
            });
            const prize = payload.prize;
            if (! prize) {
                throw new Error('The wheel did not land on a gift.');
            }

            if (! payload.master_spin) {
                storeGiftClaimCode(payload.claim_code || claimCode);
            }
            const index = prizeIndex(prize);
            await animateTo(index);
            markWinner(index);
            showResult(payload);
        } catch (error) {
            setStatus(error.message || 'The wheel did not spin. Try again.', true);
        } finally {
            busy = false;
            spinButton.disabled = false;
        }
    }

    function prizeIndex(prize) {
        const fromKey = prizes.findIndex((item) => item.key === prize.key);
        if (fromKey >= 0) {
            return fromKey;
        }

        const fromApi = Number(prize.index);

        return Number.isFinite(fromApi) ? fromApi : 0;
    }

    function markWinner(index) {
        wheel.querySelectorAll('.gift-wheel-label').forEach((label, labelIndex) => {
            label.classList.toggle('is-winner', labelIndex === index);
        });
    }

    function animateTo(index) {
        const slice = 360 / prizes.length;
        const jitter = reduceMotion ? 0 : (Math.random() - 0.5) * slice * 0.3;
        const landing = (360 - ((index * slice) + jitter) + 360) % 360;
        const extraTurns = reduceMotion ? 0 : 6 + Math.floor(Math.random() * 3);
        currentTurns = (Math.floor(currentTurns / 360) + extraTurns) * 360 + landing;
        wheel.classList.toggle('is-spinning', ! reduceMotion);
        wheel.style.transform = `rotate(${currentTurns}deg)`;

        if (reduceMotion) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const done = () => {
                wheel.removeEventListener('transitionend', done);
                resolve();
            };
            wheel.addEventListener('transitionend', done);
            setTimeout(done, 4500);
        });
    }

    function showResult(payload) {
        const prize = payload.prize;
        const kicker = root.querySelector('#gift-result-kicker');
        const prizeEl = root.querySelector('#gift-result-prize');
        const copy = root.querySelector('#gift-result-copy');
        const name = payload.first_name ? `${payload.first_name}, ` : '';

        if (kicker) {
            kicker.textContent = payload.already_spun ? 'Already claimed' : `${name}you won`;
        }
        if (prizeEl) {
            prizeEl.textContent = prize.label;
        }
        if (copy) {
            if (payload.already_spun) {
                copy.textContent = 'This claim code already has a gift. Show it at the counter.';
            } else if (payload.master_spin) {
                copy.textContent = 'Staff master spin — collect at the counter. This did not attach to a visitor.';
            } else {
                copy.textContent = 'Show this screen at the counter to collect it.';
            }
        }
        resultEl?.classList.remove('hidden');
        setStatus('');
        spinButton.textContent = payload.already_spun ? 'Show gift' : 'Spin';
        root.querySelector('.gift-stage')?.scrollIntoView({
            block: 'center',
            behavior: reduceMotion ? 'auto' : 'smooth',
        });
    }

    return { open };
}

function paintWheel(wheel, prizes) {
    const slice = 360 / prizes.length;
    const fill = prizes
        .map((prize, index) => `${prize.color} ${index * slice}deg ${(index + 1) * slice}deg`)
        .join(', ');

    wheel.style.background = `conic-gradient(from ${-slice / 2}deg, ${fill})`;
    wheel.innerHTML = prizes.map((prize, index) => {
        const angle = index * slice;
        return `<span class="gift-wheel-label" style="transform: rotate(${angle}deg) translateY(-5.55rem)">${escapeHtml(prize.short || prize.label)}</span>`;
    }).join('');
}
