<?php

namespace Skyport\AuftragsFilter\EventProcedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class OrderFilters
{
    use Loggable;

    private ConfigRepository $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    public function filter1(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 1); }
    public function filter2(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 2); }
    public function filter3(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 3); }
    public function filter4(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 4); }
    public function filter5(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 5); }
    public function filter6(EventProceduresTriggered $event): bool { return $this->checkSlot($event, 6); }

    private function checkSlot(EventProceduresTriggered $event, int $slot): bool
    {
        $order = $event->getOrder();
        if (!$order) {
            return false;
        }

        $enabled = (bool)$this->getConfig('filter' . $slot . '_enabled', false);
        if (!$enabled) {
            return false;
        }

        $type = (string)$this->getConfig('filter' . $slot . '_type', 'contact');
        $mode = (string)$this->getConfig('filter' . $slot . '_mode', 'allow');
        $idsCsv = (string)$this->getConfig('filter' . $slot . '_ids', '');
        $hint = (string)$this->getConfig('filter' . $slot . '_hint', '');

        $ids = $this->parseIds($idsCsv);
        if (count($ids) === 0) {
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' keine IDs konfiguriert -> false');
            return false;
        }

        $value = 0;

        if ($type === 'contact') {
            $value = $this->extractReceiverContactId($order);
        } elseif ($type === 'billingAddress') {
            $value = $this->extractAddressIdByTypeId($order, 1);
        } elseif ($type === 'shippingAddress') {
            $value = $this->extractAddressIdByTypeId($order, 2);
        } else {
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' unbekannter Typ: ' . $type . ' -> false');
            return false;
        }

        if ($value <= 0) {
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' kein Wert ermittelbar (type=' . $type . ') -> false');
            return false;
        }

        $inList = in_array($value, $ids, true);
        $result = ($mode === 'deny') ? !$inList : $inList;

        $this->debug(
            'Slot ' . $slot .
            $this->hint($hint) .
            ' type=' . $type .
            ' value=' . $value .
            ' mode=' . $mode .
            ' inList=' . ($inList ? '1' : '0') .
            ' => ' . ($result ? 'true' : 'false')
        );

        return $result;
    }

    private function getConfig(string $key, $default)
    {
        $value = $this->config->get('SkyportAuftragsFilter.' . $key);
        return $value === null ? $default : $value;
    }

    private function parseIds(string $input): array
    {
        // Zeilenumbrüche vereinheitlichen → Komma
        $input = str_replace(["\r\n", "\r", "\n"], ",", $input);
    
        $out = [];
    
        foreach (explode(",", $input) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
    
            $id = (int)$part;
            if ($id > 0) {
                $out[] = $id;
            }
        }
    
        return array_values(array_unique($out));
    }

    /**
     * ContactId aus OrderRelations (receiver contact)
     */
    private function extractReceiverContactId($order): int
    {
        // 1) Best case (Order-Model Feld): receiver contact id
        if (isset($order->contactReceiverId)) {
            $cid = (int)$order->contactReceiverId;
            if ($cid > 0) {
                return $cid;
            }
        }
    
        // 2) Fallback (manche Kontexte): contactId
        if (isset($order->contactId)) {
            $cid = (int)$order->contactId;
            if ($cid > 0) {
                return $cid;
            }
        }
    
        // 3) Letzter Fallback: orderRelations (falls vorhanden)
        if (!isset($order->orderRelations) || !is_array($order->orderRelations)) {
            return 0;
        }
    
        foreach ($order->orderRelations as $relation) {
            if (
                isset($relation->referenceType, $relation->relation, $relation->referenceId)
                && (string)$relation->referenceType === 'contact'
                && (string)$relation->relation === 'receiver'
            ) {
                return (int)$relation->referenceId;
            }
        }
    
        return 0;
    }

    /**
     * AddressId aus addressRelations (typeId: 1=billing, 2=shipping)
     */
    private function extractAddressIdByType($order, int $typeId): int
    {
        // Einige Systeme liefern addressRelations, andere addresses
        $list = null;
    
        if (isset($order->addressRelations) && is_array($order->addressRelations)) {
            $list = $order->addressRelations;
        } elseif (isset($order->addresses) && is_array($order->addresses)) {
            $list = $order->addresses;
        }
    
        if (!is_array($list)) {
            return 0;
        }
    
        foreach ($list as $row) {
            // Varianten: typeId / addressTypeId / orderAddressTypeId
            $t = 0;
            if (isset($row->typeId)) {
                $t = (int)$row->typeId;
            } elseif (isset($row->addressTypeId)) {
                $t = (int)$row->addressTypeId;
            }
    
            if ($t !== $typeId) {
                continue;
            }
    
            // Varianten: addressId / id
            if (isset($row->addressId)) {
                return (int)$row->addressId;
            }
            if (isset($row->id)) {
                return (int)$row->id;
            }
        }
    
        return 0;
    }

    private function hint(string $hint): string
    {
        return $hint !== '' ? ' (' . $hint . ')' : '';
    }

    private function debug(string $message): void
    {
        $debugEnabled = (bool)$this->getConfig('debug', false);
        if (!$debugEnabled) {
            return;
        }

        $this->getLogger('Skyport::AuftragsFilter')->info($message);
    }
}
