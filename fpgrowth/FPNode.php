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
 * This is an implementation of a FPTree node as used by the FPGrowth algorithm.
 *
 */
class FPNode {
	public $itemID = -1;  // item id
	public $counter = 1;  // frequency counter  (a.k.a. support)

	// the parent node of that node or null if it is the root
	public  $parent = null;
	// the child nodes of that node
	public $childs = [];
	public $nodeLink = null; // link to next node with the same item id (for the header table).

	/**
	 * Return the immediate child of this node having a given ID.
	 * If there is no such child, return null;
	 * @param int $id
	 * @return mixed|null
	 */
	function getChildWithID($id) {
		// for each child node
		foreach($this->childs as $child){
			// if the id is the one that we are looking for
			if($child->itemID == $id){
				// return that node
				return $child;
			}
		}
		// if not found, return null
		return null;
	}

	/**
	 * Method for getting a string representation of this tree
	 * (to be used for debugging purposes).
	 * @return string a string
	 */
	public function __toString() {
		$strOut = "( id={$this->itemID} count={$this->counter})\n";
		foreach($this->childs as $child) {
			$strOut .= "   " . $child;
		}
		return $strOut;
	}

}
