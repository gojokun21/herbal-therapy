# -*- coding: utf-8 -*-
"""Build Herbal_Keywords_Ahrefs_<date>.xlsx from the scraped Ahrefs free keyword generator data."""
import json, glob, os, re, sys, unicodedata, datetime
from collections import defaultdict, Counter
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.utils import get_column_letter

SP = os.path.join(os.path.dirname(os.path.abspath(__file__)), "data")
SEO_XLSX = os.path.expanduser('~/Documents/Herbal_SEO_Produse_RO_RU.xlsx')
OUT = os.path.expanduser('~/Documents/Herbal_Keywords_Ahrefs_%s.xlsx' % datetime.date.today().isoformat())

RANK = {'<100': 0, '>100': 1, '>1k': 2, '>10k': 3, '>100k': 4}
KD = {'E': 'Easy', 'M': 'Medium', 'H': 'Hard', '': ''}
BRANDS = ['secom', 'dr max', 'dr. max', 'catena', 'farmacia tei', 'farmacia dona', 'dona', 'help net', 'helpnet', 'sensiblu', 'emag',
          'weleda', 'avengers', 'zdrovit', 'cosmetic plant', 'gerovital', 'nivea', 'elmiplant', 'bioderma', 'la roche', 'eucerin',
          'lidl', 'kaufland', 'carrefour', 'auchan', 'walmark', 'sunwave', 'alevia', 'fares', 'dacia plant', 'hypericum', 'plafar',
          'naturalis', 'parapharm', 'herbagetica', 'solaray', 'now foods', 'doppelherz', 'vitabiotics', 'swanson', 'jarrow', 'zenyth',
          'zuzu', 'sanovita', 'biofarm', 'terapia', 'aboca', 'tis', 'ozone', 'antibiotice', 'labormed', 'equilibra', 'livsane', 'beres',
          'redoxon', 'cetebe', 'alinan', 'fortical', 'hyllan', 'osteocare', 'eurovita', 'additiva', 'dr. hart', 'dr hart', 'aquamin',
          'solgar', 'melkfett', 'klorane', 'farmec', 'alkmene', 'urtekram', 'farmasi', 'nala', 'yves rocher', 'avon', 'malizia', 'genera',
          'thayers', 'rituals', 'sabon', 'neutrogena', 'cerave', "l'occitane", 'l occitane', 'vaseline', 'dove', 'protex', 'teo', 'dermomed',
          'dior', 'cicaplast', 'forever', 'oriflame', 'eos', 'scholl', 'mebra', 'labobell', 'shefoot', 'favisan', 'abemar', 'jhonson',
          'johnson', 'ambre solaire', 'fiterman', 'avene', 'bio paltin', 'jamila', 'septilin', 'bronhosuport', 'garnier', 'ascolip',
          'calcidin', 'dercos', 'nizoral', 'londa', 'kerastase', 'elseve', 'seboderm', 'wella', 'parusan', 'ducray', 'nashi', 'tulipan',
          'lipikar', 'lactovit', 'atoderm', 'cottage', 'keff', 'some by mi', 'mi amante', 'huda', 'ozlex', 'vegis', 'onu', 'zentiva',
          'аптека', 'фармация', 'felicia', 'orange', 'linella', 'green hills', 'ozon', 'wildberries', 'iherb', 'эвалар', 'evalar', 'now',
          'solgar', 'doppelherz', 'доппельгерц', 'солгар', 'компливит', 'супрадин', 'алфавит', 'бепантен', 'пантенол', 'apteka', 'farmacia',
          'фармак', 'арго', 'сибирское здоровье', 'faberlic', 'фаберлик', 'oriflame', 'орифлейм', 'avon', 'эйвон']
INFO = ['cel mai bun', 'cea mai buna', 'cele mai bune', 'beneficii', 'contraindicatii', 'contraindicații', 'prospect', 'pareri', 'păreri', 'reteta', 'retete', 'rețet', 'forum', 'preparare',
        'cum ', 'ce ', 'cat ', 'cât ', 'cand ', 'când ', 'este', 'ingrasa', 'simptome', 'alimente', 'la ce', 'pentru ce', 'review', 'para que',
        'que es', 'in casa', 'homemade', 'engleza', 'wikipedia', 'efecte', 'adverse', 'periculoasa', 'din ce', 'unde ', 'de ce', 'care ',
        'польза', 'вред', 'отзыв', 'инструкция', 'как ', 'что ', 'чем ', 'для чего', 'рецепт', 'противопоказ', 'применение', 'сколько', 'можно ли',
        'зачем', 'почему', 'какой', 'какая', 'какие', 'при ', 'в домашних', 'своими руками', 'состав', 'дозировка', 'побочные']


# seed (normalised) -> alternative phrasings that were also queried; used for "alternativă mai căutată"
RELATED = {
    'vitamina c zinc': ['vitamina c cu zinc'],
    'vitamina c cu echinacea si zinc': ['vitamina c cu echinacea', 'vitamina c cu zinc'],
    'vitamina c cu propolis si echinacea': ['vitamina c cu propolis', 'vitamina c cu echinacea'],
    'vitamina c cu propolis si miere': ['vitamina c cu propolis'],
    'vitamina c cu propolis si polen': ['vitamina c cu propolis'],
    'vitamina c cu aroma de lamaie': ['vitamina c masticabila', 'vitamina c copii'],
    'vitamina c cu aroma de portocala': ['vitamina c masticabila', 'vitamina c copii'],
    'vitamina c cu aroma de struguri': ['vitamina c masticabila', 'vitamina c copii'],
    'vitamina c cu aroma de zmeura': ['vitamina c masticabila', 'vitamina c copii'],
    'vitamina c cu glucoza': ['vitamina c masticabila'],
    'vitamina c 500 mg': ['vitamina c masticabila'],
    'calciu magneziu zinc vitamina d3': ['calciu magneziu zinc d3'],
    'calciu d3': ['calciu cu vitamina d3'],
    'calciu d3 forte': ['calciu d3', 'calciu cu vitamina d3'],
    'magneziu vitamina b6': ['magneziu cu b6'],
    'multivitamine pentru adulti': ['multivitamine'],
    'multivitamine pentru adolescenti': ['multivitamine copii', 'multivitamine'],
    'b complex vitamina c': ['multivitamine'],
    'melkfett crema': ['melkfett', 'crema cu galbenele'],
    'crema de maini cu galbenele': ['crema cu galbenele'],
    'crema de maini cu catina': ['crema cu catina'],
    'crema de maini cu musetel': ['crema de maini cu glicerina'],
    'crema de maini cu aloe vera': ['crema de maini cu glicerina'],
    'crema de maini cu immortelle': ['crema de maini cu glicerina'],
    'crema de maini naturala': ['crema de maini cu glicerina'],
    'crema pentru calcaie crapate': ['crema pentru calcaie', 'crema de calcaie'],
    'unguent cu galbenele': ['crema cu galbenele'],
    'unguent cu galbenele si propolis': ['crema cu propolis', 'unguent cu propolis', 'crema cu galbenele'],
    'unguent cu galbenele si siminoc': ['crema cu galbenele'],
    'unguent cu arnica': ['crema cu arnica'],
    'unguent cu radacina de tataneasa': ['crema cu tataneasa', 'unguent cu tataneasa'],
    'unguent cu radacina de spanz': ['crema cu spanz'],
    'unguent cu ulei de catina': ['crema cu catina', 'unguent cu catina'],
    'unguent cu gheara diavolului': ['unguent pentru articulatii', 'crema pentru articulatii'],
    'unguent cu ardei iute': ['unguent pentru dureri musculare', 'unguent pentru articulatii'],
    'ulei revulsiv': ['unguent pentru dureri musculare'],
    'unguent cu rostopasca': ['unguent pentru negi'],
    'unguent cu musetel si aloe vera': ['unguent cu musetel', 'unguent cu aloe vera'],
    'reumix herbal': ['crema pentru articulatii', 'unguent pentru articulatii'],
    'varix herbal': ['crema pentru varice'],
    'artix herbal': ['unguent pentru dureri musculare', 'crema pentru articulatii'],
    'geucamen': ['unguent pentru dureri musculare'],
    'geucamen emulgel': ['unguent pentru dureri musculare'],
    'reliefix': ['crema pentru articulatii'],
    'bom-benghe': ['unguent pentru dureri musculare'],
    'balsam cu camfor si mentol': ['unguent pentru dureri musculare'],
    'flusept cu propolis': ['pastile pentru gat', 'flusept'],
    'flusept cu salvie': ['pastile pentru gat', 'flusept'],
    'sirop imuno patlagina': ['sirop de patlagina'],
    'sirop imuno cimbrisor': ['sirop de cimbrisor'],
    'sirop imuno iedera': ['sirop de iedera', 'sirop pentru tuse'],
    'sirop imuno tusin': ['sirop pentru tuse', 'sirop de tuse copii'],
    'sirop imuno echinacea': ['sirop de echinacea', 'sirop pentru imunitate'],
    'sirop imuno echinacea zinc': ['sirop pentru imunitate'],
    'sirop imuno vitamine copii': ['multivitamine copii', 'vitamina c copii'],
    'sirop imuno detox': ['sirop pentru imunitate'],
    'sirop imuno herbal-lax': ['ceai laxativ'],
    'sirop imuno resveratrol': ['sirop pentru imunitate'],
    'sirop phytocalm copii': ['sirop pentru somn copii', 'sirop calmant copii'],
    'sirop phytocalm somn': ['sirop pentru somn copii', 'sirop calmant copii'],
    'sirop de macese': ['sirop de macese'],
    'pudra pentru copii': ['pudra de talc'],
    'pudra mentolata': ['pudra de talc', 'pudra pentru picioare'],
    'pudra antitranspiratie': ['pudra pentru picioare', 'pudra de talc'],
    'apa micelara cu hidrolat de immortelle': ['apa micelara'],
    'apa micelara cu hidrolat de lavanda': ['apa micelara'],
    'acid salicilic 1%': ['acid salicilic'],
    'lotiune antiacneica': ['acid salicilic'],
    'spumant de baie': ['spuma de baie'],
    'spumant de baie relaxant': ['spuma de baie', 'spumant de baie'],
    'spumant de baie revitalizant': ['spuma de baie', 'spumant de baie'],
    'sapun lichid cu aloe vera': ['sapun lichid natural', 'sapun lichid cu glicerina'],
    'sapun lichid cu ceai verde': ['sapun lichid natural'],
    'sapun lichid cu galbenele': ['sapun lichid natural'],
    'sapun lichid cu musetel': ['sapun lichid natural'],
    'gel de dus cu catina': ['gel de dus natural'],
    'gel de dus cu galbenele': ['gel de dus natural'],
    'gel de dus cu immortelle': ['gel de dus natural'],
    'gel de dus cu musetel': ['gel de dus natural'],
    'gel de dus cu salvie': ['gel de dus natural'],
    'sampon cu catina': ['sampon natural'],
    'sampon cu galbenele': ['sampon natural'],
    'sampon cu musetel': ['sampon natural'],
    'sampon cu salvie': ['sampon natural'],
    'sampon cu urzica': ['sampon cu urzica si brusture'],
    'balsam de buze cu musetel': ['balsam de buze natural'],
    'balsam de buze cu propolis': ['balsam de buze natural'],
    'ulei de corp': ['ulei de corp hidratant'],
    'ulei de vaselina': ['ulei de parafina', 'vaselina cosmetica'],
    'vaselina floral': ['vaselina cosmetica'],
    'vaselina rose': ['vaselina cosmetica'],
    'toner facial': ['apa micelara'],
    'lotiune demachianta': ['apa micelara'],
    'crema de corp cu niacinamida': ['crema de corp hidratanta'],
    'spray de corp hidratant': ['crema de corp hidratanta'],
    'dexpanthen plus': ['crema pentru calcaie'],
    'dexpanthen plus herbal': ['crema pentru calcaie'],
    'seleniu': [],
}


ALT_COUNT = Counter(a for v in RELATED.values() for a in v)


def strip(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn').lower().strip()


def norm_seed(s):
    return re.sub(r'\s+', ' ', strip(s).replace(' + ', ' ')).strip()


def load(prefix):
    d = {}
    for f in sorted(glob.glob(os.path.join(SP, prefix + '_part*.json'))):
        d.update(json.load(open(f, encoding='utf-8')))
    return d


def is_brand(kw):
    k = ' ' + strip(kw) + ' '
    return any(' ' + b + ' ' in k for b in BRANDS)


def is_info(kw):
    k = ' ' + strip(kw) + ' '
    return any(w in k for w in INFO)


def sig_words(seed):
    stop = {'cu', 'de', 'si', 'pentru', 'la', 'in', 'c', 'd3', 'b6', 'mg', 'ui', 'с', 'и', 'для', 'из', 'со', 'мг', 'ме', 'plus', 'herbal', 'forte'}
    return [w for w in re.split(r'[\s\-+%,]+', strip(seed)) if len(w) > 2 and w not in stop]


def analyse(seed, v):
    """Return dict with seed_vol, seed_kd, exact, mismatch, ideas(list), best."""
    r = dict(seed=seed, error=None, total=0, ideas=[], questions=[], seed_vol=-1, seed_kd='', mismatch=False, best=None, generic=[])
    if v is None:
        r['error'] = 'lipsă'
        return r
    if 'e' in v:
        r['error'] = v['e']
        return r
    r['total'] = v.get('t', 0)
    ideas = [(i[0], i[1], KD.get(i[2] if len(i) > 2 else '', '')) for i in v.get('i', [])]
    r['ideas'] = ideas
    r['questions'] = v.get('q', [])
    if ideas:
        sw = sig_words(seed)
        if sw and not any(all(w[:5] in strip(i[0]) for w in sw) for i in ideas):
            r['mismatch'] = True
            r['ideas'] = []
            r['questions'] = []
            r['total'] = 0
            return r
    for i in ideas:
        if strip(i[0]) == strip(seed) or strip(i[0]).replace(' ', '') == strip(seed).replace(' ', ''):
            r['seed_vol'] = max(r['seed_vol'], RANK.get(i[1], 0))
            r['seed_kd'] = r['seed_kd'] or i[2]
    generic = [i for i in ideas if not is_brand(i[0]) and not is_info(i[0]) and strip(i[0]) != strip(seed)]
    generic.sort(key=lambda i: -RANK.get(i[1], 0))
    r['generic'] = generic
    hi = [i for i in generic if RANK.get(i[1], 0) > max(r['seed_vol'], 0)]
    r['best'] = hi[0] if hi else None
    return r


def vol_label(rank):
    return {-1: 'nu apare', 0: '<100', 1: '100+', 2: '1.000+', 3: '10.000+', 4: '100.000+'}[rank]


def recommend(a, lang='ro'):
    if a['error'] or a['mismatch'] or (a['total'] == 0 and not a['ideas']):
        return ('Fără date (volum sub pragul Ahrefs)', 'Păstrează focus keyword-ul actual: e denumirea produsului, nu există o variantă mai căutată.')
    if a['seed_vol'] >= 1:
        msg = 'OK, are volum (%s)' % vol_label(a['seed_vol'])
        extra = ''
        if a['best']:
            extra = 'Adaugă ca secundar / în descriere: „%s” (%s).' % (a['best'][0], vol_label(RANK[a['best'][1]]))
        return (msg, extra or 'Păstrează.')
    if a['seed_vol'] == 0:
        if a['best']:
            return ('Există o variantă mai căutată', 'Consideră focus: „%s” (%s); actualul are <100.' % (a['best'][0], vol_label(RANK[a['best'][1]])))
        return ('Volum <100, long-tail', 'Păstrează; nicio variantă generică nu e mai căutată.')
    # seed not in list but ideas exist
    top = a['generic'][0] if a['generic'] else (a['ideas'][0] if a['ideas'] else None)
    if top:
        return ('Formularea exactă nu e căutată', 'Ahrefs nu are exact acest keyword; cea mai apropiată căutare: „%s” (%s).' % (top[0], vol_label(RANK.get(top[1], 0))))
    return ('Fără date', 'Păstrează.')


def fmt_ideas(ideas, n=6, with_kd=False):
    out = []
    for i in ideas[:n]:
        s = '%s (%s' % (i[0], vol_label(RANK.get(i[1], 0)))
        if with_kd and i[2]:
            s += ', KD ' + i[2]
        out.append(s + ')')
    return '; '.join(out)


HDR_FILL = PatternFill('solid', fgColor='567C55')
HDR_FONT = Font(bold=True, color='FFFFFF')
WRAP = Alignment(wrap_text=True, vertical='top')


def write_sheet(wb, title, header, rows, widths):
    ws = wb.create_sheet(title)
    ws.append(header)
    for c in ws[1]:
        c.fill = HDR_FILL; c.font = HDR_FONT; c.alignment = WRAP
    for r in rows:
        ws.append(r)
    for i, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(i)].width = w
    for row in ws.iter_rows(min_row=2):
        for c in row:
            c.alignment = WRAP
    ws.freeze_panes = 'A2'
    ws.auto_filter.ref = ws.dimensions
    return ws


def main():
    ro = load('ro'); ro.update(load('ro2'))
    romd = load('romd')
    rumd = load('rumd')
    print('loaded ro', len(ro), 'romd', len(romd), 'rumd', len(rumd))

    seo = openpyxl.load_workbook(SEO_XLSX, read_only=True)
    prod_ro = defaultdict(list); prod_ru = defaultdict(list)
    sec_ro = {}
    for r in list(seo['Produse RO'].iter_rows(values_only=True))[1:]:
        if r[8]:
            prod_ro[norm_seed(str(r[8]))].append((r[1], r[4]))
            sec_ro[norm_seed(str(r[8]))] = (str(r[8]), r[9])
    for r in list(seo['Produse RU'].iter_rows(values_only=True))[1:]:
        if r[8]:
            prod_ru[norm_seed(str(r[8]))].append((r[1], r[5]))
    ro_seeds = sorted(prod_ro.keys()); ru_seeds = sorted(prod_ru.keys())

    wb = openpyxl.Workbook(); wb.remove(wb.active)

    # ---- Sheet 1: RO focus keywords ----
    rows = []; stats = Counter()
    for s in ro_seeds:
        a = analyse(s, ro.get(s)); m = analyse(s, romd.get(s)) if romd else None
        verdict, action = recommend(a)
        # alternative phrasings that were queried separately
        alts = []
        for alt in RELATED.get(s, []):
            if alt not in ro:
                continue
            b = analyse(alt, ro.get(alt))
            if b['error'] or b['mismatch']:
                continue
            if b['seed_vol'] >= 1:
                alts.append((alt, b['seed_vol'], b['seed_kd']))
            elif b['generic'] and RANK.get(b['generic'][0][1], 0) >= 1:
                alts.append((b['generic'][0][0], RANK.get(b['generic'][0][1], 0), b['generic'][0][2]))
        alts.sort(key=lambda x: -x[1])
        alt_txt = '; '.join('%s (%s%s)' % (x[0], vol_label(x[1]), ', KD ' + x[2] if x[2] else '') for x in alts)
        cur = vol_label(a['seed_vol']) if not (a['error'] or a['mismatch']) else 'fără date'
        better = [x for x in alts if x[1] > max(a['seed_vol'], 0)]
        spec = [x for x in better if ALT_COUNT.get(x[0], 0) < 3]
        gen = [x for x in better if ALT_COUNT.get(x[0], 0) >= 3]
        if spec:
            verdict = 'Există o formulare mai căutată'
            action = 'Folosește „%s” (%s) în title/H1 și în prima propoziție; actualul: %s.' % (spec[0][0], vol_label(spec[0][1]), cur)
        if gen:
            action += ' Keyword secundar de categorie: „%s” (%s).' % (gen[0][0], vol_label(gen[0][1]))
        stats[verdict] += 1
        rows.append([
            sec_ro[s][0], len(prod_ro[s]), '; '.join(str(p[1]) for p in prod_ro[s][:3]) + (' …' if len(prod_ro[s]) > 3 else ''),
            vol_label(a['seed_vol']) if not (a['error'] or a['mismatch']) else ('eroare' if a['error'] else 'date nesigure'),
            a['seed_kd'], a['total'] if not a['mismatch'] else '',
            fmt_ideas(a['generic'], 6, True), '; '.join(a['questions'][:4]),
            alt_txt,
            verdict, action,
        ])
    write_sheet(wb, 'Focus RO', ['Focus keyword (din Excel SEO)', 'Nr produse', 'Produse (exemple)', 'Volum RO (Ahrefs)', 'KD', 'Nr idei RO',
                                 'Variante generice căutate (RO, fără branduri)', 'Întrebări căutate (RO)',
                                 'Formulări alternative verificate (volum RO)', 'Verdict', 'Acțiune recomandată'],
                rows, [34, 8, 40, 12, 8, 8, 60, 45, 50, 28, 60])

    # ---- Sheet 2: RU focus keywords (Moldova) ----
    rows = []
    if not rumd:
        ws = wb.create_sheet('Focus RU (Moldova)')
        ws.append(['Runda pentru keyword-urile RU (Google Moldova) nu a putut rula: după ~250 de interogări Ahrefs a cerut CAPTCHA interactiv.'])
        ws.append(['Se poate relua ulterior cu același script (lista RU e pregătită).'])
        ws.column_dimensions['A'].width = 120
        ru_seeds = []
    for s in ru_seeds:
        a = analyse(s, rumd.get(s))
        verdict, action = recommend(a, 'ru')
        raw_kw = None
        for r in list(seo['Produse RU'].iter_rows(values_only=True))[1:]:
            if r[8] and norm_seed(str(r[8])) == s:
                raw_kw = str(r[8]); break
        rows.append([raw_kw or s, len(prod_ru[s]), '; '.join(str(p[1]) for p in prod_ru[s][:3]) + (' …' if len(prod_ru[s]) > 3 else ''),
                     vol_label(a['seed_vol']) if not (a['error'] or a['mismatch']) else ('eroare' if a['error'] else 'date nesigure'),
                     a['total'] if not a['mismatch'] else '', fmt_ideas(a['generic'], 8), '; '.join(a['questions'][:4]), verdict, action])
    if rumd:
        write_sheet(wb, 'Focus RU (Moldova)', ['Focus keyword RU (din Excel SEO)', 'Nr produse', 'Produse (exemple)', 'Volum MD (Ahrefs)', 'Nr idei MD',
                                               'Variante căutate în Moldova (RU)', 'Întrebări (RU)', 'Verdict', 'Acțiune recomandată'],
                    rows, [36, 8, 40, 12, 8, 70, 45, 26, 60])

    # ---- Sheet 3: generic / category heads ----
    rows = []
    heads_ro = [k for k in ro if k not in prod_ro]
    for s in heads_ro:
        a = analyse(s, ro.get(s)); m = analyse(s, romd.get(s)) if romd else None
        rows.append([s, 'RO', vol_label(a['seed_vol']) if not (a['error'] or a['mismatch']) else '-', a['seed_kd'], a['total'],
                     fmt_ideas(a['generic'], 10, True), '; '.join(a['questions'][:5]),
                     (m['total'] if m and not m['error'] else '') if romd else 'n/a', (fmt_ideas(m['ideas'], 8) if m and not m['error'] else '') if romd else 'n/a'])
    heads_ru = [k for k in rumd if k not in prod_ru]
    for s in heads_ru:
        a = analyse(s, rumd.get(s))
        rows.append([s, 'RU/MD', vol_label(a['seed_vol']) if not (a['error'] or a['mismatch']) else '-', a['seed_kd'], a['total'],
                     fmt_ideas(a['generic'], 10, True), '; '.join(a['questions'][:5]), '', ''])
    write_sheet(wb, 'Categorii și idei extra', ['Seed', 'Limbă/țară', 'Volum seed', 'KD', 'Nr idei', 'Variante generice căutate', 'Întrebări',
                                                'MD: nr idei', 'MD: variante'],
                rows, [32, 10, 12, 8, 8, 80, 50, 8, 50])

    # ---- Sheet 4: raw ----
    rows = []
    for name, data, country in [('ro', ro, 'RO'), ('romd', romd, 'MD'), ('rumd', rumd, 'MD')]:
        for s, v in data.items():
            if 'e' in v:
                rows.append([s, country, '', 'eroare: ' + v['e'], '', '']); continue
            for i in v.get('i', []):
                rows.append([s, country, i[0], vol_label(RANK.get(i[1], 0)), KD.get(i[2] if len(i) > 2 else '', ''), ''])
            for q in v.get('q', []):
                rows.append([s, country, q, '', '', 'întrebare'])
    write_sheet(wb, 'Brut', ['Seed', 'Țară', 'Keyword', 'Volum', 'KD', 'Tip'], rows, [34, 8, 60, 12, 10, 10])

    # ---- Sheet 0: summary ----
    ws = wb.create_sheet('Cum citești', 0)
    lines = [
        'Herbal Therapy – verificare keyword-uri cu Ahrefs Free Keyword Generator (%s)' % datetime.date.today().isoformat(),
        '',
        'Sursă: https://ahrefs.com/keyword-generator/ (varianta gratuită). Pentru fiecare focus keyword din Herbal_SEO_Produse_RO_RU.xlsx s-a rulat o căutare:',
        '  • Google România (limba română) – volumele sunt relevante pentru formulări; Moldova are volume prea mici ca să apară.',
        '  • Google Moldova: volumele apar mereu „<100” (piață mică), deci formulările s-au verificat pe Google România (aceeași limbă).',
        '  • Runda pentru keyword-urile RU (Google Moldova) NU a rulat: după ~250 de interogări Ahrefs a cerut CAPTCHA interactiv. Se poate relua.',
        '',
        'Limitele tool-ului gratuit: max 20 idei per căutare, volumul e pe intervale (<100, 100+, 1.000+, 10.000+), KD doar pentru primele 10.',
        'Când o căutare nu returnează nimic, înseamnă că fraza are volum sub pragul Ahrefs – NU că nu e căutată deloc.',
        '',
        'Statistică verdicte pentru focus keyword RO (%d keyword-uri, %d produse):' % (len(ro_seeds), sum(len(v) for v in prod_ro.values())),
    ] + ['  • %s: %d' % (k, v) for k, v in stats.most_common()] + [
        '',
        'Concluzie practică: focus keyword-urile din Excel rămân valabile (sunt denumiri de produs). Unde apare „Consideră focus”/„Adaugă ca secundar”,',
        'folosește varianta indicată în H1/title sau ca keyword secundar în Rank Math și în prima propoziție a descrierii.',
        'Coloana „Întrebări” = idei de paragrafe FAQ în descrierea produsului (întrebările sunt exact ce caută oamenii pe Google).',
    ]
    for l in lines:
        ws.append([l])
    ws.column_dimensions['A'].width = 140
    ws['A1'].font = Font(bold=True, size=13)

    wb.save(OUT)
    print('saved', OUT)
    for k, v in stats.most_common():
        print(' ', k, v)


if __name__ == '__main__':
    main()
