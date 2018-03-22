<?php

use SilverStripe\Admin\CMSMenu;
use jonom\SilverStripe\VersionHistory\VersionHistoryController;

CMSMenu::remove_menu_class(VersionHistoryController::class);