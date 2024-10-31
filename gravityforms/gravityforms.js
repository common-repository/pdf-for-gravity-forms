(function($) {
    "use strict";
    $( document ).ready( function () { 
        $("body").on("click",".gfpdf-re-generate",function(e){
            e.preventDefault();
            var entry_id = $(this).data("id");
            var form_id = $(this).data("form_id");
            var data = {
                'action': 'pdfbuilder_gf_re_generate',
                'entry_id': entry_id,
                'form_id': form_id,
                'nonce': pdfbuilder_gravityforms.nonce
            };
            $(".gfpdf-re-generate").html("Loading...");
            $.post(ajaxurl, data, function(response) {
                location.reload(true);
            });
        })
    })
})(jQuery);