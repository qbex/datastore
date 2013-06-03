<?php
/**
 * @author  Richard.Gooding
 */

namespace CloudDatastore\GoogleAPI;

use DrSlump\Protobuf\Message;

abstract class GoogleService
{
  /**
   * @var GoogleServiceOptions
   */
  protected $_options;

  public function __construct(GoogleServiceOptions $options = null)
  {
    $this->_options = $options;
  }

  /**
   * @return string
   */
  abstract protected function _getBaseUrl();
  /**
   * @param string $methodName
   *
   * @return string
   */
  abstract protected function _getUrlForMethod($methodName);
  /**
   * @return string[]
   */
  abstract protected function _getOAuthScopes();

  protected function _getFullBaseUrl()
  {
    return rtrim($this->_options->host, '/') . '/' .
    ltrim($this->_getBaseUrl(), '/');
  }


  /**
   * @param string  $methodName
   * @param Message $message
   * @param Message $response
   *
   * @return Message
   */
  protected function _callMethod(
    $methodName, Message $message, Message $response
  )
  {
    $url = $this->_getUrlForMethod($methodName);
    $client = $this->_getClient();

    $postBody = $message->serialize();

    $headers = [
      'Accept-Encoding' => 'gzip',
      'Accept' => 'text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2',
      'Content-Type' => 'application/x-protobuf',
      'Content-Length' => \Google_Utils::getStrLen($postBody)
    ];

    $httpRequest = new \Google_HttpRequest($url, 'POST', $headers, $postBody);
    \Google_Client::$auth->sign($httpRequest);

    $httpResponse = \Google_Client::$io->makeRequest($httpRequest);

    $code = $httpResponse->getResponseHttpCode();
    if($code == 200)
    {
      $response->parse($httpResponse->getResponseBody());
    }
    else
    {
      // TODO: Throw an appropriate exception
      echo "ERROR: HTTP request returned code " . $code . "\n";
      var_dump($httpResponse);
      die;
    }

    $token = $client->getAccessToken();
    if($token)
    {
      $this->_saveAuthToken($token);
    }
    return $response;
  }

  protected function _getClient()
  {
    $client = new \Google_Client();
    $client->setApplicationName($this->_options->applicationName);

    if($this->_options->serviceAccountName)
    {
      $authToken = $this->_getAuthToken();
      if($authToken)
      {
        $client->setAccessToken($authToken);
      }

      $client->setAssertionCredentials(
        new \Google_AssertionCredentials(
          $this->_options->serviceAccountName,
          $this->_getOAuthScopes(),
          $this->_getPrivateKey()
        )
      );
    }
    if($this->_options->clientId)
    {
      $client->setClientId($this->_options->clientId);
    }

    return $client;
  }

  protected function _getPrivateKey()
  {
    $file = $this->_options->privateKeyFile;
    if(! file_exists($file))
    {
      echo "ERROR: Could not find private key file: " . $file . "\n";
      die;
    }
    return file_get_contents($file);
  }

  protected function _getAuthToken()
  {
    return file_exists($this->_options->authTokenFile) ?
    file_get_contents($this->_options->authTokenFile) : false;
  }

  protected function _saveAuthToken($token)
  {
    file_put_contents($this->_options->authTokenFile, $token);
  }
}