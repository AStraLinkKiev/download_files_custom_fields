<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

abstract class RouteHelper
{
	public static function getProductRoute(int $productId): string
	{
		$query = 'index.php?option=com_prodfiles&view=products&product_id=' . (int) $productId;
		$itemId = self::getMenuItemId();

		if ($itemId > 0)
		{
			$query .= '&Itemid=' . $itemId;
		}

		return Route::_($query, false);
	}

	public static function getMenuItemId(): int
	{
		$app = Factory::getApplication();
		$menu = $app->getMenu();
		$active = $menu ? $menu->getActive() : null;

		if ($active && isset($active->query['option']) && $active->query['option'] === 'com_prodfiles')
		{
			return (int) $active->id;
		}

		if (!$menu)
		{
			return 0;
		}

		foreach ($menu->getItems('component', 'com_prodfiles') ?: array() as $item)
		{
			if (isset($item->query['view']) && $item->query['view'] === 'products')
			{
				return (int) $item->id;
			}
		}

		$item = $menu->getItems('component', 'com_prodfiles', true);

		return $item ? (int) $item->id : 0;
	}

	public static function getProductSlug(int $productId): string
	{
		$product = self::getProduct($productId);
		$name = $product ? (string) $product->product_name : '';
		$slug = self::stringToSlug($name);

		if ($slug === '')
		{
			$slug = 'product-' . (int) $productId;
		}

		return self::slugNeedsId($slug, $productId) ? $slug . '-' . (int) $productId : $slug;
	}

	public static function getProductIdBySlug(string $slug): int
	{
		$slug = trim($slug);

		if ($slug === '')
		{
			return 0;
		}

		if (preg_match('/-(\d+)$/', $slug, $match))
		{
			$productId = (int) $match[1];

			if ($productId > 0)
			{
				return $productId;
			}
		}

		foreach (self::getProducts() as $product)
		{
			if (self::getProductSlug((int) $product->product_id) === $slug)
			{
				return (int) $product->product_id;
			}
		}

		return 0;
	}

	private static function getProduct(int $productId): ?object
	{
		foreach (self::getProducts() as $product)
		{
			if ((int) $product->product_id === $productId)
			{
				return $product;
			}
		}

		return null;
	}

	private static function getProducts(): array
	{
		static $products = null;

		if ($products !== null)
		{
			return $products;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$columns = self::getProductColumns($db);
		$nameColumns = self::getNameColumns($columns);

		if (!$nameColumns)
		{
			$products = array();

			return $products;
		}

		$query = $db->getQuery(true)
			->select($db->quoteName('p.product_id'))
			->select(self::getNameExpression($db, $nameColumns) . ' AS ' . $db->quoteName('product_name'))
			->from($db->quoteName('#__jshopping_products', 'p'))
			->innerJoin($db->quoteName('#__plg_prodfiles_products', 'fp') . ' ON fp.product_id = p.product_id')
			->innerJoin($db->quoteName('#__plg_prodfiles_files', 'f') . ' ON f.id = fp.file_id AND f.published = 1')
			->group($db->quoteName('p.product_id'))
			->order($db->quoteName('product_name') . ' ASC');

		if (in_array('product_publish', $columns, true))
		{
			$query->where($db->quoteName('p.product_publish') . ' = 1');
		}

		$products = $db->setQuery($query)->loadObjectList();

		return $products;
	}

	private static function slugNeedsId(string $slug, int $productId): bool
	{
		$matches = 0;

		foreach (self::getProducts() as $product)
		{
			if (self::stringToSlug((string) $product->product_name) === $slug)
			{
				$matches++;
			}
		}

		return $matches > 1;
	}

	private static function getProductColumns(DatabaseInterface $db): array
	{
		static $columns = null;

		if ($columns !== null)
		{
			return $columns;
		}

		$table = $db->replacePrefix('#__jshopping_products');
		$rows = $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($table))->loadObjectList();
		$columns = array_map(static fn($row) => (string) $row->Field, $rows);

		return $columns;
	}

	private static function getNameColumns(array $columns): array
	{
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

	private static function getNameExpression(DatabaseInterface $db, array $nameColumns): string
	{
		$parts = array();

		foreach ($nameColumns as $column)
		{
			$parts[] = 'NULLIF(' . $db->quoteName('p.' . $column) . ', ' . $db->quote('') . ')';
		}

		$parts[] = $db->quote('product');

		return 'COALESCE(' . implode(', ', $parts) . ')';
	}

	private static function stringToSlug(string $value): string
	{
		$value = mb_strtolower(trim($value));
		$value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value);
		$value = trim((string) $value, '-');

		return $value;
	}
}
