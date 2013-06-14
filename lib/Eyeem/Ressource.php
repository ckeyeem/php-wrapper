<?php

abstract class Eyeem_Ressource
{

  /* Context */

  protected $eyeem;

  /* Static Properties */

  public static $attrs;

  public static $name;

  public static $endpoint;

  public static $properties = array();

  public static $collections = array();

  public static $parameters = array();

  /* Object Properties */

  public $id;

  public $updated;

  protected $_ressource;

  protected $_attributes = array();

  protected $_collections = array();

  protected $_queryParameters = array();

  public function __construct($params = array())
  {
    if (is_array($params)) {
      $this->setAttributes($params);
    } elseif (is_int($params) || is_string($params)) {
      $this->id = $params;
    }
  }

  public function setAttributes($infos = array())
  {
    if (!isset(static::$attrs)) {
      static::$attrs = array_flip(static::$properties);
    }
    foreach ($infos as $key => $value) {
      // Special Attributes
      if ($key == 'id' || $key == 'updated') {
        $this->$key = $value;
        $this->_attributes[$key] = $value;
      } elseif (isset(static::$attrs[$key])) {
        $this->_attributes[$key] = $value;
      } elseif (isset(static::$collections[$key])) {
        $this->_attributes[$key] = $value;
      }
    }
  }

  public function setAttribute($key, $value)
  {
    $this->setAttributes(array($key => $value));
  }

  public function getAttributes($force = false)
  {
    if (empty($this->_attributes) || $force) {
      $attributes = $this->_getRessource();
      $this->setAttributes($attributes);
    }
    return $this->_attributes;
  }

  public function getAttribute($key, $fetch = true)
  {
    if ($fetch === false) {
      return isset($this->_attributes[$key]) ? $this->_attributes[$key] : null;
    }
    $attributes = $this->getAttributes();
    if (isset($attributes[$key]) || array_key_exists($key, $attributes)) {
      return $attributes[$key];
    }
    Eyeem_Log::log('Eyeem_Ressource:getAttribute:' . static::$name . ':' . $key);
    $attributes = $this->getAttributes(true);
    if (isset($attributes[$key])) {
      return $attributes[$key];
    }
  }

  public function isValid()
  {
    try {
      $this->getAttributes(true);
      return true;
    } catch (Exception $e) {
      return false;
    }
  }

  public function getQueryParameters()
  {
    return $this->_queryParameters;
  }

  public function setQueryParameters($params = array())
  {
    foreach ($params as $key => $value) {
      if (in_array($key, static::$parameters)) {
        $this->_queryParameters[$key] = $value;
      }
    }
    return $this;
  }

  public function getEndpoint()
  {
    if (empty($this->id)) {
      throw new Exception("Unknown id.");
    }
    return str_replace('{id}', $this->id, static::$endpoint);
  }

  public function getUpdated($format = null)
  {
    if ($this->updated) {
      $format = isset($format) ? $format : DateTime::ISO8601;
      $dt = new DateTime($this->updated);
      return $dt->format($format);
    }
  }

  public function fetch()
  {
    $name = static::$name;
    $params = $this->getQueryParameters();
    $response = $this->request($this->getEndpoint(), 'GET', $params);

    if (empty($response[$name])) {
      throw new Exception("Missing ressource in response ($name).");
    }
    $result = $response[$name];

    // Pre-load collections when available
    foreach (static::$collections as $key => $type) {
      if (isset($result[$key])) {
        $collection = $this->getCollection($key, false);
        $collection->setProperties($result[$key]);
      }
    }

    return $result;
  }

  protected function _getRessource()
  {
    if (isset($this->_ressource)) {
      return $this->_ressource;
    }
    return $this->_ressource = $this->fetch();
  }

  public function flush()
  {
    $this->_ressource = null;
    $this->_attributes = array();
  }

  public function flushCollection($name = null)
  {
    if ($name && isset(static::$collections[$name])) {
      $this->_ressource = null;
      unset($this->$name);
      $totalKey = 'total' . ucfirst($name);
      unset($this->$totalKey);
      unset($this->_attributes[$totalKey]);
    }
  }

  public function getRawArray()
  {
    return $this->_getRessource();
  }

  public function getRessourceObject($type, $infos = array())
  {
    return $this->getEyeem()->getRessourceObject($type, $infos);
  }

  public function getCollection($name, $autoload = true)
  {
    if (empty($this->_collections[$name])) {
      // Load class
      $classname = 'Eyeem_Collection_' . ucfirst($name);
      if (class_exists($classname)) {
        $collection = new $classname();
      } else {
        $collection = new Eyeem_RessourceCollection();
      }
      // Collection name (match the name in URL: friendsPhotos, comments, likers, etc ...)
      $collection->setName($name);
      // Which kind of objects we are handling (user, album, photo, etc)
      $collection->setType(static::$collections[$name]);
      // Keep a link to the current object
      $collection->setParentRessource($this);
    } else {
      $collection = $this->_collections[$name];
    }

    if ($autoload == true) {
      // If we have some properties already available (offset, limit, total, items)
      if ($properties = $this->getAttribute($name, false)) {
        $collection->setProperties($properties);
      }
      // If we don't have the total in the collection properties
      if (!isset($properties['total'])) {
        // But have it available as totalX property.
        if (static::$name == 'photo' && $name == 'likers') {
          $totalKey = 'totalLikes';
        } else {
          $totalKey = 'total' . ucfirst($name);
        }
        if ($total = $this->getAttribute($totalKey, false)) {
          $collection->setTotal($total);
        }
      }
    }

    return $this->_collections[$name] = $collection;
  }

  public function update($params = array())
  {
    $response = $this->request($this->getEndpoint(), 'POST', $params);
    $this->flush();
    if (isset($response[static::$name])) {
      $this->setAttributes($response[static::$name]);
    }
    return $response;
  }

  public function delete()
  {
    $this->request($this->getEndpoint(), 'DELETE');
    return true;
  }

  public function request($endpoint, $method = 'GET', $params = array())
  {
    return $this->getEyeem()->request($endpoint, $method, $params);
  }

  public function __get($key)
  {
    if (!isset(static::$attrs)) {
      static::$attrs = array_flip(static::$properties);
    }
    if (!isset(static::$attrs[$key])) {
      throw new Exception("Unknown property ($key).");
    }
    $value = $this->getAttribute($key);
    return $value;
  }

  public function __call($name, $arguments)
  {
    $actions = array('get', 'set', 'flush');
    list($action, $key) = isset(Eyeem_Runtime::$cc[$name]) ? Eyeem_Runtime::$cc[$name] : Eyeem_Runtime::cc($name, $actions);
    // Get methods
    if ($action == 'get') {
      // Collection Objects
      if (isset(static::$collections[$key])) {
        $parameters = isset($arguments[0]) ? $arguments[0] : array();
        $collection = $this->getCollection($key);
        $collection->setQueryParameters($parameters);
        return $collection;
      }
      // Default (read object property)
      return $this->$key;
    }
    // Set methods
    elseif ($action == 'set') {
      // Default (write object property)
      $this->$key = $arguments[0];
      return $this;
    }
    // Flush methods
    elseif ($action == 'flush') {
      // Default (delete object property)
      if (isset($this->$key)) {
        unset($this->$key);
      }
      return $this;
    }
    throw new Exception("Unknown method ($name).");
  }

  public function toArray()
  {
    // To Fetch or Not To Fetch missing data?
    $array = array();
    foreach (static::$properties as $key) {
      $array[$key] = $this->$key;
    }
    return $array;
  }

}
