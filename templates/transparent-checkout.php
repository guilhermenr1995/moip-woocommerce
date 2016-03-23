<?php
/**
 * Transparent Checkout template.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="woocommerce-tabs moip-assinaturas">
	<p class="form-row form-row">
		<label for="holder_name">Nome do Titular <abbr class="required" title="obrigatório">*</abbr></label>
		<input type="text" class="input-text validate-required" name="holder_name"  id="holder_name" placeholder="Nome do Titular" />
	</p>
	<p class="form-row form-row">
		<label for="number">Número do Cartão <abbr class="required" title="obrigatório">*</abbr></label>
		<input type="text" class="input-text validate-required" name="number" id="number" placeholder="Número do Cartão" />
	</p>
	<p class="form-row form-row" style="width:50%; float: left;">
		<label for="expiration_month">Expira em: (Mês) <abbr class="required" title="obrigatório">*</abbr></label>
		<select name="expiration_month" class="validate-required" id="expiration_month" style="width: 94%; border: 2px solid; border-color: #bbb3b9 #c7c1c6 #c7c1c6;">
			<option value="">Selecione</option>
			<?php
				for ( $expiration_month = 1; $expiration_month <= 12; $expiration_month++ ) {
					echo sprintf( '<option value="%1$s">%1$s</option>', zeroise( $expiration_month, 2 ) );
				}
			?>
		</select>
	</p>
	<p class="form-row form-row" style="width:50%; float: left;">
		<label for="expiration_year">Expira em: (Ano) <abbr class="required" title="obrigatório">*</abbr></label>
		<select name="expiration_year" class="validate-required" id="expiration_year" style="width: 100%; border: 2px solid; border-color: #bbb3b9 #c7c1c6 #c7c1c6;">
			<option value="">Selecione</option>
			<?php
				for ( $expiration_year = date( 'Y' ); $expiration_year < ( date( 'Y' ) + 15 ); $expiration_year++ ) {
					echo sprintf( '<option value="%1$s">%1$s</option>', $expiration_year );
				}
			?>
		</select>
	</p>
	<div style="clear:both"></div>
</div>