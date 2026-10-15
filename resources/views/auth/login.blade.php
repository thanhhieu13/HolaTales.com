@extends('layouts.user')
@section('content')
<script id="app-bootstrap" type="application/json">{!! json_encode(['_userCount'=>$userCount, 'themeSettings'=>new stdClass, '_selectedLanguage'=>'spanish'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@endsection
