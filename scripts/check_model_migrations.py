#!/usr/bin/env python3
import re
import os

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
MODELS_DIR = os.path.join(ROOT, 'Dolphin_Backend', 'app', 'Models')
MIGRATIONS_DIR = os.path.join(ROOT, 'Dolphin_Backend', 'database', 'migrations')

fillable_re = re.compile(r"protected\s+\$fillable\s*=\s*\[([\s\S]*?)\];", re.M)
array_item_re = re.compile(r"['\"]([a-zA-Z0-9_]+)['\"]")

migration_files = []
for root, dirs, files in os.walk(MIGRATIONS_DIR):
    for f in files:
        if f.endswith('.php'):
            migration_files.append(os.path.join(root, f))

migration_text = ''
for path in migration_files:
    try:
        with open(path, 'r', encoding='utf-8') as fh:
            migration_text += fh.read()
    except Exception:
        pass

results = {}

for root, dirs, files in os.walk(MODELS_DIR):
    for f in files:
        if not f.endswith('.php'):
            continue
        path = os.path.join(root, f)
        with open(path, 'r', encoding='utf-8') as fh:
            content = fh.read()
        m = fillable_re.search(content)
        attrs = []
        if m:
            block = m.group(1)
            attrs = array_item_re.findall(block)
        # also include casts keys
        casts = re.findall(r"protected\s+\$casts\s*=\s*\[([\s\S]*?)\];", content)
        cast_keys = []
        if casts:
            cast_block = casts[0]
            cast_keys = array_item_re.findall(cast_block)
        all_keys = sorted(set(attrs + cast_keys))
        missing = []
        for key in all_keys:
            if key == 'id':
                continue
            if key in migration_text:
                continue
            missing.append(key)
        results[f] = {
            'file': path,
            'attrs': all_keys,
            'missing_in_migrations': missing,
        }

# Print concise report
any_missing = False
for model, info in results.items():
    if info['missing_in_migrations']:
        any_missing = True
        print(f"Model: {model}")
        print("  Attributes:", ', '.join(info['attrs']))
        print("  Missing in migrations:", ', '.join(info['missing_in_migrations']))
        print()

if not any_missing:
    print('No missing model attributes found in migrations (quick check).')

# Exit code 0
