var WoocommerCheckout = {
    form: null,

    init: function () {
        this.form = $j('form.checkout');
		
		if (!this.form.length) {
			this.form = jQuery('.wc-block-checkout__form');
		}
		
        if (!this.form.length) {
            return;
        }

        var _this = this;
        this.form.on({
            checkout_place_order: function () {
                var isValid = _this.validate();
                if (!isValid && !$j('html, body').is(':animated')) {
                    $j('html, body').animate({
                        scrollTop: (IsraelPostCommon.additonalBlock.offset().top - 100)
                    }, 700);
                }

                return isValid;
            }
        });
		
		jQuery( ".wc-block-checkout__form" ).on( "click", ".wc-block-components-checkout-place-order-button", function(){
			var isValid = _this.validate();
			if( !isValid && !jQuery( 'html, body' ).is( ':animated' ) ){
				jQuery( 'html, body' ).animate({
					scrollTop: ( jQuery('#israelpost-additional').offset().top - 100 )
				}, 700);
			}
			
			return isValid;
		});
    },

    validate: function () {
        return IsraelPost.validate();
    }
}