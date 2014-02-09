<?php

/**
 * Base class for all application presenters.
 *
 * @author     John Doe
 * @package    MyApplication
 */
abstract class BasePresenter extends NPresenter
{
  public function getModel() {
      $model = new GameModel(); 
      $model->userId = $this->getUser()->id;
      return $model;
  }
  
  public function loginUserBySecret()
  {
    $secret = $this->context->getService('httpRequest')->getQuery('secret');
    if ($secret) { 
      $row = $this->getModel()->getUserBySecret($secret);
      $this->getUser()->login(new NIdentity($row['id'], null, $row));
      return $row['id'];
    }
  }

  public function getFB()
  {
      $model = new FB((array) NEnvironment::getConfig()->facebook); 
      return $model; 
  }

  public function loggedFB()
  {
    $facebook = $this->getFB();
    $userFb = $facebook->getUser();
    $userFb = 1;
    if (!$userFb) {
      return false;
    }
    if (!$this->getUser()->isLoggedIn()) {
      return false;
    }
    $userRec = $this->getModel()->getUserRec($this->getUser()->id);
    if (!$userRec["facebookid"]) {
      return false;
    }
    return true;
  }
  

  public function beforeRender() {
    $url = $this->getHttpRequest()->getUrl();
    $base = $url->getBasePath();
    $this->template->rootPath = rtrim($base, '/') . (SUB ? '/..' : '');
    $this->template->FB = FB;
    $this->template->SUB = SUB;
    $this->template->CZ = CZ;
    $this->template->EN = EN;
    $this->template->embedded = $this->context->getService('httpRequest')->getQuery('embedded'); 
    //echo 'id' . $this->getUser()->id . ($this->getUser()->isLoggedIn() ? 'Y' : 'N');
  }
  public function log($msg) {
    $model = $this->getModel();
    $model->log($msg, $this->getUser()->id);
  }
  protected function createTemplate($class = NULL)
  {
    $template = parent::createTemplate($class);
    $template->registerHelper('grid', function ($s) {
        $ret = "<table class=\"example\">";
        foreach (str_split(preg_replace('/\s+/', '', $s)) as $key => $val) {
          $class = "";
          if ($key % 4 == 0) {
            $ret .= "<tr>";
          }
          if ($val == strtoupper($val)) {
            $class = " class=\"active\"";
          }
          $ret .= "<td$class>" . $val . "</td>";
          if ($key % 4 == 3) {
            $ret .= "</tr>";
          }  
        }
        $ret .= "</table>";
        return $ret;
    });
    $template->registerHelper('word', function ($word, $toUpper = false) {
        $ret = ((EN && $word["points"] > 0)? "<a href=\"http://translate.google.cz/#en/cs/" . $word["text"] . '" target="_blank">' : "") . "<span title=\""
             . (in_array($word["status"], array("I", "W", "E")) ? "Chybné slovo" : ($word["numusers"] > 0 ? "Slovo nalezli i další hráči" : "Uznané slovo"))
             . "\" class=\"result "
             . ($word["numusers"] > 0 && !in_array($word["status"], array("I", "W", "E")) ? "DUPLICATE" : $word["status"]) . "\">"
             . ($toUpper ? NStrings::upper(NTemplateHelpers::escapeHtml($word["text"])) : NTemplateHelpers::escapeHtml($word["text"])) 
             . "&thinsp;<span class=\"small\">(" . $word["points"] . ")</span>"
             . "</span>" . (EN ? "</a>" : "");
        
        return $ret;
    });
    return $template;
  }
}
