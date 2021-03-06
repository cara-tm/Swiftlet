<?php
/**
 * @package Swiftlet
 * @copyright 2009 ElbertF http://elbertf.com
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU Public License
 */

if ( !isset($this) ) die('Direct access to this file is not allowed');

class Input_Plugin extends Plugin
{
	public
		$version    = '1.0.0',
		$compatible = array('from' => '1.3.0', 'to' => '1.3.*'),
		$hooks      = array('footer' => 1, 'init' => 2)
		;

	public
		$args           = array(),
		$args_html_safe = array(),
		$errors         = array(),
		$GET_html_safe  = array(),
		$GET_raw        = array(),
		$POST_html_safe = array(),
		$POST_raw       = array(),
		$POST_valid     = array()
		;

	private
		$typesRegex = array(
			'bool'   => '/^.*$/',
			'empty'  => '/^$/',
			'int'    => '/^-?[0-9]{1,255}$/',
			'string' => '/^.{1,255}$/',
			'email'  => '/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i'
			)
		;

	/*
	 * Implement init hook
	 */
	function init()
	{
		/**
		 * Authenticity token to secure forms
		 * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
		 */
		$this->authToken = sha1(( isset($this->app->session) ? $this->app->session->id : '' ) . phpversion() . $this->app->config['sysPassword'] . $this->app->userIp . ( !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' ));

		if ( ( !empty($_POST) && !isset($_POST['auth-token']) ) || ( isset($_POST['auth-token']) && $_POST['auth-token'] != $this->authToken ) )
		{
			$this->app->error(FALSE, 'The form has expired, please go back and try again (wrong or missing authenticity token).', __FILE__, __LINE__);
		}

		if ( isset($_POST['auth-token']) )
		{
			unset($_POST['auth-token']);
		}

		$this->input_sanitize();

	}

	/*
	 * Implement footer hook
	 */
	function footer()
	{
		if ( !empty($this->errors) )
		{
			$this->view->load('input_errors.html.php');
		}
	}

	/**
	 * Redirect to confirmation page
	 * @param string $notice
	 */
	function confirm($notice)
	{
		$this->view->notice = $notice;

		$this->view->load('confirm.html.php');

		$this->app->end();
	}

	/**
	 * Undo magic quotes
	 * @param mixed $v
	 * @return mixed $v
	 * @see http://php.net/magic_quotes
	 */
	private function undo_magic_quotes($v)
	{
		return is_array($v) ? array_map(array($this, 'undo_magic_quotes'), $v) : stripslashes($v);
	}

	/**
	 * Sanatize user input
	 */
	private function input_sanitize()
	{
		/**
		 * Recursively remove magic quotes
		 */
		if ( function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() )
		{
			$_GET    = array_map(array($this, 'undo_magic_quotes'), $_GET);
			$_POST   = array_map(array($this, 'undo_magic_quotes'), $_POST);
			$_COOKIE = array_map(array($this, 'undo_magic_quotes'), $_COOKIE);
		}

		/*
		 * $_POST and $_GET values can't be trusted
		 * If neccesary, access them through $this->POST_raw and $this->GET_raw
		 */
		$this->POST_raw = isset($_POST)            ? $_POST            : array();
		$this->GET_raw  = isset($_GET)             ? $_GET             : array();
		$this->args     = isset($this->view->args) ? $this->view->args : array();

		foreach ( $this->POST_raw as $k => $v )
		{
			$this->POST_html_safe[$k] = $this->view->h($v);
		}

		foreach ( $this->GET_raw as $k => $v )
		{
			$this->GET_html_safe[$k] = $this->view->h($v);
		}

		foreach ( $this->args as $k => $v )
		{
			$this->args_html_safe[$k] = $this->view->h($v);
		}

		$this->app->hook('input_sanitize');
	}

	/**
	 * Validate POST data
	 * @param array $vars
	 */
	function validate($vars)
	{
		$this->errors = array();

		$vars['confirm'] = 'bool';

		foreach ( $vars as $var => $types )
		{
			if ( !isset($this->POST_raw[$var]) )
			{
				$this->POST_raw[$var]       = FALSE;
				$this->POST_html_safe[$var] = FALSE;
				$this->POST_valid[$var]     = FALSE;
			}
			else
			{
				$this->POST_valid[$var] = FALSE;

				$regexes = array();

				foreach ( explode(',', $types) as $type )
				{
					$type = trim($type);

					$regexes[] = isset($this->typesRegex[$type]) ? $this->typesRegex[$type] : $type;
				}

				$this->POST_valid[$var] = $this->check($this->POST_raw[$var], $regexes);

				if ( $this->POST_valid[$var] === FALSE )
				{
					$this->errors[$var] = $this->view->t('Invalid value');
				}
			}
		}

		$this->app->hook('input_sanitize');
	}

	/*
	 * Check variables against regulare expressions
	 * @param mixed $var
	 * @params array $regexes
	 * @return bool
	 */
	private function check($var, $regexes)
	{
		if ( is_array($var) )
		{
			foreach ( $var as $k => $v )
			{
				$var[$k] = $this->check($v, $regexes);
			}

			return $var;
		}
		else
		{
			foreach ( $regexes as $regex )
			{
				if ( preg_match($regex, $var) )
				{
					return $var;
				}
			}

			return FALSE;
		}
	}
}
