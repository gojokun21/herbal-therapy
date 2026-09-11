# -*- coding: utf-8 -*-
"""Caile si setarile comune ale scripturilor de import.

Secretele se citesc din mediu sau din bin/import/.env (vezi .env.example).
Nimic sensibil nu se scrie in cod.
"""
import os
from pathlib import Path

try:
    from dotenv import load_dotenv
except ImportError:
    load_dotenv = None

BASE = Path(__file__).resolve().parent
DATA = BASE / "data"
DATA.mkdir(exist_ok=True)

if load_dotenv:
    load_dotenv(BASE / ".env")

# --- surse -----------------------------------------------------------------
SHOPIFY_BASE = os.getenv("SHOPIFY_BASE", "https://herbal-therapy.md")
XLSX_PATH    = Path(os.getenv("XLSX_PATH", Path.home() / "Downloads" / "produsele tralala.xlsx"))

# --- fisiere intermediare --------------------------------------------------
SHOPIFY_JSON = DATA / "shopify_products.json"   # catalogul brut, descarcat o data
XLSX_JSON    = DATA / "xlsx_rows.json"          # lista oficiala, citita din Excel
MATCH_JSON   = DATA / "match_report.json"       # potrivirea celor doua
MANUAL_JSON  = DATA / "potriviri_manuale.json"  # potriviri decise de om
ACF_JSON     = DATA / "acf_fields.json"         # campurile ACF extrase cu AI
TABS_JSON    = DATA / "product_tabs.json"       # taburile de pe paginile de produs
RU_JSON      = DATA / "ru_translations.json"    # traducerile in rusa
SCURTE_JSON  = DATA / "descrieri_scurte.json"   # descrierile scurte RO+RU
IMPORT_LOG   = DATA / "import_log.json"         # ce a intrat in WooCommerce

# --- praguri de potrivire --------------------------------------------------
SCORE_SURE  = 74.0   # peste: se importa automat
SCORE_MAYBE = 58.0   # intre MAYBE si SURE: intra in lista de confirmat

# --- OpenAI ----------------------------------------------------------------
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
OPENAI_MODEL   = os.getenv("OPENAI_MODEL", "gpt-4o-mini")

# --- WooCommerce REST ------------------------------------------------------
WC_URL    = os.getenv("WC_URL", "http://herbaltherapy.local")
WC_KEY    = os.getenv("WC_KEY", "")
WC_SECRET = os.getenv("WC_SECRET", "")

# Ruta ht-import/v1 (campurile ACF) e o ruta WordPress obisnuita, deci nu accepta
# cheile WooCommerce. Are nevoie de o parola de aplicatie: Utilizatori > Profil.
WP_USER         = os.getenv("WP_USER", "")
WP_APP_PASSWORD = os.getenv("WP_APP_PASSWORD", "")

# ritmul cererilor catre site-ul sursa (are protectie anti-bot)
REQUEST_DELAY = float(os.getenv("REQUEST_DELAY", "1.5"))
USER_AGENT = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
              "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36")
