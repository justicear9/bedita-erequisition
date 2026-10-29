/**
 * Parse API date/datetime strings (including trailing Z / microseconds).
 */
export function parseDate(value) {
    if (!value) return null;
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const raw = String(value).trim();
    // Date-only: treat as local calendar day (avoid UTC shift).
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const [y, m, d] = raw.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    const normalized = raw.replace(/(\.\d{3})\d+/, '$1');
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function toDateInputValue(value) {
    const date = parseDate(value);
    if (!date) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function formatDate(value) {
    const date = parseDate(value);
    if (!date) return '';
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(value) {
    const date = parseDate(value);
    if (!date) return '';
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
