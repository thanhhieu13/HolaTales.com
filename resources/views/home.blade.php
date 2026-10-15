@extends('layouts.user')
@section('content')
<script id="app-bootstrap" type="application/json">{!! json_encode(['_userName'=>$userName, '_userEmail'=>$userEmail, '_userUuid'=>$userUuid, '_userCount'=>$userCount, '_isAdmin'=>$isAdmin, 'themeSettings'=>$themeSettings, '_selectedLanguage'=>$language], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
