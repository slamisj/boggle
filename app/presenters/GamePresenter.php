<?php

/**
 * My NApplication
 *
 * @copyright  Copyright (c) 2010 John Doe
 * @package    MyApplication
 */



/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class GamePresenter extends BasePresenter
{
  public function renderJoin($offset = 0)
	{
    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->template->result = $this->getModel()->getJoin($this->getUser()->id, $offset);
	}
  public function renderMobile($status)
  {
    $this->getModel()->setMobile($status, $this->getUser()->id);
    $this->redirect("Homepage:default");
  }
  public function renderAll($status)
  {
    $this->getModel()->setAll($status, $this->getUser()->id);
    $this->redirect("Homepage:default");
  }
  protected function createComponentContactForm()
	{
		$form = new NAppForm;
		$form->addText('email', 'E-mail:');

		$form->addTextArea('text', 'Vzkaz:')
			->setRequired('Zadej vzkaz');

		$form->addSubmit('send', 'Odeslat vzkaz');

		$form->onSuccess[] = callback($this, 'contactFormSubmitted');
		return $form;
	}
  
  public function renderDelAnonymous()
  {
    $this->getModel()->delAnonymous();
    //$this->getModel()->calculatePoints();
    //$this->getModel()->evalErrorWords();
  }

  public function renderSetWord($status, $id)
  {
    if (!$this->getUser()->isLoggedIn()
      || !$this->getUser()->getIdentity()->canCheck) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->getModel()->setWord($id, $status);
    $this->redirect("Game:check");
  }
  public function renderCheck()
  {
    if (!$this->getUser()->isLoggedIn()
      || !$this->getUser()->getIdentity()->canCheck) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->template->word = $this->getModel()->getCheckWord();
  }
  
  public function renderTry()
	{
    $this->log('try');
    if ($this->getModel()->forceRegister()) {
      $this->log('forcereg');
      $this->flashMessage('Díky za vyzkoušení, pro další hraní se prosím zaregistruj. Zadarmo, bez spamování.');
      $this->redirect("Sign:reg");
    }
    $values = $this->getModel()->createUser();
		$this->getUser()->login($values['email'], $values['password']);
    $this->log('toplay');
		$this->redirect('Game:play');
  }
  
	public function renderPlay($idGame = null)
	{
    $this->log('play');
    if (!$this->getUser()->isLoggedIn()) {
      $this->log('notlogged');
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    if ($this->getModel()->forceRegister($this->getUser()->id)) {
      $this->log('forcereg');
      $this->flashMessage('Díky za vyzkoušení, pro další hraní se prosím zaregistruj. Zadarmo, bez spamování.');
      $this->redirect("Sign:reg");
    }
	  $game = $this->getModel()->getGame($this->getUser()->id, $idGame);
    $session = $this->getSession()->getSection('game');
    $session->values = $game;
    
	  $this->template->game = $game;
	  $this->template->username = $this->getUser()->getIdentity()->name;
	  $this->template->currentUser = $this->getModel()->getUserRec($this->getUser()->id);
    $this->template->result = $this->getModel()->getResult($game['idGameUser']);
	}
  public function renderResult($idGameUser, $save = null)
	{
    $this->loginUserBySecret();

    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    if (!$this->getModel()->isMyGame($this->getUser()->id, $idGameUser)) {
      $this->flashMessage('Výsledek cizích her nelze zobrazit.');
      $this->redirect("Homepage:default");
    }
    $this->template->user = $this->getUser()->getIdentity();
    $this->template->result = $this->getModel()->getResult($idGameUser, $save);
	}
  public function renderTravel()
  {
    $this->template->games = $this->getModel()->getTravel();
  }
  public function renderVote($idGameUser)
	{
    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    if (!$this->getModel()->isMyGame($this->getUser()->id, $idGameUser)) {
      $this->flashMessage('Výsledek cizích her nelze zobrazit.');
      $this->redirect("Homepage:default");
    }
    $this->template->result = $this->getModel()->getVote($idGameUser);
	}
  public function renderDoVote($idWord, $value)
	{
    $this->getModel()->doVote($this->getUser()->id, $idWord, $value);
	}
  public function renderMy($offset = 0)
	{
    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->template->result = $this->getModel()->getMy($this->getUser()->id, $offset);
	}
	public function renderList()
	{
    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->template->result = $this->getModel()->getList($this->getUser()->id);
	}
  public function renderStats()
	{
    if (!$this->getUser()->isLoggedIn()) {
      $this->flashMessage('Přihlaš se.');
      $this->redirect("Sign:in");
    }
    $this->template->result = $this->getModel()->getStats();
	}
	protected function createComponentBoggleForm()
	{
		$form = new NAppForm;
		$form->addText('word', 'Slovo:');
		$form->addTextArea('words', 'Slova:');

		$form->addSubmit('add', 'Přidat slovo');
		$form->addSubmit('send', 'Odeslat');

		$form->onSuccess[] = callback($this, 'gameFormSubmitted');
		return $form;
	}
  
  public function renderAjax()
	{
	  $session = $this->getSession()->getSection('game');
    $words = $this->context->getService('httpRequest')->getQuery('words'); 
    $words = trim($words, ";");
    $wordsA = explode(";", $words);
    $this->template->word = $this->getModel()->evalGame($session->values,
     end($wordsA), true);
	}
  
  public function contactFormSubmitted($form)
	{
		try {
       $values = $form->getValues(TRUE);
  		 $this->getModel()->setMsg($values);
       $this->flashMessage('Díky za zpětnou vazbu.');
       $this->redirect("Homepage:default"); 
		} catch (NAuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}
  
	public function gameFormSubmitted($form)
	{
		try {
       $values = $form->getValues();
       $session = $this->getSession()->getSection('game');
       
  		 $this->getModel()->evalGame($session->values, $values['words']);
       $this->redirect('Game:result', $session->values["idGameUser"], 1); 
		} catch (NAuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

}