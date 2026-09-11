# -*- coding: utf-8 -*-
"""Clientul REST catre WooCommerce si catre rutele de import ale temei.

Autentificarea se face cu o parola de aplicatie WordPress, pentru toate rutele.

Cheile ck_/cs_ din WooCommerce ar fi fost varianta evidenta, dar nu merg peste
http simplu: acolo WooCommerce cere semnaturi OAuth 1.0a si respinge atat
antetul Basic, cat si cheile trimise in adresa. Parola de aplicatie nu are
problema asta, iar rutele wc/v3 o accepta la fel de bine - permisiunile lor
verifica drepturile utilizatorului autentificat, iar contul de import e
administrator. Bonus: aceeasi credentiala merge si pe rutele ht-import/v1 ale
temei, care sunt rute WordPress obisnuite si nu ar fi acceptat niciodata cheile
WooCommerce.

Cheile raman citite din configuratie ca rezerva, pentru cand magazinul ajunge pe
https si se prefera autentificarea proprie WooCommerce.
"""
import time

import requests
from requests.auth import HTTPBasicAuth

import config


class WooError(RuntimeError):
    pass


class Woo:
    def __init__(self):
        self.baza = config.WC_URL.rstrip("/")
        self.sesiune = requests.Session()

        if config.WP_APP_PASSWORD:
            self.auth = HTTPBasicAuth(config.WP_USER, config.WP_APP_PASSWORD)
            self.fel = "parola de aplicatie"
        elif config.WC_KEY and config.WC_SECRET:
            self.auth = HTTPBasicAuth(config.WC_KEY, config.WC_SECRET)
            self.fel = "chei WooCommerce"
        else:
            raise WooError("Lipsesc datele de autentificare: completeaza "
                           "WP_USER si WP_APP_PASSWORD in bin/import/.env")

    # -- transport ----------------------------------------------------------

    def _cere(self, metoda, cale, **kwargs):
        url = self.baza + cale

        for incercare in range(1, 5):
            raspuns = self.sesiune.request(metoda, url, auth=self.auth, timeout=180, **kwargs)

            if raspuns.status_code < 300:
                return raspuns.json() if raspuns.content else None

            # descarcarea imaginilor poate depasi limita de rata a serverului
            if raspuns.status_code in (429, 500, 502, 503, 504):
                pauza = min(2 ** incercare, 20)
                print("    " + str(raspuns.status_code) + ", reiau peste " + str(pauza) + "s")
                time.sleep(pauza)
                continue

            raise WooError(metoda + " " + cale + " -> " + str(raspuns.status_code) +
                           ": " + raspuns.text[:400])

        raise WooError("prea multe reincercari pentru " + cale)

    def get(self, cale, **params):
        return self._cere("GET", cale, params=params)

    def post(self, cale, date):
        return self._cere("POST", cale, json=date)

    def put(self, cale, date):
        return self._cere("PUT", cale, json=date)

    # -- verificari ---------------------------------------------------------

    def verifica_conexiunea(self):
        """Confirma ca autentificarea merge si pe WooCommerce, si pe rutele temei."""
        cine = self._cere("GET", "/wp-json/wp/v2/users/me")
        self.get("/wp-json/wc/v3/products", per_page=1, status="any")

        radacina = self.sesiune.get(self.baza + "/wp-json/", timeout=30).json()

        if "ht-import/v1" not in radacina.get("namespaces", []):
            raise WooError("Ruta ht-import/v1 lipseste. Verifica daca functions.php "
                           "mai incarca inc/import-endpoint.php.")

        return cine.get("name", config.WP_USER)

    # -- citire -------------------------------------------------------------

    def _toate_paginile(self, cale, **params):
        rezultate, pagina = [], 1
        while True:
            lot = self.get(cale, per_page=100, page=pagina, **params)
            if not lot:
                break
            rezultate.extend(lot)
            if len(lot) < 100:
                break
            pagina += 1
        return rezultate

    def toate_produsele(self, lang=None):
        """Produsele din magazin, optional doar dintr-o limba.

        Filtrul conteaza: traducerile impart acelasi SKU, deci fara el un index
        dupa SKU ar retine oricare din cele doua, dupa ordinea paginarii.
        """
        params = {"status": "any", "orderby": "id", "order": "asc"}
        if lang:
            params["lang"] = lang
        return self._toate_paginile("/wp-json/wc/v3/products", **params)

    def toate_categoriile(self, lang=None):
        params = {"lang": lang} if lang else {}
        return self._toate_paginile("/wp-json/wc/v3/products/categories", **params)

    # -- scriere ------------------------------------------------------------

    def creeaza_categorie(self, nume, lang=None):
        """Polylang cere limba explicit la creare: fara ea raspunde
        rest_invalid_language_code si termenul nu se creeaza."""
        date = {"name": nume}
        if lang:
            date["lang"] = lang
        return self.post("/wp-json/wc/v3/products/categories", date)

    def creeaza_produs(self, date):
        return self.post("/wp-json/wc/v3/products", date)

    def actualizeaza_produs(self, id_produs, date):
        """Modifica doar campurile trimise; restul produsului ramane neatins."""
        return self.put("/wp-json/wc/v3/products/" + str(id_produs), date)

    def scrie_acf(self, id_produs, campuri):
        """Trimite campurile catre ruta temei, care le da mai departe la update_field()."""
        return self.post("/wp-json/ht-import/v1/acf/" + str(id_produs), campuri)

    def creeaza_pereche(self, id_produs, date):
        """Creeaza sau actualizeaza traducerea produsului.

        Ruta temei face toata munca delicata: pune limba si legatura Polylang
        inainte de SKU (altfel WooCommerce refuza codul duplicat) si copiaza
        pretul, stocul si imaginile de pe produsul sursa.
        """
        return self.post("/wp-json/ht-import/v1/twin/" + str(id_produs), date)
