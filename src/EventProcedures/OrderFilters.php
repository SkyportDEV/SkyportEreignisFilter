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

        $slotKey = 'filters.filter' . $slot;

        $enabled = (bool)$this->getConfig($slotKey . '.enabled', false);
        if (!$enabled) {
            return false;
        }

        $type = (string)$this->getConfig($slotKey . '.type', 'contact');
        $mode = (string)$this->getConfig($slotKey . '.mode', 'allow');
        $idsCsv = (string)$this->getConfig($slotKey . '.ids', '');
        $hint = (string)$this->getConfig($slotKey . '.hint', '');

        $ids = $this->parseIds($idsCsv);
        if (count($ids) === 0) {
            $this->debug('Slot ' . $slot . ($hint !== '' ? ' (' . $hint . ')' : '') . ' hat keine IDs konfiguriert -> false');
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
            $this->debug('Slot ' . $slot . ($hint !== '' ? ' (' . $hint . ')' : '') . ' unbekannter type=' . $type . ' -> false');
            return false;
        }

        if ($value <= 0) {
            $this->debug('Slot ' . $slot . ($hint !== '' ? ' (' . $hint . ')' : '') . ' value konnte nicht ermittelt werden (type=' . $type . ') -> false');
            return false;
        }

        $inList = in_array($value, $ids, true);

        if ($mode === 'deny') {
            $result = !$inList;
        } else {
            $result = $inList;
        }

        $this->debug(
            'Slot ' . $slot .
            ($hint !== '' ? ' (' . $hint . ')' : '') .
            ' type=' . $type .
            ' mode=' . $mode .
            ' value=' . $value .
            ' inList=' . ($inList ? '1' : '0') .
            ' => ' . ($result ? 'true' : 'false')
        );

        return $result;
    }

    private function getConfig(string $key, $default)
    {
        $fullKey = 'SkyportAuftragsFilter.' . $key;

        $value = $this->config->get($fullKey);
        if ($value === null) {
            return $default;
        }

        return $value;
    }

    private function parseIds(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $out[] = (int)$part;
        }

        $out = array_filter($out, function ($v) {
            return is_int($v) && $v > 0;
        });

        return array_values(array_unique($out));
    }

    private function extractReceiverContactId($order): int
    {
        $relations = null;

        if (isset($order->relations)) {
            $relations = $order->relations;
        } elseif (isset($order->orderRelations)) {
            $relations = $order->orderRelations;
        }

        if (!$relations || !is_iterable($relations)) {
            return 0;
        }

        foreach ($relations as $rel) {
            $referenceType = $this->readProp($rel, 'referenceType');
            $relation = $this->readProp($rel, 'relation');
            $referenceId = (int)$this->readProp($rel, 'referenceId');

            if ((string)$referenceType === 'contact' && (string)$relation === 'receiver') {
                return $referenceId;
            }
        }

        return 0;
    }

    private function extractAddressIdByTypeId($order, int $typeId): int
    {
        $addressRelations = null;

        if (isset($order->addressRelations)) {
            $addressRelations = $order->addressRelations;
        } elseif (isset($order->ordersAddressRelations)) {
            $addressRelations = $order->ordersAddressRelations;
        } elseif (isset($order->orderAddressRelations)) {
            $addressRelations = $order->orderAddressRelations;
        }

        if (!$addressRelations || !is_iterable($addressRelations)) {
            return 0;
        }

        foreach ($addressRelations as $ar) {
            $tId = (int)$this->readProp($ar, 'typeId');
            if ($tId != $typeId) {
                continue;
            }

            $addressId = (int)$this->readProp($ar, 'addressId');

            if ($addressId <= 0) {
                $addressId = (int)$this->readProp($ar, 'referenceId');
            }

            if ($addressId > 0) {
                return $addressId;
            }
        }

        return 0;
    }

    private function readProp($obj, string $key)
    {
        if (is_array($obj) && array_key_exists($key, $obj)) {
            return $obj[$key];
        }

        if (is_object($obj) && isset($obj->{$key})) {
            return $obj->{$key};
        }

        $method = 'get' . ucfirst($key);
        if (is_object($obj) && method_exists($obj, $method)) {
            return $obj->{$method}();
        }

        return null;
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
