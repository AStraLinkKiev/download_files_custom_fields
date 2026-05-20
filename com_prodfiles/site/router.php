<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\Component\Prodfiles\Site\Helper\RouteHelper;

if (!class_exists(RouteHelper::class))
{
	require_once __DIR__ . '/src/Helper/RouteHelper.php';
}

function ProdfilesBuildRoute(&$query): array
{
	$segments = array();

	if (isset($query['product_id']))
	{
		$segments[] = RouteHelper::getProductSlug((int) $query['product_id']);
		unset($query['product_id']);
	}

	if (isset($query['view']) && $query['view'] === 'products')
	{
		unset($query['view']);
	}

	return $segments;
}

function ProdfilesParseRoute($segments): array
{
	$vars = array('view' => 'products');

	if (!empty($segments[0]))
	{
		$vars['product_id'] = RouteHelper::getProductIdBySlug((string) $segments[0]);
	}

	return $vars;
}
