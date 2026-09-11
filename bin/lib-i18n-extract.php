<?php
/**
 * Extragerea sirurilor traductibile din sursele temei.
 *
 * Folosita de bin/make-pot.php (sablonul .pot) si de
 * bin/generate-i18n-strings.php (inventarul pentru Polylang), ca amandoua sa
 * vada exact aceleasi siruri.
 *
 * Citeste fisierele cu token_get_all(), deci nu are nevoie nici de WordPress
 * incarcat, nici de WP-CLI.
 *
 * @package Herbal_Therapy
 */

/**
 * Functiile gettext si pozitia argumentelor lor.
 *
 * 'singular' / 'plural' / 'context' sunt indici de argument (de la 0). Lipsa
 * unei chei inseamna ca functia nu are argumentul respectiv.
 *
 * @return array
 */
function ht_i18n_functions()
{
    return array(
        '__'         => array('singular' => 0, 'domain' => 1),
        '_e'         => array('singular' => 0, 'domain' => 1),
        'esc_html__' => array('singular' => 0, 'domain' => 1),
        'esc_html_e' => array('singular' => 0, 'domain' => 1),
        'esc_attr__' => array('singular' => 0, 'domain' => 1),
        'esc_attr_e' => array('singular' => 0, 'domain' => 1),
        '_x'         => array('singular' => 0, 'context' => 1, 'domain' => 2),
        '_ex'        => array('singular' => 0, 'context' => 1, 'domain' => 2),
        'esc_html_x' => array('singular' => 0, 'context' => 1, 'domain' => 2),
        'esc_attr_x' => array('singular' => 0, 'context' => 1, 'domain' => 2),
        '_n'         => array('singular' => 0, 'plural' => 1, 'domain' => 3),
        '_n_noop'    => array('singular' => 0, 'plural' => 1, 'domain' => 2),
        '_nx'        => array('singular' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4),
        '_nx_noop'   => array('singular' => 0, 'plural' => 1, 'context' => 2, 'domain' => 3),
    );
}

/**
 * Directoarele care nu contin cod al temei.
 *
 * @return array
 */
function ht_i18n_skip_dirs()
{
    return array('node_modules', 'vendor', '.git', '.idea', 'languages', 'acf-json', 'bin');
}

/**
 * Toate fisierele .php ale temei, fara directoarele sarite.
 *
 * @param string $dir  Directorul de pornire.
 * @param array  $skip Nume de directoare de ignorat.
 *
 * @return array Cai absolute, sortate.
 */
function ht_i18n_php_files($dir, array $skip)
{
    $files = array();

    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static function ($file) use ($skip) {
                if ($file->isDir()) {
                    return !in_array($file->getFilename(), $skip, true);
                }

                return 'php' === strtolower($file->getExtension());
            }
        )
    );

    foreach ($it as $file) {
        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

/**
 * Textul unui literal PHP, daca tokenul chiar e un literal simplu.
 *
 * Sirurile cu ghilimele duble care contin variabile ajung ca mai multe tokenuri,
 * deci nu trec pe aici - si bine, fiindca nu se pot traduce oricum.
 *
 * @param array|string $token Tokenul.
 *
 * @return string|null Textul dezescapat, sau null daca nu e literal.
 */
function ht_i18n_literal($token)
{
    if (!is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
        return null;
    }

    $raw   = $token[1];
    $quote = $raw[0];
    $body  = substr($raw, 1, -1);

    if ("'" === $quote) {
        return str_replace(array('\\\\', "\\'"), array('\\', "'"), $body);
    }

    return stripcslashes($body);
}

/**
 * Argumentele literale ale unui apel, in ordine.
 *
 * Merge de la paranteza deschisa si strange, pentru fiecare argument de nivel
 * zero, textul lui daca e un singur literal; altfel pune null pe pozitia lui.
 * Concatenarile ('a' . 'b') si variabilele raman null: gettext nu le poate lua,
 * si vrem sa se vada asta, nu sa le ghicim.
 *
 * @param array $tokens Tokenurile fisierului.
 * @param int   $open   Indicele parantezei deschise.
 *
 * @return array [argumente, indicele parantezei inchise]
 */
function ht_i18n_call_args(array $tokens, $open)
{
    $args    = array();
    $depth   = 0;
    $current = array();
    $i       = $open;
    $count   = count($tokens);

    for (; $i < $count; $i++) {
        $token = $tokens[$i];
        $text  = is_array($token) ? $token[1] : $token;

        if (in_array($text, array('(', '[', '{'), true)) {
            $depth++;

            if (1 === $depth) {
                continue;
            }
        } elseif (in_array($text, array(')', ']', '}'), true)) {
            $depth--;

            if (0 === $depth) {
                $args[] = $current;
                break;
            }
        } elseif (',' === $text && 1 === $depth) {
            $args[] = $current;
            $current = array();
            continue;
        }

        if ($depth >= 1) {
            if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            $current[] = $token;
        }
    }

    $values = array();

    foreach ($args as $arg) {
        $values[] = (1 === count($arg)) ? ht_i18n_literal($arg[0]) : null;
    }

    return array($values, $i);
}

/**
 * Comentariul "translators:" care precede un apel, daca exista.
 *
 * @param array $tokens Tokenurile fisierului.
 * @param int   $index  Indicele tokenului cu numele functiei.
 *
 * @return string Nota curatata, sau sir gol.
 */
function ht_i18n_translators_comment(array $tokens, $index)
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!is_array($token)) {
            if (in_array($token, array('(', ',', '=', '.', '?', ':'), true)) {
                continue;
            }

            return '';
        }

        /* T_STRING acopera apelurile in care e imbracat gettext-ul, ca
         * esc_html(_n(...)) sau sprintf(__(...)); T_VARIABLE si T_RETURN acopera
         * atribuirile si return-urile ($x = sprintf(_n(...))); nota sta deasupra lor */
        if (in_array($token[0], array(T_WHITESPACE, T_OPEN_TAG, T_STRING, T_ECHO, T_PRINT, T_VARIABLE, T_RETURN), true)) {
            continue;
        }

        if (!in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
            return '';
        }

        $text = trim($token[1]);
        $text = preg_replace('#^/\*+|\*+/$|^//|^\##', '', $text);
        $text = trim(preg_replace('#^\s*\*\s?#m', '', $text));

        if (0 !== stripos($text, 'translators:')) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    return '';
}

/**
 * Sirurile traductibile dintr-un fisier.
 *
 * @param string $file     Calea fisierului.
 * @param string $rel      Calea relativa, pentru referinte.
 * @param string $domain   Domeniul temei.
 * @param array  $entries  Acumulatorul, dupa referinta.
 * @param array  $warnings Avertismentele, dupa referinta.
 *
 * @return void
 */
function ht_i18n_scan_file($file, $rel, $domain, array &$entries, array &$warnings)
{
    $functions = ht_i18n_functions();
    $tokens    = token_get_all(file_get_contents($file));
    $count     = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!is_array($token) || T_STRING !== $token[0] || !isset($functions[$token[1]])) {
            continue;
        }

        /* "->__()" sau "Foo::__()" nu sunt gettext */
        for ($p = $i - 1; $p >= 0 && is_array($tokens[$p]) && T_WHITESPACE === $tokens[$p][0]; $p--) {
            // doar sarim spatiile
        }

        if ($p >= 0 && is_array($tokens[$p])
            && in_array($tokens[$p][0], array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW), true)) {
            continue;
        }

        /* urmatorul token semnificativ trebuie sa fie paranteza deschisa */
        for ($n = $i + 1; $n < $count && is_array($tokens[$n]) && T_WHITESPACE === $tokens[$n][0]; $n++) {
            // doar sarim spatiile
        }

        if ($n >= $count || '(' !== $tokens[$n]) {
            continue;
        }

        $name = $token[1];
        $spec = $functions[$name];
        $line = $token[2];

        list($args, ) = ht_i18n_call_args($tokens, $n);

        $domain_arg = isset($spec['domain'], $args[$spec['domain']]) ? $args[$spec['domain']] : null;

        if ($domain_arg !== $domain) {
            if (null === $domain_arg) {
                $warnings[] = sprintf('%s:%d  %s() - domeniu lipsa sau necitibil.', $rel, $line, $name);
            }

            continue;
        }

        $singular = isset($args[$spec['singular']]) ? $args[$spec['singular']] : null;

        if (null === $singular || '' === $singular) {
            $warnings[] = sprintf('%s:%d  %s() - textul nu e un literal simplu, nu poate fi extras.', $rel, $line, $name);
            continue;
        }

        $plural  = isset($spec['plural'], $args[$spec['plural']]) ? $args[$spec['plural']] : null;
        $context = isset($spec['context'], $args[$spec['context']]) ? $args[$spec['context']] : null;

        $key = ($context !== null ? $context . "\4" : '') . $singular . ($plural !== null ? "\0" . $plural : '');

        if (!isset($entries[$key])) {
            $entries[$key] = array(
                'context'    => $context,
                'singular'   => $singular,
                'plural'     => $plural,
                'references' => array(),
                'comments'   => array(),
            );
        }

        $entries[$key]['references'][] = $rel . ':' . $line;

        $comment = ht_i18n_translators_comment($tokens, $i);

        if ('' !== $comment && !in_array($comment, $entries[$key]['comments'], true)) {
            $entries[$key]['comments'][] = $comment;
        }

        if (null !== $plural && '' === $comment && false !== strpos($singular, '%')) {
            $warnings[] = sprintf('%s:%d  %s() - are substituenti fara comentariu "translators:".', $rel, $line, $name);
        }
    }
}

/**
 * Scaneaza toata tema.
 *
 * @param string $theme_dir Radacina temei.
 * @param string $domain    Domeniul temei.
 * @param array  $warnings  Avertismentele, dupa referinta.
 *
 * @return array Intrarile, indexate dupa cheia context/singular/plural.
 */
function ht_i18n_scan_theme($theme_dir, $domain, array &$warnings)
{
    $entries = array();

    foreach (ht_i18n_php_files($theme_dir, ht_i18n_skip_dirs()) as $file) {
        $rel = str_replace('\\', '/', substr($file, strlen($theme_dir) + 1));

        ht_i18n_scan_file($file, $rel, $domain, $entries, $warnings);
    }

    return $entries;
}
