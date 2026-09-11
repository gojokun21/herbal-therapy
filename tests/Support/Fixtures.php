<?php
/**
 * Date de test pentru filtrele catalogului.
 *
 * Un catalog mic, dar cu toate cazurile care au dat batai de cap:
 * produse in mai multe categorii, produse variabile care acopera doua
 * intervale de pret, precomanda, produse noi si vechi, o subcategorie.
 *
 * @package Herbal_Therapy
 */

final class HT_Test_Fixtures
{
    const CAT_SIROPURI = 10;
    const CAT_UNGUENTE = 11;
    const CAT_VITAMINE = 12;
    const CAT_VITAMINE_COPII = 13; // subcategorie a CAT_VITAMINE
    const CAT_CEAIURI = 14;        // fara produse

    /** Data de referinta a testelor - "acum". */
    const NOW = '2026-08-30 12:00:00';

    /** Produsele mai noi de atat sunt "Nou" (30 de zile). */
    const FRESH = '2026-07-31 12:00:00';

    /**
     * Inregistreaza categoriile in HT_Test_Wp.
     */
    public static function terms()
    {
        HT_Test_Wp::term(self::CAT_SIROPURI, 'siropuri', 'Siropuri');
        HT_Test_Wp::term(self::CAT_UNGUENTE, 'unguente', 'Unguente');
        HT_Test_Wp::term(self::CAT_VITAMINE, 'vitamine-si-suplimente', 'Vitamine și Suplimente');
        HT_Test_Wp::term(self::CAT_VITAMINE_COPII, 'vitamine-copii', 'Vitamine copii', self::CAT_VITAMINE);
        HT_Test_Wp::term(self::CAT_CEAIURI, 'ceaiuri', 'Ceaiuri');
    }

    /**
     * Un rand de produs.
     *
     * @param int    $id    ID-ul.
     * @param float  $min   Pretul minim.
     * @param float  $max   Pretul maxim.
     * @param string $stock 'instock' | 'outofstock' | 'onbackorder'.
     * @param string $date  Data publicarii, GMT.
     * @param int[]  $cats  Categoriile.
     *
     * @return array
     */
    public static function row($id, $min, $max, $stock, $date, array $cats)
    {
        return array(
            'id'    => $id,
            'min'   => (float)$min,
            'max'   => (float)$max,
            'stock' => $stock,
            'date'  => $date,
            'cats'  => $cats,
        );
    }

    /**
     * Catalogul intreg (pagina de magazin).
     *
     * @return array Randuri indexate dupa ID.
     */
    public static function rows()
    {
        $new = '2026-08-20 10:00:00';
        $old = '2025-01-10 10:00:00';

        $rows = array(
            /* siropuri */
            self::row(1, 50, 50, 'instock', $new, array(self::CAT_SIROPURI)),
            self::row(2, 150, 150, 'instock', $old, array(self::CAT_SIROPURI)),
            self::row(3, 250, 250, 'outofstock', $old, array(self::CAT_SIROPURI)),
            /* unguente */
            self::row(4, 80, 80, 'instock', $new, array(self::CAT_UNGUENTE)),
            self::row(5, 199.99, 199.99, 'onbackorder', $old, array(self::CAT_UNGUENTE)),
            /* variabil: acopera si "Sub 200" si "200 - 500" */
            self::row(6, 180, 320, 'instock', $new, array(self::CAT_UNGUENTE)),
            /* in doua categorii */
            self::row(7, 120, 120, 'instock', $old, array(self::CAT_SIROPURI, self::CAT_UNGUENTE)),
            /* vitamine + subcategorie */
            self::row(8, 90, 90, 'instock', $new, array(self::CAT_VITAMINE)),
            self::row(9, 600, 600, 'instock', $old, array(self::CAT_VITAMINE)),
            self::row(10, 200, 200, 'outofstock', $new, array(self::CAT_VITAMINE_COPII, self::CAT_VITAMINE)),
            /* fara categorie */
            self::row(11, 30, 30, 'instock', $old, array()),
        );

        $indexed = array();

        foreach ($rows as $row) {
            $indexed[$row['id']] = $row;
        }

        return $indexed;
    }

    /**
     * Randurile care raman pe o pagina de categorie (termenul + urmasii).
     *
     * @param int[] $family ID-urile termenilor din familie.
     *
     * @return array
     */
    public static function rows_in(array $family)
    {
        return array_filter(self::rows(), function ($row) use ($family) {
            return (bool)array_intersect($row['cats'], $family);
        });
    }

    /**
     * Configuratia pe care o primeste ht_shop_facet_counts().
     *
     * @return array
     */
    public static function config()
    {
        return array(
            'buckets' => array(
                array('key' => '0-200', 'min' => 0, 'max' => 200),
                array('key' => '200-500', 'min' => 200, 'max' => 500),
                array('key' => '500-1000', 'min' => 500, 'max' => 1000),
                array('key' => '1000-2000', 'min' => 1000, 'max' => 2000),
            ),
            'stock'   => array(
                array('key' => 'instock'),
                array('key' => 'outofstock'),
                array('key' => 'new'),
            ),
            'fresh'   => self::FRESH,
        );
    }
}
