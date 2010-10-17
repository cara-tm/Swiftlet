<?php
/**
 * @package Swiftlet
 * @copyright 2009 ElbertF http://elbertf.com
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU Public License
 */

if ( !isset($this) ) die('Direct access to this file is not allowed');

/**
 * E-mail
 * @abstract
 */
class Email_Plugin extends Plugin
{
	public
		$version    = '1.0.0',
		$compatible = array('from' => '1.3.0', 'to' => '1.3.*'),
		$hooks      = array('init' => 1, 'email' => 1)
		;

	function init()
	{
		$this->ready = TRUE;
	}

	function email(&$params)
	{
		$params['success'] = $app->email->send($params);
	}

	/**
	 * Send an e-mail
	 */
	function send($params)
	{
		$headers = array(
			'To'           => '<' . $params['to'] . '>',
			'From'         => $this->app->siteName . ' <' . $this->app->adminEmail . '>',
			'MIME-Version' => '1.0',
			'Content-type' => 'text/html; charset=UTF-8',
			'X-Mailer'     => 'Swiftlet - http://swiftlet.org'
			);

		$headers = array_merge($headers, $params['headers']);

		$head = '';

		foreach ( $headers as $k => $v )
		{
			$head .= $k . ': ' . $v . "\r\n";
		}

		return mail($params['to'], $params['subject'], $params['body'], $head);
	}
}