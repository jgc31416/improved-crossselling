<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author Jesus Gazol <jgc3.1416@gmail.com>
 * @copyright  2007-2016 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

namespace fpgrowth;

require_once "AlgoFPGrowth.php";

/**
 * Class that wraps fpgrowth functionality for prestashop
 *
 * User: jesus gazol
 * Date: 04/07/16
 * Time: 14:37
 */
class PrestashopWrapper
{
    /**
     * Number of transactions we are going to pull from the database
     */
    const TRANSACTION_LIMIT = 10000;

    /**
     * Get the product counters from prestashop db
     * @return array
     */
    public function getProductCounters()
    {
        $sql = "
        SELECT
            id_product, COUNT(0) AS counter
        FROM
            ps_cart_product p
                LEFT JOIN
            (SELECT
                id_cart
            FROM
                ps_cart
            ORDER BY id_cart DESC
            LIMIT " . self::TRANSACTION_LIMIT . ") AS d
            ON p.id_cart = d.id_cart
        WHERE
            d.id_cart IS NOT NULL
        GROUP BY id_product" ;
        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $productDict = [];
        foreach ($rows as $row) {
            $productDict["{$row['id_product']}"] = $row['counter'];
        }
        return $productDict;
    }

    /**
     * Get the transactions from prestashop db
     * @return mixed
     */
    public function getTransactionsDb()
    {
        $sql = "
          SELECT group_concat(id_product separator ' ') as transaction
          FROM ps_cart_product
          GROUP BY id_cart
          ORDER BY id_cart DESC
          LIMIT " . self::TRANSACTION_LIMIT;
        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $transactions = [];
        foreach ($rows as $row) {
            $transactions[] = explode(" ", $row['transaction']);
        }
        return $transactions;
    }

    /**
     * Run product association fpgrowth algorithm
     * @param $minSupport float minimum support
     * @return mixed Levels of association rules found
     */
    public function getProductAssociationRules($minSupport = 0.02)
    {
        $algo = new AlgoFPGrowth();
        $transactions = $this->getTransactionsDb();
        $patterns = $algo->runAlgorithm($transactions, $minSupport, $this->getProductCounters(), count($transactions));
        return $patterns->getLevels();

    }

    /**
     * Save the product association rules, we are going to use only level 2
     * @param $levels mixed Association rules derived
     */
    public function saveProductAssociationRules($levels)
    {
        if (count($levels[2]) > 0) {
            #Truncate table
            \Db::getInstance()->execute("TRUNCATE TABLE ps_crossselling_pair");
            #Add new rules
            $sql = "INSERT INTO ps_crossselling_pair
                (`id_product_main`,
                `id_product_related`,
                `support`)
                VALUES ";
            foreach ($levels[2] as $rule) {
                $items = $rule->getItems();
                $sqlValues[] = "({$items[0]},{$items[1]}," . $rule->getAbsoluteSupport() . ")";
                #Add also reversed order
                $sqlValues[] = "({$items[1]},{$items[0]}," . $rule->getAbsoluteSupport() . ")";
            }
            $sql .= join(",", $sqlValues);

            \Db::getInstance()->execute($sql);
        }
        return true;
    }

    /**
     * Function to create the table needed in the database
     */
    public static function createTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "crossselling_pair (
              `id_crossselling_pair` INT NOT NULL AUTO_INCREMENT,
              `id_product_main` INT NOT NULL,
              `id_product_related` INT NOT NULL,
              `support` INT NULL DEFAULT 0,
              PRIMARY KEY (`id_crossselling_pair`),
              INDEX `idx_pmain` (`id_product_main` ASC, `support` ASC))
        ";
        \Db::getInstance()->execute($sql);
        return true;
    }

    /**
     * Function that injects data into test database, do not use in production
     */
    public function injectDummyCarts()
    {
        ini_set("max_execution_time",3600);
        $totalCarts = 30;
        $totalProducts = 3;
        $idProducts = range(1, 8);
        $idCustomers = range(1, 8);
        #Create cart
        for ($i = 1; $i < $totalCarts; $i++) {
            $cart = new \Cart();
            $cart->id_shop_group = 1;
            $cart->id_shop = 1;
            $cart->id_customer = array_rand($idCustomers);
            $cart->id_carrier = 2;
            $cart->id_address_delivery = 1;
            $cart->id_address_invoice = 1;
            $cart->id_currency = 1;
            $cart->id_lang = 1;
            $cart->secure_key = "";
            // Save new cart
            $cart->add();
            #Insert products
            for ($j = 0; $j <= $totalProducts; $j++) {
                $sql = "INSERT IGNORE INTO `prestashop`.`ps_cart_product`
                (`id_cart`,
                `id_product`,
                `id_address_delivery`,
                `id_shop`,
                `id_product_attribute`,
                `id_customization`,
                `quantity`
                )
                VALUES
                (" . $cart->id . "," . $idProducts[array_rand($idProducts)] . ", 1, 1, 0, 0, 1 )";
                $rows = \Db::getInstance()->execute($sql);
            }
        }
        return $rows;
    }
}
