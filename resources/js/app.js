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

// Quantity stepper on menu cards: − / + keep the number between min and max.
const clampQty = (input) => {
    const min = Number(input.min) || 1;
    const max = Number(input.max) || 99;
    const value = Math.round(Number(input.value)) || min;
    input.value = String(Math.min(max, Math.max(min, value)));

    const stepper = input.closest('[data-qty]');
    stepper?.querySelector('[data-qty-step="-1"]')?.toggleAttribute('disabled', Number(input.value) <= min);
    stepper?.querySelector('[data-qty-step="1"]')?.toggleAttribute('disabled', Number(input.value) >= max);
};

document.querySelectorAll('[data-qty] input').forEach(clampQty);

// Menu cards: stock per size + sweetness lives in data-cells (bottles this
// visitor can still add). Grey out what is gone and cap the quantity.
const DISABLED_BUTTON = 'yc-btn cursor-not-allowed border-charcoal/30 bg-charcoal/10 text-charcoal/50';

function syncCard(card) {
    let cells;
    try {
        cells = JSON.parse(card.dataset.cells || '{}');
    } catch {
        return;
    }
    const limit = Number(card.dataset.limit) || 0;
    const sizeInputs = [...card.querySelectorAll('input[name="size"][type="radio"]')];
    const sweetInputs = [...card.querySelectorAll('input[name="sweetness"][type="radio"]')];
    const cell = (size, sweet) => Number(cells?.[size]?.[sweet] ?? 0);
    const sweetNames = sweetInputs.length ? sweetInputs.map((i) => i.value) : ['-'];
    const markChip = (input, available) => {
        input.disabled = !available;
        const sub = input.parentElement.querySelector('[data-chip-sub]');
        if (sub) {
            if (sub.dataset.label) {
                sub.textContent = available ? sub.dataset.label : 'Habis';
            } else {
                sub.classList.toggle('hidden', available);
            }
        }
    };

    sizeInputs.forEach((input) => markChip(input, sweetNames.some((sweet) => cell(input.value, sweet) > 0)));

    let size = sizeInputs.find((i) => i.checked && !i.disabled) ?? sizeInputs.find((i) => !i.disabled);
    if (size && !size.checked) {
        size.checked = true;
        size.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const sizeName = size ? size.value : (sizeInputs.length ? null : '-');

    sweetInputs.forEach((input) => markChip(input, sizeName !== null && cell(sizeName, input.value) > 0));
    let sweet = sweetInputs.find((i) => i.checked && !i.disabled) ?? sweetInputs.find((i) => !i.disabled);
    if (sweet) {
        sweet.checked = true;
    }
    const sweetName = sweet ? sweet.value : (sweetInputs.length ? null : '-');

    const left = sizeName !== null && sweetName !== null ? Math.min(cell(sizeName, sweetName), limit) : 0;
    const anyStock = Object.values(cells).some((row) => Object.values(row).some((n) => Number(n) > 0));

    const qty = card.querySelector('[data-qty]');
    const qtyInput = qty?.querySelector('input');
    const button = card.querySelector('[data-add-button]');

    qty?.classList.toggle('hidden', left <= 0);
    if (qtyInput) {
        qtyInput.max = String(Math.max(1, left));
        clampQty(qtyInput);
    }
    if (button) {
        button.disabled = left <= 0;
        button.className = left > 0 ? 'yc-btn-cta' : DISABLED_BUTTON;
        button.textContent = left > 0 ? 'Tambah' : (anyStock && limit <= 0 ? 'Batas pesanan' : 'Stok habis');
    }

    // Whole card sold out: swap the tag and grey the photo.
    card.querySelector('[data-card-tag]')?.classList.toggle('hidden', !anyStock);
    card.querySelector('[data-card-soldout]')?.classList.toggle('hidden', anyStock);
    card.querySelector('[data-card-photo]')?.classList.toggle('grayscale-[60%]', !anyStock);
}

document.addEventListener('change', (event) => {
    const input = event.target;
    // Only real clicks; syncCard itself fires a change when it moves the size.
    if (event.isTrusted && input instanceof HTMLInputElement && (input.name === 'size' || input.name === 'sweetness')) {
        const card = input.closest('[data-product-card]');
        if (card) {
            syncCard(card);
        }
    }
});

function applyAvailability(availability) {
    if (!availability) {
        return;
    }
    document.querySelectorAll('[data-product-card][data-ppp]').forEach((card) => {
        const stats = availability[card.dataset.ppp];
        if (!stats) {
            return;
        }
        card.dataset.cells = JSON.stringify(stats.cells ?? {});
        card.dataset.limit = String(stats.limit ?? 0);
        syncCard(card);
    });
}


document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-qty-step]');

    if (!button) {
        return;
    }

    const input = button.closest('[data-qty]').querySelector('input');
    input.value = String(Number(input.value || 0) + Number(button.dataset.qtyStep));
    clampQty(input);
});

document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.closest('[data-qty]')) {
        clampQty(event.target);
    }
});

// "Tambah" adds to the cart in the background so the customer stays on the menu.
let availabilityAfterAdd = null;
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

        // Back to 1 for the next add.
        const qty = form.querySelector('[data-qty] input');
        if (qty) {
            qty.value = '1';
            clampQty(qty);
        }

        availabilityAfterAdd = data.availability;
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
        // Re-read stock after the label is back, so "Stok habis" sticks.
        applyAvailability(availabilityAfterAdd);
        availabilityAfterAdd = null;
    }
});

// Admin amount fields ([data-number]; "int" = whole numbers only): show the
// Indonesian format while typing ("32.000", "2,5") and submit plain numbers
// ("32000", "2.5") so the server reads them as usual.
const formatAmount = (text, allowDecimal) => {
    const [whole, ...fraction] = text.replace(/[^\d,]/g, '').split(',');
    const grouped = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const decimals = allowDecimal && fraction.length ? `,${fraction.join('').slice(0, 2)}` : '';

    return grouped + decimals;
};

const plainAmount = (text) => text.replace(/\./g, '').replace(',', '.');

const formatField = (input, keepCaret) => {
    const allowDecimal = input.dataset.number !== 'int';
    const caret = input.selectionStart ?? input.value.length;
    // Characters that matter (digits and the decimal comma) before the caret.
    const significantBefore = input.value.slice(0, caret).replace(/[^\d,]/g, '').length;

    input.value = formatAmount(input.value, allowDecimal);

    if (keepCaret && document.activeElement === input) {
        let position = 0;
        let seen = 0;
        while (position < input.value.length && seen < significantBefore) {
            if (/[\d,]/.test(input.value[position])) {
                seen += 1;
            }
            position += 1;
        }
        input.setSelectionRange(position, position);
    }
};

document.querySelectorAll('input[data-number]').forEach((input) => {
    // Values rendered by the server are plain ("2.5", "32000").
    input.value = formatAmount(input.value.replace('.', ','), input.dataset.number !== 'int');
});

document.addEventListener('input', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !input.matches('[data-number]')) {
        return;
    }

    // Thousand dots are added automatically, so a "." typed in a decimal field
    // means the decimal mark: "2.5" becomes "2,5", not 25.
    if (event.data === '.' && input.dataset.number !== 'int' && !input.value.includes(',')) {
        const caret = input.selectionStart ?? input.value.length;
        input.value = `${input.value.slice(0, caret - 1)},${input.value.slice(caret)}`;
        input.setSelectionRange(caret, caret);
    }

    formatField(input, true);
});

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) {
        return;
    }

    event.target.querySelectorAll('input[data-number]').forEach((input) => {
        input.value = plainAmount(input.value);
    });
});
