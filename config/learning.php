<?php

return [
    'sources' => ['spanish' => ['code' => 'es', 'label' => 'Español'], 'english' => ['code' => 'en', 'label' => 'English']],
    'targets' => ['en' => 'English', 'es' => 'Español', 'vi' => 'Tiếng Việt', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português'],
    'translation' => [
        'background' => env('TRANSLATION_BACKGROUND', true),
        'provider' => env('TRANSLATION_PROVIDER', 'mymemory'),
        'ollama_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'ollama_keep_alive' => env('OLLAMA_KEEP_ALIVE', '30s'),
        'ollama_model' => env('OLLAMA_TRANSLATION_MODEL', 'qwen3.5:4b'),
        'timeout' => min(210, max(1, (int) env('TRANSLATION_TIMEOUT', 180))),
    ],
    'default_source' => 'spanish',
    'default_target' => 'en',
];
