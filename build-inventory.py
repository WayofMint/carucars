#!/usr/bin/env python3
"""
CARU CARS — DealerCenter CSV → inventory-data.js converter
Run this after each new CSV feed lands on FTP.
Usage: python3 build-inventory.py
"""
import csv
import json
import os

FEED_FILE = os.path.join(os.path.dirname(__file__), 'dealercenter_feed.csv')
OUTPUT_FILE = os.path.join(os.path.dirname(__file__), 'inventory-data.js')

def classify_type(model, equipment):
    """Guess vehicle type from model name and equipment."""
    model_lower = model.lower()
    equip_lower = equipment.lower() if equipment else ''

    if any(w in model_lower for w in ['crew cab', 'supercab', 'pickup', 'silverado', 'sierra', 'colorado', 'ranger', 'f-150', 'ram 1500', 'tacoma', 'frontier']):
        return 'Truck'
    if any(w in model_lower for w in ['odyssey', 'caravan', 'sienna', 'minivan', 'pacifica']):
        return 'Minivan'
    if any(w in model_lower for w in ['convertible', 'cabriolet', 'roadster']):
        return 'Convertible'
    if any(w in model_lower for w in ['coupe', '2d']):
        return 'Coupe'
    if any(w in model_lower for w in ['sedan', 'civic', 'corolla', 'sentra', 'malibu', 'camry', 'accord', 'charger', 'altima', 'ghibli', 'c-class', 'ats', 'jetta']):
        return 'Sedan'
    if 'third row' in equip_lower or any(w in model_lower for w in ['pilot', 'tahoe', 'traverse', 'atlas', 'explorer', 'expedition', 'suburban', 'highlander', 'pathfinder']):
        return 'SUV'
    # Default to SUV for most crossovers
    if any(w in model_lower for w in ['suv', 'sport utility', 'trax', 'equinox', 'tucson', 'cr-v', 'rav4', 'rogue', 'escape', 'compass', 'renegade', 'terrain', 'encore', 'kona', 'acadia', 'discovery', '500x', 'grand cherokee']):
        return 'SUV'
    return 'SUV'  # safe default


def parse_feed():
    vehicles = []

    with open(FEED_FILE, 'r', encoding='utf-8', errors='replace') as f:
        reader = csv.DictReader(f)
        for row in reader:
            price = int(row.get('SpecialPrice', '0') or '0')

            # Split photos — space-separated in the CSV
            photo_urls = row.get('PhotoURLs', '').strip()
            photos = [p.strip() for p in photo_urls.split(' ') if p.strip().startswith('http')] if photo_urls else []

            # Parse equipment into array
            equipment_raw = row.get('EquipmentCode', '') or ''
            equipment = [e.strip() for e in equipment_raw.split('  ') if e.strip()] if equipment_raw else []

            # Clean up description — remove encoding artifacts
            desc = (row.get('WebAdDescription', '') or '').replace('\ufffd', '').replace('�', '')

            vehicle = {
                'stock': row.get('StockNumber', '').strip(),
                'vin': row.get('VIN', '').strip(),
                'year': int(row.get('Year', '0') or '0'),
                'make': row.get('Make', '').strip(),
                'model': row.get('Model', '').strip(),
                'miles': int(row.get('Odometer', '0') or '0'),
                'price': price,
                'extColor': row.get('ExteriorColor', '').strip(),
                'intColor': row.get('InteriorColor', '').strip(),
                'transmission': row.get('Transmission', '').strip(),
                'photos': photos,
                'img': photos[0] if photos else '',
                'equipment': equipment,
                'description': desc,
                'type': classify_type(
                    row.get('Model', ''),
                    row.get('EquipmentCode', '')
                )
            }
            vehicles.append(vehicle)

    # Sort by year desc, then price desc
    vehicles.sort(key=lambda v: (-v['year'], -v['price']))

    return vehicles


def write_js(vehicles):
    js_lines = [
        '/* ============================================',
        '   CARU CARS — Inventory Data',
        '   Auto-generated from DealerCenter feed',
        f'   {len(vehicles)} vehicles',
        '   ============================================ */',
        '',
        'const INVENTORY = ' + json.dumps(vehicles, indent=2, ensure_ascii=False) + ';',
    ]

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write('\n'.join(js_lines) + '\n')

    print(f'✅ Wrote {len(vehicles)} vehicles to {OUTPUT_FILE}')


if __name__ == '__main__':
    vehicles = parse_feed()
    write_js(vehicles)

    # Quick summary
    makes = set(v['make'] for v in vehicles)
    with_photos = sum(1 for v in vehicles if v['photos'])
    with_price = sum(1 for v in vehicles if v['price'] > 0)
    print(f'   Makes: {", ".join(sorted(makes))}')
    print(f'   With photos: {with_photos}/{len(vehicles)}')
    print(f'   With price: {with_price}/{len(vehicles)}')
