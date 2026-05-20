<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Component\Prodfiles\Site\Helper\RouteHelper;

class Router implements RouterInterface
{
	public function __construct(
		private CMSApplicationInterface $app,
		private AbstractMenu $menu
	) {
	}

	public function preprocess($query): array
	{
		return $query;
	}

	public function build(&$query): array
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

	public function parse(&$segments): array
	{
		$vars = array('view' => 'products');

		if (!empty($segments[0]))
		{
			$vars['product_id'] = RouteHelper::getProductIdBySlug((string) $segments[0]);
			array_shift($segments);
		}

		return $vars;
	}
}
