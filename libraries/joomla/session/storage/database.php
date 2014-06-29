<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Session
 *
 * @copyright   Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Database session storage handler for PHP
 *
 * @package     Joomla.Platform
 * @subpackage  Session
 * @see         http://www.php.net/manual/en/function.session-set-save-handler.php
 * @since       11.1
 */
class JSessionStorageDatabase extends JSessionStorage
{
	/**
	 *Prepared statement variable to keep the statement query
	 * without creating it again and again
	 */
	public static $updateStatement = null;

	/**
	 * Read the data for a particular session identifier from the SessionHandler backend.
	 *
	 * @param   string  $id  The session identifier.
	 *
	 * @return  string  The session data.
	 *
	 * @since   11.1
	 */

	public function read($id)
	{
		// Get the database connection object and verify its connected.
		$db = JFactory::getDbo();

		try
		{
			// Get the session data from the database table.
			$query = $db->getQuery(true)
				->select($db->quoteName('data'))
				->from($db->quoteName('#__session'))
				->where($db->quoteName('session_id') . ' = ' . $db->quote($id));

			$db->setQuery($query);

			$result = (string) $db->loadResult();

			$result = str_replace('\0\0\0', chr(0) . '*' . chr(0), $result);

			return $result;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Write session data to the SessionHandler backend.
	 *
	 * @param   string  $id    The session identifier.
	 * @param   string  $data  The session data.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   11.1
	 */
	public function write($id, $data)
	{
		$data = str_replace(chr(0) . '*' . chr(0), '\0\0\0', $data);

		if (!self::$updateStatement)
		{
			$conf = JFactory::getConfig();
			$dbtype = $conf->get('dbtype');
			$dbhost = $conf->get('host');
			$dbname = $conf->get('db');
			$dbuser = $conf->get('user');
			$dbpass = $conf->get('password');
			$query = 'update ltzvy_session set data=?,time=? where session_id=?';

			$conn = new PDO("$dbtype:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
			self::$updateStatement = $conn->prepare($query);
		}

		if (self::$updateStatement->execute(array($data, (int) time(), $id)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Destroy the data for a particular session identifier in the SessionHandler backend.
	 *
	 * @param   string  $id  The session identifier.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   11.1
	 */
	public function destroy($id)
	{
		// Get the database connection object and verify its connected.
		$db = JFactory::getDbo();

		try
		{
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__session'))
				->where($db->quoteName('session_id') . ' = ' . $db->quote($id));

			// Remove a session from the database.
			$db->setQuery($query);

			return (boolean) $db->execute();
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Garbage collect stale sessions from the SessionHandler backend.
	 *
	 * @param   integer  $lifetime  The maximum age of a session.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   11.1
	 */
	public function gc($lifetime = 1440)
	{
		// Get the database connection object and verify its connected.
		$db = JFactory::getDbo();

		// Determine the timestamp threshold with which to purge old sessions.
		$past = time() - $lifetime;

		try
		{
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__session'))
				->where($db->quoteName('time') . ' < ' . $db->quote((int) $past));

			// Remove expired sessions from the database.
			$db->setQuery($query);

			return (boolean) $db->execute();
		}
		catch (Exception $e)
		{
			return false;
		}
	}
}
