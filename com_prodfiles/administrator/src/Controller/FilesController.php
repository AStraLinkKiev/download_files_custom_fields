<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Component\Prodfiles\Administrator\Model\FilesModel;
use Joomla\Filesystem\File;
use RuntimeException;

class FilesController extends BaseController
{
	public function upload(): void
	{
		$this->checkToken('post');

		/** @var FilesModel $model */
		$model = $this->getModel('Files');
		$model->uploadFile($this->input->files->get('prodfiles_upload', array(), 'array'), $this->input->post);

		$this->setRedirect('index.php?option=com_prodfiles');
	}

	public function save(): void
	{
		$this->checkToken('post');

		/** @var FilesModel $model */
		$model = $this->getModel('Files');
		$model->saveMetadata((array) $this->input->post->get('prodfiles_meta', array(), 'array'), (array) $this->input->post->get('prodfiles_published', array(), 'array'));

		$this->app->enqueueMessage(Text::_('COM_PRODFILES_SAVE_SUCCESS'), 'message');
		$this->setRedirect('index.php?option=com_prodfiles');
	}

	public function delete(): void
	{
		if (!Session::checkToken('get'))
		{
			throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		/** @var FilesModel $model */
		$model = $this->getModel('Files');
		$model->deleteFiles(array($this->input->getInt('id')));

		$this->setRedirect('index.php?option=com_prodfiles');
	}

	public function download(): void
	{
		if (!Session::checkToken('get'))
		{
			throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		/** @var FilesModel $model */
		$model = $this->getModel('Files');
		$file = $model->getFile($this->input->getInt('id'));

		if (!$file)
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		$path = $model->getStoragePath() . '/' . basename($file->filename);

		if (!is_file($path))
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		while (ob_get_level())
		{
			ob_end_clean();
		}

		$name = (string) ($file->original_name ?: $file->filename);
		$fallbackName = str_replace('"', '', File::makeSafe($name));

		header('Content-Type: ' . ($file->mime ?: 'application/octet-stream'));
		header('Content-Length: ' . filesize($path));
		header('Content-Disposition: attachment; filename="' . addslashes($fallbackName) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
		header('Cache-Control: private');
		readfile($path);

		$this->app->close();
	}
}
