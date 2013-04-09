<?php

class Eyeem_Ressource_AuthUser extends Eyeem_Ressource_User
{

  public static $parameters = array(
    'includeSettings'
  );

  protected $_queryParameters = array(
    'includeSettings' => true
  );

  public function getEndpoint()
  {
    if (empty($this->id)) {
      return str_replace('{id}', 'me', static::$endpoint);
    } else {
      return str_replace('{id}', $this->id, static::$endpoint);
    }
  }

  public function request($endpoint, $method = 'GET', $params = array(), $authenticated = false)
  {
    return parent::request($endpoint, $method, $params, true);
  }

  /* Apps */

  public function getApp($id)
  {
    $apps = $this->getCollection('apps')->setAuthenticated(true);
    foreach ($apps as $app) {
      if ($id == $app->getId()) {
        return $app;
      }
    }
  }

  public function getApps()
  {
    return $this->getCollection('apps')->setAuthenticated(true);
  }

  public function getLinkedApps()
  {
    return $this->getCollection('linkedApps')->setAuthenticated(true);
  }

  public function authorizeApp($params)
  {
    $params = http_build_query($params);
    $result = $this->request($this->getCollection('linkedApps')->getEndpoint(), 'POST', $params);
    return $result;
  }

  /* Social Media */

  public function socialMediaConnect($service, $params = array())
  {
    $params['connect'] = 1;
    $result = $this->request($this->getEndpoint() . '/socialMedia/' . $service, 'POST', $params);
    $this->flush();
    return $result;
  }

  public function socialMediaKeys($service, $params = array())
  {
    $params['keys'] = 1;
    $result = $this->request($this->getEndpoint() . '/socialMedia/' . $service, 'POST', $params);
    $this->flush();
    return $result;
  }

  public function socialMediaDisconnect($service)
  {
    $result = $this->request($this->getEndpoint() . '/socialMedia/' . $service, 'DELETE');
    $this->flush();
    return $result;
  }

  public function socialMediaCallback($service, $params = array())
  {
    $params['callback'] = 1;
    $params = http_build_query($params);
    $result = $this->request($this->getEndpoint() . '/socialMedia/' . $service, 'POST', $params);
    $this->flush();
    return $result;
  }

  public function socialMediaUpdate($service, $params = array())
  {
    $result = $this->request($this->getEndpoint() . '/socialMedia/' . $service, 'PUT', $params);
    $this->flush();
    return $result;
  }

  public function getSmContacts($service)
  {
    $params['matchContacts'] = 1;
    $result = $this->request($this->getEndpoint() . '/smContacts/' . $service, 'GET', $params);
    return $result['contacts'];
  }

  /* Flags */

  public function getFlags()
  {
    if ($newsSettings = $this->getAttribute('newsSettings')) {
      return $newsSettings;
    }
    $result = $this->request($this->getEndpoint() . '/flags');
    return $result['flags'];
  }

  public function setFlags($params = array())
  {
    $params = http_build_query($params);
    $result = $this->request($this->getEndpoint() . '/flags', 'POST', $params);
    $this->flush();
    return $result['flags'];
  }

  /* Delete */

  public function delete()
  {
    $params = array('user_id' => 'me');
    $result = $this->request('/auth/deleteUser', 'DELETE', $params, true);
    $this->flush();
    return true;
  }

  /* Discover */

  public function getDiscoverAlbums()
  {
    return $this->getCollection('discoverAlbums')->setAuthenticated(true);
  }

  /* Search Friends */

  public function searchFriends($query = '', $params = array())
  {
    $params['q'] = $query;
    $result = $this->request('/users', 'GET', $params, true);
    return $result['users'];
  }

}
