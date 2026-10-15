import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from epub_security import validate_epub_archive


class EpubSecurityTest(unittest.TestCase):
    def archive(self, entries):
        temporary = tempfile.NamedTemporaryFile(suffix='.epub')
        self.addCleanup(temporary.close)
        with zipfile.ZipFile(temporary.name, 'w', zipfile.ZIP_DEFLATED) as archive:
            archive.writestr('mimetype', 'application/epub+zip')
            for name, body in entries:
                archive.writestr(name, body)
        return temporary.name

    def test_normal_epub(self):
        validate_epub_archive(self.archive([('OEBPS/chapter.xhtml', '<p>Hola</p>')]))

    def test_unsafe_paths(self):
        for name in ['../secret', '/absolute', 'a\\b', 'C:drive', '/'.join(['a'] * 13)]:
            with self.subTest(name=name), self.assertRaises(ValueError):
                validate_epub_archive(self.archive([(name, 'bad')]))

    def test_symlink(self):
        entry = zipfile.ZipInfo('link')
        entry.external_attr = 0o120777 << 16
        with self.assertRaises(ValueError):
            validate_epub_archive(self.archive([(entry, 'target')]))

    def test_bomb_and_entry_count(self):
        with self.assertRaises(ValueError):
            validate_epub_archive(self.archive([('bomb', b'0' * (2 * 1024 * 1024))]))
        with self.assertRaises(ValueError):
            validate_epub_archive(self.archive([(str(i), 'x') for i in range(1000)]))

    def test_invalid_zip(self):
        with tempfile.NamedTemporaryFile() as file:
            file.write(b'not a zip')
            file.flush()
            with self.assertRaises(ValueError):
                validate_epub_archive(file.name)
