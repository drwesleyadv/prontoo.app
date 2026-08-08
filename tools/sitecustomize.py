from pathlib import Path
import atexit
import runpy

_tools = Path(__file__).resolve().parent
_fix = _tools / 'zero-legacy-root-entrypoints-fix.py'
_self = Path(__file__).resolve()

@atexit.register
def _finalize_root_entrypoints() -> None:
    if _fix.exists():
        runpy.run_path(str(_fix), run_name='__zero_legacy_root_fix__')
        _fix.unlink()
    if _self.exists():
        _self.unlink()
