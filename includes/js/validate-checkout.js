$ = jQuery;
$( function () {

	$(document).on("change, focusout",".moip-assinaturas input, .moip-assinaturas select", function () {
        if ($(this).val() == '') {
			$(this).css('border-color','#b81c23');
		} else {
			$(this).css('border-color','#bbb3b9 #c7c1c6 #c7c1c6');
		}
    });

	$(document).on("keyup",".moip-assinaturas input[name=number]", function () { 
	    this.value = this.value.replace(/[^0-9]/g,''); 
	});

	$('.checkout.woocommerce-checkout').submit( function (e) {
		if ($('.moip-assinaturas input, .moip-assinaturas select').val() == '') {
			$('.moip-assinaturas input, .moip-assinaturas select').css('border-color','#b81c23');
		} else {
			$('.moip-assinaturas input, .moip-assinaturas select').css('border-color','#bbb3b9 #c7c1c6 #c7c1c6');
		}
	});
});