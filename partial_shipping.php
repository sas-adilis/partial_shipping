<?php
class Partial_shipping extends \Module {
    function __construct()
    {
        $this->name = 'partial_shipping';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->displayName = $this->l('Order partial delivery');
        $this->description = $this->l('Create partial delivery to order');
        $this->confirmUninstall = $this->l('Are you sure you want to delete this module ?');

        parent::__construct();
    }

    public function install()
    {
        /*Configuration::updateValue('WIC_MULTISHIPPING_SEND_EMAIL', false);
        Configuration::updateValue('WIC_MULTISHIPPING_ORDER_STATE', 4);
        Configuration::updateValue('WIC_MULTISHIPPING_B_2_B', false);*/

        if (file_exists($this->getLocalPath().'sql/install.php')) {
            require_once($this->getLocalPath().'sql/install.php');
        }

        return
            parent::install() &&
            /*$this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&*/
            $this->registerHook('displayAdminOrder') &&
            $this->installOrderStates()
        ;
    }

    private function installOrderStates()
    {
        $id_order_state = (int)Configuration::get('PARTIALSHIPPING_NEW_STATE');
        $order_state = new OrderState($id_order_state);
        $order_state->name = array();
        foreach (Language::getLanguages() as $language) {
            if (Tools::strtolower($language['iso_code']) == 'fr') {
                $order_state->name[$language['id_lang']] = 'Reliquat de commande';
            } else {
                $order_state->name[$language['id_lang']] = 'Remaining order';
            }
        }
        $order_state->send_email = false;
        $order_state->color = '#00d3c3';
        $order_state->hidden = true;
        $order_state->delivery = false;
        $order_state->logable = true;
        $order_state->invoice = false;
        $order_state->paid = false;
        if (!$order_state->save()) {
            return false;
        }

        Configuration::updateValue('PARTIALSHIPPING_NEW_STATE', (int) $order_state->id);
        Configuration::updateValue('PARTIALSHIPPING_NEW_STATE', (int) $order_state->id);

        return true;
    }

    public function getContent() {
        if (\Tools::isSubmit('submit'.$this->name.'Module')) {
            /** TODO: form validation **/
            if (!count($this->context->controller->errors)) {
                $redirect_after = $this->context->link->getAdminLink('AdminModules', true);
                $redirect_after .= '&conf=4&configure='.$this->name.'&module_name='.$this->name;
                \Tools::redirectAdmin($redirect_after);
            }
        }

        return $this->renderForm();
    }

    private function renderForm() {
        $helper = new \HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name.'Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false);
        $helper->currentIndex .= '&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'fields_value' => [
                'PARTIALSHIPPING_SEND_EMAIL' => Tools::getValue('PARTIALSHIPPING_SEND_EMAIL', \Configuration::get('PARTIALSHIPPING_SEND_EMAIL')),
                'PARTIALSHIPPING_ORDER_STATE' => Tools::getValue('PARTIALSHIPPING_ORDER_STATE', \Configuration::get('PARTIALSHIPPING_ORDER_STATE')),
                'PARTIALSHIPPING_GENERATE_INVOICE' => Tools::getValue('PARTIALSHIPPING_GENERATE_INVOICE', \Configuration::get('PARTIALSHIPPING_GENERATE_INVOICE')),
                'PARTIALSHIPPING_REMAINING' => Tools::getValue('PARTIALSHIPPING_REMAINING', \Configuration::get('PARTIALSHIPPING_REMAINING')),
                'PARTIALSHIPPING_UNPAID' => Tools::getValue('PARTIALSHIPPING_UNPAID', \Configuration::get('PARTIALSHIPPING_UNPAID'))
            ]
        ];

        return $helper->generateForm([
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Parameters'),
                        'icon' => 'icon-cogs'
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Send an email to your customer when you generate partial delivery'),
                            'name' => 'PARTIALSHIPPING_SEND_EMAIL',
                            'is_bool' => true,
                            'class' => 't',
                            'desc' => $this->l('Send an email to customer'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'select',
                            'name' => 'PARTIALSHIPPING_ORDER_STATE',
                            'label' => $this->l('New satus:'),
                            'required' => true,
                            'options' => [
                                'default' => ['value' => null, 'label' => $this->l('Please select an order state')],
                                'query' => \OrderState::getOrderStates(\Context::getContext()->cookie->id_lang),
                                'id' => 'id_order_state',
                                'name' => 'name'
                            ],
                            'desc' => $this->l('New status to order that you want to split.'),
                        ],
                        [
                            'type' => 'select',
                            'name' => 'PARTIALSHIPPING_REMAINING',
                            'label' => $this->l('Remaining order satus:'),
                            'required' => true,
                            'options' => [
                                'default' => ['value' => null, 'label' => $this->l('Please select an order state')],
                                'query' => \OrderState::getOrderStates(\Context::getContext()->cookie->id_lang),
                                'id' => 'id_order_state',
                                'name' => 'name'
                            ],
                            'desc' => $this->l('New status to remaining order.'),
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Generate an invoice for each order to split.'),
                            'name' => 'PARTIALSHIPPING_GENERATE_INVOICE',
                            'is_bool' => true,
                            'class' => 't',
                            'desc' => $this->l('If you have a B2B website normally you check create an invoice for each order.'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Display manage multishipping in order even if order isn\'t paid'),
                            'name' => 'PARTIALSHIPPING_UNPAID',
                            'is_bool' => true,
                            'class' => 't',
                            'desc' => $this->l('Normally you manage multishipping just when order is pad but if you want manage multisshiping on unpaid you must enabled this option'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save')
                    ]
                ]
            ]
        ]);
    }
}