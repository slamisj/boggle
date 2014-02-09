<?php

/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class JsonPresenter extends BasePresenter
{

  public function send($data)
  {
    $this->sendResponse(new NJsonResponse($data));
  }
	public function renderGame()
	{
    $data = array();
    if ($idUser = $this->loginUserBySecret()) {
      $data = $this->getModel()->getGame($idUser, null);
      unset($data["users"]);
      $this->send($data);
    }
  }
  public function renderEval()
  {
    if ($idUser = $this->loginUserBySecret()) {
      $words = $this->context->getService('httpRequest')->getQuery('words');
      $gs = $this->context->getService('httpRequest')->getQuery('gamestring');
      $idGu = $this->context->getService('httpRequest')->getQuery('idgameuser');
      if ($this->getModel()->isMyGame($this->getUser()->id, $idGu)) {
        $this->getModel()->evalGame(array(
          'gameString' => $gs,
          'idGameUser' => $idGu
          ), $words);
        $result = $this->getModel()->getResult($idGu, 1);
        $this->send(array());
      }
    }
  }
  public function renderLogin()
  {
    $user = $this->context->getService('httpRequest')->getQuery('user');
    $pwd = $this->context->getService('httpRequest')->getQuery('pwd');
    if (!is_null($user)) {
      $data = array();
      try {
        $this->getUser()->login($user, $pwd);
        $secret = $this->getUser()->getIdentity()->secret;
      } catch (NAuthenticationException $e) {
        if ($e->getCode() == IAuthenticator::IDENTITY_NOT_FOUND) {
          $idUser = $this->getModel()->register(array(
              'email' => $user,
              'name' => $user,
              'password' => $pwd
              ));
          $this->getUser()->login($user, $pwd);
          $secret = $this->getUser()->getIdentity()->secret;
        } else {
          $secret = "NO_USER";
        }
      }

      $data["secret"] = $secret;
      $this->send($data);
    }
  }
}
