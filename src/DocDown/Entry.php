<?php
/*!
 * This file is part of the DocDown package. 
 * Copyright 2011 John-David Dalton <http://allyoucanleet.com/>
 * Available under MIT license <http://mths.be/mit>
 */

/**
 * A class to simplify parsing a single JSDoc entry.
 */
class Entry {

  /**
   * The documentation entry.
   * @member Entry
   * @type String
   */
  public $entry = "";

  /**
   * The source code.
   * @member Entry
   * @type String
   */
  public $source = "";

  /*--------------------------------------------------------------------------*/

  /**
   * Entry constructor.
   * @constructor
   * @param {String} $entry The documentation entry to analyse.
   * @param {String} $source The source code.
   */
  public function __construct( $entry, $source ) {
    $this->entry = $entry;
    $this->source = str_replace(PHP_EOL, "\n", $source);
  }

  /*--------------------------------------------------------------------------*/

  /**
   * Extracts the documentation entries from source code.
   * @static
   * @member Entry
   * @param {String} $source The source code.
   * @returns {Array} The array of entries.
   */
  public static function getEntries( $source ) {
    preg_match_all("#/\*(?![-!])[\s\S]*?\*/\s*[^=\n;]+#", $source, $result);
    return array_pop($result);
  }

  /*--------------------------------------------------------------------------*/

  /**
   * Extracts the function call from the entry.
   * @member Entry
   * @returns {String} The function call.
   */
  public function getCall() {
    // make regexp delimiter `@` to avoid problems with members containing `#`
    preg_match("@\*/\s*(?:function ([^(]*)|([^:=,]*))@", $this->entry, $result);
    if ($result = array_pop($result)) {
      $result = array_pop(explode("var ", trim(trim(array_pop(explode(".", $result))), "'")));
    }
    if (count($params = $this->getParams())) {
      // compile
      $result = array($result);
      foreach ($params as $param) {
        $result[] = $param[1];
      }
      // format
      $result = array_shift($result) ."(". implode($result, ", ") .")";
      $result = str_replace(", [", " [, ", str_replace("], [", ", ", $result));
    }
    return $result;
  }

  /**
   * Extracts the entry description.
   * @member Entry
   * @returns {String} The entry description.
   */
  public function getDesc() {
    preg_match("#/\*\*(?:\s*\*)? ([^@]+)#", $this->entry, $result);
    if (count($result)) {
      $type = $this->getType();
      $result = array_shift(preg_split("#\n\s*\* |\*/#", $result[1]));
      $result = ($type == "Function" ? "" : "(" . str_replace("|", ", ", trim($type, "{}")) . "): ") . trim($result);
    }
    return $result;
  }

  /**
   * Extracts the entry `example` data.
   * @member Entry
   * @returns {String} The entry `example` data.
   */
  public function getExample() {
    preg_match("#@example([\s\S]*)?(?=\*\s\@[a-z]|\*/)#", $this->entry, $result);
    if (count($result)) {
      $result = "~~~ js\n" . trim(preg_replace("/\n\s*\* ?/", "\n", $result[1])) . "\n~~~";
    }
    return $result;
  }

  /**
   * Resolves the line number of the entry.
   * @member Entry
   * @returns {Number} The line number.
   */
  public function getLineNumber() {
    preg_match_all("/\n/", substr($this->source, 0, strrpos($this->source, $this->entry) + strlen($this->entry)), $lines);
    return count(array_pop($lines)) + 1;
  }

  /**
   * Extracts the entry `member` data.
   * @member Entry
   * @param {Number} $index The index of the array value to return.
   * @returns {Array|String} The entry `member` data.
   */
  public function getMembers( $index = null ) {
    preg_match("/@member ([^\n]+)/", $this->entry, $result);
    if (count($result)) {
      $result = preg_split("/,\s*/", $result[1]);
    }
    return $index !== null ? @$result[$index] : $result;
  }

  /**
   * Extracts the entry `name` data.
   * @member Entry
   * @returns {String} The entry `name` data.
   */
  public function getName() {
    preg_match("/@name ([^\n]+)/", $this->entry, $result);
    return count($result) ? $result[1] : array_shift(explode("(", $this->getCall()));
  }

  /**
   * Extracts the entry `param` data.
   * @member Entry
   * @param {Number} $index The index of the array value to return.
   * @returns {Array} The entry `param` data.
   */
  public function getParams( $index = null ) {
    preg_match_all("/@param \{([^}]+)\} (\[[^]]+\]|[$\w]+) ([^\n]+)/", $this->entry, $result);
    if (count($result = array_filter(array_slice($result, 1)))) {
      // repurpose array
      foreach ($result as $param) {
        foreach ($param as $key => $value) {
          if (!is_array($result[0][$key])) {
            $result[0][$key] = array();
          }
          $result[0][$key][] = $value;
        }
      }
      $result = $result[0];
    }
    return $index !== null ? @$result[$index] : $result;
  }

  /**
   * Extracts the entry `returns` data.
   * @member Entry
   * @returns {String} The entry `returns` data.
   */
  public function getReturns() {
    preg_match("/@returns \{([^}]+)\} ([^*]+)/", $this->entry, $result);
    if (count($result)) {
      $result = array_map("trim", array_slice($result, 1));
      $result[0] = str_replace("|", ", ", $result[0]);
    }
    return $result;
  }

  /**
   * Extracts the entry `type` data.
   * @member Entry
   * @returns {String} The entry `type` data.
   */
  public function getType() {
    preg_match("/@type ([^\n]+)/", $this->entry, $result);
    return count($result) ? $result[1] : ($this->isCtor() || count($this->getReturns()) ? "Function" : "Unknown");
  }

  /**
   * Checks if an entry is a constructor.
   * @member Entry
   * @returns {Boolean} Returns true if a constructor, else false.
   */
  public function isCtor() {
    return strripos($this->entry, "@constructor") !== false;
  }

  /**
   * Checks if an entry *is* assigned to a prototype.
   * @member Entry
   * @returns {Boolean} Returns true if assigned to a prototype, else false.
   */
  public function isPlugin() {
    return !$this->isCtor() && !$this->isPrivate() && !$this->isStatic();
  }

  /**
   * Checks if an entry is private.
   * @member Entry
   * @returns {Boolean} Returns true if private, else false.
   */
  public function isPrivate() {
    return strripos($this->entry, "@private") !== false || strripos($this->entry, "@") === false;
  }

  /**
   * Checks if an entry is *not* assigned to a prototype.
   * @member Entry
   * @returns {Boolean} Returns true if not assigned to a prototype, else false.
   */
  public function isStatic() {
    $public = !$this->isPrivate();
    $result = $public && strripos($this->entry, "@static") !== false;

    // set in cases where it isn't explicitly stated
    if ($public && !$result) {
      if ($parent = array_pop(preg_split("/[#.]/", $this->getMembers(0)))) {
        foreach (Entry::getEntries($this->source) as $entry) {
          $entry = new Entry($entry, $this->source);
          if ($entry->getName() == $parent) {
            $result = !$entry->isCtor();
            break;
          }
        }
      } else {
        $result = true;
      }
    }
    return $result;
  }
}
?>