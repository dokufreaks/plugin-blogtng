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
                        'blogtng__comment_web', 
                        'wiki__text');

        for(var i = 0; i < inputs.length; i++) {
            var input = $(inputs[i]);
            if(input) {
                if(!input.value) {
                    input.className = input.ClassName + ' error';
                    input.focus();
                    return false;
                }
            }
        }
    },

    preview_attach: function(obj) {
        if(!obj) return;
    },

    preview: function() {
    }
};

addInitEvent(function() {
    blogtng.validate_attach($('blogtng__comment_submit'));
    blogtng.preview_attach($('blogtng__preview_submit'));
});
// vim:ts=4:sw=4:et:enc=utf-8:
