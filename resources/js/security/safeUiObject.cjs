// JSON data for component props must never carry prototype mutation keys.
function safeUiObject(value, depth = 0) {
    if (depth > 40) return null;
    if (Array.isArray(value)) return value.map(item => safeUiObject(item, depth + 1));
    if (value && typeof value === 'object') {
        const result = {};
        for (const key of Object.keys(value)) {
            if (['__proto__', 'constructor', 'prototype'].includes(key)) continue;
            result[key] = safeUiObject(value[key], depth + 1);
        }
        return result;
    }
    return value;
}
module.exports = {safeUiObject};
