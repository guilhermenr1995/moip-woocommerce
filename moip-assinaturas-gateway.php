<?php

class MoipAssinaturasGateway extends WC_Payment_Gateway {
	
	function __construct() {
		$this->id             		= 'moip-assinaturas';
		$this->icon           		= apply_filters( 'woocommerce_moip_icon', plugins_url( 'includes/img/moip.png', __FILE__ ) );
		$this->has_fields     		= true;
		$this->method_title   		= 'Moip Assinaturas';
		$this->method_description   = '';

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		wp_enqueue_script( 'validate-checkout', plugins_url( 'includes/js/validate-checkout.js', __FILE__ ) , array( 'jquery' ), '', true );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => 'Habilitar/Desabilitar',
				'type' => 'checkbox',
				'label' => 'Habilitar/Desabilitar Moip Assinaturas como método de pagamento padrão',
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-moip' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-moip' ),
				'desc_tip' => true,
				'default' => __( 'Moip', 'woocommerce-moip' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-moip' ),
				'type' => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-moip' ),
				'default' => __( 'Pagamento via Moip Assinaturas', 'woocommerce-moip' )
			)
		);
	}

	function process_payment( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		// Marca como on-hold (aguardando pagamento)
		$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

		// Reduz o número de itens em estoque
		$order->reduce_order_stock();

		// Remove itens do carrinho
		$woocommerce->cart->empty_cart();

		// Retorna sucesso para a página "Obrigado"!
		return array(
			'result' => 'success',
			'redirect' =>  $this->get_return_url( $order )
		);
	}

	function payment_fields () {
		global $woocommerce;

		include plugin_dir_path( __FILE__ ) . 'templates'. DIRECTORY_SEPARATOR .'transparent-checkout.php';
	}

	public function validate_fields() {

		global $woocommerce, $post, $moipAssinaturas;

		$holder_name        = $_POST['holder_name'];
        $number             = $_POST['number'];
        $expiration_month   = $_POST['expiration_month'];
        $expiration_year    = substr( $_POST['expiration_year'], 2 );

        $error_message = '';

        if (!strlen($holder_name) >= 1) {
        	$error_message .= '<b>Nome do Titular</b> é obrigatório.<br/>';
        }
        if (!strlen($number) >= 1) {
        	$error_message .= '<b>Número do Cartão</b> é obrigatório.<br/>';
        }
        if (!strlen($expiration_month) >= 1) {
        	$error_message .= '<b>Expira em: (Mês)</b> é obrigatório.<br/>';
        }
        if (!strlen($expiration_year) >= 1) {
        	$error_message .= '<b>Expira em: (Ano)</b> é obrigatório.<br/>';
        }

    	// $moip_assinaturas = MoipAssinaturas::get_instance();
    	// $resp = $moip_assinaturas->validate_moip($_POST);

        $resp = $moipAssinaturas->validate_moip($_POST);

    	foreach ($resp as $item => $values) {
    		if (isset($values->errors)) {
    			foreach ($values->errors as $error) {
    				$error_message .= '<b>Moip: </b>' . $error->description . ' (<b>'. $error->code .'</b>)<br/>'; 
    			}
    		}
    	}

    	if (strlen($error_message) >= 1) {
        	wc_add_notice( $error_message, 'error' );
			return false;
        }

        return true;
	}
}

?>