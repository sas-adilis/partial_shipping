<?php

require_once __DIR__.'/classes/PartialShippingOrder.php';

class Partial_shipping extends \Module {
    function __construct()
    {
        $this->name = 'partial_shipping';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tab = 'shipping_logistics';
        $this->version = '1.1.0';
        $this->displayName = $this->l('Order partial delivery');
        $this->description = $this->l('Create partial delivery to order');
        $this->confirmUninstall = $this->l('Are you sure you want to delete this module ?');

        parent::__construct();
    }

    public function install()
    {
        if (file_exists($this->getLocalPath().'sql/install.php')) {
            require_once($this->getLocalPath().'sql/install.php');
        }

        Configuration::updateValue('PARTIALSHIPPING_ORDER_STATE', Configuration::get('PS_OS_SHIPPING'));
        Configuration::updateValue('PARTIALSHIPPING_SEND_EMAIL', false);
        Configuration::updateValue('PARTIALSHIPPING_GENERATE_INVOICE', false);

        return
            parent::install() &&
            /*$this->registerHook('header') && */
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayAdminOrder') &&
            $this->installOrderStates() &&
            Configuration::updateValue('PARTIALSHIPPING_REMAINING', Configuration::get('PARTIALSHIPPING_NEW_STATE'));
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

        Configuration::updateValue('PARTIALSHIPPING_NEW_STATE', (int)$order_state->id);
        Configuration::updateValue('PARTIALSHIPPING_NEW_STATE', (int)$order_state->id);

        return true;
    }



    public function getContent() {
        if (\Tools::isSubmit('submit'.$this->name.'Module')) {

            if (!(int)Tools::getValue('PARTIALSHIPPING_ORDER_STATE')) {
                $this->context->controller->errors[] = $this->l('Please select an order state for "New status" field');
            }

            if (!(int)Tools::getValue('PARTIALSHIPPING_REMAINING')) {
                $this->context->controller->errors[] = $this->l('Please select an order state for "Remaining order satus" field');
            }

            if (!count($this->context->controller->errors)) {
                Configuration::updateValue('PARTIALSHIPPING_SEND_EMAIL', (bool)\Tools::getValue('PARTIALSHIPPING_SEND_EMAIL'));
                Configuration::updateValue('PARTIALSHIPPING_ORDER_STATE', (int)\Tools::getValue('PARTIALSHIPPING_ORDER_STATE'));
                Configuration::updateValue('PARTIALSHIPPING_REMAINING', (int)\Tools::getValue('PARTIALSHIPPING_REMAINING'));
                Configuration::updateValue('PARTIALSHIPPING_GENERATE_INVOICE', (bool)\Tools::getValue('PARTIALSHIPPING_GENERATE_INVOICE'));
                Configuration::updateValue('PARTIALSHIPPING_UNPAID', (bool)\Tools::getValue('PARTIALSHIPPING_UNPAID'));

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
                            'label' => $this->l('New satus'),
                            'required' => true,
                            'options' => [
                                'default' => ['value' => null, 'label' => $this->l('Please select an order state')],
                                'query' => \OrderState::getOrderStates(\Context::getContext()->cookie->id_lang),
                                'id' => 'id_order_state',
                                'name' => 'name'
                            ],
                            'desc' => $this->l('New status to order that you want to split'),
                        ],
                        [
                            'type' => 'select',
                            'name' => 'PARTIALSHIPPING_REMAINING',
                            'label' => $this->l('Remaining order satus'),
                            'required' => true,
                            'options' => [
                                'default' => ['value' => null, 'label' => $this->l('Please select an order state')],
                                'query' => \OrderState::getOrderStates(\Context::getContext()->cookie->id_lang),
                                'id' => 'id_order_state',
                                'name' => 'name'
                            ],
                            'desc' => $this->l('New status to remaining order'),
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Generate an invoice for each order to split'),
                            'name' => 'PARTIALSHIPPING_GENERATE_INVOICE',
                            'is_bool' => true,
                            'class' => 't',
                            'desc' => $this->l('If you have a B2B website normally you check create an invoice for each order'),
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

    public function hookDisplayAdminOrder($params)
    {

        $order = new Order((int) $params['id_order']);

        /*$order_invoice = OrderInvoice::getInvoiceByNumber($order->invoice_number);
        if (Validate::isLoadedObject($order_invoice)) {
            if (Configuration::get('PARTIALSHIPPING_GENERATE_INVOICE')) {
                $order_invoice->total_products = $order->total_products;
                $order_invoice->total_products_wt = $order->total_products_wt;
                $order_invoice->total_paid = $order->total_paid;
                $order_invoice->total_paid_tax_incl = $order->total_paid_tax_incl;
                $order_invoice->total_paid_tax_excl = $order->total_paid_tax_excl;
                $order_invoice->total_paid_real = $order->total_paid_real;
                $order_invoice->total_discounts_tax_incl = $order->total_discounts_tax_incl;
                $order_invoice->total_discounts_tax_excl = $order->total_discounts_tax_excl;
                $order_invoice->save();

                $order_details = $order->getOrderDetailList();
                foreach ($order_details as $order_detail) {
                    $orderDetail = new OrderDetail($order_detail['id_order_detail']);
                    if (Validate::isLoadedObject($orderDetail)) {
                        if (!$orderDetail->id_order_invoice) {
                            $orderDetail->id_order_invoice = $order_invoice->id;
                            $orderDetail->save();
                        }
                    }
                }
            }
        }*/

        if (Validate::isLoadedObject($order)) {
            $partial_shipping = PartialShippingOrder::getPartialShippingByOrderId($order->id);
            $this->context->smarty->assign('partial_shipping', $partial_shipping);

            if (!$order->hasBeenPaid() && !Configuration::get('PARTIALSHIPPING_UNPAID')) {
                $this->context->smarty->assign('can_create_partial_shipping', false);
            } else {
                $products = $this->getProducts($order);
                $global_quantity = 0;
                foreach ($products as &$product) {
                    $product['current_stock'] = StockAvailable::getQuantityAvailableByProduct(
                        $product['product_id'],
                        $product['product_attribute_id'],
                        $product['id_shop']);

                    $resume = OrderSlip::getProductSlipResume($product['id_order_detail']);
                    $product['quantity_refundable'] = $product['product_quantity'] - $resume['product_quantity'];
                    $product['refund_history'] = OrderSlip::getProductSlipDetail($product['id_order_detail']);
                    $product['return_history'] = OrderReturn::getProductReturnDetail($product['id_order_detail']);

                    // if the current stock requires a warning
                    if ($product['id_warehouse'] != 0) {
                        $warehouse = new Warehouse((int)$product['id_warehouse']);
                        $product['warehouse_name'] = $warehouse->name;
                    } else {
                        $product['warehouse_name'] = '--';
                    }
                    $global_quantity += $product['quantity_refundable'];
                }
                $this->context->smarty->assign(
                    array(
                        'products' => $products,
                        'order' => $order,
                        'can_create_partial_shipping' => $global_quantity > 1,
                        'form_action' => static::getCurrentUrl(),
                    )
                );

                $id_order_open = (int)Tools::getValue('open_partial');
                if ($id_order_open) {
                    $open_partial = Context::getContext()->link->getAdminLink('AdminOrders', true, array(
                        'route' => 'admin_orders_view',
                        'orderId' => $id_order_open,
                    ));
                    $this->context->smarty->assign('open_partial', $open_partial);
                }
            }
            return $this->context->smarty->fetch($this->getLocalPath().'views/templates/hook/admin_order.tpl');
        }
    }

    protected function getProducts($order)
    {
        $products = $order->getProducts();
        foreach ($products as &$product) {
            if ($product['image'] != null) {
                $name = 'product_mini_'.(int) $product['product_id'].(isset($product['product_attribute_id']) ? '_'.(int) $product['product_attribute_id'] : '').'.jpg';
                $product['image_tag'] = ImageManager::thumbnail(_PS_IMG_DIR_.'p/'.$product['image']->getExistingImgPath().'.jpg', $name, 45, 'jpg');
                $product['image_size'] = file_exists(_PS_TMP_IMG_DIR_.$name) ? getimagesize(_PS_TMP_IMG_DIR_.$name) : false;
            }
        }
        return $products;
    }

    private static function refreshCurrentUrl($extra_params = array()) {
        Tools::redirectAdmin(static::getCurrentUrl().(count($extra_params) > 0 ? '&'.http_build_query($extra_params) : ''));
    }

    private static function getCurrentUrl() {
        return $_SERVER['REQUEST_URI'];
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::isSubmit('submitPartialShipping')) {
            $flash_bag = $this->context->controller->get('session')->getFlashBag();
            $id_order = (int)Tools::getValue('id_order');

            $order = new Order($id_order);
            if (!Validate::isLoadedObject($order)) {
                $flash_bag->add('error', 'Order not found');
                static::refreshCurrentUrl();
            }

            $customer = new Customer($order->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                $flash_bag->add('error', 'Customer not found');
                static::refreshCurrentUrl();
            }

            $carrier = new Carrier((int)$order->id_carrier, $order->id_lang);
            if (!Validate::isLoadedObject($carrier)) {
                $flash_bag->add('error', 'Carrier not found');
                static::refreshCurrentUrl();
            }

            $quantity_shipped = Tools::getValue('quantity_shipped');
            $paid_real_tax_incl = $order->total_products_wt;
            $paid_real_tax_excl = $order->total_products;
            $id_order_new = null;

            $global_quantity = array_sum($quantity_shipped);
            if (!$global_quantity) {
                $flash_bag->add('error', 'No product selected');
                static::refreshCurrentUrl();
            }

            foreach ($quantity_shipped as $id_order_detail => $quantity_to_ship) {

                $order_detail = new OrderDetail((int)$id_order_detail);
                if (!Validate::isLoadedObject($order_detail)) {
                    $flash_bag->add('error', 'Order detail not found');
                    static::refreshCurrentUrl();
                }

                if (!$id_order_new) {
                    $id_order_new = $this->duplicateOrder($id_order);
                    if (!$id_order_new) {
                        $flash_bag->add('error', 'Error while duplicating order');
                        static::refreshCurrentUrl();
                    }
                }
                if (!$this->deleteAndAddProduct($id_order, $id_order_new, $order_detail, (int)$quantity_to_ship)) {
                    $flash_bag->add('error', 'Error while adding product');
                    static::refreshCurrentUrl();
                }

                unset($order_detail);
            }

            $order = new Order($id_order);
            if ($order->total_discounts) {
                $order->total_discounts = round($order->total_discounts * $order->total_products_wt / $paid_real_tax_incl, 2);
                $order->total_discounts_tax_incl = round($order->total_discounts_tax_incl * $order->total_products_wt / $paid_real_tax_incl, 2);
                $order->total_discounts_tax_excl = round($order->total_discounts_tax_excl * $order->total_products / $paid_real_tax_excl, 2);
                $order->total_paid_tax_incl = $order->total_products_wt + $order->total_shipping_tax_incl - $order->total_discounts_tax_incl;
                $order->total_paid_tax_excl = $order->total_products + $order->total_shipping_tax_excl - $order->total_discounts_tax_excl;
                $order->total_paid_real = $order->total_paid_tax_incl;
                $order->total_paid = $order->total_paid_tax_incl;
                $order->update();
            }

            $new_order = new Order($id_order_new);
            if ($new_order->total_discounts) {
                $new_order->total_discounts = $new_order->total_discounts - $order->total_discounts;
                $new_order->total_discounts_tax_incl = $new_order->total_discounts_tax_incl - $order->total_discounts_tax_incl;
                $new_order->total_discounts_tax_excl = $new_order->total_discounts_tax_excl - $order->total_discounts_tax_excl;
                $new_order->total_paid_tax_incl = $new_order->total_products_wt + $new_order->total_shipping_tax_incl - $new_order->total_discounts_tax_incl;
                $new_order->total_paid_tax_excl = $new_order->total_products + $new_order->total_shipping_tax_excl - $new_order->total_discounts_tax_excl;
                $new_order->total_paid_real = $new_order->total_paid_tax_incl;
                $new_order->total_paid = $new_order->total_paid_tax_incl;
                $new_order->update();
            }

            $order_invoice = OrderInvoice::getInvoiceByNumber($new_order->invoice_number);
            if (Validate::isLoadedObject($order_invoice) && Configuration::get('PARTIALSHIPPING_GENERATE_INVOICE')) {
                $order_invoice->total_products = $new_order->total_products;
                $order_invoice->total_products_wt = $new_order->total_products_wt;
                $order_invoice->total_paid = $new_order->total_paid;
                $order_invoice->total_paid_tax_incl = $new_order->total_paid_tax_incl;
                $order_invoice->total_paid_tax_excl = $new_order->total_paid_tax_excl;
                $order_invoice->total_paid_real = $new_order->total_paid_real;
                $order_invoice->total_discounts_tax_incl = $new_order->total_discounts_tax_incl;
                $order_invoice->total_discounts_tax_excl = $new_order->total_discounts_tax_excl;
                $order_invoice->save();
            }

            if (Configuration::get('PARTIALSHIPPING_SEND_EMAIL')) {
                $file_attachement = [];
                $pdf = new PDF($order->getInvoicesCollection(), PDF::TEMPLATE_DELIVERY_SLIP, Context::getContext()->smarty);
                $file_attachement[] = array(
                    'content' => $pdf->render(false),
                    'name' => 'BL_'.sprintf('%06d', $order->id).'.pdf',
                    'mime' => 'application/pdf',
                );

                $shipped_products = $order->getProducts();
                $missing_products = $new_order->getProducts();

                foreach ($shipped_products as $k => $shipped_product) {
                    $id_image = self::getImageId((int)$shipped_product['product_id'], (int)$shipped_product['product_attribute_id']);
                    $shipped_products[$k]['image'] = $id_image > 0 ? $this->context->link->getImageLink($id_image, $id_image, 'small_default') : null;
                }
                foreach ($missing_products as $k => $missing_product) {
                    $id_image = self::getImageId((int)$missing_product['product_id'], (int)$missing_product['product_attribute_id']);
                    $missing_products[$k]['image'] = $id_image > 0 ? $this->context->link->getImageLink($id_image, $id_image, 'small_default') : null;
                }

                $shipped_products_txt = $this->getEmailTemplateContent('product_list_txt.tpl', $shipped_products);
                $shipped_products_html = $this->getEmailTemplateContent('product_list_html.tpl', $shipped_products);
                $missing_products_txt = $this->getEmailTemplateContent('product_list_txt.tpl', $missing_products);
                $missing_products_html = $this->getEmailTemplateContent('product_list_html.tpl', $missing_products);
                $iso_code = Language::getIsoById((int)$order->id_lang);

                if(!@Mail::Send(
                    $order->id_lang,
                    'partial_shipping',
                    $this->l('You order was partially shipped out', false, $iso_code),
                    [
                        '{lastname}' => $customer->lastname,
                        '{firstname}' => $customer->firstname,
                        '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                        '{order_ref}' => $order->getUniqReference(),
                        '{shipped_products}' => $shipped_products_html,
                        '{shipped_products_txt}' => $shipped_products_txt,
                        '{missing_products}' => $missing_products_html,
                        '{missing_products_txt}' => $missing_products_txt,
                    ],
                    $customer->email,
                    null,
                    Configuration::get('PS_SHOP_EMAIL'),
                    Configuration::get('PS_SHOP_NAME'),
                    $file_attachement,
                    null,
                    dirname(__FILE__).'/mails/')
                ) {
                    $flash_bag->add('error', 'Error sending email');
                    static::refreshCurrentUrl();
                }
            }

            if (Tools::getValue('tracking_multishipping')) {
                $id_order_carrier = (int)$order->getIdOrderCarrier();
                $order_carrier = new OrderCarrier($id_order_carrier);
                if (Validate::isloadedObject($order_carrier)) {
                    $order->shipping_number = $order_carrier->tracking_number = Tools::getValue('tracking_multishipping');
                    if ($order->update() && $order_carrier->update()) {
                        $templateVars = array(
                            '{followup}' => str_replace('@', $order->shipping_number, $carrier->url),
                            '{firstname}' => $customer->firstname,
                            '{lastname}' => $customer->lastname,
                            '{id_order}' => $order->id,
                            '{shipping_number}' => $order->shipping_number,
                            '{order_name}' => $order->getUniqReference(),
                        );

                        if (@Mail::Send(
                            (int)$order->id_lang,
                            'in_transit',
                            Mail::l('Package in transit', (int) $order->id_lang),
                            $templateVars,
                            $customer->email,
                            $customer->firstname.' '.$customer->lastname,
                            null, null, null, null,
                            _PS_MAIL_DIR_,
                            true,
                            (int)$order->id_shop)
                        ) {
                            Hook::exec('actionAdminOrdersTrackingNumberUpdate', [
                                'order' => $order,
                                'customer' => $customer,
                                'carrier' => $carrier
                            ], null, false, true, false, $order->id_shop);
                        } else {
                            $flash_bag->add('error', 'An error occurred while sending an email to the customer.');
                            static::refreshCurrentUrl();
                        }
                    }

                    $order_state = new OrderState((int)Configuration::get('PARTIALSHIPPING_ORDER_STATE'));
                    if (!Validate::isLoadedObject($order_state)) {
                        $flash_bag->add('error', 'The new order status is invalid.');
                        static::refreshCurrentUrl();
                    } else {
                        if ($order->current_state != $order_state->id) {
                            $history = new OrderHistory();
                            $history->id_order = $order->id;
                            $history->id_employee = (int) $this->context->employee->id;
                            $history->changeIdOrderState((int) $order_state->id, $order, !$order->hasInvoice());

                            $template_vars = [];
                            if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                                $template_vars = ['{followup}' => str_replace('@', $order->shipping_number, $carrier->url)];
                            }

                            if (!$history->addWithemail(true, $template_vars)) {
                                $flash_bag->add('error', 'An error occurred while changing order status, or we were unable to send an email to the customer.');
                                static::refreshCurrentUrl();
                            }
                        }
                    }
                } else {
                    $flash_bag->add('error', 'The order carrier cannot be updated.');
                    static::refreshCurrentUrl();
                }
            }

            $partial_shipping = new PartialShippingOrder();
            $partial_shipping->id_order_from = $id_order;
            $partial_shipping->id_order = $id_order_new;

            if (!$partial_shipping->add()) {
                $flash_bag->add('error', 'Error saving partial shipping');
                static::refreshCurrentUrl();
            }

            $flash_bag->add('success', 'Partial shipping saved');
            static::refreshCurrentUrl(['open_partial' => $id_order_new]);
        }
    }

    private function duplicateOrder($id_order)
    {
        $order = new Order($id_order);
        $order_add = $order;
        unset($order_add->id);
        $order_add->total_products = 0;
        $order_add->total_products_wt = 0;
        $order_add->total_paid = 0;
        $order_add->total_paid_tax_incl = 0;
        $order_add->total_paid_tax_excl = 0;
        $order_add->total_paid_real = 0;
        if (Configuration::get('PARTIALSHIPPING_GENERATE_INVOICE')) {
            $order_add->invoice_number = 0;
        }
        $order_add->delivery_number = 0;
        if (!$order_add->add()) {
            return false;
        }

        $id_order_carrier = (int)$order->getIdOrderCarrier();
        if ($id_order_carrier) {
            $order_carrier = new OrderCarrier($id_order_carrier);
            if (Validate::isLoadedObject($order_carrier)) {
                $order_carrier_add = $order_carrier;
                $order_carrier_add->id_order_carrier = '';
                $order_carrier_add->id_order = $order_add->id;
                $order_carrier_add->id_order_invoice = 0;
                $order_carrier_add->shipping_cost_tax_excl = 0;
                $order_carrier_add->shipping_cost_tax_incl = 0;
                $order_carrier_add->tracking_number = 0;
                if (!$order_carrier_add->add()) {
                    return false;
                }
            }
        }

        return $order_add->id;
    }

    protected function deleteAndAddProduct($id_order, $id_order_new, $order_detail, $quantity_to_ship)
    {
        $old_order = new Order($id_order);
        $new_order = new Order($id_order_new);

        if (!Validate::isLoadedObject($old_order) || !Validate::isLoadedObject($new_order)) {
            return false;
        }

        if ($quantity_to_ship >= $order_detail->product_quantity - $order_detail->product_quantity_refunded) {
            return true;
        }

        $quantity_to_move = self::f(($order_detail->product_quantity - $order_detail->product_quantity_refunded) - $quantity_to_ship);
        $product_price_tax_excl = self::f($order_detail->unit_price_tax_excl * $quantity_to_move);
        $product_price_tax_incl = self::f($order_detail->unit_price_tax_incl * $quantity_to_move);
        $product_weight = self::f($order_detail->product_weight ? $order_detail->product_weight / $order_detail->product_quantity : 0);

        /* Update order */
        $old_order->total_products = self::f($old_order->total_products - $product_price_tax_excl);
        $old_order->total_products_wt = self::f($old_order->total_products_wt - $product_price_tax_incl);
        $old_order->total_paid = self::f($old_order->total_paid - $product_price_tax_incl);
        $old_order->total_paid_tax_incl = self::f($old_order->total_paid_tax_incl - $product_price_tax_incl);
        $old_order->total_paid_tax_excl = self::f($old_order->total_paid_tax_excl - $product_price_tax_excl);
        $old_order->total_paid_real = self::f($old_order->total_paid_real - $product_price_tax_incl);

        if ($quantity_to_ship <= 0) {
            if (!$order_detail->delete()) {
                return false;
            }
        } else {
            $order_detail->product_quantity = self::f($order_detail->product_quantity - (int)$quantity_to_move);
            $order_detail->total_price_tax_incl = self::f($order_detail->total_price_tax_incl - $product_price_tax_incl);
            $order_detail->total_price_tax_excl = self::f($order_detail->total_price_tax_excl - $product_price_tax_excl);
            $order_detail->product_weight = self::f($product_weight * $order_detail->product_quantity);
            if (!$order_detail->update()) {
                return false;
            }
            $order_detail->updateTaxAmount($old_order);
        }

        if ($old_order->update()) {
            $new_order->total_products = self::f($new_order->total_products + $product_price_tax_excl);
            $new_order->total_products_wt = self::f($new_order->total_products_wt + $product_price_tax_incl);
            $new_order->total_paid = self::f($new_order->total_paid + $product_price_tax_incl);
            $new_order->total_paid_tax_incl = self::f($new_order->total_paid_tax_incl + $product_price_tax_incl);
            $new_order->total_paid_tax_excl = self::f($new_order->total_paid_tax_excl + $product_price_tax_excl);
            $new_order->total_paid_real = self::f($new_order->total_paid_real + $product_price_tax_incl);
            $new_order->total_shipping_tax_incl = 0;
            $new_order->total_shipping_tax_excl = 0;
            $new_order->total_shipping = 0;

            $order_invoice = OrderInvoice::getInvoiceByNumber($new_order->invoice_number);
            /* If no B2B mode we create invoice information */
            if (!Configuration::get('PARTIALSHIPPING_GENERATE_INVOICE') && Validate::isLoadedObject($order_invoice)) {
                $id_order_invoice = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
                    SELECT `id_order_invoice`
                    FROM `'._DB_PREFIX_.'order_invoice`
                    WHERE id_order = '.(int)$new_order->id
                );
                $new_order_invoice = $order_invoice;
                $new_order_invoice->id = $id_order_invoice ?: '';
                $new_order_invoice->id_order = $new_order->id;
                $new_order_invoice->total_paid_tax_incl = 0;
                $new_order_invoice->total_paid_tax_excl = 0;
                $new_order_invoice->number = $new_order->invoice_number;
                $new_order_invoice->delivery_number = 0;
                if ($new_order_invoice->save()) {
                    Db::getInstance()->update('order_invoice', ['date_add' => pSQL($order_invoice->date_add)], '`id_order_invoice` = '.$new_order_invoice->id, 1);
                }
            }

            if (!isset($new_order_invoice) && Validate::isLoadedObject($order_invoice)) {
                $new_order_invoice = $order_invoice;
            }

            $new_order_detail = $order_detail;
            unset($new_order_detail->id, $new_order_detail->id_order_detail);
            $new_order_detail->id_order = (int)$new_order->id;
            $new_order_detail->id_order_invoice = $new_order_invoice->id ?? '';
            $new_order_detail->product_quantity = (int)$quantity_to_move;
            $new_order_detail->total_price_tax_incl = $product_price_tax_incl;
            $new_order_detail->total_price_tax_excl = $product_price_tax_excl;
            $new_order_detail->total_shipping_price_tax_incl = 0;
            $new_order_detail->total_shipping_price_tax_excl = 0;
            $new_order_detail->product_weight = $product_weight * $new_order_detail->product_quantity;

            if ($new_order_detail->add() && $new_order->update()) {
                $new_order_detail->updateTaxAmount($new_order);
                $history = new OrderHistory();
                $history->id_order = $new_order->id;
                $history->id_employee = (int)$this->context->employee->id;
                if ($new_order->current_state != (int)Configuration::get('PARTIALSHIPPING_REMAINING')) {
                    $history->changeIdOrderState(Configuration::get('PARTIALSHIPPING_REMAINING'), $new_order, false);
                    $history->add();
                }
            }
        } else {
            return false;
        }

        return true;
    }

    private static function f($amount) {
        return max(Tools::ps_round($amount, 2), 0);
    }

    private function getEmailTemplateContent(string $template, $products)
    {
        $this->context->smarty->assign('list', $products);
        return $this->context->smarty->fetch($this->getLocalPath().'mails/_partials/'.$template);
    }

    private static function getImageId(int $id_product, int $id_product_attribute = 0) {
        $context = Context::getContext();
        $cache_id = 'Partial_shipping::getImageId' . $id_product . '-' . $id_product_attribute . '-' . (int) $context->shop->id;
        if (!Cache::isStored($cache_id)) {
            $id_image = 0;
            if ((int)$id_product_attribute) {
                $id_image = Db::getInstance()->getValue('
					SELECT image_shop.`id_image` id_image
					FROM `' . _DB_PREFIX_ . 'image` i
					INNER JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
						ON (i.id_image = image_shop.id_image AND image_shop.id_shop = ' . (int) $context->shop->id . ')
						INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_image` pai
						ON (pai.`id_image` = i.`id_image` AND pai.`id_product_attribute` = ' . $id_product_attribute . ')
					WHERE i.`id_product` = ' . $id_product . ' ORDER BY i.`position` ASC
				');
            }
            if (!$id_image) {
                $id_image = (int)Db::getInstance()->getValue('SELECT image_shop.`id_image`
                    FROM `' . _DB_PREFIX_ . 'image` i
                    ' . Shop::addSqlAssociation('image', 'i') . '
                    WHERE i.`id_product` = ' . $id_product . '
                    AND image_shop.`cover` = 1'
                );
            }
            Cache::store($cache_id, $id_image);
            return $id_image;
        }

        return Cache::retrieve($cache_id);
    }
}