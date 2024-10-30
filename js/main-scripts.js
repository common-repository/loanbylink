jQuery(document).ready(function () {
    if (jQuery('#provema-loan-status').length) {
        setInterval(function () {
            check_provema_status();
        }, 10000);
    }
});

function check_provema_status() {
    var endpoitUrl = jQuery('#provema-loan-status').data('api-url');
    var shopKey = jQuery('#provema-loan-status').data('shop-key');
    var shopSecret = jQuery('#provema-loan-status').data('shop-secret');
    var loanId = jQuery('#provema-loan-status').data('loan-id');
    var orderId = jQuery('#provema-loan-status').data('order-id');

    //jQuery('#provema-loan-status img').click(function (e) {
    //e.preventDefault();
    jQuery.ajax({
        type: 'post',
        url: '/woocommerce/?wc-ajax=check_provema_status',
        data: {endpoitUrl: endpoitUrl, shopKey: shopKey, shopSecret: shopSecret, loanId: loanId, orderId: orderId},
        success: function (response) {
            var loanStatusName = jQuery('#loan-status-name').text();
            var obj = jQuery.parseJSON(response);
            if (obj.loanStatusId == '-1' || obj.loanStatusId == '2') {
                jQuery('#ajax-loader').hide();
            }
            if (loanStatusName !== obj.loanStatusName) {
                jQuery('#loan-status-name').text(obj.loanStatusName);
            }
        }
    });
}