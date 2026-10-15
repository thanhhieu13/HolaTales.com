"""Bound untrusted EPUB archives before EbookLib allocates decompressed content."""
import stat
import time
import zipfile
from pathlib import PurePosixPath


def validate_epub_archive(filename):
    deadline = time.monotonic() + 10
    total = 0
    try:
        with zipfile.ZipFile(filename) as archive:
            entries = archive.infolist()
            if len(entries) > 1000:
                raise ValueError('EPUB contains too many entries')
            names = set()
            for entry in entries:
                name = entry.filename
                path = PurePosixPath(name)
                mode = entry.external_attr >> 16
                if (name in names or '\\' in name or ':' in name or path.is_absolute()
                        or '..' in path.parts or len(path.parts) > 12
                        or stat.S_ISLNK(mode) or entry.flag_bits & 1):
                    raise ValueError('Unsafe EPUB entry')
                names.add(name)
                total += entry.file_size
                if (total > 50 * 1024 * 1024 or entry.file_size > 10 * 1024 * 1024
                        or entry.file_size > max(1024 * 1024, entry.compress_size * 100)):
                    raise ValueError('EPUB decompression limit exceeded')
                # Check actual streamed bytes and CRC, not just attacker-controlled metadata.
                actual = 0
                if not entry.is_dir():
                    with archive.open(entry) as stream:
                        while True:
                            chunk = stream.read(65536)
                            if not chunk:
                                break
                            actual += len(chunk)
                            if actual > entry.file_size or time.monotonic() > deadline:
                                raise ValueError('EPUB processing limit exceeded')
            if 'mimetype' not in names or archive.read('mimetype') != b'application/epub+zip':
                raise ValueError('Invalid EPUB mimetype')
    except (zipfile.BadZipFile, RuntimeError, NotImplementedError, EOFError) as error:
        raise ValueError('Invalid EPUB archive') from error
