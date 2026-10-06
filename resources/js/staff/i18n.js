/**
 * Switches the on-screen text to the user's language (English or Filipino).
 * Server-rendered pages already arrive in the right language for the help guide;
 * everything else (labels, table headers, messages built in JS) is translated here
 * by exact-text lookup, and kept up to date as the page changes.
 */
import { FILIPINO } from './i18n-fil.js';

const DICTIONARIES = { fil: FILIPINO };
const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'NOSCRIPT']);
const ATTRIBUTES = ['placeholder', 'title', 'aria-label'];

let dictionary = null;
let observer = null;

const clean = (text) => text.replace(/\s+/g, ' ').trim();

function lookup(text) {
    const key = clean(text);
    if (!key) return null;
    if (Object.hasOwn(dictionary.exact, key)) return dictionary.exact[key];
    for (const [pattern, build] of dictionary.patterns) {
        const match = key.match(pattern);
        if (match) return build(...match.slice(1));
    }

    return null;
}

function translateText(node) {
    if (node._translated === node.nodeValue) return;
    const [, lead, body, tail] = node.nodeValue.match(/^(\s*)([\s\S]*?)(\s*)$/);
    const result = lookup(body);
    if (result === null) return;
    node._original = node.nodeValue;
    node._translated = lead + result + tail;
    node.nodeValue = node._translated;
}

function translateAttributes(el) {
    for (const name of ATTRIBUTES) {
        const value = el.getAttribute?.(name);
        if (!value || el._translatedAttrs?.[name] === value) continue;
        const result = lookup(value);
        if (result === null) continue;
        el._originalAttrs = { ...el._originalAttrs, [name]: value };
        el._translatedAttrs = { ...el._translatedAttrs, [name]: result };
        el.setAttribute(name, result);
    }
}

function translateTree(root) {
    if (root.nodeType === Node.TEXT_NODE) {
        if (!SKIP_TAGS.has(root.parentElement?.tagName)) translateText(root);
        return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE || SKIP_TAGS.has(root.tagName)) return;
    translateAttributes(root);
    root.querySelectorAll('[placeholder], [title], [aria-label]').forEach(translateAttributes);
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (!SKIP_TAGS.has(node.parentElement?.tagName)) translateText(node);
    }
}

/** Put every translated text and attribute back to English. */
function restoreEnglish() {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (node._original !== undefined && node.nodeValue === node._translated) node.nodeValue = node._original;
    }
    document.querySelectorAll('[placeholder], [title], [aria-label]').forEach((el) => {
        for (const [name, original] of Object.entries(el._originalAttrs || {})) {
            if (el.getAttribute(name) === el._translatedAttrs?.[name]) el.setAttribute(name, original);
        }
    });
}

/** Call once per page load with the user's locale ("en" or "fil"). */
export function applyLocale(locale) {
    observer?.disconnect();
    observer = null;
    if (dictionary) restoreEnglish();
    dictionary = DICTIONARIES[locale] || null;
    if (!dictionary || !document.body) return;

    translateTree(document.body);
    observer = new MutationObserver((records) => {
        for (const record of records) {
            if (record.type === 'childList') record.addedNodes.forEach(translateTree);
            else if (record.type === 'characterData') translateTree(record.target);
            else translateAttributes(record.target);
        }
        observer.takeRecords();
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRIBUTES });
}

export function currentLocale() {
    return document.querySelector('meta[name="ssk-locale"]')?.content || 'en';
}
