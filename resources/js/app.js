import './bootstrap';

const rupiah = (amount) => `Rp${Math.round(amount).toLocaleString('id-ID')}`;

// Menu cards: the shown price is the chosen size's price, plus the size's
// Extra matcha price when that is ticked (sizes without it untick it).
function syncExtra(card, extraLeft = null) {
    const size = card.querySelector('input[name="size"]:checked');
    const toggle = card.querySelector('[data-extra-toggle]');
    const price = card.querySelector('[data-price-display]');
    const extraPrice = Number(size?.dataset.extra || 0);

    if (toggle) {
        const sub = card.querySelector('[data-extra-sub]');
        const soldOut = extraPrice > 0 && extraLeft !== null && extraLeft <= 0;
        toggle.disabled = extraPrice <= 0 || soldOut;
        if (toggle.disabled) {
            toggle.checked = false;
        }
        if (sub) {
            sub.textContent = extraPrice <= 0 ? 'Tidak ada' : (soldOut ? 'Habis' : `+${rupiah(extraPrice)}`);
        }
    }

    if (price && size?.dataset.base !== undefined) {
        price.textContent = rupiah(Number(size.dataset.base) + (toggle?.checked ? extraPrice : 0));
    } else if (price && size?.dataset.price) {
        price.textContent = size.dataset.price;
    }
}

document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || (input.name !== 'size' && input.name !== 'extra_matcha')) {
        return;
    }

    const card = input.closest('[data-product-card], [data-catalog-card]');

    if (!card) {
        return;
    }

    // Order cards are handled by syncCard (it knows the stock left).
    if (card.matches('[data-catalog-card]')) {
        syncExtra(card);
    }

    if (input.name === 'size') {
        card.querySelectorAll('[data-bottle-size]').forEach((label) => {
            label.textContent = input.value;
        });
    }
});

// Menu tabs (Matcha / Coffee): switch the cards in place. The tab is kept in
// the address (?kategori=coffee) so a refresh or a shared link opens it too.
document.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-menu-tab]');

    if (!tab || event.metaKey || event.ctrlKey || event.shiftKey) {
        return;
    }

    event.preventDefault();
    const name = tab.dataset.menuTab;

    document.querySelectorAll('[data-menu-tab]').forEach((other) => {
        other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
        other.tabIndex = other === tab ? 0 : -1;
    });
    document.querySelectorAll('[data-menu-panel]').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.menuPanel !== name);
    });

    const url = new URL(window.location.href);
    url.searchParams.set('kategori', name);
    window.history.replaceState(null, '', url.pathname + url.search + '#menu');

    setMenuTheme(name === 'coffee' ? 'coffee' : 'matcha');
});

// Matcha green <-> coffee brown. The colors fade slowly while leaves fall
// across the screen, turning from the old color to the new one.
const THEME_COLORS = {
    matcha: { brand: '#4A6B4F', leaves: ['#6E9A5E', '#4A6B4F', '#8FB07A'] },
    coffee: { brand: '#5C3A26', leaves: ['#7A4B2E', '#5C3A26', '#A0673F'] },
};
const LEAF_PATH = 'M12 1.5C6.5 5 3.6 10.4 5.2 17.6c.3 1.3.9 2.6 1.7 3.6 1-4.8 3.4-9.6 7-13.2-2.9 3.9-4.6 8.3-5.3 13.4 6.9.5 11.7-5.2 11.9-12.7.1-2.6-.3-5.1-1.1-7.3-2.2.1-4.8-.3-7.4-1.9Z';


// Theme the menu is heading to (a switch takes a moment), and a counter so a
// quick second click cancels the steps of the first one.
let targetTheme = document.documentElement.dataset.theme || 'matcha';
let switchId = 0;
const MASCOT_OUT_MS = 450;

const mascotsOf = (theme) => [...document.querySelectorAll(theme === 'coffee' ? 'img.yc-mascot-coffee' : 'img.yc-mascot-matcha')];
const nextFrame = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function setMenuTheme(theme) {
    const root = document.documentElement;

    if (targetTheme === theme) {
        return;
    }

    // What is on screen now (a cut-short switch may not have turned yet).
    const from = root.dataset.theme || 'matcha';
    targetTheme = theme;
    const id = ++switchId;
    const meta = document.querySelector('meta[name="theme-color"]');

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        root.dataset.theme = theme;
        meta?.setAttribute('content', THEME_COLORS[theme].brand);
        return;
    }

    // Leftovers of a switch that was cut short.
    document.querySelectorAll('img.yc-mascot-leave, img.yc-mascot-enter, img.yc-mascot-swap')
        .forEach((img) => img.classList.remove('yc-mascot-leave', 'yc-mascot-enter', 'yc-mascot-swap'));

    root.classList.add('yc-theme-shift');
    dropLeaves(THEME_COLORS[from].leaves, THEME_COLORS[theme].leaves);

    // 1. The current Yoka melts away while the first leaves come down.
    const outgoing = mascotsOf(from);
    outgoing.forEach((img) => img.classList.add('yc-mascot-leave'));
    await wait(MASCOT_OUT_MS);
    if (id !== switchId) {
        return;
    }

    // 2. Colors start turning, and the other Yoka drifts in from the same blur.
    const incoming = mascotsOf(theme);
    incoming.forEach((img) => img.classList.add('yc-mascot-swap', 'yc-mascot-enter'));
    root.dataset.theme = theme;
    meta?.setAttribute('content', THEME_COLORS[theme].brand);
    outgoing.forEach((img) => img.classList.remove('yc-mascot-leave'));

    await nextFrame(); // start state painted before it animates
    if (id !== switchId) {
        return;
    }
    incoming.forEach((img) => img.classList.remove('yc-mascot-enter'));

    // 3. Tidy up once everything has settled.
    await wait(1900);
    if (id !== switchId) {
        return;
    }
    incoming.forEach((img) => img.classList.remove('yc-mascot-swap'));
    root.classList.remove('yc-theme-shift');
}

// Fetch the other theme's mascots once the page is idle, so the swap doesn't
// wait for the download.
window.addEventListener('load', () => {
    const warm = () => document.querySelectorAll('img.yc-mascot-coffee[loading="lazy"], img.yc-mascot-matcha[loading="lazy"]')
        .forEach((img) => { img.loading = 'eager'; });
    (window.requestIdleCallback ?? ((fn) => setTimeout(fn, 1500)))(warm);
});

function dropLeaves(fromColors, toColors) {
    document.querySelector('.yc-leaves')?.remove();

    const layer = document.createElement('div');
    layer.className = 'yc-leaves';
    layer.setAttribute('aria-hidden', 'true');

    const count = window.innerWidth < 640 ? 22 : 38;
    const pick = (list) => list[Math.floor(Math.random() * list.length)];
    let longest = 0;

    for (let i = 0; i < count; i += 1) {
        const fall = 2.6 + Math.random() * 2;
        const delay = Math.random() * 1.1;
        longest = Math.max(longest, fall + delay);

        const leaf = document.createElement('span');
        leaf.className = 'yc-leaf';
        leaf.style.cssText = [
            `--x:${Math.random() * 100}vw`,
            `--size:${16 + Math.random() * 22}px`,
            `--fall:${fall}s`,
            `--delay:${delay}s`,
            `--sway:${20 + Math.random() * 50}px`,
            `--sway-time:${0.9 + Math.random() * 0.9}s`,
            `--spin:${25 + Math.random() * 70}deg`,
            `--from:${pick(fromColors)}`,
            `--to:${pick(toColors)}`,
        ].join(';');
        leaf.innerHTML = `<span><svg viewBox="0 0 24 24" fill="currentColor"><path d="${LEAF_PATH}"/></svg></span>`;
        layer.appendChild(leaf);
    }

    document.body.appendChild(layer);
    setTimeout(() => layer.remove(), (longest + 0.3) * 1000);
}

// Arrow keys move between tabs, like a native tab list.
document.addEventListener('keydown', (event) => {
    const tab = event.target.closest?.('[data-menu-tab]');

    if (!tab || !['ArrowLeft', 'ArrowRight'].includes(event.key)) {
        return;
    }

    const tabs = [...document.querySelectorAll('[data-menu-tab]')];
    const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
    next.focus();
    next.click();
});

document.querySelectorAll('[data-menu-tab]').forEach((tab) => {
    tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
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
    let extraCells;
    try {
        cells = JSON.parse(card.dataset.cells || '{}');
        extraCells = JSON.parse(card.dataset.extraCells || '{}');
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

    // Extra matcha uses more matcha, so it can run out before the plain bottle.
    const extraLeft = sizeName !== null && sweetName !== null ? Number(extraCells?.[sizeName]?.[sweetName] ?? 0) : 0;
    syncExtra(card, extraLeft);
    const withExtra = card.querySelector('[data-extra-toggle]')?.checked;
    const stockLeft = sizeName !== null && sweetName !== null ? (withExtra ? extraLeft : cell(sizeName, sweetName)) : 0;
    const left = Math.min(stockLeft, limit);
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
    if (event.isTrusted && input instanceof HTMLInputElement && ['size', 'sweetness', 'extra_matcha'].includes(input.name)) {
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
        card.dataset.extraCells = JSON.stringify(stats.extraCells ?? {});
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
        } else if (response?.status === 429) {
            const wait = Number(response.headers?.['retry-after']) || 60;
            message = `Terlalu cepat. Tunggu ${wait} detik, lalu coba lagi.`;
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

// Values from the server are plain ("2.5", "32000"). One that already comes
// in the Indonesian format ("80.000", "1.250,5") is kept as it is, so
// "80.000" is never read as 80 with three decimals.
const alreadyFormatted = (text) => /^\d{1,3}(\.\d{3})+(,\d+)?$/.test(text) || /^\d+,\d+$/.test(text);

document.querySelectorAll('input[data-number]').forEach((input) => {
    const value = input.value.trim();
    const allowDecimal = input.dataset.number !== 'int';

    if (alreadyFormatted(value)) {
        input.value = formatAmount(value, allowDecimal);
    } else if (allowDecimal) {
        input.value = formatAmount(value.replace('.', ','), true);
    } else {
        // Whole numbers: drop a plain decimal part ("80000.00" -> "80000").
        input.value = formatAmount(value.replace(/\.\d*$/, ''), false);
    }
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
