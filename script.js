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
        jQuery(obj).on('click', function() { return blogtng.validate(); });
    },

    /**
     * Validates the comment form inputs and highlights
     * missing fields on client side
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    validate: function() {
        let inputs = ['blogtng__comment_name',
            'blogtng__comment_mail',
            'wiki__text'];

        for(let i = 0; i < inputs.length; i++) {
            let input = jQuery("#" + inputs[i]).get(0);
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

        jQuery(obj).on('click',function(e) {
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

        let preview = jQuery("#"+previewid).get(0);
        if(!preview){
            if(!wrap) return false;

            preview = document.createElement('div');
            preview.id = previewid;
            wrap.appendChild(preview);
        }

        preview.innerHTML = '<img src="'+DOKU_BASE+'/lib/images/throbber.gif" />';

        let params = {
            call:    'blogtng__comment_preview',
            tplname: jQuery('#blogtng__comment_form').data('tplname')
        };

        let $name = jQuery('#blogtng__comment_name');
        let $email = jQuery('#blogtng__comment_mail');
        let $web = jQuery('#blogtng__comment_web');
        let $text = jQuery('#wiki__text');

        if($name.length > 0)  params.name = $name.val();
        if($email.length > 0) params.mail = $email.val();
        if($web.length > 0)   params.web  = $web.val();
        if($text.length > 0)  params.text = $text.val();

        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', params,
        function(data){
            if(data === '') return;
            preview.innerHTML = data;
        });

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
        let objs = jQuery('a.blogtng_num');
        for (let i = 0; i < objs.length; i++) {
            objs[i].title = LANG['plugins']['blogtng']['reply'];
            jQuery(objs[i]).on('click', function(e) {
                insertAtCarret('wiki__text','@#'+this.href.substring(this.href.lastIndexOf('#')+'#comment_'.length)+': ');

                e.preventDefault();
                e.stopPropagation();
                return false;
            });
        }

        // make "footnotes" from comment references
        objs = jQuery('a.blogtng_reply');
        for (let i = 0; i < objs.length; i++) {
            jQuery(objs[i]).on('mouseover', function(e) {
                commentPopup(e, this.href.substring(this.href.lastIndexOf('#')+'#comment_'.length));
            });
        }
    },

    /**
     * Attach and handle the check-all checkbox.
     */
    insert_checkall_checkbox: function() {
        if(jQuery('#blogtng__admin').length === 0) return;
        let th = jQuery('#blogtng__admin_checkall_th').get(0);
        if(th) {
            th.innerHTML = '<input type="checkbox" id="blogtng__admin_checkall" />';
            let checkbox = jQuery('#blogtng__admin_checkall').get(0);
            jQuery(checkbox).on('click', function(e) {
                blogtng.checkall();
            });
        }
    },

    /**
     * Set all checkboxes to checked.
     */
    checkall: function() {
        let objs = jQuery('input.comment_cid');
        if(objs) {
            let num = objs.length;
            for(let i=0;i<num;i++) {
                objs[i].checked = !objs[i].checked;
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
    let obj = e.target;

    // get or create the comment popup div
    let comment_div = jQuery('#insitu__comment').get(0);
    if(!comment_div){
        comment_div = document.createElement('div');
        comment_div.id        = 'insitu__comment';
        comment_div.className = 'insitu-footnote JSpopup dokuwiki';

        // autoclose on mouseout - ignoring bubbled up events
        jQuery(comment_div).on('mouseout', function(e){
            if(e.target != comment_div){
                e.stopPropagation();
                return;
            }
            // check if the element was really left
            if(e.pageX){        // Mozilla
                let bx1 = findPosX(comment_div);
                let bx2 = bx1 + comment_div.offsetWidth;
                let by1 = findPosY(comment_div);
                let by2 = by1 + comment_div.offsetHeight;
                let x = e.pageX;
                let y = e.pageY;
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
    let a = jQuery("#comment_" + id).get(0);
    if (!a){ return; }

    // anchor parent is the footnote container, get its innerHTML
    let content = String(a.innerHTML);

    // prefix ids on any elements with "insitu__" to ensure they remain unique
    content = content.replace(/\bid="(.*?)"/gi,'id="insitu__$1"');

    // now put the content into the wrapper
    comment_div.innerHTML = content;
    // position the div and make it visible
    let x, y;
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
jQuery(function() {
    blogtng.validate_attach(jQuery('#blogtng__comment_submit').get(0));
    blogtng.preview_attach(jQuery('#blogtng__preview_submit').get(0),jQuery('#blogtng__comment_form_wrap').get(0),'blogtng__comment_preview');
    blogtng.reply_attach();
    blogtng.insert_checkall_checkbox();
});
