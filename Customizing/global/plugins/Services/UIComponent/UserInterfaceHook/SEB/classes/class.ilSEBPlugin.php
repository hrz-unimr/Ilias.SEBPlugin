<?php

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Example user interface plugin
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 *
 */
class ilSEBPlugin extends ilUserInterfaceHookPlugin
{
	const NOT_A_SEB_REQUEST = 0;
	const SEB_REQUEST = 1;
	const ROLES_NONE = 0;
	const ROLES_ALL = 1;
	const BROWSER_KIOSK_ALL = 0;
	const BROWSER_KIOSK_SEB = 1;
	const CACHE = "SEB_CONFIG_CACHE";
	
	public static function _isAPCInstalled() {
		//$ret = return 1;
		return (function_exists("apc_store") && function_exists("apc_fetch"));
	}
	
	public static function _flushAPC() {
		if (ilSEBPlugin::_isAPCInstalled() && apc_exists(ilSEBPlugin::CACHE))  {
			apc_delete(ilSEBPlugin::CACHE);
		}
	}
	
	function getPluginName() {
		return "SEB";
	}
}

?>
