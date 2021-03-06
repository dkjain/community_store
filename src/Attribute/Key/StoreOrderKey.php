<?php
namespace Concrete\Package\CommunityStore\Src\Attribute\Key;

use Database;
use Concrete\Core\Attribute\Value\ValueList as AttributeValueList;
use Concrete\Package\CommunityStore\Src\Attribute\Value\StoreOrderValue as StoreOrderValue;
use Concrete\Core\Attribute\Key\Key as Key;

class StoreOrderKey extends Key
{
    public function getAttributes($oID, $method = 'getValue')
    {
        $db = \Database::connection();
        $values = $db->GetAll("select akID, avID from CommunityStoreOrderAttributeValues where oID = ?", array($oID));
        $avl = new AttributeValueList();
        foreach ($values as $val) {
            $ak = self::getByID($val['akID']);
            if (is_object($ak)) {
                $value = $ak->getAttributeValue($val['avID'], $method);
                $avl->addAttributeValue($ak, $value);
            }
        }

        return $avl;
    }

    public function load($akID, $loadBy = 'akID')
    {
        parent::load($akID);
        $db = \Database::connection();
        $row = $db->GetRow("select * from CommunityStoreOrderAttributeKeys where akID = ?", array($akID));
        $this->setPropertiesFromArray($row);
    }

    public function getAttributeValue($avID, $method = 'getValue')
    {
        $av = StoreOrderValue::getByID($avID);
        $av->setAttributeKey($this);

        return $av->{$method}();
    }

    public static function getByID($akID)
    {
        $ak = new self();
        $ak->load($akID);
        if ($ak->getAttributeKeyID() > 0) {
            return $ak;
        }
    }

    public static function getByHandle($akHandle)
    {
        $db = \Database::connection();
        $q = "SELECT ak.akID
            FROM AttributeKeys ak
            INNER JOIN AttributeKeyCategories akc ON ak.akCategoryID = akc.akCategoryID
            WHERE ak.akHandle = ?
            AND akc.akCategoryHandle = 'store_order'";
        $akID = $db->GetOne($q, array($akHandle));
        if ($akID > 0) {
            $ak = self::getByID($akID);
        }
        if ($ak === -1) {
            return false;
        }

        return $ak;
    }

    public static function getList()
    {
        return parent::getList('store_order');
    }

    protected function saveAttribute($order, $value = false)
    {
        $av = $order->getAttributeValueObject($this, true);
        parent::saveAttribute($av, $value);
        $db = \Database::connection();
        $v = array($order->getOrderID(), $this->getAttributeKeyID(), $av->getAttributeValueID());
        $db->Replace('CommunityStoreOrderAttributeValues', array(
            'oID' => $order->getOrderID(),
            'akID' => $this->getAttributeKeyID(),
            'avID' => $av->getAttributeValueID(),
        ), array('oID', 'akID'));
        unset($av);
    }

    public static function add($type, $args, $pkg = false)
    {
        $ak = parent::add('store_order', $type, $args, $pkg);

        extract($args);

        $v = array($ak->getAttributeKeyID());
        $db = \Database::connection();
        $db->query('REPLACE INTO CommunityStoreOrderAttributeKeys (akID) VALUES (?)', $v);

        $nak = new self();
        $nak->load($ak->getAttributeKeyID());

        return $ak;
    }

    public function update($args)
    {
        $ak = parent::update($args);
        extract($args);
        $v = array($ak->getAttributeKeyID());
        $db = \Database::connection();
        $db->query('REPLACE INTO CommunityStoreOrderAttributeKeys (akID) VALUES (?)', $v);
    }

    public function delete()
    {
        parent::delete();
        $db = \Database::connection();
        $r = $db->query('select avID from CommunityStoreOrderAttributeValues where akID = ?', array($this->getAttributeKeyID()));
        while ($row = $r->FetchRow()) {
            $db->query('delete from AttributeValues where avID = ?', array($row['avID']));
        }
        $db->query('delete from CommunityStoreOrderAttributeValues where akID = ?', array($this->getAttributeKeyID()));
    }
}
