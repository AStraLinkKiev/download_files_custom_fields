<?php
/**
 * @package    plg_prodfiles
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Filesystem\File;

/**
 * Product files for JoomShopping.
 *
 * Adds a reusable file library to JoomShopping products and renders assigned
 * files in the product page.
 */
class plgJshoppingProdfiles extends CMSPlugin
{
	use DatabaseAwareTrait;

	/**
	 * @var CMSApplication
	 */
	protected $app;

	/**
	 * @var JDatabaseDriver
	 */
	protected $db;

	/**
	 * @var boolean
	 */
	protected $autoloadLanguage = true;

	/**
	 * @var boolean
	 */
	private $schemaReady = false;

	public function onAfterLoadShopParamsAdmin()
	{
		$this->debug('Product files plugin loaded: onAfterLoadShopParamsAdmin');
	}

	public function onAfterRoute()
	{
		$this->ensureSchema();
	}

	public function onAfterRender()
	{
		return;
	}

	public function onAfterDispatch()
	{
		if (!$this->app->isClient('administrator') || !$this->isProductSaveRequest())
		{
			return;
		}

		$this->saveFilesFromRequest($this->getCurrentProductId());
	}

	public function onAfterSaveProduct(&$product)
	{
		if (!$this->app->isClient('administrator'))
		{
			return;
		}

		$productId = isset($product->product_id) ? (int) $product->product_id : $this->getCurrentProductId();
		$this->saveFilesFromRequest($productId);
	}

	public function onBeforeDisplayProductView(&$view)
	{
		$productId = 0;

		if (isset($view->product->product_id))
		{
			$productId = (int) $view->product->product_id;
		}
		elseif (isset($view->product_id))
		{
			$productId = (int) $view->product_id;
		}

		if ($productId <= 0)
		{
			return;
		}

		$this->ensureSchema();

		$files = $this->getProductFiles($productId);

		if (!$files)
		{
			return;
		}

		$html     = $this->renderFrontendFiles($files, $productId);
		$position = $this->params->get('display_position', '_tmp_product_html_after_buttons');

		if (!isset($view->{$position}))
		{
			$view->{$position} = '';
		}

		$view->{$position} .= $html;
	}

	public function onBeforeDisplayEditProductView(&$view)
	{
		$this->debug('Product files event: onBeforeDisplayEditProductView');
	}

	public function onBeforeDisplayOptionsPanel(&$view)
	{
		if (!$this->app->isClient('administrator'))
		{
			return;
		}

		$this->ensureSchema();

		if (!isset($view->tmp_html_end))
		{
			$view->tmp_html_end = '';
		}

		$view->tmp_html_end .= $this->renderOptionsFileManager();
	}

	public function onDisplayProductEditTabsTab(&$row, &$lists, &$tax_value)
	{
		$this->debug('Product files event: onDisplayProductEditTabsTab');
		$this->ensureSchema();

		echo '<li class="nav-item"><a href="#prodfiles-page" class="nav-link" data-toggle="tab">' . Text::_('PLG_JSHOPPING_PRODFILES_ADMIN_TITLE') . '</a></li>';
	}

	public function onDisplayProductEditTabs(&$pane, &$row, &$lists, &$tax_value, &$currency)
	{
		$this->debug('Product files event: onDisplayProductEditTabs');
		$this->ensureSchema();

		$productId = isset($row->product_id) ? (int) $row->product_id : 0;

		echo '<div id="prodfiles-page" class="tab-pane">';
		echo $this->renderAdminProductPanel($productId);
		echo '</div>';
	}

	private function debug($message)
	{
		if ((int) $this->params->get('debug', 0) !== 1 || !$this->app || !$this->app->isClient('administrator'))
		{
			return;
		}

		$this->app->enqueueMessage($message, 'notice');
	}

	private function isProductEditRequest()
	{
		$input = $this->app->getInput();

		if ($input->getCmd('option') !== 'com_jshopping')
		{
			return false;
		}

		$controller = $input->getCmd('controller');
		$view       = $input->getCmd('view');
		$task       = $input->getCmd('task');
		$layout     = $input->getCmd('layout');

		if (in_array($controller, array('product', 'products'), true)
			&& in_array($task, array('add', 'edit', 'editA', 'apply', 'save'), true))
		{
			return true;
		}

		if (in_array($view, array('product', 'products'), true)
			&& in_array($layout, array('edit', 'form'), true))
		{
			return true;
		}

		return $this->getCurrentProductId() > 0
			&& (in_array($controller, array('product', 'products'), true) || in_array($view, array('product', 'products'), true));
	}

	private function getCurrentProductId()
	{
		$input     = $this->app->getInput();
		$productId = $input->getInt('product_id');

		if ($productId <= 0)
		{
			$productId = $input->getInt('id');
		}

		if ($productId <= 0)
		{
			$cid = (array) $input->get('cid', array(), 'array');
			$cid = array_values(array_filter(array_map('intval', $cid)));

			if ($cid)
			{
				$productId = (int) $cid[0];
			}
		}

		return $productId;
	}

	private function isProductEditHtml($body)
	{
		return strpos($body, 'com_jshopping') !== false
			&& strpos($body, 'name="adminForm"') !== false
			&& strpos($body, 'name="product_id"') !== false
			&& strpos($body, 'name="task" value="save"') !== false;
	}

	private function isProductSaveRequest()
	{
		$input = $this->app->getInput();

		return $input->getCmd('option') === 'com_jshopping'
			&& $input->getCmd('controller') === 'products'
			&& in_array($input->getCmd('task'), array('save', 'apply', 'save2new'), true)
			&& $input->post->getInt('prodfiles_marker') === 1;
	}

	private function saveFilesFromRequest($productId)
	{
		$input = $this->app->getInput();

		if (!$input->post->getInt('prodfiles_marker') || $productId <= 0)
		{
			return;
		}

		$this->ensureSchema();

		$fileIds = array_map('intval', (array) $input->post->get('prodfiles_ids', array(), 'array'));
		$fileIds = array_values(array_unique(array_filter($fileIds)));
		$orderValues = (array) $input->post->get('prodfiles_ordering', array(), 'array');

		$this->saveProductFiles($productId, $fileIds, $orderValues);
	}

	private function ensureSchema()
	{
		if ($this->schemaReady)
		{
			return;
		}

		$queries = array(
			"CREATE TABLE IF NOT EXISTS `#__plg_prodfiles_files` (
				`id` int unsigned NOT NULL AUTO_INCREMENT,
				`title` varchar(255) NOT NULL DEFAULT '',
				`filename` varchar(255) NOT NULL DEFAULT '',
				`original_name` varchar(255) NOT NULL DEFAULT '',
				`mime` varchar(120) NOT NULL DEFAULT '',
				`size` int unsigned NOT NULL DEFAULT 0,
				`description` text NULL,
				`extra_data` text NULL,
				`published` tinyint NOT NULL DEFAULT 1,
				`created` datetime NULL,
				`created_by` int unsigned NOT NULL DEFAULT 0,
				`modified` datetime NULL,
				PRIMARY KEY (`id`),
				KEY `idx_published` (`published`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE IF NOT EXISTS `#__plg_prodfiles_products` (
				`product_id` int unsigned NOT NULL,
				`file_id` int unsigned NOT NULL,
				`ordering` int NOT NULL DEFAULT 0,
				PRIMARY KEY (`product_id`, `file_id`),
				KEY `idx_file_id` (`file_id`),
				KEY `idx_product_ordering` (`product_id`, `ordering`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		);

		foreach ($queries as $query)
		{
			$this->getDatabase()->setQuery($query)->execute();
		}

		$this->ensureColumn('#__plg_prodfiles_files', 'extra_data', "ALTER TABLE `#__plg_prodfiles_files` ADD COLUMN `extra_data` text NULL AFTER `description`");

		$this->schemaReady = true;
	}

	private function renderAdminProductPanel($productId)
	{
		$files       = $this->getAllFiles();
		$productFiles = $productId > 0 ? $this->getProductFiles($productId, true) : array();
		$selectedIds = array_map('intval', array_column($productFiles, 'id'));
		$selected = array_fill_keys($selectedIds, true);
		$ordering = array();

		foreach ($productFiles as $productFile)
		{
			$ordering[(int) $productFile->id] = (int) ($productFile->ordering ?? 0);
		}

		$html        = array();

		$html[] = '<!-- plg_prodfiles admin panel -->';
		$html[] = '<div class="card prodfiles-admin" style="margin:16px 0;">';
		$html[] = '<div class="card-header"><strong>' . Text::_('PLG_JSHOPPING_PRODFILES_ADMIN_TITLE') . '</strong></div>';
		$html[] = '<div class="card-body">';
		$html[] = '<input type="hidden" name="prodfiles_marker" value="1">';

		if ($files)
		{
			$html[] = '<div class="prodfiles-list" style="max-height:320px;overflow:auto;border:1px solid #dfe3e7;padding:10px;border-radius:4px;">';

			foreach ($files as $file)
			{
				$fileId = (int) $file->id;
				$checked = isset($selected[$fileId]) ? ' checked' : '';
				$orderValue = $ordering[$fileId] ?? '';
				$url     = htmlspecialchars($this->getDownloadUrl((int) $file->id, $productId), ENT_QUOTES, 'UTF-8');
				$title   = htmlspecialchars($file->title, ENT_QUOTES, 'UTF-8');
				$meta    = htmlspecialchars($this->formatFileMeta($file), ENT_QUOTES, 'UTF-8');

				$html[] = '<div class="prodfiles-row prodfiles-row-' . $fileId . '" style="display:grid;grid-template-columns:24px 76px 1fr;gap:8px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eef0f2;">';
				$html[] = '<input type="checkbox" name="prodfiles_ids[]" value="' . $fileId . '"' . $checked . ' style="margin-top:8px;">';
				$html[] = '<input class="form-control form-control-sm" type="number" name="prodfiles_ordering[' . $fileId . ']" value="' . htmlspecialchars((string) $orderValue, ENT_QUOTES, 'UTF-8') . '" min="0" step="1" title="' . Text::_('PLG_JSHOPPING_PRODFILES_ORDERING') . '">';
				$html[] = '<div>';
				$html[] = '<strong>' . $title . '</strong><br>';
				$html[] = '<small class="text-muted">' . $meta . ' | <a href="' . $url . '" target="_blank" rel="noopener">' . Text::_('PLG_JSHOPPING_PRODFILES_DOWNLOAD') . '</a></small>';
				$html[] = '</div></div>';
			}

			$html[] = '</div>';
		}
		else
		{
			$html[] = '<p class="text-muted">' . Text::_('PLG_JSHOPPING_PRODFILES_NO_FILES') . '</p>';
		}

		$html[] = '<div class="form-text mt-2">' . Text::_('PLG_JSHOPPING_PRODFILES_PRODUCT_SELECT_HINT') . '</div>';
		$html[] = '</div></div>';

		return implode("\n", $html);
	}

	private function renderOptionsFileManager()
	{
		$html  = array();

		$html[] = '<div class="card prodfiles-options" style="clear:both;margin-top:24px;">';
		$html[] = '<div class="card-header"><strong>' . Text::_('PLG_JSHOPPING_PRODFILES_LIBRARY_TITLE') . '</strong></div>';
		$html[] = '<div class="card-body">';
		$html[] = '<p class="text-muted">' . Text::_('PLG_JSHOPPING_PRODFILES_COMPONENT_HINT') . '</p>';
		$html[] = '<a class="btn btn-primary" href="index.php?option=com_prodfiles">' . Text::_('PLG_JSHOPPING_PRODFILES_OPEN_COMPONENT') . '</a>';
		$html[] = '</div></div>';

		return implode("\n", $html);
	}

	private function renderFrontendFiles(array $files, $productId)
	{
		$html = array();
		$extraFields = $this->getExtraFieldDefinitions();

		$html[] = '<div class="jshop prodfiles-product-files">';
		$html[] = '<h3>' . Text::_('PLG_JSHOPPING_PRODFILES_FRONT_TITLE') . '</h3>';
		$html[] = '<ul class="prodfiles-list">';

		foreach ($files as $file)
		{
			$url         = htmlspecialchars($this->getDownloadUrl((int) $file->id, $productId), ENT_QUOTES, 'UTF-8');
			$title       = htmlspecialchars($file->title, ENT_QUOTES, 'UTF-8');
			$description = trim((string) $file->description);
			$meta        = $this->formatFileMeta($file);
			$extraValues = $this->getExtraValues($file);

			$html[] = '<li class="prodfiles-item">';
			$html[] = '<a href="' . $url . '">' . $title . '</a> <span class="prodfiles-size">(' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . ')</span>';

			if ($description !== '')
			{
				$html[] = '<div class="prodfiles-description">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</div>';
			}

			if ($extraFields)
			{
				$html[] = '<dl class="prodfiles-extra">';

				foreach ($extraFields as $field)
				{
					$value = trim((string) ($extraValues[$field['key']] ?? ''));

					if ($value === '')
					{
						continue;
					}

					$html[] = '<dt>' . htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') . '</dt>';
					$html[] = '<dd>' . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</dd>';
				}

				$html[] = '</dl>';
			}

			$html[] = '</li>';
		}

		$html[] = '</ul></div>';

		return implode("\n", $html);
	}

	private function saveProductFiles($productId, array $fileIds, array $orderValues = array())
	{
		$query = $this->getDatabase()->getQuery(true)
			->delete($this->getDatabase()->quoteName('#__plg_prodfiles_products'))
			->where($this->getDatabase()->quoteName('product_id') . ' = ' . (int) $productId);

		$this->getDatabase()->setQuery($query)->execute();

		$rows = array();

		foreach (array_values($fileIds) as $position => $fileId)
		{
			$customOrdering = isset($orderValues[$fileId]) ? (int) $orderValues[$fileId] : 0;
			$rows[] = array(
				'file_id' => (int) $fileId,
				'ordering' => $customOrdering > 0 ? $customOrdering : ($position + 1),
			);
		}

		usort(
			$rows,
			static function ($a, $b) {
				if ($a['ordering'] === $b['ordering'])
				{
					return $a['file_id'] <=> $b['file_id'];
				}

				return $a['ordering'] <=> $b['ordering'];
			}
		);

		foreach ($rows as $position => $item)
		{
			$row = (object) array(
				'product_id' => (int) $productId,
				'file_id'    => (int) $item['file_id'],
				'ordering'   => $position + 1,
			);

			$this->getDatabase()->insertObject('#__plg_prodfiles_products', $row);
		}
	}

	private function getAllFiles($includeUnpublished = false)
	{
		$query = $this->getDatabase()->getQuery(true)
			->select('*')
			->from($this->getDatabase()->quoteName('#__plg_prodfiles_files'))
			->order($this->getDatabase()->quoteName('created') . ' DESC');

		if (!$includeUnpublished)
		{
			$query->where($this->getDatabase()->quoteName('published') . ' = 1');
		}

		return $this->getDatabase()->setQuery($query)->loadObjectList();
	}

	private function getProductFiles($productId, $admin = false)
	{
		$query = $this->getDatabase()->getQuery(true)
			->select('f.*')
			->select('p.ordering')
			->from($this->getDatabase()->quoteName('#__plg_prodfiles_files', 'f'))
			->innerJoin($this->getDatabase()->quoteName('#__plg_prodfiles_products', 'p') . ' ON p.file_id = f.id')
			->where('p.product_id = ' . (int) $productId)
			->order('p.ordering ASC, f.title ASC');

		if (!$admin)
		{
			$query->where('f.published = 1');
		}

		return $this->getDatabase()->setQuery($query)->loadObjectList();
	}

	private function getDownloadUrl($fileId, $productId = 0)
	{
		return Route::_('index.php?option=com_prodfiles&task=download&id=' . (int) $fileId . '&product_id=' . (int) $productId);
	}

	private function getStoragePath()
	{
		return JPATH_ROOT . '/media/plg_prodfiles/files';
	}

	private function getExtraFieldDefinitions()
	{
		$raw = $this->getExtraFieldsConfig();
		$fields = array();

		foreach (preg_split('/\R/', $raw) as $line)
		{
			$line = trim($line);

			if ($line === '' || str_starts_with($line, '#'))
			{
				continue;
			}

			$parts = array_map('trim', explode('|', $line));
			$hasOptions = count($parts) > 1;
			$label = $hasOptions ? (string) ($parts[1] ?? $parts[0]) : $line;
			$key = preg_replace('/[^a-zA-Z0-9_\\-]/', '', (string) ($parts[0] ?? ''));
			$show = !$hasOptions || !isset($parts[3]) || !in_array(strtolower((string) $parts[3]), array('0', 'no', 'false', 'hide'), true);

			if ($key === '')
			{
				$key = $this->getAutoFieldKey($label);
			}

			if (!$show)
			{
				continue;
			}

			$fields[] = array(
				'key' => $key,
				'label' => $label !== '' ? $label : $key,
			);
		}

		return $fields;
	}

	private function getExtraValues($file)
	{
		$raw = (string) ($file->extra_data ?? '');
		$data = $raw !== '' ? json_decode($raw, true) : array();

		return is_array($data) ? $data : array();
	}

	private function getAutoFieldKey($label)
	{
		return 'field_' . substr(md5((string) $label), 0, 12);
	}

	private function getExtraFieldsConfig()
	{
		$params = ComponentHelper::getParams('com_prodfiles');
		$raw = (string) $params->get('extra_fields', '');

		if ($raw === '')
		{
			$raw = (string) $params->get('params.extra_fields', '');
		}

		return $raw;
	}

	private function ensureColumn($table, $column, $alterQuery)
	{
		$db = $this->getDatabase();
		$tableName = $db->replacePrefix($table);
		$columns = $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($tableName) . ' LIKE ' . $db->quote($column))->loadObjectList();

		if (!$columns)
		{
			$db->setQuery($db->replacePrefix($alterQuery))->execute();
		}
	}

	private function formatFileMeta($file)
	{
		$name = (string) ($file->original_name ?: $file->filename);
		$extension = strtolower(File::getExt($name));
		$parts = array();

		if ($extension !== '')
		{
			$parts[] = strtoupper($extension);
		}

		$parts[] = $this->formatBytes((int) $file->size);

		if ($name !== '')
		{
			$parts[] = $name;
		}

		return implode(' | ', $parts);
	}

	private function formatBytes($bytes)
	{
		if ($bytes >= 1048576)
		{
			return round($bytes / 1048576, 1) . ' MB';
		}

		if ($bytes >= 1024)
		{
			return round($bytes / 1024, 1) . ' KB';
		}

		return $bytes . ' B';
	}
}
