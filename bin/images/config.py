# -*- coding: utf-8 -*-
"""Caile si setarile scripturilor de imagini de prezentare.

Secretele vin din mediu sau din bin/images/.env; daca lipseste, se cade pe
bin/import/.env (aceeasi cheie OpenAI). Nimic sensibil nu se scrie in cod.
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
    load_dotenv(BASE.parent / "import" / ".env")

# --- fisiere intermediare --------------------------------------------------
PRODUCTS_JSON   = DATA / "products.json"         # produsele fara imagine Figma (export_products.php)
COPY_JSON       = DATA / "copy.json"             # textele RO/RU + scena + culori (texts.py)
BACKGROUNDS_DIR = DATA / "backgrounds"           # fundalurile generate, cate un PNG pe produs (generate.py)
MANIFEST_JSON   = DATA / "figma_manifest.json"   # ce citeste pluginul Figma (manifest.py)
NODES_JSON      = DATA / "figma_nodes.json"      # slug -> id cadru RO/RU, scris de plugin
BACKGROUNDS_DIR.mkdir(exist_ok=True)

STYLE_REF = BASE / "reference" / "style-ref.png"  # un fundal existent, ca referinta de stil

# --- OpenAI ----------------------------------------------------------------
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
COPY_MODEL     = os.getenv("HT_COPY_MODEL", "gpt-4o")
IMAGE_MODEL    = os.getenv("HT_IMAGE_MODEL", "gpt-image-2")
IMAGE_QUALITY  = os.getenv("HT_IMAGE_QUALITY", "medium")   # low | medium | high
IMAGE_SIZE     = "1024x1024"

# --- Figma (doar pentru upload_to_wp.php, exportul cadrelor) ---------------
FIGMA_TOKEN    = os.getenv("FIGMA_TOKEN", "")
FIGMA_FILE_KEY = os.getenv("FIGMA_FILE_KEY", "kcN6mD2YKtUttVTf3f26i2")


def need_openai():
    if not OPENAI_API_KEY:
        raise SystemExit("Lipseste OPENAI_API_KEY: pune-l in bin/images/.env sau bin/import/.env")
