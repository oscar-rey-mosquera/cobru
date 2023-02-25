<?php
/**
 * Clase en donde se guardan las transacciones
 */

class CobruDb extends ObjectModel{

    public $id;

    public $order_id;
    public $cobru_link;
    public $expiration_date;
    public $link_id;
    public $is_paid;

    public static $definition = array(
        'table' => _DB_PREFIX_.'cobru',
        'primary' => 'id',
        'multilang' => false,
        'fields' => array(
            'id' => array('type' => self::TYPE_INT, 'required' => false),
            'order_id' => array('type' => self::TYPE_INT, 'required' => false),
            'link_id' => array('type' => self::TYPE_STRING, 'required' => false),
            'cobru_link' => array('type' => self::TYPE_STRING, 'required' => false),
            'is_paid' => array('type' => self::TYPE_BOOL, 'required' => false, 'default' => 0),
            'expiration_date' => array('type' => self::TYPE_DATE, 'required' => false),
        )
    );

    /**
     * Guarda el registro de un link cobru
     * @param int $orderId
     * @param string $cobruLink
     * @param string $linkId
     * @param Date $expirationDate
     */
    public static function create($orderId, $linkId ,$cobruLink, $expirationDate)
    {
        $db = Db::getInstance();
        $result = $db->execute('
			INSERT INTO `'._DB_PREFIX_.'cobru`
			( `order_id`,`link_id`,`cobru_link`, `expiration_date`)
			VALUES
			("'.intval($orderId).'","'.$linkId.'","'.$cobruLink.'","'.$expirationDate.'")');
        return $result;
    }

    /**
     * elimina el registro de un link cobru
     * @param int $orderId
     */
    public static function deleteByOrderId($orderId)
    {
        $db = Db::getInstance();
        $result = $db->execute('DELETE FROM '.CobruDb::$definition['table'].' WHERE order_id ='.$orderId);
        return $result;
    }


    /**
     * Consultar si existe el registro de una oden
     * @param int $orderId
     */
    public static function ifExist($orderId)
    {
        $sql = 'SELECT COUNT(*) FROM '.CobruDb::$definition['table'].' WHERE order_id ='.$orderId;

        if (\Db::getInstance()->getValue($sql) > 0)
            return true;
        return false;
    }

    /**
     * Consultar un registro
     * @param int $orderId
     */
    public static function findByOrderId($orderId)
    {
        $sql = 'SELECT * FROM '.CobruDb::$definition['table'].' WHERE order_id ='.$orderId;

       return Db::getInstance()->getRow($sql);

    }


    /**
     * Consultar un registro por la url
     * @param string $linkId
     */
    public static function findByUrl($id)
    {
            $sql = "SELECT * FROM " .CobruDb::$definition['table']. " where link_id = '$id'";

            return Db::getInstance()->getRow($sql);
    }



    /**
     * Consultar un registro por la url
     * @param string $id
     */
    public static function updateCobruPaid($id)
    {
        try {
            $sql = "UPDATE " .CobruDb::$definition['table']. " SET is_paid = true where order_id = '$id'";

            return Db::getInstance()->getRow($sql);
        }catch (Exception $e) {
            error_log($e->getMessage());
        }
    }



    /**
     * Crear la tabla en la base de datos.
     * @return true or false
     */
    public static function setup()
    {
        $sql = array();
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'cobru` (
		    `id` int(11) NOT NULL AUTO_INCREMENT,
		    `order_id` INT NULL,
		    `link_id` TEXT NULL,
		    `cobru_link` TEXT NULL,
		    `is_paid` BOOLEAN NOT NULL DEFAULT 0,
		    `expiration_date` DATE NULL,
		    PRIMARY KEY  (`id`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }
    }

    /**
     * Borra la tabla en la base de datos.
     * @return true or false
     */
    public static function remove(){
        $sql = array(
            'DROP TABLE IF EXISTS '._DB_PREFIX_.'cobru'
        );

        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }
    }
}