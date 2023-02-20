<?php

class PartialShippingOrder extends ObjectModel
{
    /**
     * @var integer Order id
     */
    public $id_order;

    /**
     * @var integer Order id
     */
    public $id_order_from;

    /**
     * @var string Object creation date
     */
    public $date_add;

    public static $definition = array(
        'table' => 'partial_shipping_order',
        'primary' => 'id_shipping_order',
        'fields' => array(
            'id_order'  => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order_from' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'date_add'  => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    public static function getPartialShippingByOrderId(int $id_order)
    {
        $partial_shipping = Db::getInstance()->getValue('
		    SELECT `id_order_from`
		    FROM `'._DB_PREFIX_.'partial_shipping_order`
		    WHERE `id_order` ='.(int)$id_order
        );
        return $partial_shipping ?: false;
    }
}