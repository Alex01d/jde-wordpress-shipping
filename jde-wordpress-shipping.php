<?php
/*
Plugin Name: Jde Shipping
Plugin URI: https://github.com/devjde/wordpress-woocommerce
Description: Расчет стоимости доставки при оформлении заказа в WooCommerce
Version: 1.0
Author: Желдорэкспедиция. Дорогов Алексей
Author URI: http://dev.jde.ru
License: GPLv2 or later
Text Domain: jde-wordpress-shipping
*/

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function JdeShippingMethodInit()
    {
        if (!class_exists('JDEShippingMethod')) {

            class JDEShippingMethod extends WC_Shipping_Method
            {

                /**
                 * Constructor
                 *
                 * @access public
                 */
                public function __construct()
                {

                    $this->id = 'jde_shipping'; // Id for your shipping method. Should be uunique.
                    $this->method_title = __('Желдорэкспедиция');  // Title shown in admin
                    $this->method_description = __('Расчет стоимости доставки в компании Желдорэкспедиция'); // Description shown in admin

                    $this->init();

                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                    $this->enabled = $this->get_option('enabled');
                    $this->title = "Желдорэкспедиция"; // This can be added as an setting but for this example its forced.


                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                function init_form_fields()
                {

                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('Включить доставку ЖДЭ', 'woocommerce'),
                            'default' => 'no'
                        ),
                        'apiurl' => array(
                            'title' => 'API URL',
                            'type' => 'text',
                            'description' => 'Адрес API ждэ',
                            'default' => 'http://apitest.jde.ru:8000'
                        ),
                        'from' => array(
                            'title' => 'Откуда',
                            'type' => 'text',
                            'description' => 'Город откуда отправляется товар.',
                            'default' => 'Москва'
                        ),
                        'volume' => array(
                            'title' => 'Объем товара, м3',
                            'type' => 'text',
                            'description' => 'Объем одного товара по-умолчанию, когда параметры товара не заданы.',
                            'default' => '1'
                        ),
                        'weight' => array(
                            'title' => 'Вес товара, кг',
                            'type' => 'text',
                            'description' => 'Вес одного товара по-умолчанию, когда параметры товара не заданы.',
                            'default' => '1'
                        )

                    );

                }


                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package=array())
                {

                    global $woocommerce;

                    // get state - from
                    $to = $woocommerce->customer->get_shipping_state();

                    // set default city FROM
                    $from = $this->settings['from'];

                    // get weight for cart
                    $weight = $woocommerce->cart->cart_contents_weight;

                    // if empty, use default from settings
                    if (empty($weight) == true) {
                        $weight = $this->settings['weight'];
                    }

                    $cart = $woocommerce->cart->get_cart();

                    // get volume by default for one item
                    $defaultItemVolume = $this->settings['volume'];
                    $totalVolume = 0;

                    foreach ($cart as $itemId => $values) {

                        $product = $values['data'];

                        $height = floatval($product->get_height);
                        $length = floatval($product->get_length);
                        $width = floatval($product->get_width);

                        $quantity = intval($cart[$itemId]['quantity']);

                        $productVolume = ($height * $length * $width) / (1000000);

                        // if volume is 0, use default value
                        if ($productVolume <= 0) {
                            $productVolume = $defaultItemVolume;
                        }

                        $totalVolume += $quantity * $productVolume;

                    }

                    $cost = -1;

                    // if city exist
                    $city = $woocommerce->customer->get_shipping_city();
                    if (empty($city) == false) {
                        $to = $city;
                    }

                    $params = array(
                        'from' => $from,
                        'to' => $to,
                        'weight' => $weight,
                        'volume' => $totalVolume,
                        // use smart mode, when from and to set like city names
                        'smart' => 1
                    );

                    // get prices
                    $url = $this->settings['apiurl'] . '/calculator/price?' . http_build_query($params);

                    $data = file_get_contents($url);
                    $json = json_decode($data);


                    if (empty($json->price) == false) {
                        $cost = $json->price;
                    }

                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $cost,
                        'calc_tax' => 'per_item'
                    );

                    if ($cost > 0) {
                        // Register the rate
                        $this->add_rate($rate);
                    }

                }

            }

        }

    }

    add_action('woocommerce_shipping_init', 'JdeShippingMethodInit');

    function JdeShippingMethodFunc($methods)
    {

        $methods[] = 'JDEShippingMethod';
        return $methods;

    }

    add_filter('woocommerce_shipping_methods', 'JdeShippingMethodFunc');

}

