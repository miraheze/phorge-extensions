<?php

abstract class PhutilOpenstackFuture extends FutureProxy {

  private $future;
  private $account;
  private $secretKey;
  private $region;
  private $httpMethod = 'GET';
  private $path = '/';
  private $endpoint;
  private $data = '';
  private $headers = array();

  abstract public function getServiceName();

  public function __construct() {
    parent::__construct(null);
  }

  public function setAccount($account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  public function setSecretKey(PhutilOpaqueEnvelope $secret_key) {
    $this->secretKey = $secret_key;
    return $this;
  }

  public function getSecretKey() {
    return $this->secretKey;
  }

  public function setEndpoint($endpoint) {
    $this->endpoint = $endpoint;
    return $this;
  }

  public function getEndpoint() {
    return $this->endpoint;
  }

  public function setHTTPMethod($method) {
    $this->httpMethod = $method;
    return $this;
  }

  public function getHTTPMethod() {
    return $this->httpMethod;
  }

  public function setPath($path) {
    $account = $this->getAccount();
    $this->path = "v1/$account/$path";
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  protected function getParameters() {
    return array();
  }

  public function addHeader($key, $value) {
    $this->headers[] = array($key, $value);
    return $this;
  }

  protected function getProxiedFuture() {
    if (!$this->future) {
      $params = $this->getParameters();
      $method = $this->getHTTPMethod();
      $host = $this->getEndpoint();
      $path = $this->getPath();
      $data = $this->getData();

      $uri = id(new PhutilURI("{$host}"))
        ->setPath($path)
        ->setQueryParams($params);

      $future = id(new HTTPSFuture($uri, $data))
        ->setMethod($method);

      foreach ($this->headers as $header) {
        list($key, $value) = $header;
        $future->addHeader($key, $value);
      }

      $this->future = $future;
    }

    return $this->future;
  }

  protected function shouldSignContent() {
    return false;
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    try {
      // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
      $xml = @(new SimpleXMLElement($body));
    } catch (Exception $ex) {
      $xml = null;
    }

    if ($status->isError() || !$xml) {
      if (!($status instanceof HTTPFutureHTTPResponseStatus)) {
        throw $status;
      }

      $params = array(
        'body' => $body,
      );
      if ($xml) {
        $params['RequestID'] = $xml->RequestID[0];
        $errors = array($xml->Error);
        foreach ($errors as $error) {
          $params['Errors'][] = array($error->Code, $error->Message);
        }
      }

      throw new PhutilAWSException($status->getStatusCode(), $params);
    }

    return $xml;
  }

}
