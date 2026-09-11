<?php
/**
 * Genereaza languages/herbal-therapy.pot din sursele temei.
 *
 * Tine locul lui `wp i18n make-pot`, care aici nu e disponibil (site-ul ruleaza
 * in Local, fara WP-CLI - vezi bin/seed.php pentru acelasi motiv). Extragerea
 * propriu-zisa sta in bin/lib-i18n-extract.php.
 *
 *   php bin/make-pot.php
 *
 * Fisierul rezultat se deschide in Poedit sau in Loco Translate. Traducerile se
 * salveaza tot in /languages, dar cu numele <locale>.po / <locale>.mo (ru_RU.mo,
 * en_US.mo) - pentru o tema WordPress nu pune prefixul domeniului in numele
 * fisierului; vezi languages/README.md.
 *
 * @package Herbal_Therapy
 */

if (PHP_SAPI !== 'cli') {
    exit("Se ruleaza doar din linia de comanda.\n");
}

require_once __DIR__ . '/lib-i18n-extract.php';

$ht_theme_dir = dirname(__DIR__);
$ht_domain    = 'herbal-therapy';
$ht_pot_path  = $ht_theme_dir . '/languages/' . $ht_domain . '.pot';

/**
 * Un sir, in forma din fisierele PO.
 *
 * Sirurile pe mai multe randuri se scriu ca msgid "" urmat de cate un rand
 * pentru fiecare linie, cum cere formatul.
 *
 * @param string $prefix Cuvantul cheie (msgid, msgstr...).
 * @param string $value  Textul.
 *
 * @return string
 */
function ht_pot_line($prefix, $value)
{
    $escaped = str_replace(
        array('\\', '"', "\t", "\r"),
        array('\\\\', '\"', '\t', ''),
        $value
    );

    if (false === strpos($escaped, "\n")) {
        return $prefix . ' "' . $escaped . '"' . "\n";
    }

    $parts = explode("\n", $escaped);
    $last  = count($parts) - 1;
    $out   = $prefix . ' ""' . "\n";

    foreach ($parts as $index => $part) {
        if ($index === $last && '' === $part) {
            continue;
        }

        $out .= '"' . $part . ($index === $last ? '' : '\n') . '"' . "\n";
    }

    return $out;
}

/**
 * Antetul temei din style.css, ca intrari traductibile.
 *
 * @param string $style_path Calea catre style.css.
 * @param array  $entries    Acumulatorul, dupa referinta.
 *
 * @return void
 */
function ht_pot_theme_header($style_path, array &$entries)
{
    if (!is_readable($style_path)) {
        return;
    }

    $head    = substr(file_get_contents($style_path), 0, 8192);
    $headers = array(
        'Theme Name'  => 'Numele temei.',
        'Theme URI'   => 'Adresa temei.',
        'Description' => 'Descrierea temei.',
        'Author'      => 'Autorul temei.',
        'Author URI'  => 'Adresa autorului.',
        'Tags'        => 'Etichetele temei, despartite prin virgula.',
    );

    foreach ($headers as $header => $note) {
        if (!preg_match('/^[ \t\/*#@]*' . preg_quote($header, '/') . ':(.*)$/mi', $head, $m)) {
            continue;
        }

        $value = trim($m[1]);

        if ('' === $value) {
            continue;
        }

        $entries[$value] = array(
            'context'    => null,
            'singular'   => $value,
            'plural'     => null,
            'references' => array('style.css'),
            'comments'   => array('translators: ' . $note),
        );
    }
}

/* ---------------------------------------------------------------------------
 * Rularea
 * ------------------------------------------------------------------------ */

$ht_warnings = array();
$ht_entries  = array();

ht_pot_theme_header($ht_theme_dir . '/style.css', $ht_entries);

$ht_entries += ht_i18n_scan_theme($ht_theme_dir, $ht_domain, $ht_warnings);

/* ordine stabila: dupa prima referinta, ca fisierul sa nu se rescrie degeaba */
uasort($ht_entries, static function ($a, $b) {
    return strcmp($a['references'][0], $b['references'][0])
        ?: strcmp($a['singular'], $b['singular']);
});

$ht_now = gmdate('Y-m-d H:iO');
$ht_out = <<<POT
# Copyright (C) Wavenity
# This file is distributed under the GNU General Public License v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Herbal Therapy 1.0.0\\n"
"Report-Msgid-Bugs-To: https://www.wavenity.com\\n"
"POT-Creation-Date: {$ht_now}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: \\n"
"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"
"X-Generator: bin/make-pot.php\\n"
"X-Domain: {$ht_domain}\\n"

POT;

foreach ($ht_entries as $ht_entry) {
    $ht_out .= "\n";

    foreach ($ht_entry['comments'] as $ht_comment) {
        $ht_out .= '#. ' . $ht_comment . "\n";
    }

    foreach (array_unique($ht_entry['references']) as $ht_ref) {
        $ht_out .= '#: ' . $ht_ref . "\n";
    }

    if (false !== strpos($ht_entry['singular'], '%')) {
        $ht_out .= "#, php-format\n";
    }

    if (null !== $ht_entry['context']) {
        $ht_out .= ht_pot_line('msgctxt', $ht_entry['context']);
    }

    $ht_out .= ht_pot_line('msgid', $ht_entry['singular']);

    if (null !== $ht_entry['plural']) {
        $ht_out .= ht_pot_line('msgid_plural', $ht_entry['plural']);

        /* sablonul are doua forme; numarul real de forme il pune Poedit / Loco
         * cand se creeaza fisierul .po al fiecarei limbi, din Plural-Forms */
        $ht_out .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n";
    } else {
        $ht_out .= "msgstr \"\"\n";
    }
}

if (!is_dir(dirname($ht_pot_path))) {
    mkdir(dirname($ht_pot_path), 0755, true);
}

file_put_contents($ht_pot_path, $ht_out);

printf("Scris %s\n", str_replace('\\', '/', substr($ht_pot_path, strlen($ht_theme_dir) + 1)));
printf("%d siruri unice.\n", count($ht_entries));

if ($ht_warnings) {
    printf("\n%d de verificat:\n", count($ht_warnings));

    foreach (array_unique($ht_warnings) as $ht_warning) {
        printf("  %s\n", $ht_warning);
    }
}
