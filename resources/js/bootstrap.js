function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

window.expoFetch = async function expoFetch(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
    };

    if (options.method && options.method.toUpperCase() !== 'GET') {
        headers['X-CSRF-TOKEN'] = csrfToken();
        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
    }

    const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(payload.message || 'Request failed');
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
};
