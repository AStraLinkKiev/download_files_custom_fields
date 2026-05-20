<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class ProductsModel extends BaseDatabaseModel
{
	private ?array $productColumns = null;

	public function searchProducts(string $search, int $limit = 12): array
	{
		$search = trim($search);

		if ($search === '' || mb_strlen($search) < 2)
		{
			return array();
		}

		$db = $this->getDb();
		$nameColumns = $this->getNameColumns();

		if (!$nameColumns)
		{
			return array();
		}

		$query = $db->getQuery(true)
			->select($db->quoteName('p.product_id'))
			->select($this->getNameExpression() . ' AS ' . $db->quoteName('product_name'))
			->select('COUNT(fp.file_id) AS ' . $db->quoteName('files_count'))
			->from($db->quoteName('#__jshopping_products', 'p'))
			->innerJoin($db->quoteName('#__plg_prodfiles_products', 'fp') . ' ON fp.product_id = p.product_id')
			->innerJoin($db->quoteName('#__plg_prodfiles_files', 'f') . ' ON f.id = fp.file_id AND f.published = 1')
			->group($db->quoteName('p.product_id'))
			->order($db->quoteName('product_name') . ' ASC');

		$conditions = array();
		$pattern = '%' . $search . '%';

		foreach ($nameColumns as $i => $column)
		{
			$key = ':search' . $i;
			$conditions[] = $db->quoteName('p.' . $column) . ' LIKE ' . $key;
			$query->bind($key, $pattern);
		}

		if (in_array('product_ean', $this->getProductColumns(), true))
		{
			$conditions[] = $db->quoteName('p.product_ean') . ' LIKE :ean';
			$query->bind(':ean', $pattern);
		}

		$query->where('(' . implode(' OR ', $conditions) . ')');

		if (in_array('product_publish', $this->getProductColumns(), true))
		{
			$query->where($db->quoteName('p.product_publish') . ' = 1');
		}

		return $db->setQuery($query, 0, max(1, $limit))->loadObjectList();
	}

	public function getProduct(int $productId): ?object
	{
		if ($productId <= 0 || !$this->getNameColumns())
		{
			return null;
		}

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select($db->quoteName('p.product_id'))
			->select($this->getNameExpression() . ' AS ' . $db->quoteName('product_name'))
			->from($db->quoteName('#__jshopping_products', 'p'))
			->where($db->quoteName('p.product_id') . ' = :product_id')
			->bind(':product_id', $productId, ParameterType::INTEGER);

		return $db->setQuery($query, 0, 1)->loadObject() ?: null;
	}

	public function getProductsWithFiles(): array
	{
		if (!$this->getNameColumns())
		{
			return array();
		}

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select($db->quoteName('p.product_id'))
			->select($this->getNameExpression() . ' AS ' . $db->quoteName('product_name'))
			->select('COUNT(fp.file_id) AS ' . $db->quoteName('files_count'))
			->from($db->quoteName('#__jshopping_products', 'p'))
			->innerJoin($db->quoteName('#__plg_prodfiles_products', 'fp') . ' ON fp.product_id = p.product_id')
			->innerJoin($db->quoteName('#__plg_prodfiles_files', 'f') . ' ON f.id = fp.file_id AND f.published = 1')
			->group($db->quoteName('p.product_id'))
			->order($db->quoteName('product_name') . ' ASC');

		if (in_array('product_publish', $this->getProductColumns(), true))
		{
			$query->where($db->quoteName('p.product_publish') . ' = 1');
		}

		return $db->setQuery($query)->loadObjectList();
	}

	public function getProductFiles(int $productId, array $fileIds = array()): array
	{
		if ($productId <= 0)
		{
			return array();
		}

		$db = $this->getDb();
		$query = $db->getQuery(true)
			->select('f.*')
			->from($db->quoteName('#__plg_prodfiles_files', 'f'))
			->innerJoin($db->quoteName('#__plg_prodfiles_products', 'fp') . ' ON fp.file_id = f.id')
			->where($db->quoteName('fp.product_id') . ' = :product_id')
			->where($db->quoteName('f.published') . ' = 1')
			->order($db->quoteName('fp.ordering') . ' ASC, ' . $db->quoteName('f.title') . ' ASC')
			->bind(':product_id', $productId, ParameterType::INTEGER);

		$fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));

		if ($fileIds)
		{
			$query->where($db->quoteName('f.id') . ' IN (' . implode(',', $fileIds) . ')');
		}

		return $db->setQuery($query)->loadObjectList();
	}

	public function getProductFile(int $productId, int $fileId): ?object
	{
		if ($productId <= 0 || $fileId <= 0)
		{
			return null;
		}

		$files = $this->getProductFiles($productId, array($fileId));

		return $files[0] ?? null;
	}

	public function getStoragePath(): string
	{
		return JPATH_ROOT . '/media/plg_prodfiles/files';
	}

	public function formatFileMeta(object $file): string
	{
		$name = (string) ($file->original_name ?: $file->filename);
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
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

	public function getExtraFieldDefinitions(bool $siteOnly = true): array
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

			$show = !$hasOptions || !isset($parts[3]) || !in_array(strtolower((string) $parts[3]), array('0', 'no', 'false', 'hide'), true);

			if ($siteOnly && !$show)
			{
				continue;
			}

			$fields[] = array(
				'key' => $key,
				'label' => $label !== '' ? $label : $key,
				'type' => strtolower((string) ($hasOptions ? ($parts[2] ?? 'text') : 'text')),
				'show' => $show,
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

	private function getDb(): DatabaseInterface
	{
		return Factory::getContainer()->get(DatabaseInterface::class);
	}

	private function getProductColumns(): array
	{
		if ($this->productColumns !== null)
		{
			return $this->productColumns;
		}

		$db = $this->getDb();
		$table = $db->replacePrefix('#__jshopping_products');
		$rows = $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($table))->loadObjectList();
		$this->productColumns = array_map(static fn($row) => (string) $row->Field, $rows);

		return $this->productColumns;
	}

	private function getNameColumns(): array
	{
		$columns = $this->getProductColumns();
		$current = 'name_' . Factory::getApplication()->getLanguage()->getTag();
		$ordered = array();

		if (in_array($current, $columns, true))
		{
			$ordered[] = $current;
		}

		foreach ($columns as $column)
		{
			if (preg_match('/^name(_|-|$)/', $column) && !in_array($column, $ordered, true))
			{
				$ordered[] = $column;
			}
		}

		return $ordered;
	}

	private function getNameExpression(): string
	{
		$db = $this->getDb();
		$parts = array();

		foreach ($this->getNameColumns() as $column)
		{
			$parts[] = 'NULLIF(' . $db->quoteName('p.' . $column) . ', ' . $db->quote('') . ')';
		}

		$parts[] = $db->quote(Text::_('COM_PRODFILES_UNNAMED_PRODUCT'));

		return 'COALESCE(' . implode(', ', $parts) . ')';
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
