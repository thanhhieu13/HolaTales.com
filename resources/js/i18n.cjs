const messages = {en:require('./locales/en.json'),vi:require('./locales/vi.json')};
function translate(key, locale) { return (messages[locale] || messages.vi)[key] || messages.en[key] || key; }
module.exports = {translate};
