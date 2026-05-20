<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Site\View\Products;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Prodfiles\Site\Model\ProductsModel;

class HtmlView extends BaseHtmlView
{
	public int $productId = 0;
	public ?object $product = null;
	public array $files = array();
	public array $products = array();

	public function display($tpl = null): void
	{
		/** @var ProductsModel $model */
		$model = $this->getModel();

		$this->productId = (int) Factory::getApplication()->getInput()->getInt('product_id', 0);
		$this->products = $model->getProductsWithFiles();

		if ($this->productId > 0)
		{
			$this->product = $model->getProduct($this->productId);
			$this->files = $model->getProductFiles($this->productId);
		}

		parent::display($tpl);
	}
}
