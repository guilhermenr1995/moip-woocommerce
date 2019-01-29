<?php

/*

Plugin Name: Moip Assinaturas

Description: Integração do Moip no Woocommerce

Author: Guilherme Rodrigues

Version: 1.0

*/

class MoipAssinaturas {

    /**
     * Instance of this class.
     *
     * @var object
     */
    public static $instance = null;

    function __construct() {

        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            // Inclui a classe moip gateway.
            include_once 'moip-assinaturas-gateway.php';
        }

        add_action( 'plugins_loaded', array( 'MoipAssinaturas', 'get_instance' ) ); // Cria uma instância dessa classe quando os plugins são carregados
        add_action( 'save_post', array( $this, 'save_plan' ), 10, 3 ); // Salva plano quando salva um produto que é assinatura.
        add_action( 'save_post', array( $this, 'save_customer_and_subscription' ), 10, 3 ); // Salva cliente e assinatura quando um pedido é concluído.
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woo_add_custom_general_fields' ) , 10, 3 ); // Adiciona os campos no painel de produtos para criá-lo como uma assinatura.
        add_filter( 'woocommerce_payment_gateways', array( $this, 'moip_assinaturas_gateway' ) );
    }

    
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    function moip_assinaturas_gateway ( $methods ) {
        $methods[] = 'MoipAssinaturasGateway'; 
        return $methods;
    }

    /**
     * Save post metadata when a post is saved.
     *
     * @param int $post_id The post ID.
     * @param post $post The post object.
     * @param bool $update Whether this is an existing post being updated or not.
     */
    function save_plan( $post_id, $post, $update ) {

        if ($post->post_type == 'product')
        {
            $woocommerce_is_subscription = isset( $_POST['_is_subscription'] ) ? 'yes' : 'no';
            update_post_meta( $post_id, '_is_subscription', $woocommerce_is_subscription );

            if ('yes' === $woocommerce_is_subscription) // Se for assinatura
            {
                $woocommerce_billing_interval = isset( $_POST['_billing_interval'] ) ? $_POST['_billing_interval'] : 'no';
                update_post_meta( $post_id, '_billing_interval', $woocommerce_billing_interval );

                $woocommerce_billing_interval_only_numbers = isset( $_POST['_billing_interval_only_numbers'] ) ? (is_numeric($_POST['_billing_interval_only_numbers']) ? $_POST['_billing_interval_only_numbers'] : '1') : null;
                update_post_meta( $post_id, '_billing_interval_only_numbers', $woocommerce_billing_interval_only_numbers );

                $woocommerce_billing_cycles = isset( $_POST['_billing_cycles'] ) ? (is_numeric($_POST['_billing_cycles']) ? $_POST['_billing_cycles'] : '1') : null;
                update_post_meta( $post_id, '_billing_cycles', $woocommerce_billing_cycles );

                $post_meta = get_post_meta($post_id);

                $params = array(
                    //Identificação
                    'code'          => $post_id,          // Identificador do plano na sua aplicação. Até 65 caracteres.
                    'name'          => $post->post_title, // Nome do plano na sua aplicação. Até 65 caracteres.
                    'description'   => '',                // Descrição do plano na sua aplicação. Até 255 caracteres.
                    'amount'        => str_replace('.', '', $post_meta['_regular_price'][0]), // Valor do plano a ser cobrado em centavos de Real. obrigatório
                    'setup_fee'     => '',  // Taxa de contratação a ser cobrada na assinatura em centavos de Real.
                    'interval'      => array(                                      // Node do intervalo do plano, contendo unit e length condicional
                        'unit'      => $woocommerce_billing_interval,              // A unidade de medida do intervalo de cobrança, o default é MONTH. Opções: DAY, MONTH, YEAR condicional
                        'length'    => $woocommerce_billing_interval_only_numbers, // A duração do intervalo de cobrança, default é 1 condicional
                    ),
                    'billing_cycles' => $woocommerce_billing_cycles,               // Quantidade de ciclos (faturas) que a assinatura terá até expirar (se não informar, não haverá expiração).
                    'status'         => ('no' === $woocommerce_is_subscription) ? 'INACTIVE' : 'ACTIVE',
                    'payment_method' => 'ALL' // Métodos de pagamento
                );

                $response = $this->make_request($url = 'plans', $params);
            }
            else 
            {
                $woocommerce_billing_interval = 'no';
                update_post_meta( $post_id, '_billing_interval', $woocommerce_billing_interval );

                $woocommerce_billing_interval_only_numbers = null;
                update_post_meta( $post_id, '_billing_interval_only_numbers', $woocommerce_billing_interval_only_numbers );

                $woocommerce_billing_cycles = null;
                update_post_meta( $post_id, '_billing_cycles', $woocommerce_billing_cycles );
            }
        }
    }

    function validate_moip($fields) 
    {
        $response = array(); // Receberá todos os retornos da validação

        //Cliente
        $customer_id      = str_replace(str_split('.-'), '', $fields['billing_cpf']);
        $fullname         = $fields['billing_first_name'] . ' ' . $fields['billing_last_name'];
        $email            = $fields['billing_email'];
        $cpf              = str_replace(str_split('.-'), '', $fields['billing_cpf']);
        $phone_code       = substr($fields['billing_phone'], 1, 2);
        $phone            = str_replace('-','',substr($fields['billing_phone'],5));
        $birthdate_day    = '01';   // Não está disponível no perfil do usuário do Wordpress
        $birthdate_month  = '01';   // Não está disponível no perfil do usuário do Wordpress
        $birthdate_year   = '1991'; // Não está disponível no perfil do usuário do Wordpress

        // Endereço
        $street       = $fields['billing_address_1'];
        $number       = $fields['billing_number'];
        $complement   = $fields['billing_address_2'];
        $district     = $fields['billing_neighborhood'];
        $city         = $fields['billing_city'];
        $state        = $fields['billing_state'];
        $country      = 'BRA'; // Campo não disponível no Wordpress no formato do Moip
        $zipcode      = str_replace('-', '', $fields['billing_postcode']);


        $customer = array(
            'code'     => $customer_id,
            'fullname' => $fullname,
            'email'    => $email,
            'cpf'      => $cpf,
            'phone_area_code' => $phone_code,
            'phone_number'    => $phone,
            'birthdate_day'   => $birthdate_day,   // Não está disponível no perfil do usuário do Wordpress
            'birthdate_month' => $birthdate_month, // Não está disponível no perfil do usuário do Wordpress
            'birthdate_year'  => $birthdate_year,  // Não está disponível no perfil do usuário do Wordpress
            'address' => array(
                'street'     => $street,
                'number'     => $number,
                'complement' => $complement,
                'district'   => $district,
                'city'       => $city,
                'state'      => $state,
                'country'    => $country,
                'zipcode'    => $zipcode,
            )
        );


        $has_customer = $this->make_request($url = 'customers/'.$customer_id);
                        
        // Se não existir este cliente no Moip, cria
        if (empty($has_customer)) {
            $response['customer_new'] = $this->make_request($url = 'customers', $customer);
        } else { // Existe um usuário com esse ID. Portanto, atualiza os dados com o array $customer acima.
            $response['customer_edit'] = $this->make_request($url = 'customers/'.$customer_id, $customer, $type = "PUT");
        }

        //Dados bancários do cliente
        $holder_name        = strtoupper($fields['holder_name']);
        $number             = $fields['number'];
        $expiration_month   = $fields['expiration_month'];
        $expiration_year    = substr( $fields['expiration_year'], 2 );

        // Atualizar cadastro do usuário para inserir dados bancários (Não está sendo cadastrado junto com o usuário)
        $credit_card = array(
            'credit_card' => array(
                'holder_name'      => $fullname,// $holder_name,
                'number'           => $number,
                'expiration_month' => $expiration_month,
                'expiration_year'  => $expiration_year
            )
        );

        $response['credit_card'] = $this->make_request($url = 'customers/'.$customer_id.'/billing_infos', $credit_card, $type = "PUT");

        return $response;
    }

    function save_customer_and_subscription( $post_id, $post, $update ) 
    {
        if ( ($post->post_status == 'wc-on-hold') && ($post->post_type == 'shop_order') && ($update == 1) ) // Quando criar um pedido, cria uma assinatura
        {
            $postmeta       = get_post_meta( $post_id );
            $order_meta     = new WC_Order( $post_id );
            $order_id       = $order_meta->id;
            $order_all_meta = get_post_meta( $order_id );
            $products       = $order_meta->get_items();
            $plans          = $this->make_request( $url = 'plans' );
            $plans_id       = array();

            // Dados do usuário
            $customer_id      = str_replace(str_split('.-'), '', $postmeta['_billing_cpf'][0]);
            $fullname         = $postmeta['_billing_first_name'][0] . ' ' . $postmeta['_billing_last_name'][0];
            $email            = $postmeta['_billing_email'][0];
            $cpf              = str_replace(str_split('.-'), '', $postmeta['_billing_cpf'][0]);
            $phone_code       = substr($postmeta['_billing_phone'][0], 1, 2);
            $phone            = str_replace('-','',substr($postmeta['_billing_phone'][0],5));
            $birthdate_day    = '01';   // Não está disponível no perfil do usuário do Wordpress
            $birthdate_month  = '01';   // Não está disponível no perfil do usuário do Wordpress
            $birthdate_year   = '1991'; // Não está disponível no perfil do usuário do Wordpress
            $checked_customer = false;  // Verificador se o usuário já foi atualizado nessa compra

            // Endereço
            $street       = $postmeta['_billing_address_1'][0];
            $number       = $postmeta['_billing_number'][0];
            $complement   = $postmeta['_billing_address_2'][0];
            $district     = $postmeta['_billing_neighborhood'][0];
            $city         = $postmeta['_billing_city'][0];
            $state        = $postmeta['_billing_state'][0];
            $country      = 'BRA'; // Campo não disponível no Wordpress no formato do Moip
            $zipcode      = str_replace('-', '', $postmeta['_billing_postcode'][0]);

            $response = array();

            foreach ($products as $product) {

                $product_id = $product['item_meta']['_product_id'][0];
                $product_meta = get_post_meta($product_id);

                $woocommerce_is_subscription = $product_meta['_is_subscription'][0];

                if ('yes' === $woocommerce_is_subscription)
                {
                    //Cria assinatura
                    $prd_meta         = get_post_meta($product_id);
                    $subscription_id  = hash('crc32', $product_id.$order_id); // Para se tornar único
                    $amount           = str_replace('.', '', $prd_meta['_regular_price'][0]);
                    
                    $subscription = array(
                        'code'   => $subscription_id,
                        'amount' => $amount,
                        'plan' => array(
                            'code' => $product_id
                        ),
                        'customer' => array(
                            'code' => $customer_id
                        )
                    );

                    $response['subscriptions'] = $this->make_request($url = 'subscriptions?new_customer=false', $subscription);
                }
            }

            $error_message = '';

            foreach ($response as $item => $values) {
                if (isset($values->errors)) {
                    foreach ($values->errors as $error) {
                        $error_message .= '<b>Moip: </b>' . $error->description . ' (<b>'. $error->code .'</b>)<br/>'; 
                    }
                }
            }

            write_log('$error_message');
            write_log( $error_message );

            if (strlen($error_message) >= 1) {
                wc_add_notice( $error_message, 'error' );
                return false;
            }
        }

        return true;
    }

    function make_request ($url, $params = array(), $type = '') {

        $ch = curl_init("https://sandbox.moip.com.br/assinaturas/v1/".$url); //Endereço que redireciona para o ambiente de testes

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Verifica o SSL do cliente
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // Verifica o SSL do host
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          "Content-Type: application/json",
          "Authorization: Basic " . base64_encode('HC0S66EHCNFWZLYTYHN1IK3G2ESTUNWP'.':'.'UJUDNELFM0KSXOTEESX0M5WNAX4OAYYZQ5XVVHXE') // TODO: Trazer o token inserido no painel de configuração do WooCommerce (Este é só um token de exemplo)
        ));

        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        if ($type != '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        }

        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        return $result;
    }

    function woo_add_custom_general_fields() {
        global $woocommerce, $post;

        echo '<div class="options_group">';
            woocommerce_wp_checkbox( array( 'id' => '_is_subscription', 'wrapper_class' => 'show_if_simple show_if_variable', 'label' => __( 'Assinatura', 'woocommerce' ), 'description' => __( 'O produto passa a ser uma assinatura.', 'woocommerce' ) ) );

            woocommerce_wp_text_input(
                array(
                    'id' => '_billing_interval_only_numbers',
                    'label' => __( 'Intervalo de cobrança', 'woocommerce' ),
                    'description' => __( 'Somente números.', 'woocommerce' ), 
                    'desc_tip' => true 
                )
            );

            woocommerce_wp_select(
                array(
                    'id' => '_billing_interval',
                    'label' => __( 'Intervalo de cobrança', 'woocommerce' ),
                    'options' => array(
                        'day' => __( 'Dia(s)', 'woocommerce' ),
                        'month' => __( 'Mês(es)', 'woocommerce' ),
                        'year' => __( 'Ano(s)', 'woocommerce' )
                    )
                )
            );

            woocommerce_wp_text_input(
                array(
                    'id' => '_billing_cycles',
                    'label' => __( 'Quantidade de ciclos', 'woocommerce' ),
                    'description' => __( 'Somente números.', 'woocommerce' ), 
                    'desc_tip' => true
                )
            );
        echo '</div>';
    }
}

add_action("init", "initMoipAssinaturas");
function initMoipAssinaturas() { global $moipAssinaturas; $moipAssinaturas = new MoipAssinaturas(); }

?>