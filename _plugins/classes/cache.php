<?php
/**
 * @package Swiftlet
 * @copyright 2009 ElbertF http://elbertf.com
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU Public License
 */

if ( !isset($model) ) die('Direct access to this file is not allowed');

/**
 * Cache
 * @abstract
 */
class cache
{
	public
		$ready = FALSE
		;

	private
		$model,
		$contr,

		$cacheLifeTime = 3600
		;

	/**
	 * Initialize buffer
	 * @param object $model
	 */
	function __construct($model)
	{
		$this->model = $model;
		$this->contr = $model->contr;

		$this->ready = TRUE;

		$this->read();
	}

	/**
	 * Read a file from cache
	 * @return bool
	 */
	private function read()
	{
		$model = $this->model;
		$contr = $model->contr;

		if ( !empty($model->session->ready) && !empty($model->user->ready) && $model->session->get('user id') != user::guestId )
		{
			return;
		}

		if ( $model->caching && empty($model->POST_raw) && empty($_POST) )
		{
			if ( $handle = opendir($contr->rootPath . 'cache') )
			{
				while ( $filename = readdir($handle) )
				{
					if ( is_file($contr->rootPath . 'cache/' . $filename) )
					{					
						list($time, $hash) = explode('_', $filename);

						if ( $time <= time() )
						{
							@unlink($contr->rootPath . 'cache/' . $filename);
						}
						else
						{
							if ( $hash == sha1($_SERVER['REQUEST_URI']) )
							{
								if ( $model->debugMode )
								{
									echo '<!-- Served from cache -->' . "\n";
								}
								
								echo file_get_contents($contr->rootPath . 'cache/' . $filename);

								$model->buffer->flush();

								exit;
							}
						}
					}
				}

				closedir($handle);
			}
		}
	}

	/**
	 * Write a file to cache
	 * @param string $v
	 */
	function write(&$contents)
	{
		$model = $this->model;
		$contr = $model->contr;

		if ( !empty($model->session->ready) && !empty($model->user->ready) && $model->session->get('user id') != user::guestId )
		{
			return;
		}

		if ( $headers = headers_list() )
		{
			foreach ( $headers as $header )
			{
				if ( preg_match('/^Content\-type:/i', $header) )
				{
					return;
				}
			}
		}

		if ( $model->caching && empty($model->POST_raw) )
		{
			if ( !is_dir($contr->rootPath . 'cache') )
			{
				$this->model->error(FALSE, 'Directory "/cache" does not exist.', __FILE__, __LINE__);
			}

			if ( !is_writable($contr->rootPath . 'cache') )
			{
				$this->model->error(FALSE, 'Directory "/cache" is not writable.', __FILE__, __LINE__);
			}

			$filename = ( time() + $this->cacheLifeTime ) . '_' . sha1($_SERVER['REQUEST_URI']);

			if ( !$handle = fopen($contr->rootPath . 'cache/' . $filename, 'a+') )
			{
				$this->model->error(FALSE, 'Could not open file "/cache/' . $filename . '".', __FILE__, __LINE__);
			}

			if ( fwrite($handle, $contents) === FALSE )
			{
				$this->model->error(FALSE, 'Could not write to file "/cache/' . $filename . '".', __FILE__, __LINE__);
			}

			fclose($handle);
		}

		unset($contents);
	}
	
	/**
	 * Clear cache
	 */
	function clear()
	{
		$model = $this->model;
		$contr = $model->contr;
		
		if ( $handle = opendir($contr->rootPath . 'cache') )
		{
			while ( $filename = readdir($handle) )
			{
				if ( is_file($contr->rootPath . 'cache/' . $filename) )
				{					
					@unlink($contr->rootPath . 'cache/' . $filename);
				}
			}

			closedir($handle);
		}
	}
}
