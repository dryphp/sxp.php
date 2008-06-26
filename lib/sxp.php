<?php
/**
 * lib/sxp.php - SXP library for PHP 5.2+ <http://sxp.cc/>
 *
 * @author Arto Bendiken <http://bendiken.net/>
 * @copyright Copyright (c) 2007-2008 Arto Bendiken. All rights reserved.
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @license http://www.opensource.org/licenses/gpl-license.php GPL
 * @package sxp
 */

function sxp_read_file($url)  { return SXP::read_file($url); }
function sxp_read_all($input) { return SXP::read_all($input); }
function sxp_read($input)     { return SXP::read($input); }

class SXP {
  static function read_file($url) {
    if (($stream = fopen($url, 'rb')) !== FALSE) {
      $result = self::read_all($stream);
      fclose($stream);
      return $result;
    }
  }

  static function read_all($input) {
    $reader = new SXP_Reader($input);
    return $reader->read_all();
  }

  static function read($input) {
    $reader = new SXP_Reader($input);
    return $reader->read();
  }
}

class SXP_Reader implements Iterator {
  const FLOAT           = '/^[+-]?(?:\d+)?\.\d*$/';
  const INTEGER_BASE_2  = '/^[+-]?[01]+$/';
  const INTEGER_BASE_8  = '/^[+-]?[0-7]+$/';
  const INTEGER_BASE_10 = '/^[+-]?\d+$/';
  const INTEGER_BASE_16 = '/^[+-]?[\da-z]+$/i';
  const RATIONAL        = '/^([+-]?\d+)\/(\d+)$/';
  const ATOM            = '/^[^\s()]+/';
  const WHITESPACE      = '/^\s+/';

  public function __construct($input) {
    $this->escaped_chars = array('"' => '"', '\\' => '\\', '/' => '/', 'b' => "\b", 'f' => "\f", 'n' => "\n", 'r' => "\r", 't' => "\t");

    $this->input = $input;
    $this->lookahead = NULL;
    $this->eof = FALSE;
    $this->index = 0;
  }

  /* Iterator interface */

  public function rewind()  { $this->current = $this->read(); }
  public function current() { return $this->current; }
  public function key()     { /* not supported */ }
  public function next()    { $this->current = $this->read(); }
  public function valid()   { return !$this->eof; }

  /* Implementation */

  public function read_all() {
    $list = array();
    foreach ($this as $value) {
      $list[] = $value;
    }
    return $list;
  }

  /**
   * Reads one S-expression.
   */
  public function read() {
    list($token, $value) = $this->read_token();
    if ($token == 'list') {
      if ($value == '(') {
        return $this->read_list();
      }
      else {
        throw new SXP_EndOfListException('unexpected list terminator: )');
      }
    }
    else {
      return $value;
    }
  }

  protected function skip() {
    $this->read();
  }

  protected function read_token() {
    $this->skip_comments();
    switch ($char = $this->peek_char()) {
      case '(':
      case ')':
        return array('list', $this->read_char());
      case '#':
        return array('atom', $this->read_sharp());
      case '"':
        return array('atom', $this->read_string());
      case '<':
        return array('atom', $this->read_uri());
      default:
        return array('atom', $this->read_atom());
    }
  }

  protected function read_list() {
    $list = array();
    try {
      while (!$this->eof) {
        $list[] = $this->read();
      }
    }
    catch (SXP_EndOfListException $e) {}
    return $list;
  }

  protected function read_sharp() {
    $this->skip_char(); // '#'
    switch ($char = $this->read_char()) {
      case 'n':
        return NULL;
      case 'f':
        return FALSE;
      case 't':
        return TRUE;
      case 'b':
        return $this->read_integer(2);
      case 'o':
        return $this->read_integer(8);
      case 'd':
        return $this->read_integer(10);
      case 'x':
        return $this->read_integer(16);
      case '\\':
        return $this->read_character();
      case ';':
        $this->skip();
        return $this->read();
      default:
        throw new SXP_ReaderException(sprintf('invalid sharp-sign read syntax: %s', $char));
    }
  }

  protected function read_integer($base = 10) {
    $buffer = $this->read_literal();
    if (preg_match(constant('self::INTEGER_BASE_' . $base), $buffer)) {
      return function_exists('integer') ? integer($buffer, $base) : intval($buffer, $base);
    }
    else {
      throw new SXP_ReaderException(sprintf('illegal base-%d number syntax: %s', $base, $buffer));
    }
  }

  protected function read_atom() {
    $buffer = $this->read_literal();
    if (preg_match(self::FLOAT, $buffer)) {
      return function_exists('float') ? float($buffer) : (float)$buffer;
    }
    if (preg_match(self::INTEGER_BASE_10, $buffer)) {
      return function_exists('integer') ? integer($buffer) : (int)$buffer;
    }
    if (preg_match(self::RATIONAL, $buffer)) {
      return NULL; // FIXME
    }
    return function_exists('symbol') ? symbol($buffer) : $buffer;
  }

  protected function read_uri() {
    $buffer = '';
    $this->skip_char(); // '<'
    while ($this->peek_char() != '>') {
      $char = $this->read_char();
      $buffer .= ($char == '\\') ? $this->read_character() : $char;
    }
    $this->skip_char(); // '>'
    return function_exists('uri') ? uri($buffer) : $buffer;
  }

  protected function read_string() {
    $buffer = '';
    $this->skip_char(); // '"'
    while ($this->peek_char() != '"') {
      $char = $this->read_char();
      $buffer .= ($char == '\\') ? $this->read_character() : $char;
    }
    $this->skip_char(); // '"'
    return $buffer;
  }

  protected function read_character() {
    $char = $this->read_char();
    return array_key_exists($char, $this->escaped_chars) ? $this->escaped_chars[$char] : $char;
  }

  protected function read_literal() {
    $buffer = '';
    while (preg_match(self::ATOM, $this->peek_char())) {
      $buffer .= $this->read_char();
    }
    return $buffer;
  }

  protected function skip_comments() {
    while (!$this->eof) {
      $char = $this->peek_char();
      if ($char == ';') {
        while (!$this->eof) {
          if ($this->read_char() == "\n") {
            break;
          }
        }
      }
      else if (preg_match(self::WHITESPACE, $char)) {
        $this->skip_char();
      }
      else {
        break;
      }
    }
  }

  protected function read_chars($count = 1) {
    $buffer = '';
    for ($i = 0; $i < $count; $i++) {
      $buffer .= $this->read_char();
    }
    return $buffer;
  }

  protected function peek_char() {
    if (is_null($this->lookahead)) {
      $this->lookahead = $this->read_char(FALSE);
    }
    return $this->lookahead;
  }

  protected function skip_char() {
    $this->read_char();
  }

  protected function read_char($error_on_eof = TRUE) {
    if (!is_null($this->lookahead)) {
      $char = $this->lookahead;
      $this->lookahead = NULL;
    }
    else {
      if (($char = $this->getc()) === FALSE) {
        $this->eof = TRUE;
        if ($error_on_eof) {
          throw new SXP_EndOfInputException('unexpected end of input');
        }
      }
    }
    return $char;
  }

  protected function getc() {
    if (is_resource($this->input)) {
      return fgetc($this->input);
    }
    if (is_string($this->input) && $this->index < strlen($this->input)) {
      return $this->input[$this->index++];
    }
    return FALSE;
  }
} // class SXP_Reader

class SXP_Writer {} // class SXP_Writer

class SXP_Exception extends Exception {}
class SXP_ReaderException extends SXP_Exception {}
class SXP_EndOfInputException extends SXP_ReaderException {}
class SXP_EndOfListException extends SXP_ReaderException {}
class SXP_WriterException extends SXP_Exception {}
