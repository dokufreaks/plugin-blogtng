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
        if(!preview) return;

        addEvent(obj, 'click', function(e) {
            blogtng.preview(preview);
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    },

    preview: function(obj) {
        if(!obj) return;
        if(!blogtng.validate()) return false;

        obj.innerHTML = '<img src="'+DOKU_BASE+'/lib/images/throbber.gif" />';

        var ajax = new sack(DOKU_BASE+'lib/plugins/blogtng/ajax/preview.php');
        ajax.AjaxFailedAlert = '';
        ajax.encodeURIString = false;

        // define callback
        ajax.onCompletion = function(){
            var data = this.response;
            if(data === '') return;
            obj.innerHTML = data;
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

    reply_attach: function() {
        objs = getElementsByClass('blogtng_num', null, 'a');
        for (var i = 0; i < objs.length; i++) {
            addEvent(objs[i], 'click', function(e) {
                insertAtCarret('wiki__text', '@#'+this.href.substring(this.href.lastIndexOf('#')+'#comment_'.length)+': ');
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
        }
    }
};

/*
 * Insert the given value at the current cursor position
 *
 * @see http://www.alexking.org/index.php?content=software/javascript/content.php
 *
 * FIXME: Is there a way to include this function from edit.js instead?
 */
function insertAtCarret(edid,value){
  var field = document.getElementById(edid);

  //IE support
  if (document.selection) {
    field.focus();
    sel = document.selection.createRange();
    sel.text = value;

  //MOZILLA/NETSCAPE support
  }else if (field.selectionStart || field.selectionStart == '0') {
    var startPos  = field.selectionStart;
    var endPos    = field.selectionEnd;
    var scrollTop = field.scrollTop;
    field.value = field.value.substring(0, startPos) +
                  value +
                  field.value.substring(endPos, field.value.length);

    field.focus();
    var cPos=startPos+(value.length);
    field.selectionStart=cPos;
    field.selectionEnd=cPos;
    field.scrollTop=scrollTop;
  } else {
    field.value += "\n"+value;
  }
  // reposition cursor if possible
  if (field.createTextRange){
    field.caretPos = document.selection.createRange().duplicate();
  }
  if(value){
    window.textChanged = true;
    summaryCheck();
  }
}

addInitEvent(function() {
    blogtng.validate_attach($('blogtng__comment_submit'));
    blogtng.preview_attach($('blogtng__preview_submit'), $('blogtng__comment_preview'));
    blogtng.reply_attach();
});
// vim:ts=4:sw=4:et:enc=utf-8:
