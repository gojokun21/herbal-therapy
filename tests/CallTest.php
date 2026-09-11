<?php
/**
 * Teste pentru inc/call.php - functiile pure din spatele butonului plutitor
 * de apel: adresa tel: si textul afisat.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class CallTest extends TestCase
{
    /* -----------------------------------------------------------------------
     * ht_call_href()
     * -------------------------------------------------------------------- */

    public function testConstruiesteAdresaTelInFormaE164()
    {
        $this->assertSame('tel:+37378884061', ht_call_href('+373 78 88 40 61'));
        $this->assertSame('tel:+37378884061', ht_call_href('tel:+37378884061'));
        $this->assertSame('tel:+37378884061', ht_call_href('00373 (78) 88-40-61'));
    }

    public function testCompleteazaPrefixulDeTaraLaNumereleLocale()
    {
        $this->assertSame('tel:+37378884061', ht_call_href('078 88 40 61'));
    }

    public function testAdresaEGoalaCandNumarulNuEValid()
    {
        $this->assertSame('', ht_call_href(''));
        $this->assertSame('', ht_call_href('abc'));
        $this->assertSame('', ht_call_href('12345'));
    }

    /* -----------------------------------------------------------------------
     * ht_call_display()
     * -------------------------------------------------------------------- */

    public function testAfiseazaNumarulAsaCumAFostScris()
    {
        $this->assertSame('+373 78 88 40 61', ht_call_display('+373 78 88 40 61'));
        $this->assertSame('+373 78 88 40 61', ht_call_display("  +373  78 88\t40 61 "));
    }

    public function testScoatePrefixulTelDinTextulAfisat()
    {
        $this->assertSame('+37378884061', ht_call_display('tel:+37378884061'));
    }

    public function testCadePeFormaInternationalaCandTextulEGol()
    {
        $this->assertSame('+37378884061', ht_call_display('', '37378884061'));
        $this->assertSame('', ht_call_display('', ''));
    }
}
