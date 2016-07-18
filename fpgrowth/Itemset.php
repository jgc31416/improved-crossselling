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


/**
 * This class represents an itemset (a set of items) implemented as an array of integers with
 * a variable to store the support count of the itemset.
 *
 * @author Jesus Gazol
 */
class Itemset
{
    /** the array of items **/
    public $itemset = [];

    /**  the support of this itemset */
    public $support = 0;

    public function __construct($itemset = false)
    {
        if ($itemset != false) {
            $this->itemset = $itemset;
        }
    }

    /**
     * Get the items as array
     * @return mixed the items
     */
    public function getItems()
    {
        return $this->itemset;
    }

    /**
     * Get the support of this itemset
     */
    public function getAbsoluteSupport()
    {
        return $this->support;
    }

    /**
     * Get the size of this itemset
     */
    public function size()
    {
        return count($this->itemset);
    }

    /**
     * Get the item at a given position in this itemset
     * @param $position
     * @return mixed
     */
    public function get($position)
    {
        return $this->itemset[$position];
    }

    /**
     * Set the support of this itemset
     * @param $support int the support
     */
    public function setAbsoluteSupport($support)
    {
        $this->support = $support;
    }

    /**
     * Increase the support of this itemset by 1
     */
    public function increaseTransactionCount()
    {
        $this->support++;
    }

    /**
     * This method return an itemset containing items that are included
     * in this itemset and in a given itemset
     * @param $itemset2 Itemset the given itemset
     * @return Itemset the new itemset
     */
    public function intersection($itemset2)
    {
        $resItemset = new Itemset();
        $resItemset->itemset = $this->intersectTwoSortedArrays($this->getItems(), $itemset2->getItems());
        return $resItemset;
    }

    /**
     * Intersection of two sorted arrays
     * @param $array1
     * @param $array2
     * @return array
     */
    public function intersectTwoSortedArrays($array1, $array2)
    {
        // create a new array having the smallest size between the two arrays
        $newArraySize = (count($array1) < count($array2)) ? count($array1) : count($array2);
        $newArray = new SplFixedArray($newArraySize);

        $pos1 = 0;
        $pos2 = 0;
        $posNewArray = 0;
        while ($pos1 < count($array1) && $pos2 < count($array2)) {
            if ($array1[$pos1] < $array2[$pos2]) {
                $pos1++;
            } else if ($array2[$pos2] < $array1[$pos1]) {
                $pos2++;
            } else { // if they are the same
                $newArray[$posNewArray] = $array1[$pos1];
                $posNewArray++;
                $pos1++;
                $pos2++;
            }
        }
        // return the subrange of the new array that is full.
        return array_slice($newArray, 0, $posNewArray);
    }


}
