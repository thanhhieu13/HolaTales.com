"""Synthetic EPUB/SRT fixtures; no user content."""
from pathlib import Path
from ebooklib import epub

root = Path(__file__).resolve().parents[2] / 'storage' / 'app' / 'temp'
root.mkdir(parents=True, exist_ok=True)
book = epub.EpubBook()
book.set_identifier('rc-synthetic')
book.set_title('RC synthetic fixture')
book.set_language('es')
chapter = epub.EpubHtml(title='Ana', file_name='ana.xhtml', lang='es')
chapter.content = '<h1>Ana</h1><p>Ana lee un libro. Lucía visita Madrid.</p>'
book.add_item(chapter)
book.toc = (chapter,)
book.spine = ['nav', chapter]
book.add_item(epub.EpubNcx())
book.add_item(epub.EpubNav())
epub.write_epub(str(root / 'rc-fixture.epub'), book)
(root / 'rc-fixture.srt').write_text(
    '1\n00:00:01,000 --> 00:00:03,000\nAna lee un libro.\n\n'
    '2\n00:00:04,000 --> 00:00:06,000\nLucía visita Madrid.\n', encoding='utf-8')
