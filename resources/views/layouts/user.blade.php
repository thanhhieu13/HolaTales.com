<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="interface-locale" content="{{ app()->getLocale() }}">
    <link href="/css/vuetify.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.json"> 
    <link rel="icon" type="image/png" href="/icon512rounded.png">
    @if ($theme === 'dark')
        <meta name="theme-color" content="#28272C" />
    @else
        <meta name="theme-color" content="#F2F3F5" />
    @endif

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>HolaTales</title>
    <meta name="learning-source" content="{{ auth()->user()?->selected_language }}">
    <meta name="translation-target" content="{{ auth()->user()?->translation_language ?? 'en' }}">
    <meta name="translation-languages" content="{{ json_encode(config('learning.targets')) }}">
    <meta name="reverb-key" content="{{ config('broadcasting.connections.pusher.key') }}">
    <meta name="reverb-port" content="{{ config('reverb.client_port', 6001) }}">

    <script src="{{ mix('js/app.js') }}" defer></script>
    <script src="/js/dmak/raphael.js"></script>
    <script src="/js/dmak/dmak.js"></script>
    <script src="/js/dmak/dmakLoader.js"></script>
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    

    <link href="{{ mix('css/app.css') }}" rel="stylesheet">

    <!-- These are dynamically set with javascript -->
    <style id="dynamic-default-font"></style>
    <style id="dynamic-selected-font"></style>

    @yield('header')
<link rel="stylesheet" href="/learning-design.css">
</head>
<body><!--
--><div id="app"><!--
    -->@yield('content')<!--
--></div><!--
--></body></html>
