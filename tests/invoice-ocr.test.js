import assert from 'node:assert/strict';
import test from 'node:test';
import { parseInvoiceText } from '../resources/js/staff/invoice-ocr.js';

const inventoryItems = [
    { id: 1, name: 'Ariel', status: 'active', unit_price: 10 },
    { id: 2, name: 'Downy', status: 'active', unit_price: 14 },
];

test('reads item quantities when the pcs unit is on a separate OCR line', () => {
    const parsed = parseInvoiceText([
        'Item Quantity',
        'Ariel',
        '2',
        'pcs',
        'Downy',
        '3',
        'PCS',
    ].join('\n'), inventoryItems);

    assert.deepEqual(parsed.lines, [
        { id: 1, name: 'Ariel', quantity: 2 },
        { id: 2, name: 'Downy', quantity: 3 },
    ]);
    assert.deepEqual(parsed.unreadableProducts, []);
});

test('reads quantities when the unit appears above the count', () => {
    const parsed = parseInvoiceText([
        'Ariel',
        'pcs',
        '4',
    ].join('\n'), inventoryItems);

    assert.deepEqual(parsed.lines, [
        { id: 1, name: 'Ariel', quantity: 4 },
    ]);
});

test('reads quantities split across quantity and unit rows', () => {
    const parsed = parseInvoiceText([
        'Ariel',
        'Quantity',
        '5',
        'PCS',
    ].join('\n'), inventoryItems);

    assert.deepEqual(parsed.lines, [
        { id: 1, name: 'Ariel', quantity: 5 },
    ]);
});

test('does not treat an adjacent price as a quantity without a quantity marker or unit', () => {
    const parsed = parseInvoiceText([
        'Ariel',
        '12.00',
        'Downy',
        '14.00',
    ].join('\n'), inventoryItems);

    assert.deepEqual(parsed.lines, [
        { id: 1, name: 'Ariel', quantity: 0 },
        { id: 2, name: 'Downy', quantity: 0 },
    ]);
    assert.deepEqual(parsed.unreadableProducts, ['Ariel', 'Downy']);
});
