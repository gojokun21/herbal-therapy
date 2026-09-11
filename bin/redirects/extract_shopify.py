"""
Extrage din datele brute ale site-ului vechi (Shopify) lista de adrese care au
nevoie de redirect si o scrie compact in bin/redirects/shopify.json.

Sursele (folderul "SEO Herbal", in afara repo-ului):
  _date-brute_scripturi/shopify_json.json   catalogul Shopify (produse, colectii)
  _date-brute_scripturi/crawl_results.json  crawl-ul complet al site-ului (pagini, blog, politici)
Optional, pentru legatura produs Shopify -> produs WooCommerce:
  bin/import/data/match_report.json         SKU Excel/WooCommerce -> handle Shopify
  bin/import/data/potriviri_manuale.json    denumire Excel -> id produs Shopify

Rulare:
  python bin/redirects/extract_shopify.py "C:/Users/.../SEO Herbal"

Fisierul rezultat e comis in repo, ca bin/redirects/build-map.php sa poata
regenera harta fara datele brute.
"""
import json
import os
import sys
from collections import defaultdict
from urllib.parse import unquote, urlparse

HERE = os.path.dirname(os.path.abspath(__file__))
THEME = os.path.dirname(os.path.dirname(HERE))
SEO = sys.argv[1] if len(sys.argv) > 1 else 'C:/Users/Sorin-kun/Pictures/SEO Herbal'
RAW = os.path.join(SEO, '_date-brute_scripturi')
IMPORT_DATA = os.path.join(THEME, 'bin', 'import', 'data')


def load(path):
    with open(path, encoding='utf-8') as f:
        return json.load(f)


def path_of(url):
    p = unquote(urlparse(url).path).rstrip('/')
    for pre in ('/ru', '/en'):
        if p == pre or p.startswith(pre + '/'):
            p = p[len(pre):]
    return p or '/'


sj = load(os.path.join(RAW, 'shopify_json.json'))
crawl = load(os.path.join(RAW, 'crawl_results.json'))

# --- legatura cu WooCommerce, cand exista raportul importului ---------------
sku_of_handle = {}
title_of_handle = {}
by_id = {p['id']: p for p in sj['products']}
mr_path = os.path.join(IMPORT_DATA, 'match_report.json')
pm_path = os.path.join(IMPORT_DATA, 'potriviri_manuale.json')
if os.path.exists(mr_path):
    for r in load(mr_path)['sure']:
        if r.get('sku'):
            sku_of_handle.setdefault(r['handle'], r['sku'])
        else:
            title_of_handle.setdefault(r['handle'], r['denumire'])
if os.path.exists(pm_path):
    for name, sid in load(pm_path).items():
        if sid in by_id:
            title_of_handle.setdefault(by_id[sid]['handle'], name)

coll_of = defaultdict(list)
for c in sj['collections']:
    for h in c['products']:
        coll_of[h].append(c['handle'])

products = []
for p in sorted(sj['products'], key=lambda x: x['handle']):
    h = p['handle']
    rec = {
        'handle': h,
        'title': p['title'],
        'skus': sorted({v.get('sku') or '' for v in p['variants']} - {''}),
        'collections': sorted(c for c in coll_of[h] if c not in ('all', 'colectie', 'toate-produsele')),
    }
    if h in sku_of_handle:
        rec['wp_sku'] = sku_of_handle[h]
    elif h in title_of_handle:
        rec['wp_title'] = title_of_handle[h]
    products.append(rec)

collections = [{'handle': c['handle'], 'title': c['title'], 'count': c['products_count']}
               for c in sorted(sj['collections'], key=lambda x: x['handle'])]

pages, policies, blogs, other = set(), set(), defaultdict(set), set()
for url, d in crawl.items():
    if d.get('status') != 200 or d.get('redirected'):
        continue
    p = path_of(url)
    seg = p.strip('/').split('/')
    if seg[0] == 'pages' and len(seg) == 2:
        pages.add(seg[1])
    elif seg[0] == 'policies' and len(seg) == 2:
        policies.add(seg[1])
    elif seg[0] == 'blogs' and len(seg) >= 2:
        blogs[seg[1]].update(seg[2:3])
    elif seg[0] not in ('products', 'collections', '') and p != '/':
        other.add(p)

out = {
    'site': 'https://herbal-therapy.md',
    'languages': ['ro', 'ru', 'en'],
    'products': products,
    'collections': collections,
    'pages': sorted(pages),
    'policies': sorted(policies),
    'blogs': {b: sorted(a) for b, a in sorted(blogs.items())},
    'other': sorted(other),
}
dest = os.path.join(HERE, 'shopify.json')
with open(dest, 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, indent=1)
print('produse', len(products), 'cu wp_sku', sum('wp_sku' in p for p in products), 'cu wp_title', sum('wp_title' in p for p in products))
print('colectii', len(collections), 'pagini', len(pages), 'politici', len(policies), 'bloguri', {b: len(a) for b, a in blogs.items()}, 'altele', sorted(other))
print('scris', dest)
