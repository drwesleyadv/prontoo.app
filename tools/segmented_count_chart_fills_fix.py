from pathlib import Path

path = Path("tools/segmented_count_chart_fills.py")
content = path.read_text(encoding="utf-8")
old = '''    ''' + "'''" + '''        '"value_type" => "count"',
        '$recentValue = $valueType === "count"',''' + "'''" + ''','''
new = '''    ''' + "'''" + '''        '"value_type" => "count"',
        '? $responseArea . $loadArea',
        ': $loadArea . $responseArea;',
        '$recentValue = $valueType === "count"',''' + "'''" + ''','''
count = content.count(old)
if count != 1:
    raise RuntimeError(f"generator architecture anchor: expected one occurrence, found {count}")
path.write_text(content.replace(old, new, 1), encoding="utf-8")
