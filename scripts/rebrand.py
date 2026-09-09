#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
repls = [
    ('teal-', 'brand-'),
    ('rgba(45,212,191,', 'rgba(224,34,154,'),
    ('rgba(20,184,166,', 'rgba(145,37,202,'),
    ('#08DEC7', '#E0229A'),
    ('#14b8a6', '#E0229A'),
    ('#0d9488', '#9125CA'),
    ('#0D9488', '#9125CA'),
]

updated = 0
for folder in [ROOT / 'resources' / 'js', ROOT / 'public', ROOT / 'resources' / 'views']:
    if not folder.exists():
        continue
    for p in folder.rglob('*'):
        if p.suffix.lower() not in {'.js', '.jsx', '.json', '.webmanifest', '.php', '.css', '.blade.php'}:
            if '.blade.php' not in p.name and p.suffix not in {'.js', '.jsx', '.json', '.webmanifest', '.php', '.css'}:
                continue
        try:
            c = p.read_text(encoding='utf-8')
        except Exception:
            continue
        n = c
        for a, b in repls:
            n = n.replace(a, b)
        if n != c:
            p.write_text(n, encoding='utf-8')
            updated += 1
            print(p.relative_to(ROOT))

print(f'DONE {updated}')
