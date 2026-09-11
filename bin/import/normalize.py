# -*- coding: utf-8 -*-
"""Normalizarea denumirilor de produs, folosita la potrivire.

Denumirile oficiale din Excel ("Unguent cu Galbenele 40 ml") si titlurile de
marketing de pe site ("Unguent Regenerant - Galbenele, Ulei de Cocos, 40 ml")
descriu acelasi produs cu cuvinte diferite. Ce le leaga sigur e marimea:
gramajul, doza si numarul de comprimate. Astea se extrag separat si se trateaza
ca restrictii dure, nu ca simple puncte de scor.
"""
import re
import unicodedata

DIACRITICE = str.maketrans("șşțţăâî", "ssttaai")

# cuvinte care apar la toata lumea si nu ajuta la departajare
STOP = set("""
cu si de la pentru din x cpr comprimate comprimat capsule capsula masticabile
supliment alimentar herbal therapy cadou ml mg gr ui ug mcg efect locala ingrijire
""".split())


def norm(s):
    """Text comparabil: fara diacritice, fara punctuatie, cu unitatile separate."""
    s = unicodedata.normalize("NFKD", (s or "").lower()).translate(DIACRITICE)
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = re.sub(r"[^a-z0-9]+", " ", s)
    # 2000ui -> "2000 ui", ca numarul si unitatea sa fie jetoane distincte
    s = re.sub(r"(\d+)\s*(ml|mg|mcg|ug|ui|gr|g)(?![a-z0-9])",
               lambda m: f"{m.group(1)} {m.group(2)} ", s)
    s = re.sub(r"\bn\s*(\d+)\b", lambda m: f"n {m.group(1)} ", s)
    s = re.sub(r"\bx\s*(\d+)\b", lambda m: f"x {m.group(1)} ", s)
    return re.sub(r"\s+", " ", s).strip()


def tokens(s):
    """Cuvintele cu continut, fara cifre si fara jetoanele comune."""
    return {t for t in norm(s).split()
            if t not in STOP and not t.isdigit() and len(t) > 2}


def volume(s):
    """Gramajul in ml/g: 20 ml, 50 g. Separa variantele aceluiasi unguent."""
    return {int(m) for m in re.findall(r"(\d+) (?:ml|gr|g)\b", norm(s))}


def dose(s):
    """Doza activa: 100 mg, 2000 UI. Separa Vitamina C 100 de 180 de 500."""
    return {int(m) for m in re.findall(r"(\d+) (?:mg|ui|ug|mcg)\b", norm(s))}


def count(s):
    """Numarul de comprimate: N30, x30, "60 comprimate"."""
    n = norm(s)
    out  = {int(m) for m in re.findall(r"\bn (\d+)\b", n)}
    out |= {int(m) for m in re.findall(r"\bx (\d+)\b", n)}
    out |= {int(m) for m in re.findall(r"(\d+) (?:comprimate|comprimat|capsule|capsula)\b", n)}
    return out


def size_conflict(a, b):
    """True daca ambele declara o marime si marimile difera -> produse diferite."""
    for extract in (volume, dose, count):
        sa, sb = extract(a), extract(b)
        if sa and sb and not (sa & sb):
            return True
    return False


def size_bonus(a, b):
    """Puncte in plus cand marimile declarate chiar coincid."""
    bonus = 0
    for extract, pts in ((volume, 6), (count, 6), (dose, 4)):
        if extract(a) & extract(b):
            bonus += pts
    return bonus
