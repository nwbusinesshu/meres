<?php
namespace App\Exceptions;

use Exception;

/**
 * AuthFailException
 */
class AuthFailException extends Exception
{
  protected $fallback;

  /**
   * Constructs the exception.
   *
   * @param  string  $msg The message of the exception.
   * @param  string  $fallback The fallback route of the exception.
   */
  public function __construct($msg, $fallback)
  {
      $this->fallback = $fallback;
      parent::__construct($msg);
  }

  /**
   * Gets the exceptions fallback route.
   *
   * @return  string
   */
  public function getFallback()
  {
    return $this->fallback;
  }
}