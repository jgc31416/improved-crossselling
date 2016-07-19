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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__)."/fpgrowth/PrestashopWrapper.php";

class ImprovedCrossSelling extends Module
{
    protected $html;

    public function __construct()
    {
        $this->name = 'improvedcrossselling';
        $this->tab = 'front_office_features';
        $this->version = '0.1';
        $this->author = 'Jesus Gazol <jgc3.1416@gmail.com>';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Improved Cross-selling');
        $this->description = $this->l('Adds a "Customers who bought this product also bought..." section to every product page.');
        $this->ps_versions_compliancy = array('min' => '1.5.6.1', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('productFooter') ||
            !$this->registerHook('header') ||
            !$this->registerHook('shoppingCart') ||
            !$this->registerHook('actionOrderStatusPostUpdate') ||
            !Configuration::updateValue('IMPROVED_XSELLING_DISPLAY_PRICE', "0") ||
            !Configuration::updateValue('IMPROVED_XSELLING_NBR', "10") ||
            !\fpgrowth\PrestashopWrapper::createTable()        ) {
            $this->processTransactionsDb();
            return false;
        }
        $this->_clearCache('improved_crossselling.tpl');

        return true;
    }

    public function uninstall()
    {
        $this->_clearCache('improved_crossselling.tpl');
        if (!parent::uninstall() ||
            !Configuration::deleteByName('IMPROVED_XSELLING_DISPLAY_PRICE') ||
            !Configuration::deleteByName('IMPROVED_XSELLING_NBR')
        ) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('submitCross')) {
            if (Tools::getValue('displayPrice') != 0 && Tools::getValue('IMPROVED_XSELLING_DISPLAY_PRICE') != 1) {
                $this->html .= $this->displayError('Invalid displayPrice');
            } elseif (!($product_nbr = Tools::getValue('IMPROVED_XSELLING_NBR')) || empty($product_nbr)) {
                $this->html .= $this->displayError('You must fill in the "Number of displayed products" field.');
            } elseif ((int)$product_nbr == 0) {
                $this->html .= $this->displayError('Invalid number.');
            } else {
                Configuration::updateValue('IMPROVED_XSELLING_DISPLAY_PRICE', (int)Tools::getValue('IMPROVED_XSELLING_DISPLAY_PRICE'));
                Configuration::updateValue('IMPROVED_XSELLING_NBR', (int)Tools::getValue('IMPROVED_XSELLING_NBR'));
                $this->_clearCache('improved_crossselling.tpl');
                $this->html .= $this->displayConfirmation($this->l('Settings updated successfully'));
            }
        }elseif (Tools::isSubmit('submitRefresh')) {
            $this->processTransactionsDb();
            $this->html .= $this->displayConfirmation($this->l('Rules updated successfully'));
        }

        return $this->html.$this->renderForm();
    }

    public function hookHeader()
    {
        if (!isset($this->context->controller->php_self) || !in_array(
                $this->context->controller->php_self, array(
                    'product',
                    'order',
                    'order-opc'
                )
            )
        ) {
            return;
        }
        if (in_array($this->context->controller->php_self, array('order')) && Tools::getValue('step')) {
            return;
        }
        $this->context->controller->addCSS(($this->_path).'css/crossselling.css', 'all');
        $this->context->controller->addJS(($this->_path).'js/crossselling.js');
        $this->context->controller->addJqueryPlugin(array('scrollTo', 'serialScroll', 'bxslider'));
    }




    /**
     * @param array $products_id an array of product ids
     * @return array
     */
    protected function getOrderProducts(array $products_id)
    {
        $final_products_list = array();
        $list_product_ids = join(',', $products_id);

        if (Group::isFeatureActive()) {
            $sql_groups_join = '
            LEFT JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.`id_category` = product_shop.id_category_default
                AND cp.id_product = product_shop.id_product)
            LEFT JOIN `'._DB_PREFIX_.'category_group` cg ON (cp.`id_category` = cg.`id_category`)';
            $groups = FrontController::getCurrentCustomerGroups();
            $sql_groups_where = 'AND cg.`id_group` '.(count($groups) ? 'IN ('.implode(',', $groups).')' : '='.(int)Group::getCurrent()->id);
        }

        $sqlSelection = '
            SELECT DISTINCT cpa.id_product_related as product_id, pl.name, pl.description_short, pl.link_rewrite, p.reference, i.id_image, product_shop.show_price,
                cl.link_rewrite category, p.ean13, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity
            FROM '._DB_PREFIX_.'crossselling_pair cpa
            LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = cpa.id_product_related)
            '.Shop::addSqlAssociation('product', 'p').
            (Combination::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
            ON (p.`id_product` = pa.`id_product`)
            '.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1').'
            '.Product::sqlStock('p', 'product_attribute_shop', false, $this->context->shop) :  Product::sqlStock('p', 'product', false,
                $this->context->shop)).'
            LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (pl.id_product = cpa.id_product_related'.Shop::addSqlRestrictionOnLang('pl').')
            LEFT JOIN '._DB_PREFIX_.'category_lang cl ON (cl.id_category = product_shop.id_category_default'
            .Shop::addSqlRestrictionOnLang('cl').')
            LEFT JOIN '._DB_PREFIX_.'image i ON (i.id_product = cpa.id_product_related)
            '.(Group::isFeatureActive() ? $sql_groups_join : '').'
            WHERE cpa.id_product_main in ('.$list_product_ids.')
                AND cpa.id_product_related NOT IN ('.$list_product_ids.')
                AND pl.id_lang = '.(int)$this->context->language->id.'
                AND cl.id_lang = '.(int)$this->context->language->id.'
                AND i.cover = 1
                AND product_shop.active = 1
                '.(Group::isFeatureActive() ? $sql_groups_where : '').'
            ORDER BY cpa.support desc
            LIMIT '.(int)Configuration::get('IMPROVED_XSELLING_NBR');

        $order_products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlSelection);

        if($order_products != false){
            $tax_calc = Product::getTaxCalculationMethod();
            foreach ($order_products as &$order_product) {
                $order_product['id_product'] = (int)$order_product['product_id'];
                $order_product['image'] = $this->context->link->getImageLink($order_product['link_rewrite'],
                    (int)$order_product['product_id'].'-'.(int)$order_product['id_image'], ImageType::getFormatedName('home'));
                $order_product['link'] = $this->context->link->getProductLink((int)$order_product['product_id'], $order_product['link_rewrite'],
                    $order_product['category'], $order_product['ean13']);
                if (Configuration::get('IMPROVED_XSELLING_DISPLAY_PRICE') && ($tax_calc == 0 || $tax_calc == 2)) {
                    $order_product['displayed_price'] = Product::getPriceStatic((int)$order_product['product_id'], true, null);
                } elseif (Configuration::get('IMPROVED_XSELLING_DISPLAY_PRICE') && $tax_calc == 1) {
                    $order_product['displayed_price'] = Product::getPriceStatic((int)$order_product['product_id'], false, null);
                }
                $order_product['allow_oosp'] = Product::isAvailableWhenOutOfStock((int)$order_product['out_of_stock']);

                if (!isset($final_products_list[$order_product['product_id'].'-'.$order_product['id_image']])) {
                    $final_products_list[$order_product['product_id'].'-'.$order_product['id_image']] = $order_product;
                }
            }
        }
        return $final_products_list;
    }

    /**
     * Returns module content
     */
    public function hookshoppingCart($params)
    {
        if (!$params['products']) {
            return;
        }

        $products_id = array();
        foreach ($params['products'] as $product) {
            $products_id[] = (int)$product['id_product'];
        }

        $cache_id = 'crossselling|shoppingcart|'.implode('|', $products_id);

        if (!$this->isCached('improved_crossselling.tpl', $this->getCacheId($cache_id))) {
            $final_products_list = $this->getOrderProducts($products_id);

            if (count($final_products_list) > 0) {
                $this->smarty->assign(
                    array(
                        'orderProducts' => $final_products_list,
                        'middlePosition_crossselling' => round(count($final_products_list) / 2, 0),
                        'crossDisplayPrice' => Configuration::get('IMPROVED_XSELLING_DISPLAY_PRICE')
                    )
                );
            }
        }

        return $this->display(__FILE__, 'improved_crossselling.tpl', $this->getCacheId($cache_id));
    }

    public function hookProductTabContent($params)
    {
        return $this->hookProductFooter($params);
    }

    public function displayProductListReviews($params)
    {
        return $this->hookProductFooter($params);
    }

    /**
     * Returns module content for product footer
     */
    public function hookProductFooter($params)
    {

        $cache_id = 'crossselling|productfooter|'.(int)$params['product']->id;
        if (!$this->isCached('improved_crossselling.tpl', $this->getCacheId($cache_id))) {
            $final_products_list = $this->getOrderProducts(array($params['product']->id));
            if (count($final_products_list) > 0) {
                $this->smarty->assign(
                    array(
                        'orderProducts' => $final_products_list,
                        'middlePosition_crossselling' => round(count($final_products_list) / 2, 0),
                        'crossDisplayPrice' => Configuration::get('IMPROVED_XSELLING_DISPLAY_PRICE')
                    )
                );
            }
        }
        $strOut = $this->display(__FILE__, 'improved_crossselling.tpl', $this->getCacheId($cache_id));
        return $strOut;
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $this->_clearCache('improved_crossselling.tpl');
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Display price on products'),
                        'name' => 'IMPROVED_XSELLING_DISPLAY_PRICE',
                        'desc' => $this->l('Show the price on the products in the block.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Number of displayed products'),
                        'name' => 'IMPROVED_XSELLING_NBR',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Set the number of products displayed in this block.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),

        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCross';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab
            .'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $forms = $helper->generateForm(array($fields_form));


        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Association rules')
                ),
                'submit' => array(
                    'title' => $this->l('Process Transactions'),
                )
            ),

        );
        $helper->submit_action = 'submitRefresh';
        $forms .= $helper->generateForm(array($fields_form));
        return $forms;
    }

    public function getConfigFieldsValues()
    {
        return array(
            'IMPROVED_XSELLING_NBR' => Tools::getValue('IMPROVED_XSELLING_NBR', Configuration::get('IMPROVED_XSELLING_NBR')),
            'IMPROVED_XSELLING_DISPLAY_PRICE' => Tools::getValue('IMPROVED_XSELLING_DISPLAY_PRICE', Configuration::get('IMPROVED_XSELLING_DISPLAY_PRICE')),
        );
    }

    /**
     * Processes all the carts in search of product association rules
     * Saves them into the database
     */
    public function processTransactionsDb(){
        $wrapper = new fpgrowth\PrestashopWrapper();
        $levels = $wrapper->getProductAssociationRules();
        $wrapper->saveProductAssociationRules($levels);
    }
}
