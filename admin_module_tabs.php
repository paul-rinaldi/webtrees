<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees;

use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;

require 'includes/session.php';

$controller = new PageController;
$controller
	->restrictAccess(Auth::isAdmin())
	->setPageTitle(I18N::translate('Tabs'));

$action  = Filter::post('action');
$modules = Module::getAllModulesByComponent('tab');

if ($action === 'update_mods' && Filter::checkCsrf()) {
	foreach ($modules as $module) {
		foreach (Tree::getAll() as $tree) {
			$access_level = Filter::post('access-' . $module->getName() . '-' . $tree->getTreeId(), WT_REGEX_INTEGER, $module->defaultAccessLevel());
			Database::prepare(
				"REPLACE INTO `##module_privacy` (module_name, gedcom_id, component, access_level) VALUES (?, ?, 'tab', ?)"
			)->execute([$module->getName(), $tree->getTreeId(), $access_level]);
		}
		$order = Filter::post('order-' . $module->getName());
		Database::prepare(
			"UPDATE `##module` SET tab_order=? WHERE module_name=?"
		)->execute([$order, $module->getName()]);
	}

	header('Location: admin_module_tabs.php');

	return;
}

$controller
	->addInlineJavascript('
		$("#module_table").sortable({
			items: ".sortme",
			forceHelperSize: true,
			forcePlaceholderSize: true,
			opacity: 0.7,
			cursor: "move",
			axis: "y",
			update: function(event, ui) {
				$("input", $(this)).each(
					function (index, element) {
						element.value = index + 1;
					}
				);
			}
		});
	')
	->pageHeader();

echo Bootstrap4::breadcrumbs([
	'admin.php'         => I18N::translate('Control panel'),
	'admin_modules.php' => I18N::translate('Module administration'),
], $controller->getPageTitle());
?>

<h1><?= $controller->getPageTitle() ?></h1>

<form method="post">
	<input type="hidden" name="action" value="update_mods">
	<?= Filter::getCsrf() ?>
	<table id="module_table" class="table table-bordered">
		<thead>
		<tr>
			<th class="col-xs-1"><?= I18N::translate('Tab') ?></th>
			<th class="col-xs-5"><?= I18N::translate('Description') ?></th>
			<th class="col-xs-1"><?= I18N::translate('Order') ?></th>
			<th class="col-xs-5"><?= I18N::translate('Access level') ?></th>
		</tr>
		</thead>
		<tbody>
		<?php $order = 0 ?>
		<?php foreach ($modules as $module): ?>
			<?php $order++ ?>
			<tr class="sortme">
				<td class="col-xs-1">
					<?php if ($module instanceof ModuleConfigInterface): ?>
					<a href="<?= $module->getConfigLink() ?>"><?= $module->getTitle() ?> <i class="fa fa-cogs"></i></a>
					<?php else: ?>
					<?= $module->getTitle() ?>
					<?php endif ?>
				</td>
				<td class="col-xs-5"><?= $module->getDescription() ?></td>
				<td class="col-xs-1"><input type="text" size="3" value="<?= $order ?>" name="order-<?= $module->getName() ?>"></td>
				<td class="col-xs-5">
					<table class="table">
						<tbody>
							<?php foreach (Tree::getAll() as $tree): ?>
								<tr>
									<td>
										<?= $tree->getTitleHtml() ?>
									</td>
									<td>
										<?= Bootstrap4::select(FunctionsEdit::optionsAccessLevels(), $module->getAccessLevel($tree, 'tab'), ['name' => 'access-' . $module->getName() . '-' . $tree->getTreeId()]) ?>
									</td>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
				</td>
			</tr>
		<?php endforeach ?>
		</tbody>
	</table>
	<button class="btn btn-primary" type="submit">
		<i class="fa fa-check"></i>
		<?= I18N::translate('save') ?>
	</button>
</form>
