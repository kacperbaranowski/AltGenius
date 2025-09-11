(function($){
$(document).on('click','.altgpt-generate',function(e){
 e.preventDefault(); var b=$(this); var id=b.data('id'); var n=b.data('nonce'); var s=b.siblings('.altgpt-status'); b.prop('disabled',true); s.text('...');
 $.post(ALTGPT.ajax,{action:ALTGPT.action,attachment_id:id,_nonce:n},function(r){ if(r.success){ b.siblings('.altgpt-alt').text(r.data.alt); s.text('OK'); } else { s.text('ERR'); } b.prop('disabled',false); });
});
})(jQuery);