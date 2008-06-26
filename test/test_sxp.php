<?php
/**
 * test/test_sxp.php - Unit tests for SXP.
 *
 * @author Arto Bendiken <http://bendiken.net/>
 * @copyright Copyright (c) 2007-2008 Arto Bendiken. All rights reserved.
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @license http://www.opensource.org/licenses/gpl-license.php GPL
 * @package sxp
 */

require_once 'inquisition.php';
require_once 'sxp.php';

class SXP_Inquisitor implements Inquisitor {
  function test_tokenization() {
    assert_match(SXP_Reader::FLOAT, '3.1415');
    assert_no_match(SXP_Reader::FLOAT, '3');
    assert_match(SXP_Reader::INTEGER_BASE_10, '3');
    assert_no_match(SXP_Reader::INTEGER_BASE_10, '3.1415');
    assert_match(SXP_Reader::RATIONAL, '1/3');
    assert_match(SXP_Reader::ATOM, 'abc');
    assert_match(SXP_Reader::ATOM, 'a:b:c');
  }

  function test_booleans() {
    assert_equal(NULL, sxp_read('#n'));
    assert_equal(FALSE, sxp_read('#f'));
    assert_equal(TRUE, sxp_read('#t'));
  }

  function test_symbols() {
    assert_instance_of(@symbol, sxp_read('a'));
    assert_instance_of(@symbol, sxp_read('foo-bar'));
    assert_instance_of(@symbol, sxp_read('a:b:c'));
  }

  function test_uris() {
    assert_instance_of(@uri, sxp_read('<mailto:john@example.org>'));
    assert_equal('<mailto:john@example.org>', sxp_read('<mailto:john@example.org>'));
  }

  function test_numbers() {
    assert_true(is_number(sxp_read('3')));
    assert_equal(3, sxp_read('3'));
    assert_true(is_number(sxp_read('3.1415')));
    assert_equal(3.1415, sxp_read('3'));
  }

  function test_strings() {
    assert_equal('foobar', sxp_read('"foobar"'));
    assert_equal('"quoted"', sxp_read('"\"quoted\""'));
    assert_equal("\b\f\n\r\t", sxp_read('"\b\f\n\r\t"'));
  }

  function test_lists() {
    assert_equal(array(), sxp_read('()'));
    assert_equal(array(array(), array()), sxp_read('(() ())'));
    assert_equal(array(NULL, FALSE, TRUE), sxp_read('(#n #f #t)'));
    assert_equal(array(symbol('a'), symbol('b'), symbol('c')), sxp_read('(a b c)'));
    //assert_equal(array(1, 2, 3), sxp_read('(1 2 3)'));
  }

  function test_invalid_lists() {
    assert_throws(@SXP_EndOfListException, 'sxp_read', array(')'));
    assert_throws(@SXP_EndOfListException, 'sxp_read_all', array('())'));
  }

  function test_iteration() {
    $sxp = '(a (b) c)';
    $values = sxp_read($sxp);
    foreach (sxp_read($sxp) as $index => $value) {
      assert_equal($values[$index], $value);
    }
  }
}

execute_inquisitors(@SXP_Inquisitor);
