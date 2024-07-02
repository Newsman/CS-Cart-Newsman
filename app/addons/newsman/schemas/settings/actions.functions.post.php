<?php

function fn_settings_actions_addons_newsman_newsman_userid($new_value, $old_value)
{
	if (empty($new_value))
	{
		$new_value = $old_value;
		return false;
	}

	return true;
}

function fn_settings_actions_addons_newsman_newsman_apikey($new_value, $old_value)
{
	if (empty($new_value))
	{
		$new_value = $old_value;
		return false;
	}

	return true;
}
?>