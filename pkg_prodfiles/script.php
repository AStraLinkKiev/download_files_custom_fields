<?php
/**
 * @package     pkg_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class Pkg_ProdfilesInstallerScript
{
	private const MIN_JOOMSHOPPING_VERSION = '5.0.0';

	public function preflight($type, $parent)
	{
		$version = $this->getJoomShoppingVersion();

		if ($version === '')
		{
			Factory::getApplication()->enqueueMessage(Text::_('PKG_PRODFILES_JOOMSHOPPING_MISSING'), 'error');

			return false;
		}

		if (version_compare($version, self::MIN_JOOMSHOPPING_VERSION, '<'))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_PRODFILES_JOOMSHOPPING_VERSION_ERROR', self::MIN_JOOMSHOPPING_VERSION, $version), 'error');

			return false;
		}

		return true;
	}

	public function postflight($type, $parent)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
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
			$db->setQuery($query)->execute();
		}

		$tableName = $db->replacePrefix('#__plg_prodfiles_files');
		$columns = $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($tableName) . ' LIKE ' . $db->quote('extra_data'))->loadObjectList();

		if (!$columns)
		{
			$db->setQuery('ALTER TABLE ' . $db->quoteName($tableName) . ' ADD COLUMN ' . $db->quoteName('extra_data') . ' text NULL AFTER ' . $db->quoteName('description'))->execute();
		}

		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('jshopping'))
			->where($db->quoteName('element') . ' = ' . $db->quote('prodfiles'));

		$db->setQuery($query)->execute();

		return true;
	}

	private function getJoomShoppingVersion(): string
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName('manifest_cache'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_jshopping'));

		$manifest = (string) $db->setQuery($query, 0, 1)->loadResult();

		if ($manifest === '')
		{
			return '';
		}

		$data = json_decode($manifest, true);
		$version = is_array($data) ? (string) ($data['version'] ?? '') : '';

		if ($version !== '')
		{
			return $version;
		}

		$manifestPath = JPATH_ADMINISTRATOR . '/components/com_jshopping/jshopping.xml';

		if (is_file($manifestPath))
		{
			$xml = simplexml_load_file($manifestPath);

			if ($xml && isset($xml->version))
			{
				return trim((string) $xml->version);
			}
		}

		return '';
	}
}
