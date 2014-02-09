<?php



/**
 * Sign in/out presenters.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class SignPresenter extends BasePresenter
{


	/**
	 * Sign in form component factory.
	 * @return NAppForm
	 */
	protected function createComponentSignInForm()
	{
		$form = new NAppForm;
		$form->addText('email', 'E-mail:')
      ->addRule($form::EMAIL, 'Musíš zadat platný e-mail')
			->setRequired('Zadej e-mail.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadej heslo.');

		$form->addSubmit('send', 'Přihlásit');

		$form->onSuccess[] = callback($this, 'signInFormSubmitted');
		return $form;
	}
	
	/**
	 * Sign in form component factory.
	 * @return NAppForm
	 */
	protected function createComponentLostForm()
	{
		$form = new NAppForm;
		$form->addText('email', 'E-mail:')
      ->addRule($form::EMAIL, 'Musíš zadat platný e-mail')
			->setRequired('Zadej e-mail.');

		$form->addSubmit('send', 'Zaslat nové heslo');

		$form->onSuccess[] = callback($this, 'lostFormSubmitted');
		return $form;
	}

	protected function createComponentChangeForm()
	{
		$form = new NAppForm;
		$form->addText('pwd', 'Nové heslo:')
			 ->setRequired('Zadej nové heslo.');

		$form->addHidden('token', $this->context->getService('httpRequest')->getQuery('token'));
		$form->addSubmit('send', 'Nastavit heslo');

		$form->onSuccess[] = callback($this, 'changeFormSubmitted');
		return $form;
	}

	/**
	 * Sign in form component factory.
	 * @return NAppForm
	 */
	protected function createComponentRegForm()
	{
		$form = new NAppForm;
		$form->addText('name', 'Přezdívka:')
			->setRequired('Zadej přezdívku.');
			
		$form->addText('email', 'E-mail:')
      ->addRule($form::EMAIL, 'Musíš zadat platný e-mail')
			->setRequired('Zadej e-mail.');
			
		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadej heslo.');
		$form->addPassword('password2', 'Heslo znovu:')
		  ->setRequired('Zadej heslo znovu.')
		  ->addConditionOn($form["password"], $form::FILLED)
      ->addRule($form::EQUAL, "Hesla se musí shodovat.", $form["password"]);
			


		$form->addSubmit('send', 'Registrovat');

		$form->onSuccess[] = callback($this, 'regFormSubmitted');
		return $form;
	}


	public function actionFb()
	{
	 $facebook = $this->getFB();
	 $idFb = $facebook->getUser();
	 //$idFb = 1;
	 $m = $this->getModel();

	 // If the user has not installed the app, redirect them to the Login Dialog
		if (!$idFb) {
		        $loginUrl = $facebook->getLoginUrl();

		        print('<script> top.location.href=\'' . $loginUrl . '\'</script>');
		} else {
			// We may or may not have this data based on whether the user is logged in.
			//
			// If we have a $idFb id here, it means we know the user is logged into
			// Facebook, but we don't know if the access token is valid. An access
			// token is invalid if the user logged out of Facebook.

			if ($idFb) {
			  try {
			  	$this->log(1);
			  	$profile = $facebook->api('/me');
			  	/*$profile = array(
			  		'email' => 't@t.cz',
			  		'name' => 'Test'
			  		);*/
			  	$this->log(2);
			  	if ($this->getUser()->isLoggedIn()) {
			  		$this->log(3);
			  		$FbRec = $this->getModel()->getUserRec($this->getUser()->id);
			  		/*print_r($idFbRec);
			  		print_R($this->getUser());*/
			  		$this->log(4 . $this->getUser()->id);

			  		if ($FbRec["facebookid"] != $idFb) {
			  			$this->getModel()->setFbId($this->getUser()->id, $idFb);
			  		}
			  	} else {
			  		$this->log(5);
			  		$idUser = $m->getUserIdByFbId($idFb);
			  		$this->log($idUser);
			  		if (!$idUser) {
			  			$idUser = $m->register(array(
			  			'email' => $profile["email"],
			  			'name' => $profile["name"],
			  			'facebookid' => $idFb,
			  			'password' => 'heslo'
			  			));	
			  		}
			  		$this->log('logging' . $idUser);
			  		$this->getUser()->login(new NIdentity($idUser));
			  		$this->log('logged' . $this->getUser()->id);
			  	}

    			//$this->redirect('Homepage:');
			    // Proceed knowing you have a logged in user who's authenticated.
			    //$this->template->user_profile = $facebook->api('/me');
			    //$this->template->friends = $facebook->api('/me/friends');
			  } catch (FacebookApiException $e) {
			    error_log($e);
			    $idFb = null;
			  }
			}
		}
	}

  	public function actionHash()
	{
	  $user = $this->context->getService('httpRequest')->getQuery('user'); 
	  $pwd = $this->context->getService('httpRequest')->getQuery('pwd'); 
	  if (!is_null($user)) {
  		try {
  			$this->getUser()->login($user, $pwd);
  			$this->redirect('Homepage:');
  		} catch (NAuthenticationException $e) {
  			$form->addError($e->getMessage());
  		}
  	}
	}

	public function signInFormSubmitted($form)
	{
		try {
			$values = $form->getValues();
			$this->getUser()->login($values->email, $values->password);
			$this->redirect('Homepage:');

		} catch (NAuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	public function lostFormSubmitted($form)
	{
		try {
			$values = $form->getValues();
			$token = $this->getModel()->lostPwd($values["email"]);
			$mail = new NMail;
			$mail->setFrom('Boggle <slamisj@seznam.cz>')
    			 ->addTo($values["email"])
    			 ->setSubject('Zapomenuté heslo na boggle.cz')
    			 ->setBody("Ahoj,\n pro změnu hesla klikni na následující odkaz\n http://boggle.cz/sign/change?token=$token")
    			 ->send();
    	    $this->flashMessage('Na tvůj e-mail byl odeslán odkaz pro změnu hesla.');
			$this->redirect('Homepage:');
		} catch (RuntimeException $e) {
			$this->flashMessage($e->getMessage());
		}
	}

	public function changeFormSubmitted($form)
	{
		try {
			$values = $form->getValues();
			$this->getModel()->changePwd($values["pwd"], $values["token"]);

    	    $this->flashMessage('Heslo bylo změněno.');
			$this->redirect('Sign:in');
		} catch (RuntimeException $e) {
			$this->flashMessage($e->getMessage());
			$this->redirect('Homepage:');
		}
	}
	
	public function regFormSubmitted($form)
	{
		try {
			$values = $form->getValues();
			
			$this->getModel()->register($values, $this->getUser()->isLoggedIn() ? $this->getUser()->id : null);
			$this->getUser()->login($values->email, $values->password);
			$this->redirect('Homepage:');

		} catch (NAuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}



	public function actionOut()
	{
		$this->getUser()->logout();
		$this->flashMessage('Byl jsi odhlášen.');
		$this->redirect('in');
	}

}
