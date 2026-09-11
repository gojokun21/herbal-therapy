<?php
/**
 * Calculul fatetelor (contoarele din bara de filtre).
 *
 * Functii pure: primesc randurile de produse si filtrele active si intorc
 * numerele. Nu ating WordPress, WooCommerce sau baza de date, ca sa poata fi
 * testate izolat (tests/ShopFacetsTest.php). Cine le hraneste cu date este
 * inc/shop-filters.php.
 *
 * Un rand de produs este un array cu cheile:
 *   id     - ID-ul produsului
 *   min    - pretul minim (float)
 *   max    - pretul maxim (float)
 *   stock  - 'instock', 'outofstock' sau 'onbackorder'
 *   date   - data publicarii, GMT, 'Y-m-d H:i:s'
 *   cats   - ID-urile categoriilor de produs (int[])
 *
 * Selectia (filtrele active) este un array cu cheile:
 *   cat        - ID-urile categoriilor bifate (int[])
 *   price      - cheile intervalelor predefinite bifate (string[])
 *   min_price  - capatul liber de jos (float|null)
 *   max_price  - capatul liber de sus (float|null)
 *   stock      - cheile de disponibilitate bifate (string[])
 *
 * Regula de numarare este cea clasica a filtrelor cu fatete: numarul de langa
 * o optiune spune cate produse ar ramane daca ai bifa-o, tinand cont de
 * celelalte grupuri, dar nu si de grupul din care face parte (intr-un grup
 * optiunile se aduna, nu se intersecteaza).
 *
 * @package Herbal_Therapy
 */

/**
 * Selectia goala, cu toate cheile la locul lor.
 *
 * @param array $selection Selectia partiala.
 *
 * @return array
 */
function ht_shop_facet_selection(array $selection = array())
{
    return array_merge(array(
        'cat'       => array(),
        'price'     => array(),
        'min_price' => null,
        'max_price' => null,
        'stock'     => array(),
    ), $selection);
}

/**
 * Produsul are macar un pret in interval? (semantica WooCommerce: suprapunere)
 *
 * @param array      $row Randul de produs.
 * @param float      $min Capatul de jos.
 * @param float|null $max Capatul de sus; null inseamna fara plafon.
 *
 * @return bool
 */
function ht_shop_row_in_range(array $row, $min, $max)
{
    $top = (null === $max) ? PHP_FLOAT_MAX : (float)$max;

    return (float)$row['max'] >= (float)$min && (float)$row['min'] <= $top;
}

/**
 * Produsul trece de grupul de pret (intervale bifate + interval liber)?
 *
 * @param array $row       Randul de produs.
 * @param array $selection Selectia (vezi antetul fisierului).
 * @param array $buckets   Intervalele predefinite (ht_shop_price_buckets()).
 *
 * @return bool
 */
function ht_shop_row_matches_price(array $row, array $selection, array $buckets)
{
    $selection = ht_shop_facet_selection($selection);

    if ($selection['price']) {
        $hit = false;

        foreach ($buckets as $bucket) {
            if (in_array($bucket['key'], $selection['price'], true)
                && ht_shop_row_in_range($row, $bucket['min'], $bucket['max'])) {
                $hit = true;
                break;
            }
        }

        if (!$hit) {
            return false;
        }
    }

    if (null !== $selection['min_price'] || null !== $selection['max_price']) {
        $min = (null === $selection['min_price']) ? 0 : (float)$selection['min_price'];

        if (!ht_shop_row_in_range($row, $min, $selection['max_price'])) {
            return false;
        }
    }

    return true;
}

/**
 * Produsul e considerat noutate?
 *
 * @param array  $row   Randul de produs.
 * @param string $fresh Data GMT de la care incoace un produs e nou.
 *
 * @return bool
 */
function ht_shop_row_is_new(array $row, $fresh)
{
    return (string)$row['date'] >= (string)$fresh;
}

/**
 * Produsul trece de grupul de disponibilitate?
 *
 * Aceeasi impartire ca in interogare: 'instock' e doar in stoc, 'outofstock'
 * cuprinde si precomanda, 'new' e dupa data publicarii.
 *
 * @param array  $row      Randul de produs.
 * @param array  $selected Cheile bifate.
 * @param string $fresh    Data GMT de la care incoace un produs e nou.
 *
 * @return bool
 */
function ht_shop_row_matches_stock(array $row, array $selected, $fresh)
{
    if (!$selected) {
        return true;
    }

    if (in_array('instock', $selected, true) && 'instock' === $row['stock']) {
        return true;
    }

    if (in_array('outofstock', $selected, true) && 'instock' !== $row['stock']) {
        return true;
    }

    if (in_array('new', $selected, true) && ht_shop_row_is_new($row, $fresh)) {
        return true;
    }

    return false;
}

/**
 * Produsul e in macar una dintre categoriile bifate?
 *
 * @param array $row      Randul de produs.
 * @param int[] $selected ID-urile bifate.
 *
 * @return bool
 */
function ht_shop_row_matches_cats(array $row, array $selected)
{
    if (!$selected) {
        return true;
    }

    foreach ($row['cats'] as $term_id) {
        if (in_array((int)$term_id, $selected, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Contoarele pentru toate grupurile.
 *
 * @param array $rows      Randurile de produse din listarea curenta (fara filtre).
 * @param array $selection Filtrele active.
 * @param array $config {
 *     @type array  $buckets Intervalele predefinite.
 *     @type array  $stock   Optiunile de disponibilitate.
 *     @type string $fresh   Data GMT de la care incoace un produs e nou.
 * }
 *
 * @return array {
 *     @type array $cat   Contor per ID de categorie (doar categoriile intalnite).
 *     @type array $price Contor per cheie de interval.
 *     @type array $stock Contor per cheie de disponibilitate.
 *     @type float $max   Cel mai mare pret din listare, indiferent de filtre.
 *     @type int   $total Cate produse raman cu toate filtrele aplicate.
 * }
 */
function ht_shop_facet_counts(array $rows, array $selection, array $config)
{
    $selection = ht_shop_facet_selection($selection);
    $selection['cat'] = array_map('intval', $selection['cat']);

    $buckets = isset($config['buckets']) ? $config['buckets'] : array();
    $stock_options = isset($config['stock']) ? $config['stock'] : array();
    $fresh = isset($config['fresh']) ? $config['fresh'] : '';

    $facets = array('cat' => array(), 'price' => array(), 'stock' => array(), 'max' => 0.0, 'total' => 0);

    foreach ($buckets as $bucket) {
        $facets['price'][$bucket['key']] = 0;
    }

    foreach ($stock_options as $option) {
        $facets['stock'][$option['key']] = 0;
    }

    foreach ($rows as $row) {
        if ((float)$row['max'] > $facets['max']) {
            $facets['max'] = (float)$row['max'];
        }

        $cat_ok = ht_shop_row_matches_cats($row, $selection['cat']);
        $price_ok = ht_shop_row_matches_price($row, $selection, $buckets);
        $stock_ok = ht_shop_row_matches_stock($row, $selection['stock'], $fresh);

        if ($cat_ok && $price_ok && $stock_ok) {
            $facets['total']++;
        }

        /* categorii: celelalte doua grupuri conteaza, grupul propriu nu */
        if ($price_ok && $stock_ok) {
            foreach ($row['cats'] as $term_id) {
                $term_id = (int)$term_id;
                $facets['cat'][$term_id] = isset($facets['cat'][$term_id]) ? $facets['cat'][$term_id] + 1 : 1;
            }
        }

        /* pret: fara grupul de pret (nici intervalele bifate, nici cel liber) */
        if ($cat_ok && $stock_ok) {
            foreach ($buckets as $bucket) {
                if (ht_shop_row_in_range($row, $bucket['min'], $bucket['max'])) {
                    $facets['price'][$bucket['key']]++;
                }
            }
        }

        /* disponibilitate: fara grupul propriu */
        if ($cat_ok && $price_ok) {
            foreach ($stock_options as $option) {
                if (ht_shop_row_matches_stock($row, array($option['key']), $fresh)) {
                    $facets['stock'][$option['key']]++;
                }
            }
        }
    }

    return $facets;
}
