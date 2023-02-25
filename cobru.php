<?php
/**
 * 2007-2018 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2017 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

require __DIR__ . '/lib/PaymentMethod.php';

require __DIR__ . '/lib/CobruRepository.php';

require __DIR__.'/lib/CobruService.php';

require __DIR__.'/lib/CobruDb.php';

class cobru extends PaymentModule {

    protected  $config_form = false;
    private $_html= '';
    private $_posterrors = array();

    public $active;

    public $x_api_key;

    public $refresh_token;

    public  $cobru_mode;

    private $config;

    private $date_format = 'Y-m-d';

    public const X_API_KEY = 'X_API_KEY';
    public const REFRESH_TOKEN = 'REFRESH_TOKEN';
    public const PAYMENT_EXPIRATION_DAYS = 'PAYMENT_EXPIRATION_DAYS';
    public const PAYMENT_METHOD_ENABLED = 'PAYMENT_METHOD_ENABLED';
    public const NOTIFY_CALLBACK_URL = 'NOTIFY_CALLBACK_URL';
    public const PAYER_REDIRECT_URL = 'PAYER_REDIRECT_URL';
    public const COBRU_MODE = 'COBRU_MODE';
    public const C_TITULO = 'C_TITULO';
    public const STATUS_PAYMENT_ORDER = 'STATUS_PAYMENT_ORDER';
    public const INITIAL_STATUS_PAYMENT_ORDER = 'INITIAL_STATUS_PAYMENT_ORDER';

    public const SUCCESS_PAYMENT_STATUS =  3;

    public function __construct() {
        $this->name =  'cobru';
        $this->version = '1.0.0';
        $this->tab = 'payments_gateways';
        $this->author = 'oscar-rey';
        $this->need_instance= 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('cobru');
        $this->description = $this->l('Integra cobru en tu tienda virtual y provee a tus clientes de forma fácil múltiples medios de pago (cobru billetera virtual, pse, tarjeta de cerdito, nequi, !dale, bancolombia, efecty)');
        $this->confirmuninstall = $this->l('¿Esta seguro de desistalar el modulo?');

        $this->active = Configuration::get($this->name);
        $this->x_api_key = Configuration::get(self::X_API_KEY);
        $this->refresh_token = Configuration::get(self::REFRESH_TOKEN);
        $this->cobru_mode = Configuration::get(self::COBRU_MODE);

        if(!sizeof(Currency::checkpaymentcurrencies($this->id)))
            $this->warning = $this->l('no currency set for this module');


    }

    public function install()
    {
        if(Configuration::get('cobru') == 1) {
            $this->_errors[] = $this->l('Modulo cobru ya se encuentra instalado');
            return false;
        }

        if(!extension_loaded('curl')) {
            $this->_errors[] = $this->l('Modulo cobru requiere extensión curl');
            return false;
        }

        Configuration::updateValue(cobru::PAYMENT_METHOD_ENABLED, PaymentMethod::toString());
        Configuration::updateValue(cobru::PAYMENT_EXPIRATION_DAYS, 365);
        Configuration::updateValue(cobru::NOTIFY_CALLBACK_URL, $this->generateUrlModule(cobru::NOTIFY_CALLBACK_URL));
        Configuration::updateValue(cobru::PAYER_REDIRECT_URL, $this->generateUrlModule(cobru::PAYER_REDIRECT_URL));
        Configuration::updateValue(cobru::C_TITULO, $this->l('La mejor forma de pagar es con cobru'));
        Configuration::updateValue($this->name, true);

        CobruDb::setup();

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('orderDetailDisplayed');

    }


    public function generateUrlModule($key) {

        $url = [
            cobru::NOTIFY_CALLBACK_URL => Context::getContext()->link->getModuleLink('cobru', 'response'),
            cobru::PAYER_REDIRECT_URL => Context::getContext()->link->getModuleLink('cobru', 'redirect'),
            'validation' => Context::getContext()->link->getModuleLink('cobru', 'validation', [], true)
        ];

        return $url[$key];
    }


    public function uninstall()
    {

        Configuration::deleteByName(cobru::NOTIFY_CALLBACK_URL);
        Configuration::deleteByName(cobru::PAYER_REDIRECT_URL);
        Configuration::deleteByName(cobru::REFRESH_TOKEN);
        Configuration::deleteByName(cobru::X_API_KEY);
        Configuration::updateValue($this->name, false);

        CobruDb::remove();

        return parent::uninstall();
    }


    public function getContent() {

        if(Tools::isSubmit('submit'. $this->name) &&  !$this->validation_settings()) {
          $this->process_settings();

        }

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path
        ));
        /*$this->_html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/infos.tpl');*/
        return $this->_html . $this->displayForm();
    }

    /** save data form config */
    public function process_settings() {
        $notification_url = Tools::getValue(cobru::NOTIFY_CALLBACK_URL);
        $redirect_url = Tools::getValue(cobru::PAYER_REDIRECT_URL);

        if(empty($notification_url)) {
            $notification_url = $this->generateUrlModule(cobru::NOTIFY_CALLBACK_URL);
        }

        if(empty($redirect_url)) {
            $redirect_url = $this->generateUrlModule(cobru::PAYER_REDIRECT_URL);
        }

        Configuration::updateValue(cobru::C_TITULO, Tools::getValue(cobru::C_TITULO));
        Configuration::updateValue(cobru::X_API_KEY, Tools::getValue(cobru::X_API_KEY));
        Configuration::updateValue(cobru::REFRESH_TOKEN, Tools::getValue(cobru::REFRESH_TOKEN));
        Configuration::updateValue(cobru::PAYMENT_METHOD_ENABLED, implode(',', Tools::getValue(cobru::PAYMENT_METHOD_ENABLED)));
        Configuration::updateValue(cobru::PAYMENT_EXPIRATION_DAYS, Tools::getValue(cobru::PAYMENT_EXPIRATION_DAYS));
        Configuration::updateValue(cobru::COBRU_MODE, Tools::getValue(cobru::COBRU_MODE));
        Configuration::updateValue(cobru::STATUS_PAYMENT_ORDER, Tools::getValue(cobru::STATUS_PAYMENT_ORDER));
        Configuration::updateValue(cobru::INITIAL_STATUS_PAYMENT_ORDER, Tools::getValue(cobru::INITIAL_STATUS_PAYMENT_ORDER));
        Configuration::updateValue(cobru::PAYER_REDIRECT_URL, $redirect_url);
        Configuration::updateValue(cobru::NOTIFY_CALLBACK_URL, $notification_url);

        $this->_html .= '<div class="bootstrap"><div class="alert alert-success">'.$this->l('Configuración guardada con éxito.') .'</div> </div>';
    }


    private function validation_settings() {

        $validator = new \Rakit\Validation\Validator([
            'required' => $this->l(':attribute es requerido'),
            'array' => $this->l(':attribute debe ser un array valido'),
            'numeric' => $this->l(':attribute debe ser un numero valido'),
            'min' => $this->l(':attribute no debe ser menor de :min'),
            'max' => $this->l(':attribute no debe ser mayor de :max'),
            'url' => $this->l(':attribute formato de url no valida'),
            'boolean' => $this->l(':attribute debe ser un buleano valido true o false')
        ]);

        $closure = function ($value) {
            if (CobruRepository::exitOrderState($value)) {
                return true;
            }
            return $this->l('Estado de orden no existente');
        };

        $validation = $validator->make(Tools::getAllValues(), [
           cobru::C_TITULO                   => 'required',
           cobru::X_API_KEY                  => 'required',
           cobru::REFRESH_TOKEN              => 'required',
           cobru::PAYMENT_METHOD_ENABLED     => 'required|array',
           cobru::PAYMENT_EXPIRATION_DAYS    => 'required|numeric|min:0|max:365',
           cobru::NOTIFY_CALLBACK_URL        => 'url:http,https',
           cobru::PAYER_REDIRECT_URL         => 'url:http,https',
           cobru::COBRU_MODE                 => 'required|boolean',
            cobru::STATUS_PAYMENT_ORDER      => [
                'required',
                $closure
            ],
            cobru::INITIAL_STATUS_PAYMENT_ORDER => [
                'required',
                $closure
            ]
        ]);

        $validation->setAliases([
           cobru::C_TITULO => $this->l('Titulo'),
           cobru::PAYMENT_METHOD_ENABLED => $this->l('Métodos de pago permitidos'),
           cobru::PAYMENT_EXPIRATION_DAYS => $this->l('Cantidad de días para la expiración del pago'),
           cobru::NOTIFY_CALLBACK_URL => $this->l('Url de notificación'),
           cobru::PAYER_REDIRECT_URL => $this->l('Url de redirección'),
           cobru::COBRU_MODE => $this->l('Cambiar de modo cobru'),
            cobru::X_API_KEY => 'X_API_KEY',
            cobru::REFRESH_TOKEN => 'REFRESH_TOKEN',
            cobru::STATUS_PAYMENT_ORDER => $this->l('Estado de orden'),
            cobru::INITIAL_STATUS_PAYMENT_ORDER => $this->l('Estado inicial de orden')
        ]);

        $validation->validate();

        $errors = $validation->errors->all();

        foreach ($errors as $error) {
            $this->_html .= $this->displayError($error);
        }

        return count($errors) > 0;

    }

    public function displayForm(){

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->module = $this;
        $helper->submit_action = 'submit'. $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex. '&' . http_build_query(['configure' => $this->name]);
        $helper->default_form_language = $this->context->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;

        $helper->tpl_vars = [
          'fields_value' => $this->load_settings()
        ];

       return $helper->generateForm([$this->getConfigForm()]);

    }

    public function load_settings() {
        return [
            cobru::PAYMENT_METHOD_ENABLED.'[]' =>  Tools::getValue(cobru::PAYMENT_METHOD_ENABLED, $this->getPaymentMethodArray()),
            cobru::C_TITULO => Tools::getValue(cobru::C_TITULO, Configuration::get(cobru::C_TITULO)),
            cobru::X_API_KEY => Tools::getValue(cobru::X_API_KEY, Configuration::get(cobru::X_API_KEY)),
            cobru::REFRESH_TOKEN => Tools::getValue(cobru::REFRESH_TOKEN, Configuration::get(cobru::REFRESH_TOKEN)),
            cobru::PAYMENT_EXPIRATION_DAYS => Tools::getValue(cobru::PAYMENT_EXPIRATION_DAYS, Configuration::get(cobru::PAYMENT_EXPIRATION_DAYS)),
            cobru::PAYER_REDIRECT_URL => Tools::getValue(cobru::PAYER_REDIRECT_URL, Configuration::get(cobru::PAYER_REDIRECT_URL)),
            cobru::NOTIFY_CALLBACK_URL => Tools::getValue(cobru::NOTIFY_CALLBACK_URL, Configuration::get(cobru::NOTIFY_CALLBACK_URL)),
            cobru::COBRU_MODE => Tools::getValue(cobru::COBRU_MODE, Configuration::get(cobru::COBRU_MODE)),
            cobru::STATUS_PAYMENT_ORDER => Tools::getValue(cobru::STATUS_PAYMENT_ORDER, Configuration::get(cobru::STATUS_PAYMENT_ORDER)),
            cobru::INITIAL_STATUS_PAYMENT_ORDER => Tools::getValue(cobru::INITIAL_STATUS_PAYMENT_ORDER, Configuration::get(cobru::INITIAL_STATUS_PAYMENT_ORDER))
        ];
    }

     public function getPaymentMethodArray() {
        return explode(',', Configuration::get(cobru::PAYMENT_METHOD_ENABLED));
     }
    public function hookPaymentOptions($params) {

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        $this->context->smarty->assign(['titulo' => Configuration::get(cobru::C_TITULO)]);

        $paymentOption->setCallToActionText($this->l(''))
            ->setAction($this->generateUrlModule('validation'))
            ->setAdditionalInformation($this->context->smarty->fetch('module:cobru/views/templates/hook/payment_infos.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/checkout-logo.jpg'));

        return [
          $paymentOption
        ];
    }


    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        $order = $params['order'];

        if($order->payment != $this->name || !$this->checkCurrency($order) || !$this->active) {
            return;
        }

        $cobru = CobruDb::findByOrderId($order->id);

        $link = $cobru['cobru_link'];

        $expirationLink = $cobru['expiration_date'] ?? date($this->date_format);

        $currentDate = date($this->date_format);

        if(!$link || strtotime($expirationLink) <= strtotime($currentDate)) {
            
           $cobruService = $this->generateCobruLink($params);

            $link = $cobruService->getLink();
            $linkId = $cobruService->linkId;

            if($link){
                CobruDb::deleteByOrderId($order->id);
                CobruDb::create($order->id, $linkId, $link ,$this->dateAddDays(Configuration::get(cobru::PAYMENT_EXPIRATION_DAYS)));
            }
       }

        $this->smarty->assign([
            'paymentUrl' => $link
        ]);

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

/** Le suma a la fecha ctual los dias pasados por parametro*/
    /**
     * @param int $days
    **/
    public function dateAddDays($days) {
        $days = '+ '.$days.' days';

       return date($this->date_format, strtotime(date($this->date_format).$days));
    }

    public function hookOrderDetailDisplayed($params)
    {
        return $this->hookPaymentReturn($params);
    }

    /**
     * @description generar link de pago cobru
     */
    public function generateCobruLink($params) {

        $order = $params['order'];
        
        $totalToPay = version_compare(_PS_VERSION_,'1.7.0.0', '<')
            ? $params['total_to_pay']
            : $order->getOrdersTotalPaid();

        $reference = $order->reference;

        $paymentMethodEnabled = $this->booleanAssoc($this->getPaymentMethodArray());

        $imagesProduct = $this->getProductImageListUrl($order);

       return (new CobruService(
            Configuration::get(cobru::X_API_KEY),
            Configuration::get(cobru::REFRESH_TOKEN),
            Configuration::get(cobru::COBRU_MODE) ))
           ->auth()
            ->createLinkPayment(
                $order->id,
                $totalToPay,
                $imagesProduct,
                $reference,
                ContextCore::getContext()->shop->name.', '.$reference,
                Configuration::get(cobru::PAYMENT_EXPIRATION_DAYS),
                $paymentMethodEnabled,
                Configuration::get(cobru::NOTIFY_CALLBACK_URL),
                Configuration::get(cobru::PAYER_REDIRECT_URL)
            );
    }

    private function getProductImageListUrl($order) {

        $images = array_map(function($product){
            return $this->getUrlImage($product);
        }, $order->getProductsDetail());

        return $images;

    }

    public function getUrlImage($product){

         $product = new Product($product['id_product'], false, $this->context->language->id);
         $image = $product->getCover($product->id);

      return $this->context->link->getImageLink($product->name, $image['id_image']);
    }
    private function booleanAssoc($arrayString) {

        $arrayBoolean = [];

        foreach ($arrayString as $values){

            $arrayBoolean[$values] = true;
        }

        return $arrayBoolean;
    }

     public function successPaymentCobru($data) {

         try {
             $cobru = CobruDb::findByUrl($data['url']);

             $order = new Order($cobru['order_id']);

             if(
                 $order->current_state != Configuration::get(cobru::INITIAL_STATUS_PAYMENT_ORDER)
                 || $order->getOrdersTotalPaid() != $data['amount'] ) {
                 return;
             }

             CobruDb::updateCobruPaid($order->id);

             $history = new OrderHistory();

             $history->id_order = $order->id;

             $history->changeIdOrderState(Configuration::get(cobru::STATUS_PAYMENT_ORDER), $order->id);

             $history->addWithemail();


         }catch (Exception $exception) {

         }

     }
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }



    private function getConfigForm() {
        $orderStates = CobruRepository::findOrderStates();

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuración cobru'),
                    'icon' => 'icon-envelope'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Titulo'),
                        'name' => cobru::C_TITULO,
                        'required' => true,
                        'desc' => $this->l('Titulo que el usuario vera en el checkout del plugin')
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Buscar credenciales'),
                        'required' => true,
                        'html_content' => '<a href="https://panel.cobru.co/integracion" name="search_btn" id="search_btn" target="_blank" class="btn-block btn btn-success">'.$this->l('Click aqui para buscar tu X_API_KEY Y REFRESH_TOKEN en tu cuenta cobru').'<a/>'
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l(cobru::X_API_KEY),
                        'name' => cobru::X_API_KEY,
                        'required' => true,
                        'desc' => $this->l('Api key de la plataforma cobru (https://panel.cobru.co/integracion)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l(cobru::REFRESH_TOKEN),
                        'name' => cobru::REFRESH_TOKEN,
                        'required' => true,
                        'desc' => $this->l('refresh token de la plataforma cobru (https://panel.cobru.co/integracion)')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Cantidad de días para la expiración del pago'),
                        'name' => cobru::PAYMENT_EXPIRATION_DAYS,
                        'required' => true,
                        'desc' => $this->l('Cantidad de días para la expiración del pago, no mayor 365 dias')
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Métodos de pago permitidos'),
                        'name' => cobru::PAYMENT_METHOD_ENABLED,
                        'multiple' => true,
                        'required' => true,
                        'desc' => $this->l('Métodos de pago permitidos por tu plataforma (para seleccionar, arrastra el mouse encima de los items o preciona ctr + click para seleccionar individualmente)'),
                        'options' => [
                            'name' => 'label',
                            'id' => 'id',
                            'query' => [
                                [
                                    'id' => PaymentMethod::COBRU,
                                    'val' => PaymentMethod::COBRU,
                                    'label' => 'cobru'
                                ],
                                [
                                    'id' => PaymentMethod::PSE,
                                    'val' => PaymentMethod::PSE,
                                    'label' => 'pse'
                                ],
                                [
                                    'id' => PaymentMethod::BANCOLOMBIA_TRANSFER,
                                    'val' => PaymentMethod::BANCOLOMBIA_TRANSFER,
                                    'label' => $this->l('Transferencia bancolombia')
                                ],
                                [
                                    'id' => PaymentMethod::BANCOLOMBIA_QR,
                                    'val' => PaymentMethod::BANCOLOMBIA_QR,
                                    'label' => $this->l('bancolombia qr')
                                ],
                                [
                                    'id' => PaymentMethod::BALOTO,
                                    'val' => PaymentMethod::BALOTO,
                                    'label' => 'baloto'
                                ],

                                [
                                    'id' => PaymentMethod::DALE,
                                    'val' => PaymentMethod::DALE,
                                    'label' => '!dale'
                                ],
                                [
                                    'id' => PaymentMethod::CREDIT_CARD,
                                    'val' => PaymentMethod::CREDIT_CARD,
                                    'label' => $this->l('Targeta de crédito')
                                ],

                                [
                                    'id' => PaymentMethod::NEQUI,
                                    'val' => PaymentMethod::NEQUI,
                                    'label' => 'nequi'
                                ],
                                [
                                    'id' => PaymentMethod::EFECTY,
                                    'val' => PaymentMethod::EFECTY,
                                    'label' => 'efecty'
                                ],
                                [
                                    'id' => PaymentMethod::CORRESPONSAL_BANCOLOMBIA,
                                    'val' => PaymentMethod::CORRESPONSAL_BANCOLOMBIA,
                                    'label' => $this->l('Corresponsal bancolombia')
                                ],
                                [
                                    'id' => PaymentMethod::BTC,
                                    'val' => PaymentMethod::BTC,
                                    'label' => $this->l('Cripto moneda')
                                ],
                                [
                                    'id' => PaymentMethod::CUSD,
                                    'val' => PaymentMethod::CUSD,
                                    'label' => 'Celo Dollar'
                                ]
                            ]
                        ]
                    ],
                    [
                    'type' => 'text',
                    'label' => $this->l('Url de notificación'),
                    'name' => cobru::NOTIFY_CALLBACK_URL,
                    'desc' => $this->l('Url donde se notificará una vez el pago se haya realizado')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Url de redirección'),
                        'name' => cobru::PAYER_REDIRECT_URL,
                        'desc' => $this->l('Url donde será redirigido el usuario una vez haya realizado el pago en cobru')
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Estado inicial de la orden'),
                        'name' => cobru::INITIAL_STATUS_PAYMENT_ORDER,
                        'required' => true,
                        'options' => [
                            'name' => 'name',
                            'id' => 'id_order_state',
                            'default' => [
                                'value' => '',
                                'label' => $this->l('Seleccione un estado de orden')
                            ],
                            'query' => $orderStates
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Estado de la orden una vez efectuado el pago'),
                        'name' => cobru::STATUS_PAYMENT_ORDER,
                        'required' => true,
                        'options' => [
                            'name' => 'name',
                            'id' => 'id_order_state',
                            'default' => [
                              'value' => '',
                              'label' => $this->l('Seleccione un estado de orden')
                            ],
                            'query' => $orderStates
                        ]
                    ],
                    [
                        'type' => 'radio',
                        'label' => $this->l('Cambiar el modo de integración de cobru'),
                        'name' => cobru::COBRU_MODE,
                        'required' => true,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'COBRU_MODE_PRODUCTION',
                                'value' => 1,
                                'label' => $this->l('En producción')
                            ],
                            [
                                'id' => 'COBRU_MODE_TEST',
                                'value' => 0,
                                'label' => $this->l('En pruebas')
                            ]
                        ]
                    ]

                ],
                'submit' => [
                    'title' => $this->l('Guardar')
                ]
            ]
        ];

        return $form;
    }



}
