#!/usr/bin/env python3
"""
CaruCars — Build inventory-data.js from DealerCenter CSV.

Reads dealercenter_feed.csv (downloaded via FTP in the sync workflow),
parses it, classifies vehicle types, sorts by year/price descending,
and writes the result as a JS const for the website.

Filters out $0 price vehicles (not for sale — DealerCenter uses $0 for
in-transit or pending units).
"""
import csv
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).parent.parent
CSV_FILE = ROOT / 'dealercenter_feed.csv'
OUT_FILE = ROOT / 'inventory-data.js'


def classify(model: str, equipment: str) -> str:
    """Categorize vehicle by model keywords."""
    m = (model or '').lower()
    e = (equipment or '').lower()

    truck_words = ['crew cab', 'supercab', 'pickup', 'silverado', 'sierra',
                   'colorado', 'ranger', 'f-150', 'ram 1500', 'tacoma', 'frontier']
    if any(w in m for w in truck_words):
        return 'Truck'

    minivan_words = ['odyssey', 'caravan', 'sienna', 'minivan', 'pacifica']
    if any(w in m for w in minivan_words):
        return 'Minivan'

    convertible_words = ['convertible', 'cabriolet', 'roadster']
    if any(w in m for w in convertible_words):
        return 'Convertible'

    coupe_words = ['coupe', '2d']
    if any(w in m for w in coupe_words):
        return 'Coupe'

    sedan_words = ['sedan', 'civic', 'corolla', 'sentra', 'malibu', 'camry',
                   'accord', 'charger', 'altima', 'ghibli', 'c-class', 'ats', 'jetta']
    if any(w in m for w in sedan_words):
        return 'Sedan'

    if 'third row' in e:
        return 'SUV'

    suv_large = ['pilot', 'tahoe', 'traverse', 'atlas', 'explorer', 'expedition',
                 'suburban', 'highlander', 'pathfinder']
    if any(w in m for w in suv_large):
        return 'SUV'

    suv_words = ['suv', 'sport utility', 'trax', 'equinox', 'tucson', 'cr-v',
                 'rav4', 'rogue', 'escape', 'compass', 'renegade', 'terrain',
                 'encore', 'kona', 'acadia', 'discovery', '500x', 'grand cherokee']
    if any(w in m for w in suv_words):
        return 'SUV'

    return 'SUV'  # default fallback


def safe_int(value, default=0):
    """Parse int from a string, default on failure."""
    try:
        return int(str(value or '').strip() or 0)
    except (ValueError, TypeError):
        return default


def main():
    if not CSV_FILE.exists():
        print(f'ERROR: {CSV_FILE} not found', file=sys.stderr)
        sys.exit(1)

    vehicles = []

    with open(CSV_FILE, 'r', encoding='utf-8', errors='replace') as f:
        reader = csv.DictReader(f)
        for row in reader:
            price = safe_int(row.get('SpecialPrice'))
            if price <= 0:
                continue  # Skip $0 (not for sale)

            # Photos — space-separated URLs
            photo_raw = (row.get('PhotoURLs') or '').strip()
            photos = [p.strip() for p in photo_raw.split(' ')
                      if p.strip().startswith('http')]

            # Equipment — double-space separated
            equip_raw = row.get('EquipmentCode') or ''
            equipment = [e.strip() for e in re.split(r'\s{2,}', equip_raw) if e.strip()]

            # Description — strip replacement chars from encoding issues
            desc = (row.get('WebAdDescription') or '').replace('\ufffd', '').replace('\u2003', ' ')

            model = (row.get('Model') or '').strip()

            vehicles.append({
                'stock': (row.get('StockNumber') or '').strip(),
                'vin': (row.get('VIN') or '').strip(),
                'year': safe_int(row.get('Year')),
                'make': (row.get('Make') or '').strip(),
                'model': model,
                'miles': safe_int(row.get('Odometer')),
                'price': price,
                'extColor': (row.get('ExteriorColor') or '').strip(),
                'intColor': (row.get('InteriorColor') or '').strip(),
                'transmission': (row.get('Transmission') or '').strip(),
                'photos': photos,
                'img': photos[0] if photos else '',
                'equipment': equipment,
                'description': desc,
                'type': classify(model, equip_raw),
            })

    # Sort newest year first, then most expensive
    vehicles.sort(key=lambda v: (-v['year'], -v['price']))

    n = len(vehicles)
    makes = sorted(set(v['make'] for v in vehicles if v['make']))

    header = (
        '/* ============================================\n'
        '   CARU CARS — Inventory Data\n'
        '   Auto-generated from DealerCenter feed\n'
        f'   {n} vehicles\n'
        '   ============================================ */\n\n'
    )

    body = 'const INVENTORY = ' + json.dumps(vehicles, indent=2, ensure_ascii=False) + ';\n'

    OUT_FILE.write_text(header + body, encoding='utf-8')

    print(f'OK: Built {OUT_FILE.name} with {n} vehicles')
    print(f'  Makes: {", ".join(makes)}')
    print(f'  With photos: {sum(1 for v in vehicles if v["photos"])}/{n}')


if __name__ == '__main__':
    main()
