<?php
/**
 * Teste pentru inc/shipping-progress.php - calculul barei de livrare gratuita.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class ShippingProgressTest extends TestCase
{
    public function testCatMaiLipsesteSiProcentul()
    {
        $data = ht_shipping_progress_data(350, 100);

        $this->assertSame(250.0, $data['remaining']);
        $this->assertSame(28, $data['percent']);
        $this->assertFalse($data['reached']);
    }

    public function testPragulAtinsUmpleBara()
    {
        $data = ht_shipping_progress_data(350, 350);

        $this->assertSame(0.0, $data['remaining']);
        $this->assertSame(100, $data['percent']);
        $this->assertTrue($data['reached']);

        $this->assertTrue(ht_shipping_progress_data(350, 603.2)['reached']);
    }

    public function testCosulGolPorneșteDeLaZero()
    {
        $data = ht_shipping_progress_data(350, 0);

        $this->assertSame(350.0, $data['remaining']);
        $this->assertSame(0, $data['percent']);
    }

    public function testSumaNegativaDupaReduceriSeTrateazaCaZero()
    {
        $data = ht_shipping_progress_data(350, -20);

        $this->assertSame(350.0, $data['remaining']);
        $this->assertSame(0, $data['percent']);
    }

    public function testProcentulNuTreceDe100SiNuSeRotunjesteInSus()
    {
        /* 349,99 din 350 e 99,99% - nu 100, ca sa nu para atins */
        $this->assertSame(99, ht_shipping_progress_data(350, 349.99)['percent']);
    }

    public function testPragZeroInseamnaAtins()
    {
        $this->assertTrue(ht_shipping_progress_data(0, 0)['reached']);
    }
}
