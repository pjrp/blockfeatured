<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @author    pp & the thirty bees <modules@thirtybees.com>
 * @license   Academic Free License (AFL 3.0)
 * 
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class BlockCategoryFeatured
 *
 * @since 1.0.0
 */
class BlockFeatured extends Module
{
    /**
     * BlockFeatured constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->name = 'blockfeatured';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'pp & the thirty bees';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Block Featured Products');
        $this->description = $this->l('List featured products by category in home and category pages.');
        $this->tb_versions_compliancy = '> 1.0.0';
        $this->tb_min_version = '1.0.0';
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function install($deleteParams = true)
    {

        if (!parent::install() 
            || !$this->registerHook('actionObjectCategoryDeleteAfter')
            || !$this->registerHook('actionAdminProductsControllerSaveAfter')
            || !$this->registerHook('actionObjectProductDeleteAfter')
            || !$this->registerHook('displayCategoryFeaturedProducts')
            || !$this->registerHook('displayHeader') 
            || !$this->registerHook('displayHome')
            ) {
            return false;
        } 

        $this->clearFeaturedCache();

        if ($deleteParams) {
            if (!Configuration::updateGlobalValue('BLOCK_FEAT_CATEGORY_ID', 0)
                || !Configuration::updateGlobalValue('BLOCK_FEAT_MAX_PROD', 4)
                || !Configuration::updateGlobalValue('BLOCK_FEAT_CATS', '')
                || !Configuration::updateGlobalValue('BLOCK_FEAT_HOME', '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function uninstall($deleteParams = true)
    {
        if (!parent::uninstall()) {
            return false;
        }

        $this->clearFeaturedCache();

        if ($deleteParams) {
            if (!Configuration::deleteByName('BLOCK_FEAT_CATEGORY_ID')
                || !Configuration::deleteByName('BLOCK_FEAT_MAX_PROD')
                || !Configuration::deleteByName('BLOCK_FEAT_CATS')
                || !Configuration::deleteByName('BLOCK_FEAT_HOME')
            ) {
                return false;
            }
        }

        return true;
    }


    /**
     * @return bool
     */
    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function getContent()
    {
        $output = '';
        $output .= '<p class="alert alert-warning">'.$this->l('Add the Hook in the theme category.tpl ').' <br>{hook h=\'displayCategoryFeaturedProducts\'}</p>';
       
        if (Tools::isSubmit('submitBlockFeatured')) {
            $maxProducts = (int) (Tools::getValue('BLOCK_FEAT_MAX_PROD'));
            $idFeaturedCategory = (int) (Tools::getValue('BLOCK_FEAT_CATEGORY_ID'));
            $categories = Tools::getValue('BLOCK_FEAT_CATS');
            $homeCategories = Tools::getValue('BLOCK_FEAT_HOME');
            if ($maxProducts < 0) {
                $output .= $this->displayError($this->l('Maximum products: Invalid number.'));
            } else {
                Configuration::updateValue('BLOCK_FEAT_CATEGORY_ID', $idFeaturedCategory);
                Configuration::updateValue('BLOCK_FEAT_MAX_PROD', $maxProducts);
                Configuration::updateValue('BLOCK_FEAT_CATS', implode(',',$categories));
                Configuration::updateValue('BLOCK_FEAT_HOME', implode(',',$homeCategories));

                $this->clearFeaturedCache();
                
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=6');
            }
        }

        return $output.$this->renderForm();
    }


    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function renderForm()
    {
        $categories = Category::getNestedCategories(Category::getRootCategory()->id);
        $categoriesOpts = $this->generateCategoriesOpts($categories);

        $formFields = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'  => 'select',
                        'label' => $this->l('Featured category'),
                        'name'  => 'BLOCK_FEAT_CATEGORY_ID',
                        'options' => [ 
                            'query' => $categoriesOpts,
                            'id' => 'id_category',
                            'name' => 'name'
                        ],
                        'desc'  => $this->l('Use this category to set a product as featured.'),
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Maximum products'),
                        'name'  => 'BLOCK_FEAT_MAX_PROD',
                        'desc'  => $this->l('Set the maximum products displayed in the blocks.'),
                    ],
                    [
                        'type'  => 'select',
                        'label' => $this->l('Show the featured products block in the following categories'),
                        'name'  => 'BLOCK_FEAT_CATS[]',
                        'desc'  => $this->l('For best result show only in parent categories and disable products listing in category options.'),
                        'multiple' => true,
                        'options' => [ 
                            'query' => $categoriesOpts,
                            'id' => 'id_category',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type'  => 'select',
                        'label' => $this->l('Categories to show in the home page'),
                        'name'  => 'BLOCK_FEAT_HOME[]',
                        'desc'  => $this->l('Unselect all to disable.'),
                        'multiple' => true,
                        'options' => [ 
                            'query' => $categoriesOpts,
                            'id' => 'id_category',
                            'name' => 'name'
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBlockFeatured';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];
        return $helper->generateForm([$formFields]);
    }

    /**
     * @return array
     *
     * @since 1.0.0
     */
    public function getConfigFieldsValues()
    {
        return [
            'BLOCK_FEAT_CATEGORY_ID' => Tools::getValue('BLOCK_FEAT_CATEGORY_ID', Configuration::get('BLOCK_FEAT_CATEGORY_ID')),
            'BLOCK_FEAT_MAX_PROD' => Tools::getValue('BLOCK_FEAT_MAX_PROD', Configuration::get('BLOCK_FEAT_MAX_PROD')),
            'BLOCK_FEAT_CATS[]' => Tools::getValue('BLOCK_FEAT_CATS', $this->getConfigCategories()),
            'BLOCK_FEAT_HOME[]' => Tools::getValue('BLOCK_FEAT_HOME', $this->getConfigHomeCategories()),
        ];
    }

    /**
     * @param array $categories
     * @param int $depth
     *
     * @return string
     * @throws PrestaShopException
     */
    protected function generateCategoriesOpts($categories, $depth=0) {
        $idRootCategory = Category::getRootCategory()->id;
        $list = [];
        foreach ($categories as $key => $category) {
            if($category['id_category'] != $idRootCategory) {
                $list[] = [
                    'id_category' => $category['id_category'],
                    'name' => str_repeat('-&nbsp;&nbsp;-&nbsp;&nbsp;', $depth-1 ).$category['name'],
                ];
            }
            if (isset($category['children']) && !!$category['children']) {
                $list = array_merge($list, $this->generateCategoriesOpts($category['children'], $depth + 1));
            }
        }
        return $list;
    }

    /**
     * @param int  $idCategory Category id
     * @param int  $idLang Language id
     * @param int  $idShop Shop id
     * @param bool $active
     *
     * @return array Products
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getFeaturedProducts($idCategory, $idLang, $idShop, $active = true)
    {
        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, pl.`description`, pl.`description_short`, pl.`link_rewrite`,
					pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`,
					image_shop.`id_image` id_image, il.`legend`, m.`name` as manufacturer_name, cl.`name` AS category_default, IFNULL(product_attribute_shop.id_product_attribute, 0) id_product_attribute,
					DATEDIFF(
						p.`date_add`,
						DATE_SUB(
							"'.date('Y-m-d').' 00:00:00",
							INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).' DAY
						)
					) > 0 AS new
                FROM (`'._DB_PREFIX_.'category_product` cp, `'._DB_PREFIX_.'category_product` cp2 )
                
				LEFT JOIN `'._DB_PREFIX_.'product` p ON p.`id_product` = cp.`id_product`
				'.Shop::addSqlAssociation('product', 'p').'
				LEFT JOIN `'._DB_PREFIX_.'product_attribute_shop` product_attribute_shop
					ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop='.(int) $idShop.')
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (
					p.`id_product` = pl.`id_product`
					AND pl.`id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('pl').'
				)
				LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (
					product_shop.`id_category_default` = cl.`id_category`
					AND cl.`id_lang` = '.(int) $idLang.Shop::addSqlRestrictionOnLang('cl').'
				)
				LEFT JOIN `'._DB_PREFIX_.'image_shop` image_shop
					ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop='.(int) $idShop.')
				LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = '.(int) $idLang.')
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (p.`id_manufacturer`= m.`id_manufacturer`)
				'.Product::sqlStock('p', 0).'
                WHERE cp.`id_category` = '.(int) $idCategory .' 
                AND  cp2.`id_category` = '.(int) Configuration::get('BLOCK_FEAT_CATEGORY_ID') .' 
                AND cp.id_product = cp2.id_product '.
            ($active ? ' AND product_shop.`active` = 1 AND product_shop.`visibility` != \'none\'' : '').'
                GROUP BY product_shop.id_product LIMIT '. Configuration::get('BLOCK_FEAT_MAX_PROD');

        if (!$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
            return false;
        }

        foreach ($result as &$row) {
            $row['id_product_attribute'] = Product::getDefaultAttribute((int) $row['id_product']);
        }

        return Product::getProductsProperties($idLang, $result);
    }

    /**
     * 
     * @return array Category IDs
     */
    protected function getConfigCategories()
    {
        return explode(',', Configuration::get('BLOCK_FEAT_CATS'));
    }

    /**
     * 
     * @return array Category IDs
     */
    protected function getConfigHomeCategories()
    {
        return explode(',', Configuration::get('BLOCK_FEAT_HOME'));
    }

    /**
     * Hook header
     *
     */
    
    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/block-featured.css', 'all');
    }

    /**
     * Clear cache
     *
     */
    protected function clearFeaturedCache()
    {
        $this->_clearCache('category-featured-products.tpl');
    }

    /**
     * @param $params
     *
     */
    public function hookActionObjectCategoryDeleteAfter($params)
    {
        $idCategory = Tools::getValue('id_category');
        if(in_array($idCategory, $this->getConfigCategories()) || in_array($idCategory, $this->getConfigHomeCategories())) {
            $this->clearFeaturedCache();
        }
    }

    /**
     * @param $params
     *
     */
    public function hookActionObjectProductDeleteAfter($params)
    {
        $this->clearFeaturedCache();
    }

    public function hookActionAdminProductsControllerSaveAfter($params)
    {
        $categories = Tools::getValue('categoryBox');
        if(is_array($categories)) {
            $this->clearFeaturedCache();       
        }
    }
     
    /**
     * @param $params
     *
     */
    public function hookDisplayHome($params)
    {
        return $this->hookDisplayCategoryFeaturedProducts($params);
    }

    /**
     * @param $params
     *
     */
    public function hookDisplayCategoryFeaturedProducts($params)
    {    
        if('category' == $this->context->controller->php_self) {
            $idCategory = (int) Tools::getValue('id_category');
            $catsEnabled = $this->getConfigCategories();
            if(!in_array($idCategory, $catsEnabled)) return;
        } else {
            $idCategory = 0;
            $homeCategories = $this->getConfigHomeCategories();
            if(empty($homeCategories)) return;
        }
    
        $idLang = $this->context->language->id;
        $idShop = $this->context->shop->id;
            
        $cacheId = $this->name.'|'.$idCategory.'|'.$idShop.'|'.$idLang;
        
        if (!$this->isCached('category-featured-products.tpl', $cacheId)) {
            if(!empty($homeCategories)){
                $categories = Category::getCategoryInformations($homeCategories);
            } else {
                $categories = $this->context->smarty->getTemplateVars('subcategories');
            }
            $categoryFeatured=[];
            foreach($categories as $category) {
                $products = $this->getFeaturedProducts($category['id_category'], $idLang, $idShop);
                if(!empty($products)){
                    $categoryFeatured[] = [
                        'name' => $category['name'],
                        'link_rewrite' => $category['link_rewrite'],
                        'products' => $products
                    ];
                }
            }
            $this->context->smarty->assign('categoryFeatured', $categoryFeatured);
        }

        return $this->display(__FILE__, 'category-featured-products.tpl', $cacheId);
    }
}
