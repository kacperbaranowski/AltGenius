(function($){
$(document).on('click','.altgpt-generate',function(e){
 e.preventDefault(); var b=$(this); var id=b.data('id'); var n=b.data('nonce'); var s=b.siblings('.altgpt-status'); b.prop('disabled',true); s.text('...');
 $.post(ALTGPT.ajax,{action:ALTGPT.action,attachment_id:id,_nonce:n},function(r){
   if (r && r.data && r.data.request) {
     console.log('ALTGPT request to OpenAI:', r.data.request);
   }
   if(r.success){ b.siblings('.altgpt-alt').text(r.data.alt); s.text('OK'); }
   else {
     var msg = (r && r.data && r.data.message) ? (': ' + r.data.message) : '';
     s.text('ERR' + msg);
     if (r && r.data && r.data.request) console.warn('ALTGPT request (error):', r.data.request);
   }
   b.prop('disabled',false);
 });
});
})(jQuery);
