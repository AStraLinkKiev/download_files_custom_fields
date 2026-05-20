<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$token = Session::getFormToken();
$extraFields = $this->model->getExtraFieldDefinitions();
?>
<form action="<?php echo Route::_('index.php?option=com_prodfiles&task=files.save'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row g-3 mb-4 align-items-end">
		<div class="col-md-4">
			<label class="form-label" for="prodfiles_upload"><?php echo Text::_('COM_PRODFILES_UPLOAD_LABEL'); ?></label>
			<input class="form-control" type="file" name="prodfiles_upload" id="prodfiles_upload" form="prodfiles-upload-form">
		</div>
		<div class="col-md-3">
			<label class="form-label" for="prodfiles_title"><?php echo Text::_('COM_PRODFILES_TITLE_LABEL'); ?></label>
			<input class="form-control" type="text" name="prodfiles_title" id="prodfiles_title" form="prodfiles-upload-form">
		</div>
		<div class="col-md-3">
			<label class="form-label" for="prodfiles_description"><?php echo Text::_('COM_PRODFILES_DESCRIPTION_LABEL'); ?></label>
			<input class="form-control" type="text" name="prodfiles_description" id="prodfiles_description" form="prodfiles-upload-form">
		</div>
		<div class="col-md-2">
			<button class="btn btn-primary w-100" type="submit" form="prodfiles-upload-form"><?php echo Text::_('COM_PRODFILES_UPLOAD_BUTTON'); ?></button>
		</div>
	</div>
	<?php if ($extraFields) : ?>
		<div class="row g-3 mb-4">
			<?php foreach ($extraFields as $field) : ?>
				<div class="col-md-4">
					<label class="form-label" for="prodfiles_extra_<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?></label>
					<?php if ($field['type'] === 'textarea') : ?>
						<textarea class="form-control" name="prodfiles_extra[<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>]" id="prodfiles_extra_<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>" rows="2" form="prodfiles-upload-form"></textarea>
					<?php else : ?>
						<input class="form-control" type="<?php echo htmlspecialchars($field['type'], ENT_QUOTES, 'UTF-8'); ?>" name="prodfiles_extra[<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>]" id="prodfiles_extra_<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>" form="prodfiles-upload-form">
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ($this->items) : ?>
		<div class="table-responsive">
			<table class="table table-striped table-hover align-middle">
				<thead>
					<tr>
						<th><?php echo Text::_('COM_PRODFILES_TITLE_LABEL'); ?></th>
						<th><?php echo Text::_('COM_PRODFILES_DESCRIPTION_LABEL'); ?></th>
						<?php foreach ($extraFields as $field) : ?>
							<th><?php echo htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?></th>
						<?php endforeach; ?>
						<th><?php echo Text::_('COM_PRODFILES_FILE'); ?></th>
						<th class="text-center"><?php echo Text::_('COM_PRODFILES_PRODUCTS_COUNT'); ?></th>
						<th class="text-center"><?php echo Text::_('JPUBLISHED'); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->items as $item) : ?>
						<?php
						$id = (int) $item->id;
						$extraValues = $this->model->getExtraValues($item);
						$downloadUrl = Route::_('index.php?option=com_prodfiles&task=files.download&id=' . $id . '&' . $token . '=1');
						$deleteUrl = Route::_('index.php?option=com_prodfiles&task=files.delete&id=' . $id . '&' . $token . '=1');
						?>
						<tr>
							<td>
								<input class="form-control form-control-sm" type="text" name="prodfiles_meta[<?php echo $id; ?>][title]" value="<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>">
							</td>
							<td>
								<input class="form-control form-control-sm" type="text" name="prodfiles_meta[<?php echo $id; ?>][description]" value="<?php echo htmlspecialchars((string) $item->description, ENT_QUOTES, 'UTF-8'); ?>">
							</td>
							<?php foreach ($extraFields as $field) : ?>
								<?php $value = (string) ($extraValues[$field['key']] ?? ''); ?>
								<td>
									<?php if ($field['type'] === 'textarea') : ?>
										<textarea class="form-control form-control-sm" name="prodfiles_meta[<?php echo $id; ?>][extra][<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>]" rows="2"><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></textarea>
									<?php else : ?>
										<input class="form-control form-control-sm" type="<?php echo htmlspecialchars($field['type'], ENT_QUOTES, 'UTF-8'); ?>" name="prodfiles_meta[<?php echo $id; ?>][extra][<?php echo htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>]" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td>
								<a href="<?php echo $downloadUrl; ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($this->model->formatFileMeta($item), ENT_QUOTES, 'UTF-8'); ?></a>
							</td>
							<td class="text-center"><?php echo (int) $item->products_count; ?></td>
							<td class="text-center">
								<input type="checkbox" name="prodfiles_published[]" value="<?php echo $id; ?>"<?php echo (int) $item->published === 1 ? ' checked' : ''; ?>>
							</td>
							<td class="text-end">
								<a class="btn btn-sm btn-danger" href="<?php echo $deleteUrl; ?>" onclick="return confirm('<?php echo addslashes(Text::_('COM_PRODFILES_DELETE_CONFIRM')); ?>');"><?php echo Text::_('COM_PRODFILES_DELETE'); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<button class="btn btn-success" type="submit"><?php echo Text::_('JSAVE'); ?></button>
	<?php else : ?>
		<p class="text-muted"><?php echo Text::_('COM_PRODFILES_NO_FILES'); ?></p>
	<?php endif; ?>

	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<form action="<?php echo Route::_('index.php?option=com_prodfiles&task=files.upload'); ?>" method="post" enctype="multipart/form-data" id="prodfiles-upload-form">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
