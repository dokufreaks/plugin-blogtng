/**
 * Javascript for DokuWiki Plugin BlogTNG
 */

blogtng = {

    validate_attach: function(obj) {
        if(!obj) return;
        addEvent(obj, 'click', function() { return blogtng.validate(); });
    },

    validate: function() {
        var inputs = new Array(
                        'blogtng__comment_name', 
                        'blogtng__comment_mail', 
                        'wiki__text');

        for(var i = 0; i < inputs.length; i++) {
            var input = $(inputs[i]);
            if(input) {
                if(!input.value) {
                    input.className = 'edit error';
                    input.focus();
                    return false;
                } else {
                    input.className = 'edit';
                }
            }
        }
        return true;
    },

    preview_attach: function(obj, preview) {
        if(!obj) return;
        addEvent(obj, 'click', function() { return blogtng.preview(preview); });
    },

    preview: function(obj) {
        if(!obj) return;
        if(!blogtng.validate()) return false;

        var ajax = new sack(DOKU_BASE+'lib/plugins/blogtng/ajax/preview.php');
        ajax_qsearch.sack.AjaxFailedAlert = ''; 
        ajax_qsearch.sack.encodeURIString = false;

        // define callback
        ajax.onCompletion = function(){
            var data = this.response;
            if(data === '') return;
            obj.style.visibility = 'hidden';
            obj.innerHTML = data;
            obj.style.visibility = 'visible';
        };  

        var name = $('blogtng__comment_name').value;
        var mail = $('blogtng__comment_mail').value;
        var web  = $('blogtng__comment_web').value;
        var text = $('wiki__text').value;

        ajax.runAJAX('name=' + name + '&mail=' + mail + '&web=' + web + '&text=' + text);
        return false;
    }
};

addInitEvent(function() {
    blogtng.validate_attach($('blogtng__comment_submit'));
    blogtng.preview_attach($('blogtng__preview_submit'), $('blogtng__ajax_preview'));
});
// vim:ts=4:sw=4:et:enc=utf-8:
