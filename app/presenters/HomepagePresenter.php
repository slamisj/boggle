<?php

/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class HomepagePresenter extends BasePresenter
{
	public function renderDefault()
	{
	  //$this->getModel()->checkWord(3);
	  //$this->getModel()->fillFound();
    //$this->getModel()->checkWord(1279106);

	  //die();
    if (FB) {
      if (!$this->loggedFB()) {
        $this->redirect('Sign:FB');
      }
      $this->setView('fb');
    }
    $stats = $this->getModel()->getStats();
	  $this->template->result = $stats;
    $userR = $this->getModel()->getUserRec($this->getUser()->id);
    //TODO!
    $this->template->username = $this->getUser()->isLoggedIn() ? $userR["name"] : "";
    $this->template->user = $userR;
    //$this->template->forceRegister = $this->getModel()->forceRegister($this->getUser()->isLoggedIn() ? $this->getUser()->id : "");
    $others = array(
      "Najdi za tři minuty více slov než ostatní.",
      "Buď originální.",
      "Nejlepší na dotek.",
      "Lepší než sex. O tři body.",
      "Ch&copy; included.",
      "Odehráli jsme již více než " . (floor($stats["numgames"] / 10000)) * 10000 . " her, díky za přízeň!",
      "Vytiskni si Boggle na cesty.",
      "Vyzkoušej anglickou verzi, bodují se již třípísmenná slova, viz menu.",
      "Neuznalo se ti krásné slovo? Hlasuj o něm.",
      "Návyková hra se slovy.",
      "Zkus verzi pro android, viz odkaz v patičce."
    );
    $motto = $others[array_rand($others)];
    $this->template->motto = $motto;
	}

}
