<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *
 * @author Jesus Gazol <jgc3.1416@gmail.com>
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace fpgrowth;


class TransactionFileGenerator extends \SplFileObject
{

    function current()
    {
        $line = trim(parent::current());
        if (strlen($line) > 0) {
            return explode(" ", $line);
        } else {
            return [];
        }

    }


}