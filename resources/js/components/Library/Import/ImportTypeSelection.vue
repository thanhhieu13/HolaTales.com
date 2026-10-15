<template>
    <div v-if="!loading">
        <label class="font-weight-bold">Select an import option</label><p class="text-caption">Văn bản trực tiếp đã được kiểm thử. Các nguồn khác đang thử nghiệm và cần kiểm tra định dạng trước khi sử dụng.</p>
        <div class="import-type-group flex-wrap">
            <!--Plain text -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('plain-text')" role="button" tabindex="0" @keydown.enter="selectImportType('plain-text')" @keydown.space.prevent="selectImportType('plain-text')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-text-box</v-icon>
                </div>
                <span>Plain text</span>
            </div>
            
            <!-- Text file -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('text-file')" role="button" tabindex="0" @keydown.enter="selectImportType('text-file')" @keydown.space.prevent="selectImportType('text-file')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-file-document</v-icon>
                </div>
                <span>Text file</span><small style="display:block">Experimental</small>
            </div>

            <!-- E-book -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('e-book')" role="button" tabindex="0" @keydown.enter="selectImportType('e-book')" @keydown.space.prevent="selectImportType('e-book')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-book</v-icon>
                </div>
                <span>E-book</span><small style="display:block">Experimental</small>
            </div>
        
            <!-- Youtube -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('youtube')" role="button" tabindex="0" @keydown.enter="selectImportType('youtube')" @keydown.space.prevent="selectImportType('youtube')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-youtube</v-icon>
                </div>
                <span>Youtube</span><small style="display:block">Experimental</small>
            </div>

            <!-- Jellyfin subtitle -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('jellyfin-subtitle')" role="button" tabindex="0" @keydown.enter="selectImportType('jellyfin-subtitle')" @keydown.space.prevent="selectImportType('jellyfin-subtitle')" v-if="jellyfinEnabled">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-movie</v-icon>
                </div>
                <span>Jellyfin subtitle</span><small style="display:block">Experimental</small>
            </div>

            <!-- Subtitle file -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('subtitle-file')" role="button" tabindex="0" @keydown.enter="selectImportType('subtitle-file')" @keydown.space.prevent="selectImportType('subtitle-file')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-subtitles</v-icon>
                </div>
                <span>Subtitle file</span><small style="display:block">Experimental</small>
            </div>

            <!-- Website -->
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('website')" role="button" tabindex="0" @keydown.enter="selectImportType('website')" @keydown.space.prevent="selectImportType('website')" v-if="websiteImportSupported">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-web</v-icon>
                </div>
                <span>Website</span><small style="display:block">Experimental</small>
            </div>

            <!--
            <div class="import-type-button rounded-lg mx-2 mb-4" @click="selectImportType('rss')" role="button" tabindex="0" @keydown.enter="selectImportType('rss')" @keydown.space.prevent="selectImportType('rss')">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-rss-box</v-icon>
                </div>
                <span>RSS feed</span>
            </div>
            
            <div class="import-type-button rounded-lg mx-2 mb-4">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-file-document</v-icon>
                </div>
                <span>PDF</span>
            </div>

            <div class="import-type-button rounded-lg mx-2 mb-4">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-play-circle</v-icon>
                </div>
                <span>Mpv player</span>
            </div>

            <div class="import-type-button rounded-lg mx-2 mb-4">
                <div class="import-type-button-icon-box">
                    <v-icon large>mdi-chat-processing</v-icon>
                </div>
                <span>Manga</span>
            </div>
            -->
        </div>
    </div>
    <div class="h-100 d-flex justify-center" v-else>
        <v-progress-linear
            indeterminate
            color="primary"
            height="4"
            rounded
        ></v-progress-linear>
    </div>
</template>

<script>
    export default {
        data: function() {
            return {
                loading: true,
                websiteImportSupported: false,
                jellyfinEnabled: false,
            }
        },
        props: {
            language: String
        },
        mounted() {
            axios.all([
                axios.get('/config/get/linguacafe.languages.website_import_supported_languages'),
                axios.get('/settings/is-jellyfin-enabled'),
            ]).then(axios.spread((response1, response2) => {
                this.websiteImportSupported = response1.data.includes(this.$props.language);
                this.jellyfinEnabled = response2.data;
                this.loading = false;
            }));
        },
        methods: {
            selectImportType(type) {
                this.$emit('import-type-selected', type);
            }
        }
    }
</script>
