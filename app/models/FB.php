<?php
class FB extends Facebook
{
	public $pars = null;
	public function __construct($pars) 
	{
		
		$this->pars = $pars;
		 return parent::__construct(array(
         'appId'  => $pars['app_id'],
         'secret' => $pars['app_secret'],
		 ));
	}

	public function getLoginUrl($params = array()) 
	{
		return parent::getLoginUrl(array(
		        'scope' => $this->pars['scope'],
		        'redirect_uri' => $this->pars['app_url']
		        ));
	}
}