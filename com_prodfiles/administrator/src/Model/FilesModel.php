<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class FilesModel extends BaseDatabaseModel
{
	public function getItems(): array
	{
		$this->ensureSchema();

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select('f.*')
			->select('COUNT(p.product_id) AS ' . $db->quoteName('products_count'))
			->from($db->quoteName('#__plg_prodfiles_files', 'f'))
			->leftJoin($db->quoteName('#__plg_prodfiles_products', 'p') . ' ON p.file_id = f.id')
			->group($db->quoteName('f.id'))
			->order($db->quoteName('f.created') . ' DESC, ' . $db->quoteName('f.id') . ' DESC');

		return $db->setQuery($query)->loadObjectList();
	}

	public function getFile(int $id): ?object
	{
		if ($id <= 0)
		{
			return null;
		}

		$this->ensureSchema();

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__plg_prodfiles_files'))
			->where($db->quoteName('id') . ' = ' . (int) $id);

		return $db->setQuery($query, 0, 1)->loadObject() ?: null;
	}

	public function uploadFile(array $upload, $input): int
	{
		$this->ensureSchema();

		if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
		{
			Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_UPLOAD_EMPTY'), 'warning');

			return 0;
		}

		if ((int) $upload['error'] !== UPLOAD_ERR_OK)
		{
			Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_UPLOAD_ERROR'), 'error');

			return 0;
		}

		$originalName = File::makeSafe((string) $upload['name']);
		$extension = strtolower(File::getExt($originalName));
		$allowed = $this->getAllowedExtensions();

		if ($extension === '' || !in_array($extension, $allowed, true))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_PRODFILES_EXTENSION_DENIED', $extension), 'error');

			return 0;
		}

		$folder = $this->getStoragePath();

		if (!Folder::exists($folder) && !Folder::create($folder))
		{
			Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_FOLDER_ERROR'), 'error');

			return 0;
		}

		$storedName = uniqid('file_', true) . '.' . $extension;

		if (!File::upload($upload['tmp_name'], $folder . '/' . $storedName, false))
		{
			Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_UPLOAD_ERROR'), 'error');

			return 0;
		}

		$title = trim((string) $input->get('prodfiles_title', '', 'string'));
		$description = trim((string) $input->get('prodfiles_description', '', 'raw'));
		$extraData = $this->filterExtraData((array) $input->get('prodfiles_extra', array(), 'array'));

		if ($title === '')
		{
			$title = pathinfo($originalName, PATHINFO_FILENAME);
		}

		$row = (object) array(
			'id'            => 0,
			'title'         => $title,
			'filename'      => $storedName,
			'original_name' => $originalName,
			'mime'          => isset($upload['type']) ? (string) $upload['type'] : '',
			'size'          => (int) $upload['size'],
			'description'   => $description,
			'extra_data'    => json_encode($extraData, JSON_UNESCAPED_UNICODE),
			'published'     => 1,
			'created'       => Factory::getDate()->toSql(),
			'created_by'    => Factory::getApplication()->getIdentity()->id,
		);

		$this->getDb()->insertObject('#__plg_prodfiles_files', $row, 'id');
		Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_UPLOAD_SUCCESS'), 'message');

		return (int) $row->id;
	}

	public function saveMetadata(array $metadata, array $publishedIds): void
	{
		$this->ensureSchema();

		$published = array_fill_keys(array_map('intval', $publishedIds), true);

		foreach ($metadata as $fileId => $data)
		{
			$fileId = (int) $fileId;

			if ($fileId <= 0 || !is_array($data))
			{
				continue;
			}

			$title = trim((string) ($data['title'] ?? ''));

			if ($title === '')
			{
				$title = $this->getFileDefaultTitle($fileId);
			}

			$row = (object) array(
				'id'          => $fileId,
				'title'       => $title,
				'description' => trim((string) ($data['description'] ?? '')),
				'extra_data'  => json_encode($this->filterExtraData((array) ($data['extra'] ?? array())), JSON_UNESCAPED_UNICODE),
				'published'   => isset($published[$fileId]) ? 1 : 0,
				'modified'    => Factory::getDate()->toSql(),
			);

			$this->getDb()->updateObject('#__plg_prodfiles_files', $row, 'id');
		}
	}

	public function deleteFiles(array $fileIds): void
	{
		$this->ensureSchema();

		$fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));

		if (!$fileIds)
		{
			return;
		}

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('id', 'filename')))
			->from($db->quoteName('#__plg_prodfiles_files'))
			->where($db->quoteName('id') . ' IN (' . implode(',', $fileIds) . ')');

		$files = $db->setQuery($query)->loadObjectList();

		foreach ($files as $file)
		{
			$path = $this->getStoragePath() . '/' . basename($file->filename);

			if (is_file($path))
			{
				File::delete($path);
			}
		}

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__plg_prodfiles_products'))
				->where($db->quoteName('file_id') . ' IN (' . implode(',', $fileIds) . ')')
		)->execute();

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__plg_prodfiles_files'))
				->where($db->quoteName('id') . ' IN (' . implode(',', $fileIds) . ')')
		)->execute();

		Factory::getApplication()->enqueueMessage(Text::_('COM_PRODFILES_DELETE_SUCCESS'), 'message');
	}

	public function ensureSchema(): void
	{
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
			$this->getDb()->setQuery($query)->execute();
		}

		$this->ensureColumn('#__plg_prodfiles_files', 'extra_data', "ALTER TABLE `#__plg_prodfiles_files` ADD COLUMN `extra_data` text NULL AFTER `description`");
	}

	public function getStoragePath(): string
	{
		return JPATH_ROOT . '/media/plg_prodfiles/files';
	}

	public function formatFileMeta(object $file): string
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

	public function getExtraFieldDefinitions(): array
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

			if ($key === '')
			{
				$key = $this->getAutoFieldKey($label);
			}

			$type = strtolower((string) ($hasOptions ? ($parts[2] ?? 'text') : 'text'));

			if (!in_array($type, array('text', 'textarea', 'url', 'email', 'number', 'date'), true))
			{
				$type = 'text';
			}

			$fields[] = array(
				'key'  => $key,
				'label' => $label !== '' ? $label : $key,
				'type' => $type,
				'show' => !$hasOptions || !isset($parts[3]) || !in_array(strtolower((string) $parts[3]), array('0', 'no', 'false', 'hide'), true),
			);
		}

		return $fields;
	}

	public function getExtraValues(object $file): array
	{
		$raw = (string) ($file->extra_data ?? '');
		$data = $raw !== '' ? json_decode($raw, true) : array();

		return is_array($data) ? $data : array();
	}

	private function getDb(): DatabaseInterface
	{
		return Factory::getContainer()->get(DatabaseInterface::class);
	}

	private function ensureColumn(string $table, string $column, string $alterQuery): void
	{
		$db = $this->getDb();
		$tableName = $db->replacePrefix($table);
		$columns = $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($tableName) . ' LIKE ' . $db->quote($column))->loadObjectList();

		if (!$columns)
		{
			$db->setQuery($db->replacePrefix($alterQuery))->execute();
		}
	}

	private function filterExtraData(array $input): array
	{
		$out = array();

		foreach ($this->getExtraFieldDefinitions() as $field)
		{
			$key = $field['key'];
			$value = trim((string) ($input[$key] ?? ''));

			if ($value !== '')
			{
				$out[$key] = $value;
			}
		}

		return $out;
	}

	private function getAutoFieldKey(string $label): string
	{
		return 'field_' . substr(md5($label), 0, 12);
	}

	private function getExtraFieldsConfig(): string
	{
		$params = ComponentHelper::getParams('com_prodfiles');
		$raw = (string) $params->get('extra_fields', '');

		if ($raw === '')
		{
			$raw = (string) $params->get('params.extra_fields', '');
		}

		return $raw;
	}

	private function getAllowedExtensions(): array
	{
		$params = PluginHelper::getPlugin('jshopping', 'prodfiles')->params ?? '';
		$registry = new \Joomla\Registry\Registry($params);

		return array_filter(array_map('trim', explode(',', strtolower((string) $registry->get('allowed_extensions', 'pdf,doc,docx,xls,xlsx,zip,rar,jpg,jpeg,png,txt')))));
	}

	private function getFileDefaultTitle(int $fileId): string
	{
		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select($db->quoteName('original_name'))
			->from($db->quoteName('#__plg_prodfiles_files'))
			->where($db->quoteName('id') . ' = ' . (int) $fileId);

		$name = (string) $db->setQuery($query)->loadResult();

		return $name !== '' ? pathinfo($name, PATHINFO_FILENAME) : ('File ' . $fileId);
	}

	private function formatBytes(int $bytes): string
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
