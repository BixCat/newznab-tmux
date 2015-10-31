<?php

use newznab\Users;

/**
 * All admin pages implement this class. Enforces admin role for requesting user.
 */
class AdminPage extends BasePage
{
	/**
	 * Default constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$tplpaths = [];
		if ($this->settings->getSetting('style') != "default")
			$tplpaths["style_admin"] = NN_WWW.'themes_shared/templates/admin';
		$tplpaths["admin"] = NN_WWW.'themes_shared/templates/admin';
		$tplpaths["frontend"] = NN_WWW.'themes/omicron/templates/frontend';
		$this->smarty->setTemplateDir($tplpaths);

		if (!$this->users->isLoggedIn() || !isset($this->userdata["role"]) || $this->userdata["role"] != Users::ROLE_ADMIN)
			$this->show403(true);

	}

	/**
	 * Output a page using the admin template.
	 */
	public function render()
	{
		$this->smarty->assign('page',$this);

		$admin_menu = $this->smarty->fetch('adminmenu.tpl');
		$this->smarty->assign('admin_menu',$admin_menu);

		$this->page_template = "baseadminpage.tpl";

		parent::render();
	}
}