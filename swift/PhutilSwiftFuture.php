<?php

final class PhutilSwiftFuture extends PhutilOpenstackFuture {

  private $container;

  public function getServiceName() {
    return 'swift';
  }

  public function setContainer($container) {
    $this->container = $container;
    return $this;
  }

  /**
   * Get the container name to use for storing a given key
   */
  public function getContainer($key='') {
    $key_prefix = $key;
    if (strlen($key_prefix) >= 2) {
      $key_prefix = '-' . substr($key_prefix, 0, 2);
      return $this->container.$key_prefix;
    } else {
      return $this->container;
    }
  }

    public function setParametersForGetObject($key) {
    $container = $this->getContainer($key);

    $this->setHTTPMethod('GET');
    $this->setPath($container.'/'.$key);

    return $this;
  }

  public function setParametersForPutContainer($key) {
    $container = $this->getContainer($key);

    $this->setHTTPMethod('PUT');
    $this->setPath($container);
    $this->addHeader('X-Auth-Token', $this->getSecretKey());
    return $this;
  }


  public function setParametersForPutObject($key, $value) {
    $container = $this->getContainer($key);

    $this->setHTTPMethod('PUT');
    $this->setPath($container.'/'.$key);
    $this->addHeader('X-Auth-Token', $this->getSecretKey());
    $this->addHeader('Content-Type', 'application/octet-stream');

    $this->setData($value);
    return $this;
  }

  public function setParametersForDeleteObject($key) {
    $container = $this->getContainer($key);

    $this->setHTTPMethod('DELETE');
    $this->setPath($container.'/'.$key);
    $this->addHeader('X-Auth-Token', $this->getSecretKey());

    return $this;
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    if (!$status->isError()) {
      return $body;
    }

    return parent::didReceiveResult($result);
  }

  protected function shouldSignContent() {
    return false;
  }

}
