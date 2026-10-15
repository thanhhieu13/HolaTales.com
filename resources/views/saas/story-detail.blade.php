@extends('saas.layout')
@section('title', $story->title.' — HolaTales')
@section('content')
<a class="back-link" href="{{ auth()->check()?'/stories':'/catalogue' }}">← Thư viện truyện</a>
<section class="story-detail-hero">
<div><span class="eyebrow">Một câu chuyện, một bước tiến</span><h1>{{ $story->title }}</h1><p class="detail-summary">{{ $story->summary }}</p><div class="row"><span class="tag">{{ config('learning.sources.'.$story->language.'.label') }}</span><span class="tag">{{ $story->level }}</span><span class="tag">{{ $story->is_premium?'Pro':'Free' }}</span></div><p class="meta">{{ $story->topic }} · {{ $story->estimated_word_count }} từ · khoảng {{ max(1,ceil($story->estimated_word_count/100)) }} phút</p>
<div class="actions row">
@auth
@if(auth()->user()->selected_language!==$story->language)
<a class="btn" href="/onboarding">Chọn ngôn ngữ học này</a>
@elseif($chapter)
<a class="btn" href="/learn/books/{{ $chapter->book_id }}">{{ $chapter->read_count?'Đọc lại truyện':'Tiếp tục đọc' }} →</a>
@else
<form method="post" action="/stories/{{ $story->id }}/start">@csrf<button>{{ $story->is_premium && auth()->user()->plan!=='pro'?'Khám phá Pro':'Bắt đầu đọc' }} →</button></form>
@endif
@else
<a class="btn" href="/register?story={{ $story->id }}&target={{ $target }}">Tạo tài khoản để lưu tiến độ</a>
@endauth
@if(!$story->is_premium)<a class="btn secondary" href="/try/{{ $story->id }}?target={{ $target }}">Đọc thử không cần tài khoản</a>@endif
</div></div>
<aside class="card learning-outcomes"><span class="eyebrow">Trong lượt đọc này</span><h2>Đọc. Hiểu. Ghi nhớ.</h2><ol><li>Đọc bản gốc {{ config('learning.sources.'.$story->language.'.label') }} theo nhịp của bạn.</li><li>Xem giải nghĩa bằng {{ config('learning.targets.'.$target) }}.</li><li>Lưu từ trong ngữ cảnh để ôn lại sau.</li></ol><p class="meta">Bản dịch của phiên bản này:</p>@forelse($translations as $translation)<p>{{ config('learning.targets.'.$translation->target) }} <span class="tag">{{ $translation->review_status==='approved'?'Đã duyệt':'Tự động · chưa duyệt' }}</span></p>@empty<p class="meta">Chưa lưu bản dịch. Hệ thống tạo khi mở đọc; nếu dịch vụ lỗi, bản gốc vẫn đọc được.</p>@endforelse</aside>
</section>
<section class="card section">@if($story->relationLoaded('published_chapters'))<h2>{{ $story->getRelation('published_chapters')->count() }} chương · version {{ $story->publication_version }}</h2><ol>@foreach($story->getRelation('published_chapters') as $original)<li>{{ $original->title }}</li>@endforeach</ol>@endif<h2>Mở đầu câu chuyện</h2><p class="story-excerpt" lang="{{ config('learning.sources.'.$story->language.'.code') }}">{{ \Illuminate\Support\Str::limit($story->body,300) }}</p>@if($chapter)<p class="meta">{{ $chapter->read_count?'Bạn đã hoàn thành chương này. Đọc lại không cộng trùng tiến độ.':'Truyện đã ở trong thư viện của bạn; tiếp tục sẽ không tạo thêm bản sao.' }}</p>@endif</section>
@endsection
