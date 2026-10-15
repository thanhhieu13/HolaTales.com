<template>
   <v-app :class="{'eink': theme == 'eink', 'dark': theme == 'dark', 'reader-focus': readerFocus}">

        <!-- Dialogs -->
        <start-review-dialog v-model="startReviewDialog" />
        <logout-dialog v-model="logoutDialog"/>
        <v-alert v-if="sessionExpired && $route.path !== '/login'" type="warning" style="position:fixed;top:0;left:0;right:0;z-index:100">{{ $t('session_expired') }} <a href="/login">{{ $t('login') }}</a></v-alert>


        <v-alert v-if="networkUnavailable" type="warning" role="alert" style="position:fixed;bottom:64px;left:16px;right:16px;z-index:100">{{ $t('offline') }}</v-alert>
        <template v-if="$router.currentRoute.path !== '/login'">
            <theme-selection-dialog v-model="themeSelectionDialog" @input="updateTheme"></theme-selection-dialog>
            <language-selection-dialog v-model="languageSelectionDialog"></language-selection-dialog>
            <v-navigation-drawer :width="240" :mini-variant-width="68"
                id="navigation-drawer"
                app
                dense
                :class="{'eink': theme == 'eink'}"
                :mini-variant="$vuetify.breakpoint.md || navbarCollapsed || isReader"
                :permanent="$vuetify.breakpoint.mdAndUp"
                :temporary="$vuetify.breakpoint.smAndDown"
                v-model="drawer"
                color="foreground"
            >
                <!-- Logo -->
                <div id="logo" class="d-flex justify-center my-5" v-if="$vuetify.breakpoint.lgAndUp && !navbarCollapsed && !isReader">
                    <img src="/icon512rounded.png" class="mr-2" width="32px" height="32px"/>
                    <span class="text--text">HolaTales</span>
                </div>

                <v-list nav shaped dense class="pl-0">
                    <!-- Navigation buttons -->
                    <template v-for="(item, index) in navigation">
                    <v-subheader v-if="item.group && !navbarCollapsed && !$vuetify.breakpoint.md && !isReader" :key="'group-'+index">{{ item.group }}</v-subheader>
                    <v-list-item
                        class="navigation-button"
                        :key="index"
                        :title="item.name"
                        exact
                        :to="item.external ? undefined : item.url"
                        :href="item.external ? item.url : undefined"
                        @click="navigationClick(item.name, $event)"
                    >
                        <v-icon> {{ item.icon }} </v-icon>
                        <span class="pl-6"> {{ item.name }} </span>
                    </v-list-item>
                    </template>
                    <v-list-item class="navigation-button" @click="openLogoutDialog">
                        <v-icon> mdi-logout </v-icon>
                        <span class="pl-6"> Logout </span>
                    </v-list-item>
                </v-list>

                <template v-slot:append>
                    <!-- Large navigation drawer -->
                    <template v-if="!$vuetify.breakpoint.md && !navbarCollapsed">
                        <v-list nav shaped dense class="pl-0">
                            <!-- Navigation buttons -->
                            <v-list-item class="navigation-button" @click="collapseNavbar">
                                <v-icon> mdi-arrow-collapse-left </v-icon>
                                <span class="pl-6"> Hide</span>
                            </v-list-item>
                            <v-list-item class="navigation-button" @click="themeSelectionDialog = true;">
                                <v-icon> mdi-palette </v-icon>
                                <span class="pl-6"> Theme</span>
                            </v-list-item>
                            <v-list-item class="navigation-button" @click="languageSelectionDialog = true;">
                                <v-img class="border" :src="'/images/flags/' + selectedLanguage.toLowerCase() + '.png'" max-width="26" height="17"></v-img>
                                <span class="pl-5"> Language</span>
                            </v-list-item>
                        </v-list>
                    </template>

                    <!-- Mini navigation drawer -->
                    <template v-else>
                        <v-btn v-if="$vuetify.breakpoint.lgAndUp" id="collapse" rounded text class="mini-drawer-button" @click="expandNavbar" title="Expand sidebar">
                            <v-icon>mdi-arrow-collapse-right</v-icon>
                        </v-btn>
                        <v-btn id="theme" rounded text class="mini-drawer-button" @click="themeSelectionDialog = true" title="Theme">
                            <v-icon>mdi-palette</v-icon>
                        </v-btn>
                        <v-btn id="language" rounded text class="mini-drawer-button" @click="languageSelectionDialog = true" title="Select language">
                            <v-img :src="'/images/flags/' + selectedLanguage.toLowerCase() + '.png'" max-width="31" height="20"></v-img>
                        </v-btn>
                    </template>
                </template>
            </v-navigation-drawer>

            <!-- Bottom navigation -->
            <v-bottom-navigation v-if="!isReader" dense grow class="d-flex d-sm-flex d-md-none" dark background-color="primary">

                <v-btn
                    class="text-decoration-none"
                    grow
                    v-for="(item, index) in navigation"
                    :key="index"
                    :to="item.external ? undefined : item.url"
                        :href="item.external ? item.url : undefined"
                    v-if="item.bottomNav"
                >
                    <span>{{ item.name }}</span>
                    <v-icon>{{ item.icon }}</v-icon>
                </v-btn>
            </v-bottom-navigation>
        </template>
        <v-main :style="{background: $vuetify.theme.currentTheme.background, ...textStyling}" :class="{ eink: theme == 'eink'}">
            <router-view :user-count="$props._userCount" :language="selectedLanguage" :key="$route.fullPath"></router-view>
        </v-main>
    </v-app>
</template>

<script>
    import ThemeService from './../services/ThemeService';
    import TextStylingService from './../services/TextStylingService';
    import FontTypeService from './../services/FontTypeService';
    import { DefaultLocalStorageManager } from './../services/LocalStorageManagerService';
    
    export default {
        data: function() {
            return {
                selectedLanguage: this.$props._selectedLanguage,
                theme: DefaultLocalStorageManager.loadSetting("theme") || 'light',
                readerFocus: false, sessionExpired: false, networkUnavailable: !navigator.onLine, logoutDialog: false,
                themeSelectionDialog: false,
                languageSelectionDialog: false,
                startReviewDialog: false,
                drawer: false,
                navbarVisible: true,
                navbarCollapsed: false,
                navigation: [
                    {name:this.$t('home'),group:this.$t('discover'),url:'/learn',external:true,icon:'mdi-home',bottomNav:true},
                    {name:this.$t('library'),url:'/stories',external:true,icon:'mdi-book-open-page-variant',bottomNav:true},
                    {name:this.$t('continue_reading'),group:this.$t('my_learning'),url:'/learn#continue',external:true,icon:'mdi-bookmark',bottomNav:false},
                    {name:this.$t('vocabulary'),url:'/vocabulary/search',icon:'mdi-translate',bottomNav:false},
                    {name:this.$t('review'),url:'/review/false/-1/-1',icon:'mdi-playlist-check',bottomNav:true},
                    {name:this.$t('progress'),url:'/progress',external:true,icon:'mdi-chart-line',bottomNav:true},
                    {name:this.$t('my_stories'),group:this.$t('personal'),url:'/books',icon:'mdi-bookshelf',bottomNav:false},
                    {name:this.$t('import'),url:'/books?import=1',icon:'mdi-file-upload',bottomNav:false},
                    {name:this.$t('settings'),group:this.$t('account'),url:'/onboarding',external:true,icon:'mdi-cog',bottomNav:false},
                    {name:this.$t('profile'),url:'/user-settings',icon:'mdi-account-circle',bottomNav:true},
                    {name:this.$t('manual'),url:'/user-manual',icon:'mdi-account-question',bottomNav:false}
                ],
            }
        },
        watch: { '$vuetify.breakpoint.smAndDown'(mobile) { if (mobile) this.drawer = false; } },
        computed: {
            isReader() { return this.$route.path.startsWith('/chapters/read/'); },
            textStyling: function() {
                let settingsObject = this.$store.state.shared.textStylingSettings

                if (settingsObject === null) {
                    settingsObject = TextStylingService.getDefaultTextStylingSettings()
                }

                const settingsCssObject = TextStylingService.getTextStylingSettingsObject(settingsObject)
                return settingsCssObject[this.theme]
            }
        },
        props: {
            _selectedLanguage: String,
            _userName: {
                type: String,
                default: '',
            },
            _userEmail: {
                type: String,
                default: '',
            },
            _userUuid: {
                type: String,
                default: '',
            },
            _userCount: Number,
            _isAdmin: Boolean,
            themeSettings: {
                type: Object,
                default: null,
            }
        },
        beforeMount() {
            // set store data
            this.$store.commit('shared/setUuid', this.$props._userUuid);
            this.$store.commit('shared/setUserName', this.$props._userName);
            this.$store.commit('shared/setUserEmail', this.$props._userEmail);
            this.$store.commit('shared/setUserAdmin', this.$props._isAdmin);

            if (this.$props._selectedLanguage == 'japanese') {
                this.navigation.splice(3, 0, {
                    name: 'Kanji',
                    url: '/kanji/search',
                    icon: 'mdi-ideogram-cjk',
                    bottomNav: false,
                });
            }

            if(this.$store.getters['shared/userAdmin']) {
                this.navigation.push({
                    name: 'Admin settings',
                    url: '/admin',
                    icon: 'mdi-shield-lock',
                    bottomNav: false,
                });
            }

            this.initializeThemes();

            // Watch OS theme change. Currently disabled to 
            // const preferredDarkTheme = window.matchMedia("(prefers-color-scheme: dark)");
            // preferredDarkTheme.addEventListener("change", this.loadSelectedTheme);

            // load navbar status
            const savedNavbarCollapsed = DefaultLocalStorageManager.loadSetting('navbar-collapsed');
            this.navbarCollapsed = savedNavbarCollapsed ? savedNavbarCollapsed === 'true' : false;
        },
        mounted() {
            window.addEventListener('offline', this.markOffline);
            window.addEventListener('online', this.markOnline);
            window.addEventListener('holatales-network-unavailable', this.markOffline);
            window.addEventListener('holatales-network-restored', this.markOnline);
            window.addEventListener('holatales-session-expired', this.expireSession);
            this.$root.$on('toggle-navigation', this.toggleNavigation);
            this.$root.$on('reader-overlay-open', this.closeNavigation);
            this.$root.$on('reader-focus', this.setReaderFocus);
            // load default and selected font types into the dom
            var fontTypeService = new FontTypeService(this.selectedLanguage, () => {
                fontTypeService.loadSelectedFontTypeIntoDom();
                fontTypeService.loadDefaultFontTypeIntoDom();
            });
        },
        beforeDestroy() {
            window.removeEventListener('offline', this.markOffline);
            window.removeEventListener('online', this.markOnline);
            window.removeEventListener('holatales-network-unavailable', this.markOffline);
            window.removeEventListener('holatales-network-restored', this.markOnline);
            window.removeEventListener('holatales-session-expired', this.expireSession);
            this.$root.$off('toggle-navigation', this.toggleNavigation);
            this.$root.$off('reader-overlay-open', this.closeNavigation);
            this.$root.$off('reader-focus', this.setReaderFocus);
        },
        methods: {
            toggleNavigation() { this.drawer = !this.drawer; },
            closeNavigation() { if (this.$vuetify.breakpoint.smAndDown) this.drawer = false; },
            markOffline() { this.networkUnavailable = true; },
            markOnline() { this.networkUnavailable = false; },
            setReaderFocus(enabled) { this.readerFocus = enabled; },
            expireSession() { this.sessionExpired = true; },
            initializeThemes() {
                this.loadSelectedTheme();
                ThemeService.setDefaultVuetifyTheme(this.$vuetify);

                if (this.$props.themeSettings?.vuetifyThemes) {
                    this.$store.commit('shared/setVuetifyThemeSettings', this.$props.themeSettings.vuetifyThemes)
                    this.$store.commit('shared/setTextStylingSettings', this.$props.themeSettings.textStyling)
                }

                ThemeService.setVuetifyTheme(this.$vuetify, this.$store)
            },
            loadSelectedTheme() {
                const autoEnabled = ThemeService.isAuto();
                const preferredDarkTheme = window.matchMedia("(prefers-color-scheme: dark)");

                if (autoEnabled) {
                    // auto-select user's system theme if 'auto' is enabled
                    if (preferredDarkTheme.matches) {
                        this.theme = 'dark';
                        console.log('auto dark')
                    } else {
                        this.theme = 'light';
                        console.log('auto light')
                    }

                    DefaultLocalStorageManager.saveSetting('theme', this.theme);
                } else {
                    // otherwise use saved theme
                    const savedTheme = DefaultLocalStorageManager.loadSetting('theme');
                    this.theme = savedTheme ? savedTheme : 'light';
                }
            },
            collapseNavbar() {
                this.navbarCollapsed = true;
                DefaultLocalStorageManager.saveSetting('navbar-collapsed', this.navbarCollapsed);
            },
            expandNavbar() {
                this.navbarCollapsed = false;
                DefaultLocalStorageManager.saveSetting('navbar-collapsed', this.navbarCollapsed);
            },
            navigationClick(itemName, event) {
                if (itemName === 'Review') {
                    this.startReviewDialog = true;
                    event.preventDefault();
                }

                // clicked on user manual
                if (itemName === 'User manual' && this.$router.currentRoute.path !== '/user-manual') {
                    this.$router.push({ path: '/user-manual', replace: true });
                }

            },
            updateTheme() {
                const savedTheme = DefaultLocalStorageManager.loadSetting('theme');
                this.theme = savedTheme ? savedTheme : 'light';
            },
            openLogoutDialog() {
                this.logoutDialog = true;
            },
        }
    }
</script>
