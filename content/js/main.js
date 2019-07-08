$('input[placeholder], textarea[placeholder], select[placeholder]').placeholder();

$('input,textarea,select').focus(function(){
    $(this).data('placeholder',$(this).attr('placeholder'))
    $(this).attr('placeholder','');
});
$('input,textarea,select').blur(function(){
    $(this).attr('placeholder',$(this).data('placeholder'));
});




$(window).on('load', function () {
$('a[data-scroll="true"]').click(function () {
var destination = $('#request-form').offset().top;
jQuery("html:not(:animated),body:not(:animated)").animate({scrollTop: destination}, 1400);
return false;
});
});