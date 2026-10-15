import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default {
    namespaced: true,
    state: () => ({
        userUuid: '',
        userName: false,
        userEmail: false,
        userAdmin: false,
        vuetifyThemeSettings: null,
        textStylingSettings: null,
        echo: new Echo({
            broadcaster: 'pusher',
            key: document.querySelector('meta[name="reverb-key"]')?.content || 'local',
            cluster: 'mt1',
            forceTLS: window.location.protocol === 'https:',
            wsHost: window.location.hostname,
            wsPort: Number(document.querySelector('meta[name="reverb-port"]')?.content || 6001),
            wssPort: Number(document.querySelector('meta[name="reverb-port"]')?.content || 443),
            enabledTransports: ['ws', 'wss'],
        })
    }),
    mutations: {
        setUuid (state, userUuid) {
            state.userUuid = userUuid;
        },
        setUserName (state, userName) {
            state.userName = userName;
        },
        setUserEmail (state, userEmail) {
            state.userEmail = userEmail;
        },
        setUserAdmin (state, userAdmin) {
            state.userAdmin = userAdmin;
        },
        setVuetifyThemeSettings (state, vuetifyThemeSettings) {
            state.vuetifyThemeSettings = vuetifyThemeSettings;
        },
        setTextStylingSettings (state, textStylingSettings) {
            state.textStylingSettings = textStylingSettings;
        }
    },
    getters: {
        echo (state) {
            return state.echo;
        },
        userUuid(state) {
            return state.userUuid;
        },
        userAdmin(state) {
            return state.userAdmin;
        }
    }
}
