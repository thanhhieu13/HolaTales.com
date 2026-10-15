<article class="card">
    <div class="row">
        <span class="tag">{{ config('learning.sources.'.$story->language.'.label') }} · {{ $story->level }}</span>
        <span class="meta">{{ $story->is_premium?'Pro':'Free' }} · {{ max(1,ceil($story->estimated_word_count/100)) }} phút (ước tính)</span>
    </div>
    <h3><a class="story-title-link" href="/catalogue/{{ $story->id }}">{{ $story->title }}</a></h3>
    <p>{{ $story->summary }}</p>
    <span class="meta">{{ $story->topic }} · {{ $story->estimated_word_count }} từ (ước tính)</span>
    <p class="meta">Bản dịch hiện có:
        @forelse(($translations[$story->id] ?? collect()) as $translation)
            {{ config('learning.targets.'.$translation->target,$translation->target) }} ({{ $translation->review_status==='approved'?'đã duyệt':'dịch máy' }}){{ !$loop->last?', ':'' }}
        @empty
            chưa lưu; tạo khi đọc
        @endforelse
    </p>
    <form class="actions" method="post" action="/stories/{{ $story->id }}/start">
        @csrf
        <button>{{ $story->is_premium && auth()->user()->plan!=='pro'?'Khám phá Pro':'Đọc truyện →' }}</button>
    </form>
</article>
