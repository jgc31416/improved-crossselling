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
 * This class represents a set of itemsets, where an itemset is an array of integers
 * with an associated support count. Itemsets are ordered by size. For
 * example, level 1 means itemsets of size 1 (that contains 1 item).
 */
class Itemsets
{
    /** We store the itemsets in a list named "levels".
     * Position i in "levels" contains the list of itemsets of size i */
    private $levels = [];
    /** the total number of itemsets **/
    private $itemsetsCount = 0;
    /** a name that we give to these itemsets (e.g. "frequent itemsets") */
    private $name;

    /**
     * Constructor
     * @param string $name the name of these itemsets
     */
    public function __construct($name)
    {
        $this->name = $name;
        $this->levels[] = []; // We create an empty level 0 by
        // default.
    }

    /*
     */
    public function printItemsets()
    {
        print(" ------- {$this->name} -------\n");
        $patternCount = 0;
        $levelCount = 0;
        // for each level (a level is a set of itemsets having the same number of items)
        foreach ($this->levels as $level) {
            // print how many items are contained in this level
            print("  L {$levelCount} \n");
            // for each itemset
            foreach ($level as $itemset) {

                // print the itemset
                print("  pattern {$patternCount}:  ");
                $itemset->print();
                // print the support of this itemset
                print("support :  " . $itemset->getAbsoluteSupport() . "\n");
                $patternCount++;
            }
            $levelCount++;
        }
        print(" --------------------------------\n");
    }

    /*
     *
     */
    public function addItemset($itemset, $k)
    {
        while (count($this->levels) <= $k) {
            $this->levels[] = [];
        }
        $this->levels[$k][] = $itemset;
        $this->itemsetsCount++;
    }

    /*
     */
    public function getLevels()
    {
        return $this->levels;

    }

    /*
     *
     */
    public function getItemsetsCount()
    {
        return $this->itemsetsCount;
    }

    /*
     *
     */
    public function setName($newName)
    {
        $this->name = $newName;
    }

    /*
     *
     */
    public function decreaseItemsetCount()
    {
        $this->itemsetsCount--;
    }
}
