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
 * This is an implementation of a FPTree as used by the FPGrowth algorithm.
 *
 */
class FPTree
{
    // List of items in the header table
    public $headerList = [];

    // List of pairs (item, frequency) of the header table
    public $mapItemNodes = [];

    // Map that indicates the last node for each item using the node links
    // key: item   value: an fp tree node
    public $mapItemLastNode = [];

    // root of the tree
    public $root; // null node

    public function __construct()
    {
        $this->root = new FPNode();
    }

    /**
     * Method for adding a transaction to the fp-tree (for the initial construction
     * of the FP-Tree).
     * @param transaction
     */
    public function addTransaction($transaction)
    {
        $currentNode = $this->root;
        // For each item in the transaction
        foreach ($transaction as $item) {
            // look if there is a node already in the FP-Tree
            $child = $currentNode->getChildWithID($item);
            if ($child == null) {
                // there is no node, we create a new one
                $newNode = new FPNode();
                $newNode->itemID = $item;
                $newNode->parent = $currentNode;
                // we link the new node to its parrent
                $currentNode->childs[] = $newNode;
                // we take this node as the current node for the next for loop iteration
                $currentNode = $newNode;
                // We update the header table.
                // We check if there is already a node with this id in the header table
                $this->fixNodeLinks($item, $newNode);
            } else {
                // there is a node already, we update it
                $child->counter++;
                $currentNode = $child;
            }
        }
    }

    /**
     * Method to fix the node link for an item after inserting a new node.
     * @param int $item the item of the new node
     * @param fpgrowth /FPNode $newNode the new node thas has been inserted.
     */
    private function fixNodeLinks($item, $newNode)
    {
        // get the latest node in the tree with this item
        if (isset($this->mapItemLastNode[$item])) {
            // if not null, then we add the new node to the node link of the last node
            $lastNode = $this->mapItemLastNode[$item];
            $lastNode->nodeLink = $newNode;
        }
        // Finally, we set the new node as the last node
        $this->mapItemLastNode[$item] = $newNode;

        if (!isset($this->mapItemNodes[$item])) {  // there is not
            $this->mapItemNodes[$item] = $newNode;
        }
    }

    /**
     * Method for adding a prefixpath to a fp-tree.
     * @param $prefixPath  The prefix path
     * @param $mapSupportBeta  The frequencies of items in the prefixpaths
     * @param $relativeMinsupp
     */
    function addPrefixPath($prefixPath, $mapSupportBeta, $relativeMinsupp)
    {
        // the first element of the prefix path contains the path support
        $pathCount = $prefixPath[0]->counter;

        $currentNode = $this->root;
        // For each item in the transaction  (in backward order)
        // (and we ignore the first element of the prefix path)
        for ($i = count($prefixPath) - 1; $i >= 1; $i--) {
            $pathItem = $prefixPath[$i];
            // if the item is not frequent we skip it
            if ($mapSupportBeta[$pathItem->itemID] >= $relativeMinsupp) {

                // look if there is a node already in the FP-Tree
                $child = $currentNode->getChildWithID($pathItem->itemID);
                if ($child == null) {
                    // there is no node, we create a new one
                    $newNode = new FPNode();
                    $newNode->itemID = $pathItem->itemID;
                    $newNode->parent = $currentNode;
                    $newNode->counter = $pathCount;  // set its support
                    $currentNode->childs[] = $newNode;
                    $currentNode = $newNode;
                    // We update the header table.
                    // and the node links
                    $this->fixNodeLinks($pathItem->itemID, $newNode);
                } else {
                    // there is a node already, we update it
                    $child->counter += $pathCount;
                    $currentNode = $child;
                }
            }
        }
    }

    /**
     * Method for creating the list of items in the header table,
     *  in descending order of support.
     * @param mixed $mapSupport the frequencies of each item (key: item  value: support)
     */
    function createHeaderList($mapSupport)
    {
        // create an array to store the header list with
        // all the items stored in the map received as parameter
        $this->headerList = array_keys($this->mapItemNodes);

        $sortFunction = function ($id1, $id2) use ($mapSupport) {
            // compare the support
            $compare = $mapSupport[$id2] - $mapSupport[$id1];
            // if the same frequency, we check the lexical ordering!
            // otherwise we use the support
            return ($compare == 0) ? ($id1 - $id2) : $compare;
        };
        // sort the header table by decreasing order of support
        usort($this->headerList, $sortFunction);

    }

    /**
     * Method for getting a string representation of the CP-tree
     * (to be used for debugging purposes).
     * @return string
     */
    public function __toString()
    {
        $temp = "F HeaderList: " . print_r($this->headerList, true) . "\n root: " . $this->root;
        return $temp;
    }


}
