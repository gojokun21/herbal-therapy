<?php
/**
 * Teste pentru inc/checkout.php - telefonul obligatoriu la finalizare.
 *
 * @package Herbal_Therapy
 */

use PHPUnit\Framework\TestCase;

final class CheckoutTest extends TestCase
{
    /**
     * Campuri de finalizare asa cum le da WooCommerce cu telefonul optional.
     *
     * @return array
     */
    private function fields()
    {
        return array(
            'billing' => array(
                'billing_first_name' => array('required' => true, 'class' => array('form-row-first')),
                'billing_phone'      => array('required' => false, 'type' => 'tel', 'class' => array('form-row-wide')),
                'billing_email'      => array('required' => true, 'class' => array('form-row-wide')),
            ),
            'shipping' => array(
                'shipping_phone' => array('required' => false, 'type' => 'tel', 'class' => array('form-row-wide')),
            ),
        );
    }

    public function testSetareaWooCommerceEsteFixataPeObligatoriu()
    {
        $this->assertSame('required', apply_filters('pre_option_woocommerce_checkout_phone_field', false));
        $this->assertSame('required', apply_filters('pre_option_woocommerce_checkout_phone_field', 'optional'));
    }

    public function testTelefonulDeFacturareDevineObligatoriu()
    {
        $fields = apply_filters('woocommerce_checkout_fields', $this->fields());

        $this->assertTrue($fields['billing']['billing_phone']['required']);
        /* telefonul de livrare ramane cum l-a lasat WooCommerce */
        $this->assertFalse($fields['shipping']['shipping_phone']['required']);
        /* restul campului ramane neatins */
        $this->assertSame('tel', $fields['billing']['billing_phone']['type']);
    }

    /* -----------------------------------------------------------------------
     * ht_checkout_error_message()
     * -------------------------------------------------------------------- */

    private function prefixes()
    {
        return array('billing' => 'Facturare ', 'shipping' => 'Livrare ');
    }

    public function testCampObligatoriuFaraPrefixulDeGrup()
    {
        $message = ht_checkout_error_message(
            'billing_phone_required',
            '<strong>Facturare Telefon</strong> este un câmp obligatoriu.',
            array('label' => 'Telefon', 'group' => 'billing'),
            $this->prefixes()
        );

        $this->assertSame('Te rugăm să completezi câmpul <strong>Telefon</strong>.', $message);
    }

    public function testCampulDeLivrarePrimesteGrupulDupaNume()
    {
        $message = ht_checkout_error_message(
            'shipping_city_required',
            '<strong>Livrare Oraș</strong> este un câmp obligatoriu.',
            array('label' => 'Oraș', 'group' => 'shipping'),
            $this->prefixes()
        );

        $this->assertSame('Te rugăm să completezi câmpul <strong>Oraș (livrare)</strong>.', $message);
    }

    public function testTelefonSiEmailInvalide()
    {
        $phone = ht_checkout_error_message(
            'billing_phone_validation',
            '<strong>Facturare Telefon</strong> nu este un număr de telefon valid.',
            array('label' => 'Telefon', 'group' => 'billing'),
            $this->prefixes()
        );
        $email = ht_checkout_error_message(
            'billing_email_validation',
            '<strong>Facturare Adresă email</strong> nu este o adresă de email validă.',
            array('label' => 'Adresă email', 'group' => 'billing'),
            $this->prefixes()
        );

        $this->assertSame('<strong>Telefon</strong> nu pare un număr de telefon valid.', $phone);
        $this->assertSame('<strong>Adresă email</strong> nu pare o adresă de email validă.', $email);
    }

    public function testAlteValidariPastreazaMesajulDoarCuEtichetaCurata()
    {
        $message = ht_checkout_error_message(
            'billing_postcode_validation',
            '<strong>Facturare Cod poștal</strong> nu este un cod poștal valid.',
            array('label' => 'Cod poștal', 'group' => 'billing'),
            $this->prefixes()
        );

        $this->assertSame('<strong>Cod poștal</strong> nu este un cod poștal valid.', $message);
    }

    public function testEroareFaraCampRamaneNeatinsa()
    {
        $original = 'Metoda de plată nu este validă.';

        $this->assertSame($original, ht_checkout_error_message('payment', $original, null, $this->prefixes()));
    }

    public function testFaraTelefonInLista_NuAdaugaNimic()
    {
        $fields = $this->fields();
        unset($fields['billing']['billing_phone']);

        $fields = apply_filters('woocommerce_checkout_fields', $fields);

        $this->assertArrayNotHasKey('billing_phone', $fields['billing']);
    }
}
