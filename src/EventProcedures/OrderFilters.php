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

        if (!(bool)$this->getConfig($slotKey . '.enabled', false)) {
            return false;
        }

        $type = (string)$this->getConfig($slotKey . '.type', 'contact');
        $mode = (string)$this->getConfig($slotKey . '.mode', 'allow');
        $idsCsv = (string)$this->getConfig($slotKey . '.ids', '');
        $hint = (string)$this->getConfig($slotKey . '.hint', '');

        $ids = $this->parseIds($idsCsv);
        if (count($ids) === 0) {
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' keine IDs konfiguriert');
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
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' unbekannter Typ: ' . $type);
            return false;
        }

        if ($value <= 0) {
            $this->debug('Slot ' . $slot . $this->hint($hint) . ' kein Wert ermittelbar');
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
            ' => ' . ($result ? 'true' : 'false')
        );

        return $result;
    }

    private function getConfig(string $key, $default)
    {
        $value = $this->config->get('SkyportAuftragsFilter.' . $key);
        return $value === null ? $default : $value;
    }

    private function parseIds(string $csv): array
    {
        $out = [];

        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = (int)$part;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * ContactId aus OrderRelations (receiver contact)
     */
    private function extractReceiverContactId($order): int
    {
        if (!isset($order->orderRelations) || !is_array($order->orderRelations)) {
            return 0;
        }

        foreach ($order->orderRelations as $relation) {
            if (
                isset($relation->referenceType, $relation->relation, $relation->referenceId)
                && $relation->referenceType === 'contact'
                && $relation->relation === 'receiver'
            ) {
                return (int)$relation->referenceId;
            }
        }

        return 0;
    }

    /**
     * AddressId aus addressRelations (typeId: 1=billing, 2=shipping)
     */
    private function extractAddressIdByTypeId($order, int $typeId): int
    {
        if (!isset($order->addressRelations) || !is_array($order->addressRelations)) {
            return 0;
        }

        foreach ($order->addressRelations as $relation) {
            if (
                isset($relation->typeId, $relation->addressId)
                && (int)$relation->typeId === $typeId
            ) {
                return (int)$relation->addressId;
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
        if (!(bool)$this->getConfig('debug', false)) {
            return;
        }

        $this->getLogger('Skyport::AuftragsFilter')->info($message);
    }
}
