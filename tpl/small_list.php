<?php
/**
 * small list template
 *
 * This template is used by the <blog list> syntax and can be chosen
 * using the 'tpl' attribute. It is used to display a single entry in
 * the list and is called multiple times (once for each shown entry)
 *
 * This example shows only entry abstracts with comment numbers
 */
?>
<h1>
<?php $entry->tpl_title()?>
&nbsp;&middot;
<?php $entry->tpl_commentcount('(%d Comments)','(%d Comment)', '(%d Comments)', false)?>
</h1>

<p>
<?php $entry->tpl_abstract(50)?><br />
<?php $entry->tpl_permalink('read more…')?>
</p>

