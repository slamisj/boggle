<?php



/**
 * Users authenticator.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class Authenticator extends NObject implements IAuthenticator
{
	/** @var NTableSelection */
	private $users;



	public function __construct(NTableSelection $users)
	{

	}


	/**
	 * Performs an authentication
	 * @param  array
	 * @return NIdentity
	 * @throws NAuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		$model = new GameModel();
		list($username, $password) = $credentials;
		
		//$row = $this->users->where('username', $username)->fetch();
    	$row = dibi::fetch('SELECT * FROM user WHERE email=%s', $username);

		if (!$row) {
			throw new NAuthenticationException("E-mail '$username' nenalezen.", self::IDENTITY_NOT_FOUND);
		}

		if ($row->password !== $model->calculateHash($password)) {
			throw new NAuthenticationException("Chybné heslo.", self::INVALID_CREDENTIAL);
		}

		unset($row->password);
    	dibi::query('UPDATE user SET ', 
            array('ip' => $_SERVER["REMOTE_ADDR"]),
                  'WHERE `id` = %i', $row->id);
		return new NIdentity($row->id, null, $row);
	}


}
