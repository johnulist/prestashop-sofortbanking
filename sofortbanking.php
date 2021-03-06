<?php
/**
 * sofortbanking Module
 *
 * Copyright (c) 2009 touchdesign
 *
 * @category  Payment
 * @author    Christin Gruber, <www.touchdesign.de>
 * @copyright 19.08.2009, touchdesign
 * @link      http://www.touchdesign.de/loesungen/prestashop/sofortueberweisung.htm
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module sofortbanking
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@touchdesign.de so we can send you a copy immediately.
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

class Sofortbanking extends PaymentModule
{
    const TIMEOUT = 10;
    const OS_ACCEPTED = 5;
    const OS_ERROR = 8;
    const OS_RECEIVED = 2;
    const OS_REFUNDED = 7;

    /** @var string HTML */
    private $html = '';

    /** @var string Supported languages */
    private $languages = array(
        'en' => array(
            'iso' => 'en',
            'logo' => 'https://images.sofort.com/uk/sb/200x75.png'
        ),
        'de' => array(
            'iso' => 'de',
            'logo' => 'https://images.sofort.com/de/su/200x75.png'
        ),
        'es' => array(
            'iso' => 'es',
            'logo' => 'https://images.sofort.com/es/sb/200x75.png'
        ),
        'fr' => array(
            'iso' => 'fr',
            'logo' => 'https://images.sofort.com/fr/sb/200x75.png'
        ),
        'it' => array(
            'iso' => 'it',
            'logo' => 'https://images.sofort.com/it/sb/200x75.png'
        ),
        'nl' => array(
            'iso' => 'nl',
            'logo' => 'https://images.sofort.com/nl/sb/200x75.png'
        ),
        'pl' => array(
            'iso' => 'pl',
            'logo' => 'https://images.sofort.com/pl/sb/200x75.png'
        ),
        'gb' => array(
            'iso' => 'gb',
            'logo' => 'https://images.sofort.com/uk/sb/200x75.png'
        ),
        'hu' => array(
            'iso' => 'hu',
            'logo' => 'https://images.sofort.com/hu/sb/200x75.png'
        ),
        'cs' => array(
            'iso' => 'cs',
            'logo' => 'https://images.sofort.com/cz/sb/200x75.png'
        ),
        'sk' => array(
            'iso' => 'sk',
            'logo' => 'https://images.sofort.com/sk/sb/200x75.png'
        )
    );

    /**
     * Build module
     *
     * @see PaymentModule::__construct()
     */
    public function __construct()
    {
        $this->name = 'sofortbanking';
        $this->tab = 'payments_gateways';
        $this->version = '3.0.1';
        $this->author = 'touchdesign';
        $this->module_key = '1e7a07b1bfca0b8e9c0be68eab098797';
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        $this->is_eu_compatible = 1;
        $this->controllers = array(
            'payment'
        );
        parent::__construct();
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('SOFORT');
        $this->description = $this->l('SOFORT - online direct payment method. More than 35,000 merchants in Europe trust SOFORT.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        if (!isset($this->context->smarty->registered_plugins['function']['displayPrice'])) {
            smartyRegisterFunction($this->context->smarty, 'function', 'displayPrice', array(
                'Tools',
                'displayPriceSmarty'
            ));
        }

        /* Add configuration warnings if needed */
        if (!Configuration::get('SOFORTBANKING_USER_ID')
            || !Configuration::get('SOFORTBANKING_PROJECT_ID')
            || !Configuration::get('SOFORTBANKING_API_KEY')) {
            $this->warning = $this->l('Module configuration is incomplete.');
        }
    }

    /**
     * Install module
     *
     * @see PaymentModule::install()
     */
    public function install()
    {
        if (!parent::install()
            || !Configuration::updateValue('SOFORTBANKING_USER_ID', '')
            || !Configuration::updateValue('SOFORTBANKING_PROJECT_ID', '')
            || !Configuration::updateValue('SOFORTBANKING_API_KEY', '')
            || !Configuration::updateValue('SOFORTBANKING_BLOCK_LOGO', 'Y')
            || !Configuration::updateValue('SOFORTBANKING_OS_ERROR', self::OS_ERROR)
            || !Configuration::updateValue('SOFORTBANKING_OS_ACCEPTED', self::OS_ACCEPTED)
            || !Configuration::updateValue('SOFORTBANKING_OS_ERROR_IGNORE', 'N')
            || !Configuration::updateValue('SOFORTBANKING_OS_ACCEPTED_IGNORE', 'N')
            || !$this->registerHook('payment')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('leftColumn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'touchdesign_sofortbanking_transaction(
            order_id INT(11) NOT NULL,
            transaction_id VARCHAR(255) NOT NULL,
            received DATETIME NULL,
            UNIQUE transaction (order_id, transaction_id)
        ) ENGINE=MyISAM default CHARSET=utf8';
        if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute($sql)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall module
     *
     * @see PaymentModule::uninstall()
     */
    public function uninstall()
    {
        if (!Configuration::deleteByName('SOFORTBANKING_USER_ID', '')
            || !Configuration::deleteByName('SOFORTBANKING_PROJECT_ID', '')
            || !Configuration::deleteByName('SOFORTBANKING_API_KEY', '')
            || !Configuration::deleteByName('SOFORTBANKING_BLOCK_LOGO', 'Y')
            || !Configuration::deleteByName('SOFORTBANKING_OS_ERROR', 8)
            || !Configuration::deleteByName('SOFORTBANKING_OS_ACCEPTED', 5)
            || !Configuration::deleteByName('SOFORTBANKING_OS_ERROR_IGNORE', 'N')
            || !Configuration::deleteByName('SOFORTBANKING_OS_ACCEPTED_IGNORE', 'N')
            || !parent::uninstall()) {
            return false;
        }

        $sql = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'touchdesign_sofortbanking_transaction';
        if (!Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute($sql)) {
            return false;
        }

        return true;
    }

    /**
     * Validate submited data
     */
    private function postValidation()
    {
        $this->_errors = array();
        if (Tools::getValue('submitUpdate')) {
            if (!Tools::getValue('SOFORTBANKING_USER_ID')) {
                $this->_errors[] = $this->l('sofortueberweisung "user id" is required.');
            }
            if (!Tools::getValue('SOFORTBANKING_PROJECT_ID')) {
                $this->_errors[] = $this->l('sofortueberweisung "project id" is required.');
            }
            if (!Tools::getValue('SOFORTBANKING_API_KEY')) {
                $this->_errors[] = $this->l('sofortueberweisung "API-Key" is required.');
            }
        }
    }

    /**
     * Update submited configurations
     */
    public function getContent()
    {
        $this->html = '<h2>' . $this->displayName . '</h2>';
        if (Tools::isSubmit('submitUpdate')) {
            Configuration::updateValue('SOFORTBANKING_USER_ID', Tools::getValue('SOFORTBANKING_USER_ID'));
            Configuration::updateValue('SOFORTBANKING_PROJECT_ID', Tools::getValue('SOFORTBANKING_PROJECT_ID'));
            Configuration::updateValue('SOFORTBANKING_API_KEY', Tools::getValue('SOFORTBANKING_API_KEY'));
            Configuration::updateValue('SOFORTBANKING_BLOCK_LOGO', Tools::getValue('SOFORTBANKING_BLOCK_LOGO'));
            Configuration::updateValue('SOFORTBANKING_OS_ACCEPTED', Tools::getValue('SOFORTBANKING_OS_ACCEPTED'));
            Configuration::updateValue('SOFORTBANKING_OS_ERROR', Tools::getValue('SOFORTBANKING_OS_ERROR'));
            Configuration::updateValue('SOFORTBANKING_OS_ACCEPTED_IGNORE', Tools::getValue('SOFORTBANKING_OS_ACCEPTED_IGNORE'));
            Configuration::updateValue('SOFORTBANKING_OS_ERROR_IGNORE', Tools::getValue('SOFORTBANKING_OS_ERROR_IGNORE'));
        }

        $this->postValidation();
        if (isset($this->_errors) && count($this->_errors)) {
            foreach ($this->_errors as $err) {
                $this->html .= $this->displayError($err);
            }
        } elseif (Tools::getValue('submitUpdate') && !count($this->_errors)) {
            $this->html .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $this->html . $this->displayForm();
    }

    /**
     * Build order state dropdown
     */
    private function getOrderStatesOptionFields($selected = null, $logable = false)
    {
        $order_states = OrderState::getOrderStates((int) $this->context->language->id);

        $result = '';
        foreach ($order_states as $state) {
            if ((!$logable && !$state['logable']) || ($logable && $state['logable'])) {
                $result .= '<option value="' . $state['id_order_state'] . '" ';
                $result .= $state['id_order_state'] == $selected ? 'selected' : '';
                $result .= '>' . $state['name'] . '</option>';
            }
        }

        return $result;
    }

    /**
     * Save transaction with associated order
     *
     * @param string $transaction
     * @param integer $order_id
     * @return boolean
     */
    public function saveTransaction($transaction, $order_id)
    {
        if (($order = $this->getOrderByTransaction($transaction)) && $order->id === null) {
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'touchdesign_sofortbanking_transaction SET
                order_id = ' . (int) trim($order_id) . ',
                transaction_id = \'' . pSQL(trim($transaction)) . '\',
                received = NOW()';
            if (Db::getInstance(_PS_USE_SQL_SLAVE_)->Execute($sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get order by transaction id
     *
     * @param string $transaction
     * @return Order|NULL
     */
    public function getOrderByTransaction($transaction)
    {
        if (!empty($transaction)) {
            return new Order(Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                SELECT order_id
                FROM `' . _DB_PREFIX_ . 'touchdesign_sofortbanking_transaction` AS o
                WHERE o.transaction_id = \'' . pSQL(trim($transaction)) . '\''));
        }

        return null;
    }

    /**
     * Build and display admin form for configurations
     */
    private function displayForm()
    {
        $dfl = array(
            'action' => $_SERVER['REQUEST_URI'],
            'mod_lang' => $this->isSupportedLang($this->context->language->iso_code),
            'img_path' => $this->_path . 'views/img/' . $this->isSupportedLang($this->context->language->iso_code)['iso'],
            'path' => $this->_path
        );

        $config = Configuration::getMultiple(array(
            'SOFORTBANKING_USER_ID',
            'SOFORTBANKING_PROJECT_ID',
            'SOFORTBANKING_API_KEY',
            'SOFORTBANKING_BLOCK_LOGO',
            'SOFORTBANKING_OS_ACCEPTED_IGNORE',
            'SOFORTBANKING_OS_ERROR_IGNORE'
        ));

        $order_states = array(
            'accepted' => $this->getOrderStatesOptionFields(Configuration::get('SOFORTBANKING_OS_ACCEPTED'), true),
            'error' => $this->getOrderStatesOptionFields(Configuration::get('SOFORTBANKING_OS_ERROR'))
        );

        $this->context->smarty->assign(array(
            'sofort' => array(
                'order_states' => $order_states,
                'dfl' => $dfl,
                'config' => $config
            )
        ));

        return $this->display(__FILE__, 'views/templates/admin/display_form.tpl');
    }

    /**
     * Check supported languages
     *
     * @param string $iso
     * @return string iso
     */
    private function isSupportedLang($iso = null)
    {
        if ($iso === null) {
            $iso = Language::getIsoById((int) $this->context->cart->id_lang);
        }

        if (isset($this->languages[$iso])) {
            return $this->languages[$iso];
        }

        return $this->languages['en'];
    }

    /**
     * Build and display payment button
     *
     * @param unknown $params
     * @return boolean|\PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $this->context->smarty->assign('mod_lang', $this->isSupportedLang());

        $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption->setCallToActionText($this->l('SOFORT (Online Bank Transfer)'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(
                'token' => Tools::getToken(false)
            ), true))
            ->setAdditionalInformation($this->context->smarty->fetch('module:sofortbanking/views/templates/hook/payment_options.tpl'));

        return array(
            $paymentOption
        );
    }

    /**
     * Build and display payment button
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookPayment($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $this->context->smarty->assign('mod_lang', $this->isSupportedLang());
        $this->context->smarty->assign('static_token', Tools::getToken(false));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * Build datas for eu payment hook
     *
     * @param array $params
     * @return array $result
     */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $result = array(
            'cta_text' => $this->l('Pay easy and secure with SOFORT Banking.'),
            'logo' => $this->isSupportedLang()['logo'],
            'action' => $this->context->link->getModuleLink($this->name, 'payment', array(
                'token' => Tools::getToken(false),
                'redirect' => true
            ), true)
        );

        return $result;
    }

    /**
     * Build and display confirmation
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        /* If PS version is >= 1.7 */
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->context->smarty->assign(array(
                'amount' => Tools::displayPrice($params['order']->getOrdersTotalPaid(), new Currency($params['order']->id_currency), false),
                'status' => ($params['order']->getCurrentState() == Configuration::get('SOFORTBANKING_OS_ACCEPTED') ? true : false)
            ));
        } else {
            $this->context->smarty->assign(array(
                'amount' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
                'status' => ($params['objOrder']->getCurrentState() == Configuration::get('SOFORTBANKING_OS_ACCEPTED') ? true : false)
            ));
        }

        $this->context->smarty->assign('shop_name', $this->context->shop->name);

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    /**
     * Build and display left column banner
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookLeftColumn()
    {
        if (Configuration::get('SOFORTBANKING_BLOCK_LOGO') == 'N') {
            return false;
        }

        $links = array(
            'hu' => 'https://documents.sofort.com/sb/ugyfelinformacio'
        );

        $this->context->smarty->assign('mod_lang', $this->isSupportedLang());
        $this->context->smarty->assign('sofort_link', (isset($links[$this->isSupportedLang()['iso']])
            ? $links[$this->isSupportedLang()] : $this->context->link->getCMSLink(5)));

        return $this->display(__FILE__, 'views/templates/hook/left_column.tpl');
    }

    /**
     * Check if payment is active
     *
     * @return boolean
     */
    public function isPayment()
    {
        if (!$this->active) {
            return false;
        }

        if (!Configuration::get('SOFORTBANKING_USER_ID')
            || !Configuration::get('SOFORTBANKING_PROJECT_ID')
            || !Configuration::get('SOFORTBANKING_API_KEY')) {
            return false;
        }

        return true;
    }
}
