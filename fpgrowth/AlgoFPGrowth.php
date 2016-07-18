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

require_once "TransactionFileGenerator.php";
require_once "Itemset.php";
require_once "Itemsets.php";
require_once "FPTree.php";
require_once "FPNode.php";


/**
 * This is an implementation of the FPGROWTH algorithm (Han et al., 2004),
 * derived from the work of Philippe Fournier-Viger http://www.philippe-fournier-viger.com/spmf
 * FPGrowth is described here:
 *
 * Han, J., Pei, J., & Yin, Y. (2000, May). Mining frequent patterns without candidate generation. In ACM SIGMOD Record (Vol. 29, No. 2, pp. 1-12). ACM
 */
class AlgoFPGrowth
{
    // This variable is used to determine the size of buffers to store itemsets.
    // A value of 50 is enough because it allows up to 2^50 patterns!
    const BUFFERS_SIZE = 50;

    // for statistics
    public $startTimestamp;// start time of the latest execution
    public $endTime; // end time of the latest execution
    public $transactionCount = 0; // transaction count in the database
    public $itemsetCount; // number of freq. itemsets found
    public $memmoryUsed = 0;

    // parameter
    public $minSupportRelative;// the relative minimum support

    // The  patterns that are found
    // (if the user want to keep them into memory)
    public $patterns = null;
    // buffer for storing the current itemset that is mined when performing mining
    // the idea is to always reuse the same buffer to reduce memory usage.
    public $itemsetBuffer = null;
    // another buffer for storing fpnodes in a single path of the tree
    public $fpNodeTempBuffer = null;
    // This buffer is used to store an itemset that will be written to file
    // so that the algorithm can sort the itemset before it is output to file
    // (when the user choose to output result to file).
    public $itemsetOutputBuffer = null;

    /**
     * Method to run the FPGRowth algorithm.
     * @param mixed $input an array like db of transactions.
     * @param float $minsupp the minimum support threshold.
     * @param mixed $mapSupport
     * @param int $transactionCount number of transactions passed
     * @return fpgrowth /Itemsets result if no output file path is provided.
     */
    public function runAlgorithm($input, $minsupp, $mapSupport = null, $transactionCount = null)
    {

        // record start time
        $this->startTimestamp = microtime(true);
        // number of itemsets found
        $this->itemsetCount = 0;

        //initialize tool to record memory usage
        $this->memmoryUsed = memory_get_usage();
        $this->patterns = new Itemsets("FREQUENT ITEMSETS");

        // (1) PREPROCESSING: Initial database scan to determine the frequency of each item
        // The frequency is stored in a map:
        //    key: item   value: support
        if ($mapSupport == null) {
            $mapSupport = $this->scanDatabaseToDetermineFrequencyOfSingleItems($input);
        }
        if($transactionCount != null){
            $this->transactionCount = $transactionCount;
        }

        // convert the minimum support as percentage to a
        // relative minimum support
        $this->minSupportRelative = ceil($minsupp * $this->transactionCount);

        // (2) Scan the database again to build the initial FP-Tree
        // Before inserting a transaction in the FPTree, we sort the items
        // by descending order of support.  We ignore items that
        // do not have the minimum support.
        $tree = new FPTree();

        // for each line (transaction) until the end of the file
        foreach ($input as $transactionLine) {
            $transaction = [];
            // for each item in the transaction add items that have the minimum support
            foreach ($transactionLine as $item) {
                if ($mapSupport["$item"] >= $this->minSupportRelative) {
                    $transaction[] = $item;
                }
            }
            $this->sortTransaction($transaction, $mapSupport);

            // add the sorted transaction to the fptree.
            $tree->addTransaction($transaction);
        }

        // We create the header table for the tree using the calculated support of single items
        $tree->createHeaderList($mapSupport);


        // (5) We start to mine the FP-Tree by calling the recursive method.
        // Initially, the prefix alpha is empty.
        // if at least an item is frequent
        if (count($tree->headerList) > 0) {
            // initialize the buffer for storing the current itemset
            $itemsetBuffer = new \SplFixedArray($this::BUFFERS_SIZE);
            // and another buffer
            $this->fpNodeTempBuffer = new \SplFixedArray($this::BUFFERS_SIZE);
            // recursively generate frequent itemsets using the fp-tree
            // Note: we assume that the initial FP-Tree has more than one path
            // which should generally be the case.
            $this->fpgrowth($tree, $itemsetBuffer, 0, $this->transactionCount, $mapSupport);
        }

        // record the execution end time
        $this->endTime = microtime(true);
        // check the memory usage
        $this->memmoryUsed = (memory_get_usage() - $this->memmoryUsed) / (1024);
        // return the result (if saved to memory)
        return $this->patterns;
    }

    /**
     * Sort the transaction by support or lexical ordering
     * @param $transaction
     * @param $mapSupport
     */
    public function sortTransaction(&$transaction, $mapSupport)
    {
        $sortFunction = function ($id1, $id2) use (&$mapSupport) {
            // compare the support
            $compare = $mapSupport["$id2"] - $mapSupport["$id1"];
            // if the same frequency, we check the lexical ordering!
            // otherwise we use the support
            return ($compare == 0) ? ($id1 - $id2) : $compare;
        };
        usort($transaction, $sortFunction);
    }

    /**
     * Mine an FP-Tree having more than one path.
     * @param $tree array  the FP-tree
     * @param $prefix int  the current prefix, named "alpha"
     * @param $prefixLength array the frequency of items in the FP-Tree
     * @param $prefixSupport int
     * @param $mapSupport mixed
     */
    private function fpgrowth($tree, $prefix, $prefixLength, $prefixSupport, $mapSupport)
    {
        // We will check if the FPtree contains a single path
        $singlePath = true;
        // We will use a variable to keep the support of the single path if there is one
        $singlePathSupport = 0;
        // This variable is used to count the number of items in the single path
        // if there is one
        $position = 0;
        // if the root has more than one child, than it is not a single path
        if (count($tree->root->childs) > 1) {
            $singlePath = false;
        } else {
            // Explore the single path
            // if the root has exactly one child, we need to recursively check childs
            // of the child to see if they also have one child
            $currentNode = $tree->root->childs[0];
            while (true) {
                // if the current child has more than one child, it is not a single path!
                if (count($currentNode->childs) > 1) {
                    $singlePath = false;
                    break;
                }
                // otherwise, we copy the current item in the buffer and move to the child
                // the buffer will be used to store all items in the path
                $this->fpNodeTempBuffer[$position] = $currentNode;
                $position++;
                $singlePathSupport = $currentNode->counter;
                // if this node has no child, that means that this is the end of this path
                // and it is a single path, so we break
                if (count($currentNode->childs) == 0) {
                    break;
                }
                $currentNode = $currentNode->childs[0];
            }
        }

        // Case 1: the FPtree contains a single path
        if ($singlePath && $singlePathSupport >= $this->minSupportRelative) {
            // We save the path, because it is a maximal itemset
            $this->saveAllCombinationsOfPrefixPath($this->fpNodeTempBuffer, $position, $prefix, $prefixLength);
        } else {
            // For each frequent item in the header table list of the tree in reverse order.
            for ($i = count($tree->headerList) - 1; $i >= 0; $i--) {
                // get the item
                $item = $tree->headerList[$i];
                // get the item support
                $support = $mapSupport["$item"];
                // Create Beta by concatening prefix Alpha by adding the current item to alpha
                $prefix[$prefixLength] = $item;
                // calculate the support of the new prefix beta
                $betaSupport = ($prefixSupport < $support) ? $prefixSupport : $support;
                // save beta
                $this->saveItemset($prefix, $prefixLength + 1, $betaSupport);
                // === (A) Construct beta's conditional pattern base ===
                // It is a subdatabase which consists of the set of prefix paths
                // in the FP-tree co-occuring with the prefix pattern.
                $prefixPaths = [];
                $path = $tree->mapItemNodes[$item];
                // Map to count the support of items in the conditional prefix tree
                // Key: item   Value: support
                $mapSupportBeta = [];
                while ($path != null) {
                    // if the path is not just the root node
                    if ($path->parent->itemID != -1) {
                        // create the prefixpath
                        $prefixPath = [];
                        // add this node.
                        $prefixPath[] = $path;   // NOTE: we add it just to keep its support,
                        // actually it should not be part of the prefixPath
                        $pathCount = $path->counter;

                        //Recursively add all the parents of this node.
                        $parent = $path->parent;
                        while ($parent->itemID != -1) {
                            $prefixPath[] = $parent;
                            // FOR EACH PATTERN WE ALSO UPDATE THE ITEM SUPPORT AT THE SAME TIME
                            // if the first time we see that node id
                            if (!isset($mapSupportBeta["{$parent->itemID}"])) {
                                // just add the path count
                                $mapSupportBeta["{$parent->itemID}"] = $pathCount;
                            } else {
                                // otherwise, make the sum with the value already stored
                                $mapSupportBeta["{$parent->itemID}"] = $mapSupportBeta["{$parent->itemID}"] + $pathCount;
                            }
                            $parent = $parent->parent;
                        }
                        // add the path to the list of prefixpaths
                        $prefixPaths[] = $prefixPath;
                    }
                    // We will look for the next prefixpath
                    $path = $path->nodeLink;
                }

                // (B) Construct beta's conditional FP-Tree
                // Create the tree.
                $treeBeta = new FPTree();
                // Add each prefixpath in the FP-tree.
                foreach ($prefixPaths as $prefixPath) {
                    $treeBeta->addPrefixPath($prefixPath, $mapSupportBeta, $this->minSupportRelative);
                }

                // Mine recursively the Beta tree if the root has child(s)
                if (count($treeBeta->root->childs) > 0) {
                    // Create the header list.
                    $treeBeta->createHeaderList($mapSupportBeta);
                    // recursive call
                    $this->fpgrowth($treeBeta, $prefix, $prefixLength + 1, $betaSupport, $mapSupportBeta);
                }
            }
        }

    }

    /**
     * This method saves all combinations of a prefix path if it has enough support
     * @param $fpNodeTempBuffer mixed
     * @param $position int
     * @param $prefix mixed the current prefix
     * @param $prefixLength int the current prefix length
     * TODO: Check this out with a test
     */
    public function saveAllCombinationsOfPrefixPath($fpNodeTempBuffer, $position,
                                                    $prefix, $prefixLength)
    {
        $support = 0;
        // Generate all subsets of the prefixPath except the empty set
        // and output them
        // We use bits to generate all subsets.
        $i = 1;
        $max = pow(2, $position);
        for (; $i < $max; $i++) {
            // we create a new subset
            $newPrefixLength = $prefixLength;
            // for each bit
            for ($j = 0; $j < $position; $j++) {
                // check if the j bit is set to 1
                $isSet = (int)$i & pow(2, $j);
                // if yes, add the bit position as an item to the new subset
                if ($isSet > 0) {
                    $prefix[$newPrefixLength++] = $fpNodeTempBuffer[$j]->itemID;
                    if ($support == 0) {
                        $support = $fpNodeTempBuffer[$j]->counter;
                    }
                }
            }
            // save the itemset
            $this->saveItemset($prefix, $newPrefixLength, $support);
        }
    }


    /**
     * This method scans the input database to calculate the support of single items
     * @param $input string the path of the input file
     * @return mixed a map for storing the support of each item (key: item, value: support)
     * TODO: Turn it into SQL query
     */
    public function scanDatabaseToDetermineFrequencyOfSingleItems($input)
    {

        // a map for storing the support of each item (key: item, value: support)
        $mapSupport = [];
        foreach ($input as $lineSplited) {
            // for each item
            foreach ($lineSplited as $item) {
                // increase the support count of the item
                if (!isset($mapSupport["$item"])) {
                    $mapSupport["$item"] = 1;
                } else {
                    $mapSupport["$item"] += 1;
                }
            }
            // increase the transaction count
            $this->transactionCount++;
        }
        // Unset the file to call __destruct(), closing the file handle.
        $file = null;
        return $mapSupport;
    }


    /**
     * Write a frequent itemset that is found to the output file or
     * keep into memory if the user prefer that the result be saved into memory.
     * @param $itemset \SplFixedArray
     * @param $itemsetLength int
     * @param $support int
     */
    private function saveItemset($itemset, $itemsetLength, $support)
    {
        // increase the number of itemsets found for statistics purpose
        $this->itemsetCount++;
        // create an object Itemset and add it to the set of patterns
        // found.
        $itemsetArray = array_slice($itemset->toArray(), 0, $itemsetLength);
        // sort the itemset so that it is sorted according to lexical ordering before we show it to the user
        sort($itemsetArray);
        $itemsetObj = new Itemset($itemsetArray);
        $itemsetObj->setAbsoluteSupport($support);
        $this->patterns->addItemset($itemsetObj, $itemsetLength);
    }

    /**
     * Print statistics about the algorithm execution to System.out.
     */
    public function printStats()
    {
        print("=============  FP-GROWTH 0.96r19 - STATS =============");
        $temps = $this->endTime - $this->startTimestamp;
        print("\n Transactions count from database : {$this->transactionCount}");
        print("\n Min support relative: $this->minSupportRelative");
        print("\n Max memory usage Kb: {$this->memmoryUsed}");
        print("\n Frequent itemsets count : {$this->itemsetCount}");
        print("\n Total time ~ {$temps} ms");
        print("\n===================================================");
    }


}
