<?php

namespace Bridit\JsonApiRepository;

use Exception;
use Httpful\Mime;
use Httpful\Request;
use Httpful\Exception\ConnectionErrorException;

/**
 * Class Repository
 * @package Bridit\JsonApiRepository
 * @method static getRepository(?string $uri = null, ?array $headers = null, ?bool $processResponse = true)
 * @method static find($id)
 * @method static findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)
 * @method static findOneBy(array $criteria, ?array $orderBy = null)
 * @method static findAll(?array $orderBy = null)
 * @method static create(array $params)
 * @method static update($id, array $params)
 * @method static delete($id)
 * @method static restore($id)
 */
class Repository
{

  /**
   * @var array
   */
  protected static $allowedMethods = ['getRepository', 'create', 'update', 'delete', 'restore', 'find', 'findBy', 'findOneBy', 'findAll'];

  /**
   * @var null|string
   */
  protected $uri;

  /**
   * @var null|array
   */
  protected $headers;

  /**
   * @var bool
   */
  protected $processResponse = true;

  /**
   * Response with parsed results
   * @var mixed
   */
  protected $response;

  /**
   * Repository constructor.
   */
  public function __construct()
  {
    $this->setRequestTemplate();
  }

  /**
   * @param $name
   * @param $arguments
   * @return mixed
   */
  public function __call($name, $arguments)
  {
    if (!in_array($name, self::$allowedMethods)) {
      throw new \BadMethodCallException("Method $name does not exists");
    }

    return call_user_func_array([$this, 'do' . ucfirst($name)], $arguments);
  }

  /**
   * @param $name
   * @param $arguments
   * @return mixed
   */
  public static function __callStatic($name, $arguments)
  {
    if (!in_array($name, self::$allowedMethods)) {
      throw new \BadMethodCallException("Method $name does not exists");
    }

    return call_user_func_array([new static(), $name], $arguments);
  }

  protected function setRequestTemplate()
  {
    $template = Request::init()
      ->expectsJson()
      ->sendsType(Mime::JSON);

    if ($this->getHeaders() !== null) {
      $template->addHeaders($this->getHeaders());
    }

    Request::ini($template);
  }

  /**
   * @return string
   */
  protected function getUri(): string
  {
    return $this->uri;
  }

  /**
   * @return array|null
   */
  protected function getHeaders(): ?array
  {
    return $this->headers;
  }

  /**
   * @return bool|null
   */
  protected function mustProcessResponse(): ?bool
  {
    return $this->processResponse;
  }

  /**
   * @param string|null $uri
   * @param array|null $headers
   * @param bool|null $processResponse
   * @return $this
   */
  protected function doGetRepository(?string $uri = null, ?array $headers = null, ?bool $processResponse = true)
  {
    $this->uri = $uri;
    $this->headers = $headers;
    $this->processResponse = $processResponse;

    return $this;
  }

  /**
   * @param string $method
   * @param string $uri
   * @param array $params
   * @return object|array|null
   * @throws ConnectionErrorException|Exception
   */
  protected function doRequest(string $method, string $uri, array $params = [])
  {

    switch (strtolower($method))
    {
      case 'get':
        $this->response = Request::get(http_build_url($uri, ["query" => http_build_query($params)], HTTP_URL_JOIN_QUERY))->send();
        break;
      case 'post':
        $this->response = Request::post($uri, $params)->send();
        break;
      case 'put':
        $this->response = Request::put($uri, $params)->send();
      case 'patch':
        $this->response = Request::patch($uri, $params)->send();
        break;
      case 'delete':
        $this->response = Request::delete($uri)->send();
        break;
      default:
        $this->response = null;
        break;
    }

    return $this->mustProcessResponse() === true ? $this->processResponse() : $this->response;
  }

  /**
   * @return mixed
   * @throws Exception
   */
  protected function processResponse()
  {
    if (substr((string) $this->response->code, 0, 1) === '2') {
      return isset($this->response->body->data) ? $this->response->body->data : $this->response->body;
    }

    throw new Exception(json_encode($this->response->body), $this->response->code);
  }

  /**
   * @param array $params
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doCreate(array $params)
  {
    $uri = $this->getUri();

    return $this->doRequest('post', $uri, $params);
  }

  /**
   * @param $id
   * @param array $params
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doUpdate($id, array $params)
  {
    $uri = $this->getUri(). '/' . $id;

    return $this->doRequest('put', $uri, $params);
  }

  /**
   * @param $id
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doDelete($id)
  {
    $uri = $this->getUri() . '/' . $id;

    return $this->doRequest('delete', $uri);
  }

  /**
   * @param $id
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doRestore($id)
  {
    $uri = $this->getUri() . '/' . $id . '/restore';

    return $this->doRequest('put', $uri);
  }

  /**
   * Finds an entity by its primary key/identifier.
   *
   * @param string|int|array $id
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doFind($id)
  {
    if (is_array($id)) {
      return $this->doFindBy(['id' => $id]);
    }

    $uri = $this->getUri() . '/' . $id;

    return $this->doRequest('get', $uri, []);
  }

  /**
   * Finds entities by a set of criteria.
   *
   * @param array $criteria
   * @param array|null $orderBy
   * @param int|null $limit
   * @param int|null $offset
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doFindBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)
  {
    $query = [];

    if ($criteria !== null && $criteria !== []) {
      $query['filter'] = $criteria;
    }

    if ($orderBy !== null) {

      $sort = [];
      foreach ($orderBy as $name => $direction)
      {
        $sort[] = strtolower($direction) === 'asc' ? $name : '-' . $name;
      }

      $query['sort'] = implode(',', $sort);
    }

    if ($limit !== null) {
      $query['page']['limit'] = $limit;
    }

    if ($offset !== null) {
      $query['page']['offset'] = $offset;
    }

    return $this->doRequest('get', $this->getUri(), $query);
  }

  /**
   * Finds a single entity by a set of criteria.
   *
   * @param array $criteria
   * @param array|null $orderBy
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doFindOneBy(array $criteria, ?array $orderBy = null)
  {
    return $this->doFindBy($criteria, $orderBy, 1);
  }

  /**
   * Finds all entities in the repository.
   *
   * @param array|null $orderBy
   * @return object|null
   * @throws ConnectionErrorException
   */
  protected function doFindAll(?array $orderBy = null)
  {
    return $this->doFindBy([], $orderBy);
  }

}