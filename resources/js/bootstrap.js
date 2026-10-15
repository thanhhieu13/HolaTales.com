window._ = require('lodash');

/**
 * We'll load jQuery and the Bootstrap jQuery plugin which provides support
 * for JavaScript based Bootstrap features such as modals and tabs. This
 * code may be modified to fit the specific needs of your application.
 */

try {
    window.Popper = require('popper.js').default;
    window.$ = window.jQuery = require('jquery');

    require('bootstrap');
} catch (e) {}

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = require('axios');

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// window.Pusher = require('pusher-js');

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: process.env.MIX_PUSHER_APP_KEY,
//     cluster: process.env.MIX_PUSHER_APP_CLUSTER,
//     forceTLS: true
// });

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
const {safeUiObject} = require('./security/safeUiObject.cjs');
window.axios.interceptors.response.use(response => {
    if (response.data && typeof response.data === 'object') response.data = safeUiObject(response.data);
    window.dispatchEvent(new CustomEvent('holatales-network-restored'));
    return response;
}, error => {
    if (!error.response) window.dispatchEvent(new CustomEvent('holatales-network-unavailable'));
    if ([401,419].includes(error.response?.status)) {
        window.dispatchEvent(new CustomEvent('holatales-session-expired'));
    }
    return Promise.reject(error);
});

// Snapshot the pair of the loaded page; another tab must not silently change saves.
const learningSource = document.querySelector('meta[name="learning-source"]')?.content;
const learningTarget = document.querySelector('meta[name="translation-target"]')?.content;
if (learningSource) window.axios.defaults.headers.common['X-Learning-Source'] = learningSource;
if (learningTarget) window.axios.defaults.headers.common['X-Learning-Target'] = learningTarget;
