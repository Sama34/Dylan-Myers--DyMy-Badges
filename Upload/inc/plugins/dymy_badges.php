<?php
/**
 * DyMy Badges
 * Copyright 2012 Dylan Myers
 * Author Dylan Myers
 * http://mybb.dylanspcs.com
 * MyBB 1.6x compatible
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
// Basically, when we include this from class_plugins.php we can do stuff in init.php, which is before we cache our templates
// So we won't need an extra call to cache it.

/*if(my_strpos($_SERVER['PHP_SELF'], 'index.php') || my_strpos($_SERVER['PHP_SELF'], 'userdisplay.php') || my_strpos($_SERVER['PHP_SELF'], 'showthread.php') || my_strpos($_SERVER['PHP_SELF'], 'portal.php'))
{
	global $templatelist;
	$templ_add = "";
	if(isset($templatelist) && isset($templ_add))
	{
		$templatelist .= ',';
	}
	$templatelist .= $templ_add;
}*/

$plugins->add_hook("postbit_prev", "dymy_badges_postbit");
$plugins->add_hook("postbit_announcement", "dymy_badges_postbit");
$plugins->add_hook("postbit", "dymy_badges_postbit");
$plugins->add_hook("member_profile_end", "dymy_badges_profile");

$plugins->add_hook("admin_user_menu", "dymy_badges_admin_nav");
$plugins->add_hook("admin_user_permissions", "dymy_badges_admin_permissions");
$plugins->add_hook("admin_load", "dymy_badges_admin");
$plugins->add_hook("admin_user_action_handler", "dymy_badges_action_handler");
//$plugins->add_hook("admin_config_plugins_activate_commit", "dymy_badges_key");


function dymy_badges_info()
{
	return array(
		"name"					=> "DyMy Badges",
		"description"			=> "Place badges based on userfields on a user's profile and postbit",
		"website"				=> "http://mybb.dylanspcs.com",
		"author"				=> "Dylan Myers",
		"authorsite"			=> "mailto:dylanspcs@gmail.com",
		"version"				=> "0.1.6",
		"guid"					=> "b5dc51f3cba352f4bcb56523d483a7fc",
		"compatibility" 		=> "16*",
	);
}

function dymy_badges_install()
{
	global $db;
	
	$db->write_query("CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."dymy_badges (
		badge_id INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		badge_name VARCHAR(50),
		badge_img VARCHAR(50),
		badge_field INT(10),
		badge_url VARCHAR(100)
	)");
}

function dymy_badges_is_installed()
{
	global $db;
	
	if($db->table_exists("dymy_badges"))
	{
		return true;
	}
	
	return false;
}

function dymy_badges_uninstall()
{
	global $db;
	
	$db->write_query("DROP TABLE ".TABLE_PREFIX."dymy_badges");
}

function dymy_badges_activate()
{
	require MYBB_ROOT."inc/adminfunctions_templates.php";

	find_replace_templatesets("postbit", '#(.*?)'.preg_quote('{$post[\'onlinestatus\']}').'#', '$1{$post[\'onlinestatus\']} {$post[\'badges\']}');
	find_replace_templatesets("postbit_classic", '#(.*?)'.preg_quote('{$post[\'onlinestatus\']}').'#', '$1{$post[\'onlinestatus\']} {$post[\'badges\']}');
	find_replace_templatesets("member_profile", '#(.*?)'.preg_quote('{$formattedname}</strong>').'#', '$1{$formattedname} {$memprofile[\'badges\']}</strong>');
	
	change_admin_permission('forum', 'dymy_badges');
}

function dymy_badges_deactivate()
{
	require MYBB_ROOT."inc/adminfunctions_templates.php";

	find_replace_templatesets("postbit", '#'.preg_quote(' {$post[\'badges\']}').'#', '', 0);
	find_replace_templatesets("postbit_classic", '#'.preg_quote(' {$post[\'badges\']}').'#', '', 0);
	find_replace_templatesets("member_profile", '#'.preg_quote(' {$memprofile[\'badges\']}').'#', '', 0);
	
	change_admin_permission('forum', 'dymy_badges', -1);
}

function dymy_badges_postbit(&$post)
{
	global $db;
	
	$query = $db->simple_select("userfields", "*", "ufid = {$post['uid']}");
	$uf = $db->fetch_array($query);
	$db->free_result($query);
	
	$query = $db->simple_select("dymy_badges", "*");
	while($badge = $db->fetch_array($query))
	{
		if(empty($uf['fid'.$badge['badge_field']]))
		{
			continue;
		}
		$img = "images/badges/".$badge['badge_img'];
		if(!empty($badge['badge_url']))
		{
			$val = $uf['fid'.$badge['badge_field']];
			$badge['badge_url'] = str_ireplace("{field}", $val, $badge['badge_url']);
			$post['badges'] .= '<a href="'.$badge['badge_url'].'"><img src="'.$img.'" title="Badge '.$badge['badge_id'].'" alt="Badge #'.$badge['badge_id'].'" /></a>';
		}
		else
		{
			$post['badges'] .= '<img src="'.$img.'" title="Badge '.$badge['badge_id'].'" alt="Badge #'.$badge['badge_id'].'" />';
		}
	}
	$db->free_result($query);
}

function dymy_badges_profile()
{
	global $db, $memprofile;
	
	$query = $db->simple_select("userfields", "*", "ufid = {$memprofile['uid']}");
	$uf = $db->fetch_array($query);
	$db->free_result($query);
	
	$query = $db->simple_select("dymy_badges", "*");
	while($badge = $db->fetch_array($query))
	{
		if(empty($uf['fid'.$badge['badge_field']]))
		{
			continue;
		}
		$img = "images/badges/".$badge['badge_img'];
		if(!empty($badge['badge_url']))
		{
			$val = $uf['fid'.$badge['badge_field']];
			$badge['badge_url'] = str_ireplace("{field}", $val, $badge['badge_url']);
			$memprofile['badges'] .= '<a href="'.$badge['badge_url'].'"><img src="'.$img.'" title="Badge '.$badge['badge_id'].'" alt="Badge #'.$badge['badge_id'].'" /></a>';
		}
		else
		{
			$memprofile['badges'] .= '<img src="'.$img.'" title="Badge '.$badge['badge_id'].'" alt="Badge #'.$badge['badge_id'].'" />';
		}
	}
	$db->free_result($query);
}

function dymy_badges_admin_nav(&$sub_menu)
{
	end($sub_menu);
	$key = (key($sub_menu))+10;
	
	if(!$key)
	{
		$key = '50';
	}
	
	$sub_menu[$key] = array('id' => 'dymy_badges', 'title' => 'DyMy Badges', 'link' => "index.php?module=user-dymy_badges");
}

function dymy_badges_admin_permissions(&$admin_permissions)
{
	$admin_permissions['dymy_badges'] = "Can manage DyMy Badges?";
}

function dymy_badges_admin()
{
	global $mybb, $db, $page, $lang;
	
	if($page->active_action != "dymy_badges")
	{
		return;
	}
	
	$page->add_breadcrumb_item("DyMy Badges Administration");
	
	if(!$mybb->input['action'])
	{
		$page->output_header("DyMy Badges");
		
		$sub_tabs['dymy_badges'] = array(
			'title'			=> "View Badges",
			'link'			=> "index.php?module=user-dymy_badges",
			'description'	=> "View the custom user badges you've added"
		);
		
		$sub_tabs['dymy_badges_add'] = array(
			'title'			=> "Add Badge",
			'link'			=> "index.php?module=user-dymy_badges&amp;action=add",
			'description'	=> "Add a new custom user badge"
		);
		
		$page->output_nav_tabs($sub_tabs, 'custom_profile_fields');
		
		$table = new Table;
		$table->construct_header("Badge Name");
		$table->construct_header("Badge ID", array("class" => "align_center"));
		$table->construct_header("Badge Image", array("class" => "align_center"));
		$table->construct_header("Badge URL", array("class" => "align_center"));
		$table->construct_header("Badge Userfield", array("class" => "align_center"));
		$table->construct_header("Controls", array("class" => "align_center"));
		
		$query = $db->simple_select("dymy_badges", "*", "", array('order_by' => 'badge_id'));
		while($badge = $db->fetch_array($query))
		{
			$table->construct_cell($badge['badge_name']);
			$table->construct_cell($badge['badge_id'], array("class" => "align_center", 'width' => '5%'));
			$table->construct_cell($badge['badge_img'], array("class" => "align_center", 'width' => '10%'));
			$table->construct_cell($badge['badge_url'], array("class" => "align_center, 'width' => '10%'"));
			$table->construct_cell($badge['badge_field'], array("class" => "align_center", 'width' => '10%'));
			
			$popup = new PopupMenu("badge_{$badge['badge_id']}", $lang->options);
			$popup->add_item("Edit", "index.php?module=user-dymy_badges&amp;action=edit&amp;bid={$badge['badge_id']}");
			$popup->add_item("Delete", "index.php?module=user-dymy_badges&amp;action=delete&amp;bid={$badge['badge_id']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, 'Delete this badge?')");
			$table->construct_cell($popup->fetch(), array("class" => "align_center", 'width' => '20%'));
			$table->construct_row();
		}
		$db->free_result($query);
		
		if($table->num_rows() == 0)
		{
			$table->construct_cell("No badges defined", array('colspan' => 6));
			$table->construct_row();
		}
		
		$table->output("DyMy Badges: Custom user badges");
		
		$page->output_footer();
	}
	
	if($mybb->input['action'] == "edit")
	{
		$query = $db->simple_select("dymy_badges", "*", "badge_id = '".intval($mybb->input['bid'])."'");
		$badge = $db->fetch_array($query);
		$db->free_result($query);
		
		if(!$badge['badge_id'])
		{
			flash_message("Invalid badge ID.", 'error');
			admin_redirect("index.php?module=user-dymy_badges");
		}
		
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['name']))
			{
				$errors[] = "Missing badge name.";
			}
	
			if(!trim($mybb->input['image']))
			{
				$errors[] = "Missing badge image.";
			}
	
			if(!trim($mybb->input['field']))
			{
				$errors[] = "Missing badge field number.";
			}
			
			if(!$errors)
			{
				$badge = array(
					"badge_name" => $db->escape_string($mybb->input['name']),
					"badge_img" => $db->escape_string($mybb->input['image']),
					"badge_field" => $db->escape_string($mybb->input['field']),
					"badge_url" => $db->escape_string($mybb->input['url']),
				);
				
				$db->update_query("dymy_badges", $badge, "badge_id = '".intval($mybb->input['bid'])."'");
				
				// Log admin action
				log_admin_action($badge['badge_id'], $mybb->input['name']);
	
				flash_message("Badge update saved", 'success');
				admin_redirect("index.php?module=user-dymy_badges");
			}
		}
		
		$page->add_breadcrumb_item("Edit Badges");
		$page->output_header("DyMy Badges: Edit badge");
		
		$sub_tabs['dymy_badges_edit'] = array(
			'title'			=> "Edit Badge",
			'link'			=> "index.php?module=user-dymy_badges&amp;action=edit&amp;bid=".intval($mybb->input['bid']),
			'description'	=> "Edit a custom user badge"
		);
		
		$page->output_nav_tabs($sub_tabs, 'dymy_badges_edit');
		$form = new Form("index.php?module=config-profile_fields&amp;action=edit", "post", "edit");
		
		echo $form->generate_hidden_field("bid", $badge['badge_id']);
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$mybb->input['name'] = $badge['badge_name'];
			$mybb->input['image'] = $badge['badge_img'];
			$mybb->input['field'] = $badge['badge_field'];
			$mybb->input['url'] = $badge['badge_url'];
		}
		
		$form_container = new FormContainer("Edit user badge");
		$form_container->output_row("Name <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Badge Image <em>*</em>", "The image you want shown on the postbit, this is not resized so you need to make it the correct size!", $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
		$form_container->output_row("Userfield Number <em>*</em>", "The number of the userfield that determines if the badge shows.", $form->generate_text_box('field', $mybb->input['field'], array('id' => 'field')), 'field');
		$form_container->output_row("Badge Url", "You can place {field} in the url and it will be replaced by the value of the userfield referenced by the number above.", $form->generate_text_box('url', $mybb->input['url'], array('id' => 'url')), 'url');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Save Badge");
	
		$form->output_submit_wrapper($buttons);
		$form->end();
	
		$page->output_footer();
	}
	
	if($mybb->input['action'] == "delete")
	{
		$query = $db->simple_select("dymy_badges", "*", "badge_id='".intval($mybb->input['bid'])."'");
		$badge = $db->fetch_array($query);
		$db->free_result($query);
		
		// Does the profile field not exist?
		if(!$badge['badge_id'])
		{
			flash_message("Invalid badge ID", 'error');
			admin_redirect("index.php?module=user-dymy_badges");
		}
	
		// User clicked no
		if($mybb->input['no'])
		{
			admin_redirect("index.php?module=user-dymy_badges");
		}
	
		if($mybb->request_method == "post")
		{
			// Delete the profile field
			$db->delete_query("dymy_badges", "badge_id='{$badge['badge_id']}'");
	
			// Log admin action
			log_admin_action($badge['badge_id'], $badge['name']);
	
			flash_message("Badge successfully deleted", 'success');
			admin_redirect("index.php?module=user-dymy_badges");
		}
		else
		{
			$page->output_confirm_action("index.php?module=user-dymy_badges&amp;action=delete&amp;bid={$badge['badge_id']}", "Delete this badge?");
		}
	}
	
	if($mybb->input['action'] == "add")
	{
		if($mybb->request_method == "post")
		{
			if(!trim($mybb->input['name']))
			{
				$errors[] = "Missing badge name.";
			}
	
			if(!trim($mybb->input['image']))
			{
				$errors[] = "Missing badge image.";
			}
	
			if(!trim($mybb->input['field']))
			{
				$errors[] = "Missing badge field number.";
			}
			
			if(!$errors)
			{
				$badge = array(
					"badge_name" => $db->escape_string($mybb->input['name']),
					"badge_img" => $db->escape_string($mybb->input['image']),
					"badge_field" => $db->escape_string($mybb->input['field']),
					"badge_url" => $db->escape_string($mybb->input['url']),
				);
				
				$bid = $db->insert_query("dymy_badges", $badge);
	
				// Log admin action
				log_admin_action($bid, $mybb->input['name']);
						
				flash_message("Badge successfully added", 'success');
				admin_redirect("index.php?module=user-dymy_badges");
			}
		}
		
		$page->add_breadcrumb_item("Add new user badge");
		$page->output_header("DyMy User Badges: Add new badge");
		
		$sub_tabs['dymy_badges'] = array(
			'title'			=> "View Badges",
			'link'			=> "index.php?module=user-dymy_badges",
			'description'	=> "View the custom user badges you've added"
		);
		
		$sub_tabs['dymy_badges_add'] = array(
			'title'			=> "Add Badge",
			'link'			=> "index.php?module=user-dymy_badges&amp;action=add",
			'description'	=> "Add a new custom user badge"
		);
		
		$page->output_nav_tabs($sub_tabs, 'dymy_badges_add');
		$form = new Form("index.php?module=user-dymy_badges&amp;action=add", "post", "add");
		
		if($errors)
		{
			$page->output_inline_error($errors);
		}
		else
		{
			$mybb->input = $badge;
		}
		
		$form_container = new FormContainer("Edit user badge");
		$form_container->output_row("Name <em>*</em>", "", $form->generate_text_box('name', $mybb->input['name'], array('id' => 'name')), 'name');
		$form_container->output_row("Badge Image <em>*</em>", "The image you want shown on the postbit, this is not resized so you need to make it the correct size!", $form->generate_text_box('image', $mybb->input['image'], array('id' => 'image')), 'image');
		$form_container->output_row("Userfield Number <em>*</em>", "The number of the userfield that determines if the badge shows.", $form->generate_text_box('field', $mybb->input['field'], array('id' => 'field')), 'field');
		$form_container->output_row("Badge Url", "You can place {field} in the url and it will be replaced by the value of the userfield referenced by the number above.", $form->generate_text_box('url', $mybb->input['url'], array('id' => 'url')), 'url');
		$form_container->end();
	
		$buttons[] = $form->generate_submit_button("Save Badge");
	
		$form->output_submit_wrapper($buttons);
		$form->end();
		
		$page->output_footer();
	}
}

function dymy_badges_action_handler(&$action)
{
	$action['dymy_badges'] = array('active' => 'dymy_badges', 'file' => '');
}

?>