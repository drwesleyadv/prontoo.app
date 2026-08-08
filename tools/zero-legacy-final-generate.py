from pathlib import Path
import runpy

runpy.run_path(str(Path(__file__).with_name('zero-legacy-final-v2.py')), run_name='__main__')
