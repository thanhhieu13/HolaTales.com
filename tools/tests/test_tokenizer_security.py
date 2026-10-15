"""Test the real path validator without starting Bottle or loading NLP models."""
import ast
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from bottle import abort, HTTPError

source = Path(__file__).resolve().parents[1] / 'tokenizer.py'
validator = next(node for node in ast.parse(source.read_text()).body
                 if isinstance(node, ast.FunctionDef) and node.name == 'safe_import_path')
namespace = {'Path': Path, 'os': os, 'abort': abort}
exec(compile(ast.Module(body=[validator], type_ignores=[]), str(source), 'exec'), namespace)
safe_import_path = namespace['safe_import_path']


class ImportPathTest(unittest.TestCase):
    def test_only_files_inside_import_root_are_accepted(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / 'temp'
            root.mkdir()
            valid = root / 'story.epub'
            valid.write_text('fixture')
            outside = Path(directory) / 'private.txt'
            outside.write_text('fixture')
            link = root / 'link.epub'
            link.symlink_to(outside)
            with patch.dict(os.environ, {'TOKENIZER_IMPORT_ROOT': str(root)}):
                self.assertEqual(str(valid.resolve()), safe_import_path(str(valid)))
                for value in [str(outside), str(link), str(root / 'missing'), None]:
                    with self.assertRaises(HTTPError):
                        safe_import_path(value)
                with valid.open('wb') as file:
                    file.truncate(21 * 1024 * 1024)
                with self.assertRaises(HTTPError):
                    safe_import_path(str(valid))


if __name__ == '__main__':
    unittest.main()
