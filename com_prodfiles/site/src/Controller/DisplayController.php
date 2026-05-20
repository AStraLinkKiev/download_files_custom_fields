<?php
/**
 * @package     com_prodfiles
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Component\Prodfiles\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Component\Prodfiles\Site\Helper\RouteHelper;
use Joomla\Component\Prodfiles\Site\Model\ProductsModel;
use Joomla\Filesystem\File;
use RuntimeException;
use ZipArchive;

class DisplayController extends BaseController
{
	protected $default_view = 'products';

	public function search(): void
	{
		$this->checkToken('get');

		/** @var ProductsModel $model */
		$model = $this->getModel('Products');
		$items = $model->searchProducts($this->input->getString('q', ''), 12);

		foreach ($items as $item)
		{
			$item->url = RouteHelper::getProductRoute((int) $item->product_id);
		}

		echo new JsonResponse($items);

		$this->app->close();
	}

	public function download(): void
	{
		$productId = (int) $this->input->getInt('product_id', 0);
		$fileId    = (int) $this->input->getInt('id', 0);

		/** @var ProductsModel $model */
		$model = $this->getModel('Products');
		$file  = $model->getProductFile($productId, $fileId);

		if (!$file)
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		$this->sendFile($model->getStoragePath() . '/' . basename($file->filename), $file->original_name ?: $file->filename, $file->mime ?: 'application/octet-stream');
	}

	public function zip(): void
	{
		if (!Session::checkToken('post'))
		{
			throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		if (!class_exists(ZipArchive::class))
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_ZIP_NOT_AVAILABLE'), 500);
		}

		$productId = (int) $this->input->post->getInt('product_id', 0);
		$fileIds   = array_map('intval', (array) $this->input->post->get('files', array(), 'array'));
		$fileIds   = array_values(array_unique(array_filter($fileIds)));

		if ($productId <= 0 || !$fileIds)
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_SELECT_FILES'), 400);
		}

		/** @var ProductsModel $model */
		$model = $this->getModel('Products');
		$files = $model->getProductFiles($productId, $fileIds);

		if (!$files)
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		$tmp = tempnam(sys_get_temp_dir(), 'prodfiles_');
		$zip = new ZipArchive();

		if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true)
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_ZIP_CREATE_FAILED'), 500);
		}

		$usedNames = array();
		$added = 0;

		foreach ($files as $file)
		{
			$path = $model->getStoragePath() . '/' . basename($file->filename);

			if (!is_file($path))
			{
				continue;
			}

			$name = File::makeSafe((string) ($file->original_name ?: $file->filename));
			$name = $this->deduplicateZipName($name !== '' ? $name : ('file-' . (int) $file->id), $usedNames);

			if ($zip->addFile($path, $name))
			{
				$added++;
			}
		}

		$zip->close();

		if ($added === 0)
		{
			@unlink($tmp);
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		$product = $model->getProduct($productId);
		$name = File::makeSafe(($product && $product->product_name ? $product->product_name : 'product-' . $productId) . '-files.zip');

		$this->sendFile($tmp, $name, 'application/zip', true);
	}

	private function sendFile(string $path, string $name, string $mime, bool $deleteAfter = false): void
	{
		if (!is_file($path))
		{
			throw new RuntimeException(Text::_('COM_PRODFILES_FILE_NOT_FOUND'), 404);
		}

		while (ob_get_level())
		{
			ob_end_clean();
		}

		$fallbackName = str_replace('"', '', File::makeSafe($name));

		header('Content-Type: ' . $mime);
		header('Content-Length: ' . filesize($path));
		header('Content-Disposition: attachment; filename="' . addslashes($fallbackName) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
		header('Cache-Control: private');
		readfile($path);

		if ($deleteAfter)
		{
			@unlink($path);
		}

		$this->app->close();
	}

	private function deduplicateZipName(string $name, array &$usedNames): string
	{
		$base = pathinfo($name, PATHINFO_FILENAME);
		$ext  = pathinfo($name, PATHINFO_EXTENSION);
		$out  = $name;
		$i    = 2;

		while (isset($usedNames[strtolower($out)]))
		{
			$out = $base . '-' . $i . ($ext !== '' ? '.' . $ext : '');
			$i++;
		}

		$usedNames[strtolower($out)] = true;

		return $out;
	}
}
