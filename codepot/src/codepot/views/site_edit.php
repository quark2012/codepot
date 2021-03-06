<html>

<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">

<script type="text/javascript" src="<?php print base_url_make('/js/codepot.js')?>"></script>
<link type="text/css" rel="stylesheet" href="<?php print base_url_make('/css/common.css')?>" />
<link type="text/css" rel="stylesheet" href="<?php print base_url_make('/css/site.css')?>" />
<link type="text/css" rel="stylesheet" href="<?php print base_url_make('/css/font-awesome.min.css')?>" />


<script type="text/javascript" src="<?php print base_url_make('/js/creole.js')?>"></script>

<script type="text/javascript" src="<?php print base_url_make('/js/prettify/prettify.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/prettify/lang-css.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/prettify/lang-lisp.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/prettify/lang-lua.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/prettify/lang-sql.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/prettify/lang-vb.js')?>"></script>

<script type="text/javascript" src="<?php print base_url_make('/js/jquery.min.js')?>"></script>
<script type="text/javascript" src="<?php print base_url_make('/js/jquery-ui.min.js')?>"></script>
<link type="text/css" rel="stylesheet" href="<?php print base_url_make('/css/jquery-ui.css')?>" />

<script type="text/javascript">

function preview_site_text(input_text)
{
	creole_render_wiki_with_input_text (
		input_text,
		"site_edit_text_preview", 
		"<?php print site_url()?>/site/wiki/",
		"<?php print site_url()?>/site/image/",
		true // raw
	);

	prettyPrint ();
}

$(function () {
	$("#site_edit_text_preview_button").button().click(
		function () {
			preview_site_text ($("#site_edit_text").val());
		}
	);
});
</script>

<title><?php print htmlspecialchars($site->name)?></title>
</head>

<body>

<div class="content" id="site_edit_content">

<!------------------------------------------------------------------------>

<?php $this->load->view ('taskbar'); ?>

<!------------------------------------------------------------------------>

<?php
$this->load->view (
	'projectbar',
	array (
		'banner' => $this->lang->line('Administration'),

		'page' => array (
			'type' => 'site',
			'id' => 'catalog',
			'project' => $site,
		),

		'ctxmenuitems' => array ()
	)
);
?>

<!------------------------------------------------------------------------>
<div class="mainarea" id="site_edit_mainarea">

<?php 
	if ($message != '') print "<div id='site_create_message' class='form_message'>$message</div>"; 

	$formurl = "site/{$mode}";
	if ($mode == 'update') $formurl .= '/'.$site->id;
?>

<div class='form_container'>
<?php print form_open($formurl, 'id="site_edit_form"')?>
	<div class='form_input_field'>
		<?php print form_label($this->lang->line('Language').': ', 'site_id')?>
		<?php
			$extra = ($mode == 'update')? 'readonly="readonly"': '';
			$extra .= 'maxlength="32" size="16" class="id"';
		?>

		<?php print form_input('site_id', set_value('site_id', $site->id),  $extra)
		?>
		<?php print form_error('site_id')?>
	</div>

	<div class='form_input_field'>
		<?php print form_label($this->lang->line('Name').': ', 'site_name')?>
		<?php print form_input('site_name', 
			set_value('site_name', $site->name), 
			'maxlength="80" size="40" class="name"');
		?>
		<?php print form_error('site_name')?>
	</div>

	<div class='form_input_field'>
		<?php print form_label($this->lang->line('Summary').': ', 'site_summary')?>
		<?php print form_input('site_summary', 
			set_value('site_summary', $site->summary), 
			'maxlength="100" size="50" class="summary"');
		?>
		<?php print form_error('site_summary')?>
	</div>

	<div class='form_input_label'>
		<?php print form_label($this->lang->line('Text').': ', 'site_text')?>
		<a href='#' id='site_edit_text_preview_button'><?php print $this->lang->line('Preview')?></a>
		<?php print form_error('site_text')?>
	</div>
	<div class='form_input_field'>
		<?php print form_textarea('site_text', 
			set_value('site_text', $site->text),
			'class="text" id="site_edit_text"')
		?>
	</div>
	<div id='site_edit_text_preview' class='codepot-styled-text-preview'></div>


	<?php $caption = ($mode == 'update')? $this->lang->line('Update'): $this->lang->line('Create'); ?>
	<?php print form_submit('site', $caption)?>
<?php print form_close();?>
</div> <!-- form_container -->

</div> <!-- site_edit_mainarea -->

<div class='codepot-footer-pusher'></div> <!-- for sticky footer -->

</div> <!-- site_edit_content --> 

<!---------------------------------------------------------------------------->

<?php $this->load->view ('footer'); ?>

<!---------------------------------------------------------------------------->


</body>

</html>
