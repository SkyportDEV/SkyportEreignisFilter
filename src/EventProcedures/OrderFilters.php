<?php

namespace SkyportEreignisFilter\EventProcedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class OrderFilters
{
    use Loggable;

    private const LOG_PREFIX = 'SkyportEreignisFilter::log.';

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

        // "Ping" zum Beweis, dass der Filter läuft (nur bei Debug aktiv)
        $this->debug('ping', [
            'slot' => $slot
        ]);

        // Nur dumpen, wenn Debug aktiv ist
        $this->dumpOrder($order, 'before_check_slot_' . $slot);

        // Dropdown liefert "0"/"1" als String -> immer int casten!
        $enabled = ((int)$this->getConfig('filter' . $slot . '_enabled', 0) === 1);
        if (!$enabled) {
            return false;
        }

        $type = (string)$this->getConfig('filter' . $slot . '_type', 'contact');
        $mode = (string)$this->getConfig('filter' . $slot . '_mode', 'allow');
        $idsInput = (string)$this->getConfig('filter' . $slot . '_ids', '');
        $hint = (string)$this->getConfig('filter' . $slot . '_hint', '');

        $ids = $this->parseIds($idsInput);
        if (count($ids) === 0) {
            $this->debug('noIds', [
                'slot' => $slot,
                'hint' => $this->hint($hint)
            ]);
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
            $this->debug('unknownType', [
                'slot' => $slot,
                'hint' => $this->hint($hint),
                'type' => $type
            ]);
            return false;
        }

        if ($value <= 0) {
            $this->debug('noValue', [
                'slot' => $slot,
                'hint' => $this->hint($hint),
                'type' => $type
            ]);
            return false;
        }

        $inList = in_array($value, $ids, true);
        $result = ($mode === 'deny') ? !$inList : $inList;

        $this->debug('decision', [
            'slot' => $slot,
            'hint' => $this->hint($hint),
            'type' => $type,
            'value' => (string)$value,
            'mode' => $mode,
            'inList' => $inList ? '1' : '0',
            'result' => $result ? 'true' : 'false',
            'ids' => implode(',', $ids)
        ]);

        return $result;
    }

    private function getConfig(string $key, $default)
    {
        $value = $this->config->get('SkyportEreignisFilter.' . $key);
        return $value === null ? $default : $value;
    }

    /**
     * IDs: erlaubt Komma UND Zeilenumbrüche (auch gemischt)
     */
    private function parseIds(string $input): array
    {
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
     * Receiver-ContactId
     * Best Case: $order->contactReceiverId (stabil)
     */
    private function extractReceiverContactId($order): int
    {
        if (isset($order->contactReceiverId)) {
            $cid = (int)$order->contactReceiverId;
            if ($cid > 0) {
                return $cid;
            }
        }

        // Fallback (manche Kontexte)
        if (isset($order->contactId)) {
            $cid = (int)$order->contactId;
            if ($cid > 0) {
                return $cid;
            }
        }

        // Optionaler Fallback: orderRelations (nur wenn vorhanden)
        if (isset($order->orderRelations) && is_array($order->orderRelations)) {
            foreach ($order->orderRelations as $relation) {
                if (
                    isset($relation->referenceType, $relation->relation, $relation->referenceId)
                    && (string)$relation->referenceType === 'contact'
                    && (string)$relation->relation === 'receiver'
                ) {
                    return (int)$relation->referenceId;
                }
            }
        }

        return 0;
    }

    /**
     * Billing/Shipping AddressId
     * typeId: 1=billing, 2=shipping
     */
    private function extractAddressIdByTypeId($order, int $typeId): int
    {
        // 1 = billing, 2 = shipping (delivery)
        if ($typeId === 1) {
            if (isset($order->billingAddress) && is_object($order->billingAddress)) {
                if (isset($order->billingAddress->id)) {
                    $id = (int)$order->billingAddress->id;
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        } elseif ($typeId === 2) {
            // plentymarkets uses "deliveryAddress" (shipping)
            if (isset($order->deliveryAddress) && is_object($order->deliveryAddress)) {
                if (isset($order->deliveryAddress->id)) {
                    $id = (int)$order->deliveryAddress->id;
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }
    
        // Fallbacks (falls im Event-Kontext nicht hydriert)
        // addressRelations
        if (isset($order->addressRelations) && is_array($order->addressRelations)) {
            foreach ($order->addressRelations as $rel) {
                if (isset($rel->typeId) && (int)$rel->typeId === $typeId) {
                    if (isset($rel->addressId)) {
                        $aid = (int)$rel->addressId;
                        if ($aid > 0) {
                            return $aid;
                        }
                    }
                }
            }
        }
    
        // addresses list
        if (isset($order->addresses) && is_array($order->addresses)) {
            foreach ($order->addresses as $addr) {
                $t = 0;
    
                if (isset($addr->typeId)) {
                    $t = (int)$addr->typeId;
                } elseif (isset($addr->addressTypeId)) {
                    $t = (int)$addr->addressTypeId;
                }
    
                if ($t !== $typeId) {
                    continue;
                }
    
                if (isset($addr->id)) {
                    $aid = (int)$addr->id;
                    if ($aid > 0) {
                        return $aid;
                    }
                }
    
                if (isset($addr->addressId)) {
                    $aid = (int)$addr->addressId;
                    if ($aid > 0) {
                        return $aid;
                    }
                }
            }
        }
    
        return 0;
    }

    private function hint(string $hint): string
    {
        return $hint !== '' ? ' (' . $hint . ')' : '';
    }

    /**
     * Debug-Logger: nutzt Log-Codes + Context
     * WICHTIG: requires resources/lang/<lang>/log.properties entries for each code
     */
    private function debug(string $code, array $context = []): void
    {
        $debugEnabled = ((int)$this->getConfig('debug', 0) === 1);
        if (!$debugEnabled) {
            return;
        }
    
        $this->getLogger('OrderFilters')->debug(
            self::LOG_PREFIX . $code,
            $context
        );
    }

    /**
     * Order-Dump als Log-Code
     */
    private function dumpOrder($order, string $tag): void
    {
        $debugEnabled = ((int)$this->getConfig('debug', 0) === 1);
        if (!$debugEnabled) {
            return;
        }

        $data = [
            'tag' => $tag,
            'id' => isset($order->id) ? (int)$order->id : 0,
            'orderId' => isset($order->orderId) ? (int)$order->orderId : 0,
            'statusId' => isset($order->statusId) ? (string)$order->statusId : '',
            'contactReceiverId' => isset($order->contactReceiverId) ? (int)$order->contactReceiverId : 0,
            'contactId' => isset($order->contactId) ? (int)$order->contactId : 0,
            'has_addressRelations' => (isset($order->addressRelations) && is_array($order->addressRelations)) ? 1 : 0,
            'has_addresses' => (isset($order->addresses) && is_array($order->addresses)) ? 1 : 0,
            'has_orderRelations' => (isset($order->orderRelations) && is_array($order->orderRelations)) ? 1 : 0,
            'billingAddress_id' => (isset($order->billingAddress) && isset($order->billingAddress->id)) ? (int)$order->billingAddress->id : 0,
            'deliveryAddress_id' => (isset($order->deliveryAddress) && isset($order->deliveryAddress->id)) ? (int)$order->deliveryAddress->id : 0
        ];

        if (isset($order->addressRelations) && is_array($order->addressRelations)) {
            $data['addressRelations_preview'] = $this->dumpAddressRelations($order->addressRelations, 20);
        }

        if (isset($order->addresses) && is_array($order->addresses)) {
            $data['addresses_preview'] = $this->dumpAddresses($order->addresses, 20);
        }

        if (isset($order->orderRelations) && is_array($order->orderRelations)) {
            $data['orderRelations_preview'] = $this->dumpOrderRelations($order->orderRelations, 20);
        }

        $this->debug('orderDump', [
            'tag' => $tag,
            'data' => json_encode($data)
        ]);
    }

    private function dumpOrderRelations(array $rels, int $max): array
    {
        $out = [];
        $i = 0;

        foreach ($rels as $rel) {
            $out[] = [
                'referenceType' => isset($rel->referenceType) ? (string)$rel->referenceType : '',
                'relation' => isset($rel->relation) ? (string)$rel->relation : '',
                'referenceId' => isset($rel->referenceId) ? (int)$rel->referenceId : 0
            ];

            $i++;
            if ($i >= $max) {
                $out[] = ['...' => 'max reached'];
                break;
            }
        }

        return $out;
    }

    private function dumpAddressRelations(array $rels, int $max): array
    {
        $out = [];
        $i = 0;

        foreach ($rels as $rel) {
            $out[] = [
                'typeId' => isset($rel->typeId) ? (int)$rel->typeId : 0,
                'addressId' => isset($rel->addressId) ? (int)$rel->addressId : 0
            ];

            $i++;
            if ($i >= $max) {
                $out[] = ['...' => 'max reached'];
                break;
            }
        }

        return $out;
    }

    private function dumpAddresses(array $addresses, int $max): array
    {
        $out = [];
        $i = 0;

        foreach ($addresses as $a) {
            $out[] = [
                'id' => isset($a->id) ? (int)$a->id : 0,
                'typeId' => isset($a->typeId) ? (int)$a->typeId : 0,
                'addressTypeId' => isset($a->addressTypeId) ? (int)$a->addressTypeId : 0,
                'addressId' => isset($a->addressId) ? (int)$a->addressId : 0
            ];

            $i++;
            if ($i >= $max) {
                $out[] = ['...' => 'max reached'];
                break;
            }
        }

        return $out;
    }
}
