<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");
include_once("./Services/Init/classes/class.ilStartUpGUI.php");
include_once("class.ilSEBPlugin.php");
//include_once("./Services/JSON/classes/class.ilJsonUtil.php");

/**
 * User interface hook class
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 * @ingroup ServicesUIComponent
 */
class ilSEBUIHookGUI extends ilUIHookPluginGUI {
	
	private static $_modifyGUI = 0;
	
	function getFullUrl() {
		$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
		$sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
		$protocol = substr($sp, 0, strpos($sp, "/")) . $s;
		$port = ($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443") ? "" : (":".$_SERVER["SERVER_PORT"]);
		return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
	}
	
	function detectSeb() {
		global $ilDB;
		$rec;
		
		/*
		if (ilSEBPlugin::_isAPCInstalled() && apc_exists(ilSEBPlugin::CACHE)) {
			$rec = apc_fetch(ilSEBPlugin::CACHE);
		}
		else {*/
			$q = "SELECT config_json FROM ui_uihk_seb_conf";
			$ret = $ilDB->query($q);
			$rec = $ilDB->fetchAssoc($ret);
			$rec = json_decode($rec['config_json'],true); // as assoc
		//}
		
		$seb_keys = split(",",$rec["seb_key"]);
		$rec["seb_keys"] = array();
		$url = $this->getFullUrl();
		if ($rec["url_salt"]) {
			foreach ($seb_keys as $seb_key) {
				array_push($rec["seb_keys"],hash('sha256',$url . trim($seb_key)));
			}
		}
		else {
			foreach ($seb_keys as $seb_key) {
				array_push($rec["seb_keys"],trim($seb_key));
			}
		}
		$server_req_header = $_SERVER[$rec["req_header"]];
		
		// ILIAS want to detect a valid SEB with a custom req_header and seb_key
		// if no req_header exists in the current request: not a seb request
		if (!$server_req_header || $server_req_header == "") {		
			$rec["request"] = ilSebPlugin::NOT_A_SEB_REQUEST; // not a seb request
			return $rec;
		}
		$is_seb_key = false;
		// if the value of the req_header is not the the stored or hashed seb key: // not a seb request
		foreach ($rec["seb_keys"] as $seb_key) {
			if ($server_req_header == $seb_key) {
				$is_seb_key = true;
			}
		}
		if ($is_seb_key) {
			$rec["request"] = ilSebPlugin::SEB_REQUEST; // seb request
			return $rec;
		}
		else {
			$rec["request"] = ilSebPlugin::NOT_A_SEB_REQUEST; // not a seb request
			return $rec;
		}
	}
	
	function getSebObject() { // obsolet?
		global $ilUser;
		$pl = $this->getPluginObject();
		$seb_user = array(
					"login" => $ilUser->getLogin(),
					"firstname" => $ilUser->getFirstname(),
					"lastname" => $ilUser->getLastname(),
					"matriculation" => $ilUser->getMatriculation()
				);
		$seb_object = array("user" => $seb_user);
		$ret = json_encode($seb_object); 
		return $ret;
	}
	 
	function exitIlias() {
		global $ilAuth;	
		$pl = $this->getPluginObject();	
		ilSession::setClosingContext(ilSession::SESSION_CLOSE_LOGIN);
		$ilAuth->logout();
		session_unset();
		session_destroy();
		$script = "login.php?target=".$_GET["target"]."&client_id=".$_COOKIE["ilClientId"];	
		$headerTxt = $pl->txt("forbidden_header");
		$msgTxt = $pl->txt("forbidden_message");
		$loginTxt = $pl->txt("forbidden_login");
		$login = "<a href=\"" . $script . "\">" . $loginTxt . "</a>";
		$msg = file_get_contents("./Customizing/global/skin/seb/tpl.seb_forbidden.html");
		$msg = str_replace("{TXT_HEADER}", $headerTxt, $msg);
		$msg = str_replace("{TXT_MESSAGE}", $msgTxt, $msg);
		$msg = str_replace("{LOGIN}", $login, $msg);
		
		header('HTTP/1.1 403 Forbidden');
		echo $msg;
		exit;			
	}
	
	function setUserGUI () {
		global $styleDefinition, $ilUser;
		self::$_modifyGUI = 0;
		$styleDefinition->setCurrentSkin($ilUser->getPref("skin"));
		$styleDefinition->setCurrentStyle($ilUser->getPref("style"));
	}
	
	function setSebGUI () {
		global $styleDefinition;
		self::$_modifyGUI = 1;
		$styleDefinition->setCurrentSkin("seb");
		$styleDefinition->setCurrentStyle("seb");
	}

	/**
	 * Modify HTML output of GUI elements. Modifications modes are:
	 * - ilUIHookPluginGUI::KEEP (No modification)
	 * - ilUIHookPluginGUI::REPLACE (Replace default HTML with your HTML)
	 * - ilUIHookPluginGUI::APPEND (Append your HTML to the default HTML)
	 * - ilUIHookPluginGUI::PREPEND (Prepend your HTML to the default HTML)
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array array with entries "mode" => modification mode, "html" => your html
	 */
	function getHTML($a_comp, $a_part, $a_par = array()) {				
		global $ilUser, $rbacreview, $tpl;
		
		if (!self::$_modifyGUI ) {
			return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		}
		
		// JavaScript Injection of seb_object on TA kioskmode
		
		if ($a_part == "template_load" && $a_par["tpl_id"] == "Modules/Test/tpl.il_as_tst_kiosk_head.html") {
		//if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_list_entries") {			
			$pl = $this->getPluginObject();
			$tpl->addJavaScript($pl->getDirectory() . "/ressources/seb.js");
			$seb_object = $this->getSebObject(); 
			return array("mode" => ilUIHookPluginGUI::PREPEND, "html" => "<script type=\"text/javascript\">var seb_object = " . $seb_object . ";</script>");
		}
		
		// JavaScript Injection of seb_object on PD kioskmode
		
		if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_list_entries") {			
			$pl = $this->getPluginObject();
			$tpl->addJavaScript($pl->getDirectory() . "/ressources/seb.js");
			$seb_object = $this->getSebObject(); 
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "<script type=\"text/javascript\">var seb_object = " . $seb_object . ";</script>");
		}
		
		if ($a_comp == "Services/MainMenu" && $a_part == "main_menu_search") {		
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");			
		}
		
		if ($a_comp == "Services/Locator" && $a_part == "main_locator") {			
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		
		if ($a_comp == "Services/PersonalDesktop" && $a_part == "right_column") {
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		
		if ($a_comp == "Services/PersonalDesktop" && $a_part == "left_column") {			
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		 
		return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
	}
	
	/**
	 * Modify GUI objects, before they generate ouput
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array()) {
		global $ilUser, $rbacreview, $ilAuth;
		if ($a_comp == "Services/Init" && $a_part == "init_style") {			
			$req = $this->detectSeb();
			
			// don't modify anything after an initial installation with an empty key
			if ($req['seb_key'] == '') {
				$this->setUserGUI();
				return;
			}
			
			$usr_id = $ilUser->getId();
			// don't modify anything in public ilias 
			if ($usr_id == ANONYMOUS_USER_ID) {
				$this->setUserGUI();
				return;
			}
			
			$is_admin = $rbacreview->isAssigned($usr_id,2);
			$is_logged_in = ($usr_id && $usr_id != ANONYMOUS_USER_ID);
			$deny_user = false;
			$role_deny = $req['role_deny'];
			
			// check role deny			
			if ($is_logged_in && $role_deny && !$is_admin) { // check access 				
				$deny_user = ($role_deny == 1 || $rbacreview->isAssigned($usr_id,$role_deny));
			}
			
			// check browser access
			$browser_access = $req['browser_access'];					
			$is_seb = ($req['request'] == ilSebPlugin::SEB_REQUEST);
			$allow_browser = ($browser_access && $is_seb);
			
			if ($deny_user && !$allow_browser) {
				$this->exitIlias();
				//$this->setExitGUI();
				return;
			}
				
			// check kiosk mode
			$role_kiosk = $req['role_kiosk'];
			$user_kiosk = false;
			$browser_kiosk = $req['browser_kiosk'];
			$kiosk_user = (($role_kiosk == 1 || $rbacreview->isAssigned($usr_id,$role_kiosk)) && !$is_admin);
			
			if ($is_logged_in) {	
							
				$switchToSebGUI = false;
				if ($kiosk_user) {
					switch ($browser_kiosk) {
						case ilSebPlugin::BROWSER_KIOSK_ALL :
							$switchToSebGUI = true;
							break;
						case ilSebPlugin::BROWSER_KIOSK_SEB :
							$switchToSebGUI = $is_seb;
							break;
					}
					if ($switchToSebGUI) {
						$this->setSebGUI();
					}
					else {
						$this->setUserGUI();
					}							
				}
				else {
					$this->setUserGUI();
				}
			}
			else { 			
				$switchToSebGUI = false;
				if ($role_kiosk) {
					switch ($browser_kiosk) {
						case ilSebPlugin::BROWSER_KIOSK_ALL :
							// no role deny and all BROWSER_KIOSK_ALL seems to be a demo mode, don't change login page
							$switchToSebGUI = ($role_deny != ilSebPlugin::ROLES_NONE);
							break;
						case ilSebPlugin::BROWSER_KIOSK_SEB :
							$switchToSebGUI = $is_seb;
					}
					if ($switchToSebGUI) {
						$this->setSebGUI();
					}
					else {
						$this->setUserGUI();
					}
				}
			}
		}
	}
}
?>
