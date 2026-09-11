<?php
/**
 * Teste pentru inc/whatsapp.php - functiile pure din spatele butonului
 * plutitor: curatarea numarului si construirea adresei wa.me.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class WhatsappTest extends TestCase
{
    /* -----------------------------------------------------------------------
     * ht_whatsapp_digits()
     * -------------------------------------------------------------------- */

    public function testPastreazaDoarCifreleDinNumarulAfisat()
    {
        $this->assertSame('37378884061', ht_whatsapp_digits('+373 78 88 40 61'));
    }

    public function testAcceptaLegaturaTel()
    {
        $this->assertSame('37378884061', ht_whatsapp_digits('tel:+37378884061'));
    }

    public function testScoatePrefixulInternational00()
    {
        $this->assertSame('37378884061', ht_whatsapp_digits('00373 (78) 88-40-61'));
    }

    public function testCompleteazaPrefixulDeTaraLaNumereleLocale()
    {
        $this->assertSame('37378884061', ht_whatsapp_digits('078884061'));
        $this->assertSame('37378884061', ht_whatsapp_digits('078 88 40 61'));
    }

    public function testNuAtingeNumereleCarePoartaDejaPrefixul()
    {
        $this->assertSame('37378884061', ht_whatsapp_digits('37378884061'));
    }

    public function testRespingeNumereleScurteSauGoale()
    {
        $this->assertSame('', ht_whatsapp_digits(''));
        $this->assertSame('', ht_whatsapp_digits('abc'));
        $this->assertSame('', ht_whatsapp_digits('12345'));
    }

    public function testRespingeNumerelePesteLimitaE164()
    {
        $this->assertSame('', ht_whatsapp_digits('1234567890123456'));
    }

    /* -----------------------------------------------------------------------
     * ht_whatsapp_url()
     * -------------------------------------------------------------------- */

    public function testConstruiesteAdresaFaraMesaj()
    {
        $this->assertSame('https://wa.me/37378884061', ht_whatsapp_url('+373 78 88 40 61'));
        $this->assertSame('https://wa.me/37378884061', ht_whatsapp_url('+373 78 88 40 61', '   '));
    }

    public function testCodificaMesajulInAdresa()
    {
        $this->assertSame(
            'https://wa.me/37378884061?text=Bun%C4%83%20ziua%21%20Am%20o%20%C3%AEntrebare.',
            ht_whatsapp_url('+37378884061', 'Bună ziua! Am o întrebare.')
        );
    }

    public function testAdresaEGoalaCandNumarulNuEValid()
    {
        $this->assertSame('', ht_whatsapp_url('', 'Salut'));
        $this->assertSame('', ht_whatsapp_url('123', 'Salut'));
    }
}
