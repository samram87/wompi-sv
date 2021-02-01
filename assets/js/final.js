
jQuery(function(){
    jQuery( 'body' )
    .on( 'updated_checkout', function() {
          usingGateway();

        jQuery('input[name="payment_method"]').change(function(){
            console.log("payment method changed");
              usingGateway();

        });
    });
});


function usingGateway(){
    console.log(jQuery("input[name='payment_method']:checked").val());
    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'wompi_payment'){
        new Card({
            form: document.querySelector('.form-container'),
            container: '.card-wrapper'
        });
    }
}   