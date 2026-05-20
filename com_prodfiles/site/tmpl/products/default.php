<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Prodfiles\Site\Helper\RouteHelper;
use Joomla\Component\Prodfiles\Site\Model\ProductsModel;

/** @var Joomla\Component\Prodfiles\Site\View\Products\HtmlView $this */
/** @var ProductsModel $model */
$model = $this->getModel();
$document = Factory::getApplication()->getDocument();
$document->addStyleSheet(Uri::root() . 'media/com_prodfiles/css/prodfiles.css');
$document->addScript(Uri::root() . 'media/com_prodfiles/js/prodfiles.js', array(), array('defer' => true));

$searchQuery = 'index.php?option=com_prodfiles&task=search&format=json&' . Session::getFormToken() . '=1';
$menuItemId = RouteHelper::getMenuItemId();

if ($menuItemId > 0)
{
	$searchQuery .= '&Itemid=' . $menuItemId;
}

$searchUrl = Route::_($searchQuery, false);
$downloadBase = 'index.php?option=com_prodfiles&task=download';
$extraFields = $model->getExtraFieldDefinitions();
?>
<div class="com-prodfiles" data-search-url="<?php echo htmlspecialchars($searchUrl, ENT_QUOTES, 'UTF-8'); ?>">
	<div class="com-prodfiles__search">
		<label class="com-prodfiles__label" for="prodfiles-product-search"><?php echo Text::_('COM_PRODFILES_SEARCH_LABEL'); ?></label>
		<input
			type="search"
			id="prodfiles-product-search"
			class="com-prodfiles__input"
			autocomplete="off"
			placeholder="<?php echo Text::_('COM_PRODFILES_SEARCH_PLACEHOLDER'); ?>"
		>
		<div class="com-prodfiles__results" data-prodfiles-results hidden></div>
	</div>

	<?php if ($this->products) : ?>
		<div class="com-prodfiles__products">
			<h2 class="com-prodfiles__section-title"><?php echo Text::_('COM_PRODFILES_PRODUCT_LIST_TITLE'); ?></h2>
			<ul class="com-prodfiles__product-list">
				<?php foreach ($this->products as $item) : ?>
					<?php
					$isActive = (int) $item->product_id === (int) $this->productId;
					$productUrl = RouteHelper::getProductRoute((int) $item->product_id);
					?>
					<li class="com-prodfiles__product-item<?php echo $isActive ? ' is-active' : ''; ?>">
						<a class="com-prodfiles__product-link" href="<?php echo $productUrl; ?>">
							<span><?php echo htmlspecialchars($item->product_name, ENT_QUOTES, 'UTF-8'); ?></span>
							<span class="com-prodfiles__product-count"><?php echo (int) $item->files_count; ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ($this->productId > 0) : ?>
		<?php if ($this->product) : ?>
			<div class="com-prodfiles__header">
				<h1 class="com-prodfiles__title"><?php echo htmlspecialchars($this->product->product_name, ENT_QUOTES, 'UTF-8'); ?></h1>
			</div>
		<?php endif; ?>

		<?php if ($this->files) : ?>
			<form class="com-prodfiles__files" action="<?php echo Route::_('index.php?option=com_prodfiles&task=zip', false); ?>" method="post">
				<input type="hidden" name="product_id" value="<?php echo (int) $this->productId; ?>">

				<div class="com-prodfiles__toolbar">
					<label class="com-prodfiles__select-all">
						<input type="checkbox" data-prodfiles-select-all>
						<span><?php echo Text::_('COM_PRODFILES_SELECT_ALL'); ?></span>
					</label>
					<button type="submit" class="btn btn-primary com-prodfiles__zip"><?php echo Text::_('COM_PRODFILES_DOWNLOAD_ZIP'); ?></button>
				</div>

				<ul class="com-prodfiles__list">
					<?php foreach ($this->files as $file) : ?>
						<?php
						$fileUrl = Route::_($downloadBase . '&id=' . (int) $file->id . '&product_id=' . (int) $this->productId, false);
						$extraValues = $model->getExtraValues($file);
						?>
						<li class="com-prodfiles__file">
							<label class="com-prodfiles__check">
								<input type="checkbox" name="files[]" value="<?php echo (int) $file->id; ?>">
								<span class="com-prodfiles__file-main">
									<span class="com-prodfiles__file-title"><?php echo htmlspecialchars($file->title, ENT_QUOTES, 'UTF-8'); ?></span>
									<span class="com-prodfiles__file-meta"><?php echo htmlspecialchars($model->formatFileMeta($file), ENT_QUOTES, 'UTF-8'); ?></span>
								</span>
							</label>

							<a class="com-prodfiles__download" href="<?php echo $fileUrl; ?>"><?php echo Text::_('COM_PRODFILES_DOWNLOAD_ONE'); ?></a>

							<?php if (trim((string) $file->description) !== '') : ?>
								<div class="com-prodfiles__description"><?php echo htmlspecialchars($file->description, ENT_QUOTES, 'UTF-8'); ?></div>
							<?php endif; ?>

							<?php if ($extraFields) : ?>
								<dl class="com-prodfiles__extra">
									<?php foreach ($extraFields as $field) : ?>
										<?php $value = trim((string) ($extraValues[$field['key']] ?? '')); ?>
										<?php if ($value !== '') : ?>
											<dt><?php echo htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?></dt>
											<dd>
												<?php if ($field['type'] === 'url') : ?>
													<a href="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></a>
												<?php else : ?>
													<?php echo nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')); ?>
												<?php endif; ?>
											</dd>
										<?php endif; ?>
									<?php endforeach; ?>
								</dl>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php echo HTMLHelper::_('form.token'); ?>
			</form>
		<?php else : ?>
			<p class="com-prodfiles__empty"><?php echo Text::_('COM_PRODFILES_NO_FILES'); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="com-prodfiles__empty"><?php echo Text::_('COM_PRODFILES_SEARCH_EMPTY_STATE'); ?></p>
	<?php endif; ?>
</div>
