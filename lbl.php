<?php

/*
  Plugin Name: LoanByLink
  Plugin URI: https://loanby.link
  Description: LoanByLink - zakupy na raty w internecie
  Version: 1.3.3
  Author: LoanByLink
  Author URI: https://honeypayment.pl/
 */

// Add new payment gaytway
function provema_add_gateway_class($gateways)
{
    $gateways[] = 'PROVEMA_Gateway';
    return $gateways;
}

add_filter('woocommerce_payment_gateways', 'provema_add_gateway_class');

function provema_gateway_plugin_links($links)
{

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=provema') . '">' . __('Konfiguruj', 'provema') . '</a>'
    );

    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'provema_gateway_plugin_links');

add_action('plugins_loaded', 'provema_init_gateway_class', 12);

// Payment gateway class
function provema_init_gateway_class()
{

    class PROVEMA_Gateway extends WC_Payment_Gateway
    {

        private $shop_id = '', $shop_key, $shop_secret, $calc_product_enabled, $calc_cart_enabled;

        public function __construct()
        {
            $this->id = 'provema';
            $this->icon = plugin_dir_url(__FILE__) . 'images/loanbylink-logo.png';
            $this->has_fields = false;
            $this->method_title = 'Raty LoanByLink';
            $this->method_description = 'Nie posiadasz konta LoanByLink? <br>
                                        <br>
                                         Zarejestruj się już teraz korzystając z linku <a href="https://online.loanby.link/register" target="_blank">REJESTRACJA</a> i aktywuj usługę płatności ratalnych LoanByLink.<br>
                                        <br>
                                        <br>
                                        Masz pytania? Skontaktuj się z nami.<br>
                                        <br>
                                        Biuro obsługi klienta:<br>
                                        lbl@loanby.link<br>
                                        +48 32 72 32 299<br>
            ';

            $this->supports = array('products');

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->shop_id = $this->get_option('shop_id');
            $this->shop_key = $this->get_option('shop_key');
            $this->shop_secret = $this->get_option('shop_secret');
            $this->min_order_amount = $this->get_option('min_order_amount');
            $this->max_order_amount = $this->get_option('max_order_amount');
            $this->calc_product_enabled = $this->get_option('calc_product_enabled');
            $this->calc_cart_enabled = $this->get_option('calc_cart_enabled');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        public function init_form_fields()
        {
            $this->form_fields = apply_filters('provema_form_fields', array(
                'enabled' => array(
                    'title' => 'Włącz/Wyłącz',
                    'type' => 'checkbox',
                    'label' => 'Włącz LoanByLink',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Nazwa płatności',
                    'type' => 'text',
                    'description' => 'Tą nazwę zobaczy użytkownika podczas dokonywania zakupu',
                    'default' => 'Raty LoanByLink',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Dodatkowy opis',
                    'type' => 'textarea',
                    'description' => 'Ten opis zobaczy użytkownika podczas dokonywania zakupu',
                    'default' => 'Zakupy na raty w internecie',
                    'desc_tip' => true,
                ),
                'shop_id' => array(
                    'title' => 'Identyfikator sklepu',
                    'type' => 'text',
                    'description' => 'Wprowadź identyfikator sklepu',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'shop_key' => array(
                    'title' => 'Klucz sklepu',
                    'type' => 'text',
                    'description' => 'Wprowadź klucz sklepu',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'shop_secret' => array(
                    'title' => 'Hasło sklepu',
                    'type' => 'text',
                    'description' => 'Wprowadź hasło sklepu',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'min_order_amount' => array(
                    'title' => 'Minimalna wartość zamówienia',
                    'type' => 'number',
                    'description' => 'Minimalna wartość zamówienia',
                    'default' => '300.00',
                    'desc_tip' => true,
                ),
                'max_order_amount' => array(
                    'title' => 'Maksymalna wartość zamówienia',
                    'type' => 'number',
                    'description' => 'Maksymalna wartość zamówienia',
                    'default' => '5000.00',
                    'desc_tip' => true,
                ),
                'calc_product_enabled' => array(
                    'title' => 'Włącz/Wyłącz',
                    'type' => 'checkbox',
                    'label' => 'Pokaż przycisk kalkulatora na szczegółach produktu',
                    'default' => 'yes'
                ),
                'calc_product_footer_enabled' => array(
                    'title' => 'Włącz/Wyłącz',
                    'type' => 'checkbox',
                    'label' => 'Pokaż kalkulator w stronie szczegółów produktu',
                    'default' => 'yes'
                ),
                'calc_cart_enabled' => array(
                    'title' => 'Włącz/Wyłącz',
                    'type' => 'checkbox',
                    'label' => 'Pokaż przycisk kalkulatora na stronie koszyka',
                    'default' => 'yes'
                ),
            ));
        }

        public function payment_fields()
        {
            echo wpautop( wp_kses_post( $this->description ) );
        }

        public function payment_scripts()
        {
            wp_enqueue_style('provema-style', esc_url(plugins_url('css/style.css', __FILE__)));
            wp_enqueue_script('provema-scripts', esc_url(plugins_url('js/main-scripts.js', __FILE__)), array(), null, false);
        }

        public function add_provema_calc()
        {
            global $woocommerce;
            $total_amount = $woocommerce->cart->total;
            $shop_id = base64_encode($this->shop_id);
            $calc_url = 'https://online.loanby.link/lbl/' . esc_attr($shop_id) . '/calculator?value=' . esc_attr($total_amount);
            $html = '<a target="_blank" href="' . esc_url($calc_url) . '" title="LoanByLink - oblicz ratę"><img src="' . esc_url(plugins_url( 'images/lbl-widget-button-calc-1.png', __FILE__ )) . '" style="height: 70px; display:inline;"/></a>';
            echo wp_kses_post($html);
        }

        public function validate_fields()
        {

        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $items = $order->get_items();
            $shipping = $order->get_items('shipping');
            //$order->update_status('on-hold', __('Awaiting offline payment', 'provema'));
            $order->update_status('wc-app-begin');
            $order->reduce_order_stock();
            WC()->cart->empty_cart();

            foreach ($items as $item)
            {
                $products_ids .= $item->get_product_id();
                $products_prices .= $item->get_total();
                //$products_quantity .= $item->get_quantity();
                $products_names .= $item->get_name();
                $products_ids .= '||';
                $products_prices .= '||';
                //$products_quantity .= '||';
                $products_names .= '||';
            }


            foreach( $shipping as $item){
                $products_ids .= 0;
                $products_prices .= $item->get_total();
                $products_names .= $item->get_name();

                $products_ids .= '||';
                $products_prices .= '||';
                $products_names .= '||';
            }

            $payload = array(
                'amount' => $order->get_total(),
                'url_back' => $this->get_return_url($order),
                'partner_id' => base64_encode($this->shop_id),
                'control' => '$order_id',
                'items_ids' => $products_ids,
                'items_names' => $products_names,
                'items_prices' => $products_prices
            );

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        public function webhook()
        {
            $this->provema_logger($_GET);
            $order_id = sanitize_text_field($_GET['control']);
            $order = wc_get_order($order_id);
            $order->payment_complete();
            //$order->reduce_order_stock();
        }

        public function provema_logger($request)
        {
            $logger = wc_get_logger();

            $method = sanitize_text_field($_SERVER['REQUEST_METHOD']);
            $data = "\r\n------------------$method------------------\r\n";
            foreach ($request as $key => $value)
            {
                $data .= $key . ' => ' . $value . "\r\n";
            }

            $logger->info($data, array('source' => 'provema-status'));
        }

        public function thankyou_page($order_id)
        {


            if(!isset($_POST['loanId']))
            {
                $order = wc_get_order($order_id);

                $items = $order->get_items();
                $shipping = $order->get_items('shipping');

                $i = 0;
                foreach ($items as $item)
                {
                    $name = $item->get_name();
                    $price = $item->get_total() + $item->get_total_tax();

                    if ($name!=''){
                        $separator = '';
                        if ($i > 0 || $i < sizeof($items))
                        {
                            $separator = '||';
                        }

                        $products_ids .= $item->get_product_id() . $separator;
                        $products_prices .= $price . $separator;
                        $products_names .= $name . $separator;
                    }

                    $i++;
                }

                $i = 0;

                foreach( $shipping as $item){
                    $name = $item->get_name();
                    $price = $item->get_total() + $item->get_total_tax();
                    if ($name!=''){


                        $separator = '';
                        if ($i > 0 || $i < sizeof($items))
                        {
                            $separator = '||';
                        }

                        $products_ids .= '0' . $separator;
                        $products_prices .= $price . $separator;
                        $products_names .= $name . $separator;

                    }

                    $i++;
                }



                $preloader_src = plugin_dir_url(__FILE__) . 'images/preloader.gif';
                $preloader = '<img src="' . $preloader_src . '" style="display:inline;"/>';
                $form = '<form id="lbl-form" action="https://raty.loanby.link/wniosek_partner" method="post">';
                $form .= '<input type="hidden" readonly="readonly" name="amount" value="' . esc_attr($order->get_total()) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="url_back" value="' . esc_url($this->get_return_url($order)) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="partner_id" value="' . esc_attr(base64_encode($this->shop_id)) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="control" value="' . esc_attr($order_id) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="items_ids" value="' . esc_attr($products_ids) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="items_names" value="' . esc_attr($products_names) . '" />';
                $form .= '<input type="hidden" readonly="readonly" name="items_prices" value="' . esc_attr($products_prices) . '" />';
                $form .= '</form>';
                $form .= '<div class="provema-button-wrap">' . $preloader . '</div>';
                $form .= '<script> document.getElementById(\'lbl-form\').submit(); </script>';

                echo wp_kses($form, array(
                    'form' => array(
                        'id' => array(),
                        'action' => array(),
                        'method' => array()
                    ),
                    'input' => array(
                        'type' => array(),
                        'readonly' => array(),
                        'name' => array(),
                        'value' => array()
                    ),
                    'div' => array(
                        'class' => array()
                    ),
                    'img' => array(
                        'src' => array(),
                        'style' => array()
                    ),
                    'script' => array()
                ));
            }
            else
            {

                update_post_meta($order_id, 'loan_id', sanitize_key($_POST['loanId']));

                add_action('woocommerce_thankyou', array($this, 'add_wc_provema_thanks'), 1);

            }
        }

        public function add_wc_provema_thanks($order_id)
        {
            $order = wc_get_order($order_id);
            $loan_id = get_post_meta($order_id, 'loan_id');
            $enviroment_url = 'https://online.loanby.link/lbl/' . $this->shop_id . '/api/loan/get';
            $postdata = array(
                'shopKey' => $this->shop_key,
                'shopSecret' => $this->shop_secret,
                'loanId' => $loan_id[0]
            );

            $response = wp_remote_post($enviroment_url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($postdata),
                'method' => 'POST',
                'redirection' => 5,
                'blocking' => true,
                'sslverify' => false,
                'data_format' => 'body'
            ));
            $data = json_decode($response['body'], true);

            switch ($data['loanStatusId'])
            {
                case -1:
                    $order->update_status('wc-app-rejected');
                    break;
                case 0:
                    $order->update_status('wc-app-verify');
                    break;
                case 2:
                    $order->update_status('wc-app-accepted');
                    break;
            }

            $provema_img_src = plugin_dir_url(__FILE__) . 'images/loanbylink-logo.png';
            $provema_preloader_src = plugin_dir_url(__FILE__) . 'images/preloader.gif';
            $html = '<section id="woocommerce-order-details" class="woocommerce-order-details"><h2 class="woocommerce-column__title">Status pożyczki LoanByLink</h2>';
            $html .= '<div id="provema-loan-status" class="provema-loan-details" data-api-url="' . esc_url($enviroment_url) . '" data-shop-key="' . esc_attr($this->shop_key) . '" data-shop-secret="' . esc_attr($this->shop_secret) . '" data-loan-id="' . esc_attr($data['data']['loanId']) . '" data-order-id="' . esc_attr($order_id) . '"><div class="wc-col">';
            $html .= '<img src="' . $provema_img_src . '" style="display:inline;"/><br/><br/>';
            $html .= '<img id="ajax-loader" src="' . $provema_preloader_src . '" style="display:inline;"/>';
            $html .= '<br><br>';
            $html .= '<p><strong id="loan-status-name">' . esc_attr($data['data']['loanStatusName']['pl']) . '</strong></p>';
            $html .= '</div>';
            $html .= '<div class="wc-col">';
            $html .= '<p>Numer wniosku: <strong>' . esc_attr($data['data']['loanId']) . '</strong></p>';
            $html .= '<p>Kwota pożyczki: ' . esc_attr($data['data']['loanValueFormated']) . '</p>';
            $html .= '<p>Rata miesięczna: ' . esc_attr($data['data']['loanInstallmentFormated']) . '</p>';
            $html .= '<p>Kwota do spłaty: ' . esc_attr($data['data']['loanAmountToPayFormated']) . '</strong></p>';
            $html .= '<p>Okres pożyczki: ' . esc_attr($data['data']['loanPeriod']) . '</p>';
            $html .= '</div><div class="wc-col"><p><strong>Dodatkowe informacje</strong></p>';
            $html .= '<p>Zawsze możesz zalogować się na<br/> LoanByLink w celu weryfikacji<br/> statusu wniosku</p>';
            $html .= '<p><a target="_blank" class="provama-button" href="https://raty.loanby.link/" title="">Zaloguj się</a></p>';
            $html .= '</div></div></section>';
            echo wp_kses_post($html);
        }

    }

}

function provema_add_calc_product()
{
    $provema_options = get_option('woocommerce_provema_settings');
    $product = wc_get_product(get_the_ID());
    $total_amount = $product->get_price();
    $shop_id = base64_encode($provema_options['shop_id']);
    $calc_url = 'https://online.loanby.link/lbl/' . esc_attr($shop_id) . '/calculator?value=' . esc_attr($total_amount);
    $html = '<a target="_blank" href="' . esc_url($calc_url) . '" title="LoanByLink oblicz ratę"><img src="' . esc_url(plugins_url( 'images/lbl-widget-button-calc-1.png', __FILE__ )) . '" style="height: 70px;"/></a>';

    if ($provema_options['calc_product_enabled']=='yes'){
        if ($total_amount >= $provema_options['min_order_amount'] && $total_amount <= $provema_options['max_order_amount'])
        {
            echo wp_kses_post($html);
        }
    }
}

function provema_add_calc_product_footer()
{
    $provema_options = get_option('woocommerce_provema_settings');
    $product = wc_get_product(get_the_ID());
    $total_amount = $product->get_price();

    if ($provema_options['calc_product_footer_enabled'] == 'yes') {
        if ($total_amount >= $provema_options['min_order_amount'] && $total_amount <= $provema_options['max_order_amount']) {
            wp_enqueue_script('lbl_widget_js', 'https://online.loanby.link/js/lbl-widget.1.0.js');
            wp_enqueue_style('lbl_widget_css', 'https://online.loanby.link/css/lbl-widget.1.0.css');

            $script = "
                var widgetOptionsProductPage = {
                        'element'      : 'loanByLinkProductPagePanel',
                        'template'     : 'c1',
                        'token'        : '" . $provema_options['shop_id'] . "',
                        'language'     : 'pl',
                        'currency'     : 'zł',
                
                        'itemsIDs'     : '1',
                        'itemsNames'   : 'Product',
                        'itemsPrices'  : '" . $total_amount . "',
                        'total'        : '" . $total_amount . "',
                
                        'control'      : '',
                
                        'borderColor' : '',
                }
            
                widgetWrapper.init(widgetOptionsProductPage);
            ";

            wp_add_inline_script('lbl_widget_js', $script);
            echo "<div id=\"loanByLinkProductPagePanel\" style=\"height: 700px;margin-top: 10px;\"></div>";
        }
    }
}

function provema_add_calc_cart()
{
    global $woocommerce;
    $provema_options = get_option('woocommerce_provema_settings');
    //$product = wc_get_product(get_the_ID());
    //$total_amount = $product->get_price();
    $total_amount = $woocommerce->cart->total;
    $shop_id = base64_encode($provema_options['shop_id']);
    $calc_url = 'https://online.loanby.link/lbl/' . esc_attr($shop_id) . '/calculator?value=' . esc_attr($total_amount);
    $html = '<div style="text-align: center;"><a target="_blank" href="' . esc_url($calc_url) . '" title="LoanByLink oblicz ratę"><img src="' . esc_url(plugins_url( 'images/lbl-widget-button-calc-1.png', __FILE__ )) . '" style="height: 70px; display:inline;"/></a></div>';

    if ($provema_options['calc_cart_enabled']=='yes'){
        if ($total_amount >= $provema_options['min_order_amount'] && $total_amount <= $provema_options['max_order_amount'])
        {
            echo wp_kses_post($html);
        }
    }
}

function provema_show_form_on_order_details($order_id)
{
    wp_enqueue_style('provema-style', esc_url(plugins_url('css/style.css', __FILE__)));
    $order = wc_get_order($order_id);
    $items = $order->get_items();
    $provema_options = get_option('woocommerce_provema_settings');
    $shop_id = base64_encode($provema_options['shop_id']);

    $i = 0;
    foreach ($items as $item)
    {
        $separator = '';
        if ($i > 0 || $i < sizeof($items))
        {
            $separator = '||';
        }
        $products_ids .= $item->get_product_id() . $separator;
        $products_prices .= $item->get_total() . $separator;
        $products_names .= $item->get_name() . $separator;
        $i++;
    }

    $return_url = WC_Payment_Gateway::get_return_url($order);
    $provema_img_src = plugin_dir_url(__FILE__) . 'images/loanbylink-logo.png';
    $html = '<section class="woocommerce-order-details"><div class="provema-loan-details"><h3 class="align-center">Zakupy na raty LoanByLink</h3><p class="align-center">W celu ponownego złożenia wniosku kredytowego skorzystaj z opcji poniżej</p>';
    $html .= '<form action="https://raty.loanby.link/wniosek_partner" method="post">';
    $html .= '<input type="hidden" readonly="readonly" name="amount" value="' . esc_attr($order->get_total()) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="url_back" value="' . esc_url($return_url) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="partner_id" value="' . esc_attr($shop_id) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="control" value="' . esc_attr($order_id) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="items_ids" value="' . esc_attr($products_ids) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="items_names" value="' . esc_attr($products_names) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="items_prices" value="' . esc_attr($products_prices) . '" />';
    $html .= '<input type="hidden" readonly="readonly" name="demo" value="1" />';
    $html .= '<div class="provema-button-wrap"><button class="provama-button" type="submit">Przejdź do wniosku</button></div>';
    $html .= '</form>';
    $html .= '</div></section>';
    $html .= '<section class="woocommerce-order-details"><div class="provema-loan-details"><div class="element-center"><img src="' . esc_url($provema_img_src) . '" style="display:inline;" /></div><h4 class="align-center">Masz pytania? Skontaktuje się z nami.</h4><p class="align-center"><a href="mailto:lbl@loanby.link">lbl@loanby.link</a><br/>lub<br/><a href="tel:+48 327 232 299">+48 327 232 299</a><br/> Od poniedziałku do piątku od 8:00 do 20:00<br/><a target="_blank" href="https://loanby.link">loanby.link</a></p>';
    $html .= '</div></section>';

    $order_status = $order->get_status();
    if ($order_status === 'app-begin') {
        echo wp_kses($html,
            array(
                'section' => array(
                    'class' => array(),
                ),
                'p' => array(
                    'class' => array(),
                ),
                'h3' => array(
                    'class' => array()
                ),
                'h4' => array(
                    'class' => array()
                ),
                'form' => array(
                    'id' => array(),
                    'action' => array(),
                    'method' => array()
                ),
                'input' => array(
                    'type' => array(),
                    'readonly' => array(),
                    'name' => array(),
                    'value' => array()
                ),
                'div' => array(
                    'class' => array()
                ),
                'img' => array(
                    'src' => array(),
                    'style' => array()
                ),
                'script' => array(),
                'button' => array(
                    'class' => array(),
                    'type' => array()
                ),
                'a' => array(
                    'href' => array(),
                    'target' => array()
                ),
                'br' => array()
            )
        );
    }
}

function provema_register_statuses()
{
    register_post_status('wc-app-begin', array(
        'label' => 'LoanByLink - Rozpoczęto proces składania wniosku',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('LoanByLink - Rozpoczęto proces składania wniosku (%s)', 'LoanByLink - Rozpoczęto proces składania wniosku (%s)')
    ));
    register_post_status('wc-app-verify', array(
        'label' => 'LoanByLink – Wniosek jest w trakcie weryfikacji',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('LoanByLink - Wniosek jest w trakcie weryfikacji (%s)', 'LoanByLink - Wniosek jest w trakcie weryfikacji (%s)')
    ));
    register_post_status('wc-app-accepted', array(
        'label' => 'LoanByLink – Wniosek został zaakceptowany',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('LoanByLink – Wniosek został zaakceptowany (%s)', 'Wniosek został zaakceptowany (%s)')
    ));
    register_post_status('wc-app-rejected', array(
        'label' => 'Wniosek został odrzucony',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('LoanByLink – Wniosek został odrzucony (%s)', 'LoanByLink – Wniosek został odrzucony (%s)')
    ));
}

function provema_add_statuses_to_order_statues($order_statuses)
{
    $new_order_statuses = array();
    foreach ($order_statuses as $key => $status)
    {
        $new_order_statuses[$key] = $status;
        if ($key === 'wc-processing')
        {
            $new_order_statuses['wc-app-begin'] = 'LoanByLink – Rozpoczęto proces składania wniosku';
            $new_order_statuses['wc-app-verify'] = 'LoanByLink – Wniosek jest w trakcie weryfikacji';
            $new_order_statuses['wc-app-accepted'] = 'LoanByLink – Wniosek został zaakceptowany';
            $new_order_statuses['wc-app-rejected'] = 'LoanByLink – Wniosek został odrzucony';
        }
    }
    return $new_order_statuses;
}

function provema_check_status()
{
    $order = wc_get_order($_POST['orderId']);
    $enviroment_url = sanitize_url($_POST['endpoitUrl']);
    $postdata = array(
        'shopKey' => sanitize_key($_POST['shopKey']),
        'shopSecret' => sanitize_key($_POST['shopSecret']),
        'loanId' => sanitize_key($_POST['loanId'])
    );

    $response = wp_remote_post($enviroment_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($postdata),
        'method' => 'POST',
        'redirection' => 5,
        'blocking' => true,
        'sslverify' => false,
        'data_format' => 'body'
    ));

    $data = json_decode($response['body'], true);

    $response = array('loanStatusId' => $data['data']['loanStatusId'], 'loanStatusName' => $data['data']['loanStatusName']['pl']);

    switch ($data['data']['loanStatusId'])
    {
        case -1:
            $order->update_status('wc-app-rejected');
            break;
        case 0:
            $order->update_status('wc-app-verify');
            break;
        case 2:
            $order->update_status('wc-app-accepted');
            break;
    }

    echo json_encode($response);
}

function provema_show_payment_gateway($available_gateways)
{
    global $woocommerce;
    $options = get_option('woocommerce_provema_settings');
    $total_amount = $woocommerce->cart->total;
    if ($total_amount < $options['min_order_amount'] || $total_amount > $options['max_order_amount'])
    {
        unset($available_gateways['provema']);
    }

    return $available_gateways;
}

function provema_show_thank_you_title( ){
    if (isset($_POST['loanId'])) {
        return 'Szczegóły zamówienia';
    }
    return '';
}

function provema_show_thank_you_text( ){

    if (isset($_POST['loanId'])){
        return '<p style="text-align: center">Dziękujemy za skorzystanie z oferty LoanByLink.<br> Poniżej znajdzisz szczegóły zamówienia oraz status Twojej pożyczki.</p>';
    }
    return '';
}

function provema_lbl_orders_update($debug = false)
{
    if ($debug){
        echo 'start<br>';
    }

    global $wpdb;

    $lastDays = 14;

    $sql = "SELECT ID, post_status FROM wp_posts WHERE post_type = 'shop_order' AND post_status IN('wc-app-verify','wc-app-begin') and  DATE(post_date) >= (DATE(NOW()) - INTERVAL ".$lastDays." DAY)";
    $orders = $wpdb->get_results($sql, ARRAY_A);

    if ($debug)
        echo wp_kses_post('Orders count:'.count($orders).'<br><br>');

    $provema_options = get_option('woocommerce_provema_settings');
    $shop_id = $provema_options['shop_id'];
    $shop_key = $provema_options['shop_key'];
    $shop_secret = $provema_options['shop_secret'];

    foreach ($orders as $order)
    {
        if ($debug)
            echo wp_kses_post('Order id ' . esc_attr($order['ID']) . ' Status:' . esc_attr($order['post_status']) . '<br>');

        $postdata = array(
            'shopKey' => $shop_key,
            'shopSecret' => $shop_secret,
            'orderNumber' => $order['ID']
        );

        $response = provema_get_post_data($shop_id,$postdata);
        if ($response){

            $data = json_decode($response, true);
            if ($data['status']=='ok'){
                $order_status = null;
                switch ($data['data']['loanStatusId'])
                {
                    case -1:
                        $order_status = 'wc-app-rejected';
                        break;
                    case 2:
                        $order_status = 'wc-app-accepted';
                        break;
                }

                if (!is_null($order_status)) {
                    if ($debug)
                        echo wp_kses_post('Order id ' . esc_attr($order['ID']) . ' Set new status:' . esc_attr($order_status) . '<br>');
                    $sql = "UPDATE wp_posts SET post_status = %s, post_modified=current_timestamp WHERE ID = %s";
                    $wpdb->query(
                        $wpdb->prepare($sql, [$order_status, $order['ID']])
                    );
                }

            }else{
                if ($debug)
                    echo wp_kses_post('Order id ' . esc_attr($order['ID']) . ' LBL API response error ' . esc_attr($data['error']['code']) . ' ' . esc_attr($data['error']['message']['pl']) . '<br>');

                if ($order['post_status'] != 'wc-app-begin') {
                    if ($data['error']['code'] == '010') {
                        $sql = "UPDATE wp_posts SET post_status = 'wc-app-rejected', post_modified=current_timestamp WHERE ID = %s";
                        $wpdb->query(
                            $wpdb->prepare($sql, [$order['ID']])
                        );
                    }
                }
            }

        } else {
            if ($debug)
                echo wp_kses_post('Order id ' . esc_attr($order['ID']) . ' LBL API response is null<br>');
        }

        if ($debug){
            echo '<br>';
        }

        sleep(1);
    }

    if ($debug)
        echo 'end';
}

function provema_get_post_data($shop_id,$data){
    $url = 'https://online.loanby.link/lbl/' . $shop_id . '/api/loan/get';

    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) { /* Handle error */ }

    return $result;
}

add_action('woocommerce_before_add_to_cart_button', 'provema_add_calc_product');

add_action('woocommerce_after_single_product', 'provema_add_calc_product_footer');

add_action('woocommerce_after_cart', 'provema_add_calc_cart');

add_action('woocommerce_view_order', 'provema_show_form_on_order_details', 12);

add_action('init', 'provema_register_statuses');

add_action('wc_ajax_check_provema_status', 'provema_check_status');

add_filter('wc_order_statuses', 'provema_add_statuses_to_order_statues');

add_filter('woocommerce_available_payment_gateways', 'provema_show_payment_gateway');

add_filter('woocommerce_endpoint_order-received_title', 'provema_show_thank_you_title');

add_filter('woocommerce_thankyou_order_received_text', 'provema_show_thank_you_text');

add_action('provema_lbl_orders_update', 'provema_lbl_orders_update');

add_filter('init', function () {
    if (isset($_GET['provema_lbl_orders_update']) && $_GET['provema_lbl_orders_update'] === "true") {
        provema_lbl_orders_update(true);
        die;
    }
});
