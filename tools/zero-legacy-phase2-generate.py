from pathlib import Path

_part_dir = Path(__file__).resolve().parent
_parts = [
    'zero-legacy-phase3-part-01.pyfrag',
    'zero-legacy-phase3-part-02.pyfrag',
    'zero-legacy-phase3-part-03.pyfrag',
    'zero-legacy-phase3-part-04.pyfrag',
    'zero-legacy-phase3-part-05.pyfrag',
    'zero-legacy-phase3-part-06.pyfrag',
    'zero-legacy-phase3-part-07.pyfrag',
]
for _part in _parts:
    _path = _part_dir / _part
    exec(compile(_path.read_text(), str(_path), 'exec'), globals())
for _part in _parts:
    (_part_dir / _part).unlink()
