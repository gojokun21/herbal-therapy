<?php
/**
 * Teste pentru inc/contact.php - functiile pure din spatele cartonasului de
 * contact: impartirea pe randuri a campurilor textarea.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    /* -----------------------------------------------------------------------
     * ht_contact_split_lines()
     * -------------------------------------------------------------------- */

    public function testImparteLaOriceFelDeSfarsitDeRand()
    {
        $this->assertSame(
            array('Luni - Vineri: 08:00 - 17:00', 'Sâmbătă - Duminică: Închis'),
            ht_contact_split_lines("Luni - Vineri: 08:00 - 17:00\r\nSâmbătă - Duminică: Închis")
        );
        $this->assertSame(array('a', 'b'), ht_contact_split_lines("a\nb"));
        $this->assertSame(array('a', 'b'), ht_contact_split_lines("a\rb"));
    }

    public function testSareRandurileGoaleSiSpatiile()
    {
        $this->assertSame(array('a', 'b'), ht_contact_split_lines("  a  \n\n\n b \n"));
        $this->assertSame(array(), ht_contact_split_lines(''));
        $this->assertSame(array(), ht_contact_split_lines(null));
    }

    /**
     * Litera chirilica "х" este D1 85 in UTF-8, iar 0x85 (NEL) e un sfarsit de
     * rand pentru \R cand expresia lucreaza pe octeti. "выходной" ajungea
     * "одной" pe pagina de contact in rusa.
     */
    public function testNuRupeCuvinteleChiriliceCuLiteraH()
    {
        $this->assertSame(
            array('Понедельник – Пятница: 08:00 – 17:00', 'Суббота – Воскресенье: выходной'),
            ht_contact_split_lines("Понедельник – Пятница: 08:00 – 17:00\nСуббота – Воскресенье: выходной")
        );
        $this->assertSame(array('хорошо'), ht_contact_split_lines('хорошо'));
    }
}
