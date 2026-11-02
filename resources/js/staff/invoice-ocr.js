function normalizeText(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '');
}

function productPattern(name) {
    return String(name)
        .trim()
        .split(/\s+/)
        .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('[\\s\\W]*');
}

function parseInvoiceDate(lines) {
    const text = lines.join(' ');
    const monthNames = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';
    const named = text.match(new RegExp(`\\b(${monthNames})\\s+(\\d{1,2}),?\\s+(20\\d{2})\\b`, 'i'));
    if (named) {
        const month = named[1].slice(0, 3).toLowerCase();
        const monthNumber = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].indexOf(month) + 1;
        return formatInvoiceDate(Number(named[3]), monthNumber, Number(named[2]));
    }

    const iso = text.match(/\b(20\d{2})[-/.](\d{1,2})[-/.](\d{1,2})\b/);
    if (iso) {
        return formatInvoiceDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
    }

    const numeric = text.match(/\b(\d{1,2})[/. -](\d{1,2})[/. -](20\d{2})\b/);
    if (!numeric) return '';
    const [, first, second, year] = numeric;
    const month = Number(first) > 12 ? Number(second) : Number(first);
    const day = Number(first) > 12 ? Number(first) : Number(second);
    if (month < 1 || month > 12 || day < 1 || day > 31) return '';

    return formatInvoiceDate(Number(year), month, day);
}

function formatInvoiceDate(year, month, day) {
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return '';

    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function parseInvoiceNumber(lines) {
    const match = lines.join('\n').match(/\b(?:invoice|inv|receipt|or)\s*(?:no\.?|number|#)\s*[:#-]?\s*([a-z0-9][a-z0-9./-]{2,})\b/i);
    return match?.[1]?.replace(/[.,]$/, '') || '';
}

function parseSupplier(lines) {
    const labeled = lines.find((line) => /^(?:supplier|sold\s+by|from)\s*[:#-]/i.test(line));
    if (labeled) return labeled.replace(/^(?:supplier|sold\s+by|from)\s*[:#-]\s*/i, '').trim();

    return lines.find((line) =>
        line.length >= 3 &&
        line.length <= 100 &&
        !/\b(invoice|receipt|official receipt|date|total|amount due|qty|quantity)\b/i.test(line) &&
        !/^[\d\s.,₱$-]+$/.test(line)
    ) || '';
}

function parseInvoiceTotal(lines) {
    const totalLines = lines.filter((line) => /\b(grand\s+total|total\s+amount|net\s+total|amount\s+due|total)\b/i.test(line));
    for (const line of totalLines.reverse()) {
        const values = line.match(/(?:₱|PHP|P)?\s*\d[\d,]*(?:\.\d{1,2})?/gi) || [];
        const value = values.at(-1)?.replace(/[₱$PHP,\s]/gi, '');
        if (value && Number.isFinite(Number(value))) return Number(value).toFixed(2);
    }
    return '';
}

function parseQuantity(line, itemName) {
    const nameMatch = line.match(new RegExp(productPattern(itemName), 'i'));
    const afterName = nameMatch ? line.slice(nameMatch.index + nameMatch[0].length) : line;
    const explicit = afterName.match(/(?:qty|quantity)\s*[:#-]?\s*(\d{1,5})\b/i)
        || afterName.match(/\b(?:x|×)\s*(\d{1,5})\b/i)
        || afterName.match(/\b(\d{1,5})\s*(?:pcs?|pieces?|packs?|bottles?|sachets?|units?)\b/i);
    if (explicit) return Number(explicit[1]);

    const valuesAfterName = afterName.match(/\b\d{1,5}\b/g) || [];
    if (valuesAfterName.length) return Number(valuesAfterName[0]);

    const beforeName = nameMatch ? line.slice(0, nameMatch.index) : '';
    const valuesBeforeName = beforeName.match(/\b\d{1,5}\b/g) || [];
    return valuesBeforeName.length === 1 ? Number(valuesBeforeName[0]) : 0;
}

export function parseInvoiceText(text, inventoryItems) {
    const lines = String(text || '')
        .split(/\r?\n/)
        .map((line) => line.replace(/\s+/g, ' ').trim())
        .filter(Boolean);
    const products = (inventoryItems || [])
        .filter((item) => item.status === 'active' && Number(item.unit_price) > 0)
        .map((item) => ({ item, normalizedName: normalizeText(item.name) }))
        .filter((product) => product.normalizedName)
        .sort((a, b) => b.normalizedName.length - a.normalizedName.length);
    const quantitiesByItem = new Map();
    const unreadableProducts = new Set();

    for (const line of lines) {
        const normalizedLine = normalizeText(line);
        const product = products.find((candidate) => normalizedLine.includes(candidate.normalizedName));
        if (!product) continue;

        const quantity = parseQuantity(line, product.item.name);
        if (quantity < 1) {
            unreadableProducts.add(product.item.name);
            if (!quantitiesByItem.has(product.item.id)) {
                quantitiesByItem.set(product.item.id, {
                    id: product.item.id,
                    name: product.item.name,
                    quantity: 0,
                });
            }
            continue;
        }
        const current = quantitiesByItem.get(product.item.id);
        quantitiesByItem.set(product.item.id, {
            id: product.item.id,
            name: product.item.name,
            quantity: (current?.quantity || 0) + quantity,
        });
    }

    return {
        invoiceNumber: parseInvoiceNumber(lines),
        invoiceDate: parseInvoiceDate(lines),
        supplier: parseSupplier(lines),
        total: parseInvoiceTotal(lines),
        lines: [...quantitiesByItem.values()].map((line) => (
            unreadableProducts.has(line.name) ? { ...line, quantity: 0 } : line
        )),
        unreadableProducts: [...unreadableProducts],
    };
}

export async function scanInvoiceImage(image, onProgress = () => {}) {
    const { createWorker } = await import('tesseract.js');
    const worker = await createWorker('eng', 1, {
        logger: (progress) => {
            if (progress.status) onProgress(progress.status);
        },
    });

    try {
        const { data } = await worker.recognize(image);
        return data.text;
    } finally {
        await worker.terminate();
    }
}
