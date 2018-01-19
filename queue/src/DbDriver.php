<?php

/**
 * Created by PhpStorm.
 * User: longli
 * Date: 17-3-27
 * Time: 下午4:20
 */

namespace queue;

/**
 * Class DbDriver
 * @package queue
 * @author longli
 */
class DbDriver
{
  /**
   * @var \PDO
   */
  private $pdo = null;

  private $config = [];

  public function __construct(array $config = [])
  {
    $this->config = array_merge($this->config, $config);
    if(method_exists($this, 'init'))
      $this->init();
  }

  public function close()
  {
    $this->pdo = null;
  }

  /**
   * @return \PDO
   */
  public function getPdo()
  {
    $this->connect();
    return $this->pdo;
  }

  public function connect($connect = 'default')
  {
    try
    {
      if(empty($this->pdo))
      {
        if(empty($connect))
          $connect = 'default';
        $db = $this->config[$connect]['params'];
        $dns = "mysql:dbname={$db['database']};host={$db['host']};charset={$db['charset']}";
        $this->pdo = new \PDO($dns, $db['user'], $db['password']);
      }
    }
    catch (\PDOException $e)
    {
      //echo 'Connection failed: ' . $e->getMessage();
      exit;
    }
  }

  public function config($key= null, $value = null)
  {
    if($key === null)
      return $this->config;
    if($value === null)
      return isset($this->config[$key]) ? $this->config[$key] : null;
    $this->config[$key] = $value;
  }

  /**
   * @param string $tableName
   */
  public function setTableName($tableName)
  {
    $this->tableName = $tableName;
  }

  /**
   * @return string
   */
  public function getTableName()
  {
    return $this->tableName;
  }

  public function query($sql, array $args = [], $connect = '', $isClose = true)
  {
    $ret = null;
    $this->connect($connect);
    $prepare = $this->pdo->prepare($sql);
    $prepare->execute($args);
    $errorInfo = $prepare->errorInfo();
    if(strtolower(substr(trim($sql), 0, 6)) == 'select')
      $ret = $prepare->fetchAll(\PDO::FETCH_ASSOC);
    else
      $ret = $this->pdo->lastInsertId();
    if($isClose)
      $this->close();
    return $ret;
  }

  public function push($tableName, array $data = [], $conn = '', $isClose = false)
  {
    $prepare = $this->keyPrepare($data);
    $sql = "INSERT INTO $tableName({$prepare['key']}) VALUES({$prepare['value']})";
    return $this->query($sql, $data, $conn, $isClose);
  }

  public function keyPrepare(array $data = [])
  {
    $value = array_keys($data);
    $keys = [];
    foreach($value as &$item)
    {
      $prefix =  substr($item, 0, 1);
      if($prefix != ':')
      {
        $item = ":$item";
      }
      $keys[] = substr($item, 1);
    }
    return [
      "key" => join(',', $keys),
      "value" => join(',', $value),
    ];
  }

}
