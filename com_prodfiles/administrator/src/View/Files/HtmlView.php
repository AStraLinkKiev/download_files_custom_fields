<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Administrator\View\Files;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Prodfiles\Administrator\Model\FilesModel;

class HtmlView extends BaseHtmlView
{
	public array $items = array();

	public FilesModel $model;

	public function display($tpl = null): void
	{
		/** @var FilesModel $model */
		$model = $this->getModel();
		$this->model = $model;
		$this->items = $model->getItems();

		ToolbarHelper::title(Text::_('COM_PRODFILES_MANAGER_FILES'), 'download');
		ToolbarHelper::preferences('com_prodfiles');

		parent::display($tpl);
	}
}
