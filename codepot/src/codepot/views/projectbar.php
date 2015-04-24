<div id="codepot-projectbar" class="projectbar">

<script type="text/javascript">
$(function() {
	$("#codepot-projectbar .button").button();
});
</script>

<?php
function show_projectbar ($con, $banner, $page, $ctxmenuitems)
{
	print "<div class='title'>";

	$type = $page['type'];
	$id = $page['id'];

	if (isset($banner))
	{
		print htmlspecialchars($banner);
	}
	else if ($type == 'project')
	{
		$project = $page[$type];

		if ($project->name == '')
			print $project->id;
		else if (strcasecmp ($project->name, $project->id) == 0)
			print htmlspecialchars($project->name);
		else
			print htmlspecialchars($project->name) . " ({$project->id})";
	}
	else if ($type == 'site')
	{
		$site = $page[$type];
		print htmlspecialchars($site->name);
	}
	else if ($type == 'user')
	{
		$user = $page[$type];
		print htmlspecialchars($user->id);
	}
	else print htmlspecialchars(CODEPOT_DEFAULT_SITE_NAME);

	print "</div>";

	print '<div class="ctxmenu">';
	if ($ctxmenuitems !== NULL && count($ctxmenuitems) > 0)
	{
		foreach ($ctxmenuitems as $item)
		{
			$extra = (count($item) >= 3)? "id='{$item[2]}'": '';
			print anchor ($item[0], $item[1], $extra);
		}
	}
	else print '&nbsp;';
	print '</div>';

	print '<div class="fixedmenu">';

	if ($type == 'project' && isset($project))
	{
		$menuitems = array (
			array ("project/home/{$project->id}", $con->lang->line('Overview')),
			//array ("project/home/{$project->id}", '<i class="fa fa-code fa-2x"></i>'),
			array ("wiki/home/{$project->id}", $con->lang->line('Wiki')),
			array ("issue/home/{$project->id}", $con->lang->line('Issues')),
			array ("code/home/{$project->id}", $con->lang->line('Code')),
			array ("file/home/{$project->id}", $con->lang->line('Files')),
			array ("graph/home/{$project->id}", $con->lang->line('Graphs'))
		);

		foreach ($menuitems as $item)
		{
			$menuid = substr ($item[0], 0, strpos($item[0], '/'));
			$extra = ($menuid == $id)? 'class="selected button"': 'class="button"';
			$menulink = $item[0];

			print anchor ($menulink, $item[1], $extra);
		}
	}
	else if ($type == 'site')
	{
		$menuitems = array (
			array ("site/catalog", $con->lang->line('Sites')),
			array ("site/log", $con->lang->line('Log'))
		);

		foreach ($menuitems as $item)
		{
			$menuid = substr ($item[0], strpos($item[0], '/') + 1);
			$extra = ($menuid == $id)? 'class="selected button"': 'class="button"';
			$menulink = $item[0];

			print anchor ($menulink, $item[1], $extra);
		}
	}
	else if ($type == 'user')
	{
		$menuitems = array (
			array ("user/home", $con->lang->line('Overview')),
			array ("user/log", $con->lang->line('Log')),
			array ("user/settings", $con->lang->line('Settings'))
		);

		foreach ($menuitems as $item)
		{
			$menuid = substr ($item[0], strpos($item[0], '/') + 1);
			$extra = ($menuid == $id)? 'class="selected button"': 'class="button"';
			$menulink = $item[0];

			print anchor ($menulink, $item[1], $extra);
		}
	}
	else print '&nbsp;';

	print '</div>';
}

show_projectbar ($this, $banner, $page, $ctxmenuitems);
?>

</div>
