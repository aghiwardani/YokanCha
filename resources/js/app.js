import './bootstrap';

// Menu cards: when a size chip changes, update the shown price and the bottle label.
document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.name !== 'size') {
        return;
    }

    const card = input.closest('[data-product-card]');

    if (!card) {
        return;
    }

    const price = card.querySelector('[data-price-display]');

    if (price && input.dataset.price) {
        price.textContent = input.dataset.price;
    }

    card.querySelectorAll('[data-bottle-size]').forEach((label) => {
        label.textContent = input.value;
    });
});

// Toast shown after adding to the cart (state: idle | ok | error).
const toast = document.querySelector('[data-toast]');
let toastTimer;

function showToast(message, state) {
    if (!toast) {
        return;
    }

    toast.querySelector('[data-toast-message]').textContent = message;
    toast.dataset.state = state;

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.dataset.state = 'idle';
    }, state === 'error' ? 5000 : 3500);
}

// A toast rendered from the session flash (no-JS fallback) also fades out.
if (toast && toast.dataset.state !== 'idle') {
    toastTimer = setTimeout(() => {
        toast.dataset.state = 'idle';
    }, 3500);
}

function updateCartCount(count) {
    document.querySelectorAll('[data-cart-link]').forEach((link) => {
        link.textContent = count > 0 ? `Keranjang · ${count}` : 'Keranjang';
    });
}

// "Tambah" adds to the cart in the background so the customer stays on the menu.
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-cart-add]');

    if (!form) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');
    const label = button?.textContent;

    if (button) {
        button.disabled = true;
        button.textContent = 'Sebentar…';
    }

    try {
        const { data } = await window.axios.post(form.action, new FormData(form), {
            headers: { Accept: 'application/json' },
        });

        updateCartCount(data.count);
        showToast(data.message, 'ok');
    } catch (error) {
        const response = error.response;
        let message = 'Gagal masuk keranjang. Coba lagi.';

        if (response?.status === 422 && response.data?.errors) {
            message = Object.values(response.data.errors)[0][0];
        } else if (response?.status === 419) {
            message = 'Sesi habis. Muat ulang halaman, lalu coba lagi.';
        }

        showToast(message, 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = label;
        }
    }
});
