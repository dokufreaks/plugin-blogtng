/**
 * Javascript for DokuWiki Plugin BlogTNG
 */
var blogtng = {

    /**
     * Attach the validation checking to the comment form
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    validate_attach: function(obj) {
        if(!obj) return;
        addEvent(obj, 'click', function() { return blogtng.validate(); });
    },

    /**
     * Validates the comment form inputs and highlights
     * missing fields on client side
     *
     * @author Michael Klier <chi@chimeric.de>
     */
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

    /**
     * Attach the AJAX preview action to the comment form
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    preview_attach: function(obj, wrap, previewid) {
        if(!obj) return;
        if(!wrap) return;

        addEvent(obj, 'click', function(e) {
            blogtng.preview(wrap,previewid);
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    },

    /**
     * Uses AJAX to render and preview the comment before submitting
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    preview: function(wrap,previewid) {
        if(!blogtng.validate()) return false;

        var preview = $(previewid);
        if(!preview){
            if(!wrap) return false;

            preview = document.createElement('div');
            preview.id = previewid;
            wrap.appendChild(preview);
        }

        preview.innerHTML = '<img src="'+DOKU_BASE+'/lib/images/throbber.gif" />';

        var ajax = new sack(DOKU_BASE + 'lib/exe/ajax.php');
        ajax.AjaxFailedAlert = '';
        ajax.encodeURIString = false;
        ajax.setVar('call', 'blogtng__comment_preview');

        // define callback
        ajax.onCompletion = function(){
            var data = this.response;
            if(data === '') return;
            preview.innerHTML = data;
        };

        if($('blogtng__comment_name'))
            ajax.setVar('name',$('blogtng__comment_name').value);
        if($('blogtng__comment_mail'))
            ajax.setVar('mail',$('blogtng__comment_mail').value);
        if($('blogtng__comment_web'))
            ajax.setVar('web',$('blogtng__comment_web').value);
        if($('wiki__text'))
            ajax.setVar('text',$('wiki__text').value);
        ajax.runAJAX();
        return false;
    },

    /**
     * Attach the reply action to the comment numbers and add tooltip
     * previews to reply syntax markers.
     *
     * @author Gina Haeussge <osd@foosel.net>
     */
    reply_attach: function() {
        // attach reply action
        var objs = getElementsByClass('blogtng_num', null, 'a');
        for (var i = 0; i < objs.length; i++) {
            objs[i].title = LANG['plugins']['blogtng']['reply'];
            addEvent(objs[i], 'click', function(e) {
                insertAtCarret('wiki__text','@#'+this.href.substring(this.href.lastIndexOf('#')+'#comment_'.length)+': ');

                e.preventDefault();
                e.stopPropagation();
                return false;
            });
        }

        // make "footnotes" from comment references
        objs = getElementsByClass('blogtng_reply', null, 'a');
        for (var i = 0; i < objs.length; i++) {
            addEvent(objs[i], 'mouseover', function(e) {
                commentPopup(e, this.href.substring(this.href.lastIndexOf('#')+'#comment_'.length));
            });
        }
    },

    insert_checkall_checkbox: function() {
        if(!$('blogtng__admin')) return;
        var th = $('blogtng__admin_checkall_th');
        if(th) {
            var html_checkbox = '<input type="checkbox" id="blogtng__admin_checkall" />';
            th.innerHTML = html_checkbox;
            var checkbox = $('blogtng__admin_checkall');
            addEvent(checkbox, 'click', function(e) {
                blogtng.checkall();
            });
        }
    },

    checkall: function() {
        objs = getElementsByClass('comment_cid', null, 'input');
        if(objs) {
            var num = objs.length;
            for(var i=0;i<num;i++) {
                if(objs[i].checked) {
                    objs[i].checked = false;
                } else {
                    objs[i].checked = true;
                }
            }
        }
    }
};

/**
 * Display an insitu comment popup. Heavily copied from the footnote insitu
 * popup.
 *
 * FIXME: make the footnote one wrap a generic function to define popups?
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Chris Smith <chris@jalakai.co.uk>
 * @author Gina Haeussge <gina@foosel.net>
 */
function commentPopup(e, id){
    var obj = e.target;

    // get or create the comment popup div
    var comment_div = $('insitu__comment');
    if(!comment_div){
        comment_div = document.createElement('div');
        comment_div.id        = 'insitu__comment';
        comment_div.className = 'insitu-footnote JSpopup dokuwiki';

        // autoclose on mouseout - ignoring bubbled up events
        addEvent(comment_div,'mouseout',function(e){
            if(e.target != comment_div){
                e.stopPropagation();
                return;
            }
            // check if the element was really left
            if(e.pageX){        // Mozilla
                var bx1 = findPosX(comment_div);
                var bx2 = bx1 + comment_div.offsetWidth;
                var by1 = findPosY(comment_div);
                var by2 = by1 + comment_div.offsetHeight;
                var x = e.pageX;
                var y = e.pageY;
                if(x > bx1 && x < bx2 && y > by1 && y < by2){
                    // we're still inside boundaries
                    e.stopPropagation();
                    return;
                }
            }else{              // IE
                if(e.offsetX > 0 && e.offsetX < comment_div.offsetWidth-1 &&
                   e.offsetY > 0 && e.offsetY < comment_div.offsetHeight-1){
                    // we're still inside boundaries
                    e.stopPropagation();
                    return;
                }
            }
            // okay, hide it
            comment_div.style.display='none';
        });
        document.body.appendChild(comment_div);
    }

    // locate the comment anchor element
    var a = $( "comment_"+id );
    if (!a){ return; }

    // anchor parent is the footnote container, get its innerHTML
    var content = new String (a.innerHTML);

    // prefix ids on any elements with "insitu__" to ensure they remain unique
    content = content.replace(/\bid=\"(.*?)\"/gi,'id="insitu__$1');

    // now put the content into the wrapper
    comment_div.innerHTML = content;

    // position the div and make it visible
    var x; var y;
    if(e.pageX){        // Mozilla
        x = e.pageX;
        y = e.pageY;
    }else{              // IE
        x = e.offsetX;
        y = e.offsetY;
    }
    comment_div.style.position = 'absolute';
    comment_div.style.left = (x+2)+'px';
    comment_div.style.top  = (y+2)+'px';
    comment_div.style.display = '';
}

/**
 * Attach events
 */
addInitEvent(function() {
    blogtng.validate_attach($('blogtng__comment_submit'));
    blogtng.preview_attach($('blogtng__preview_submit'),$('blogtng__comment_form_wrap'),'blogtng__comment_preview');
    blogtng.reply_attach();
    blogtng.insert_checkall_checkbox();
});
// vim:ts=4:sw=4:et:
