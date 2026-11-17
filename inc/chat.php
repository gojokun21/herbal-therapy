<?php
/**
 * Chatul CreativeChat.
 *
 * Scriptul lor se incarca asincron, la sfarsitul paginii, si isi deseneaza
 * singur butonul si panoul. Pozitia, culorile si textele se seteaza din
 * platforma CreativeChat, nu de aici.
 *
 * Cheia pk_live_... e publica (ajunge oricum in sursa paginii), deci poate sta
 * in cod. Filtrul 'ht_chat_key' cu '' opreste chatul.
 *
 * @package Herbal_Therapy
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tag-ul <script> al chatului, in subsolul paginii.
 *
 * @return void
 */
function ht_chat_script()
{
    $key = (string)apply_filters('ht_chat_key', 'pk_live_6OHzeLbEOeg1UjY1rG1YNd7l');

    if ('' === $key) {
        return;
    }

    wp_print_script_tag(array(
        'src'          => 'https://cdn.creativechat.ro/loader.js',
        'data-key'     => $key,
        'data-cfasync' => 'false', // Rocket Loader-ul Cloudflare nu are voie sa-l amane
        'async'        => true,
    ));
}
add_action('wp_footer', 'ht_chat_script');
