<?php

namespace OrderNotifier\SSE\Factory;

use OrderNotifier\SSE\DataEvent;
use OrderNotifier\Helpers\StorageHelper;
use OrderNotifier\Helpers\UserHelper;
use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;

class RealEventFactory
{
    public static function fetchNext(): ?DataEvent
    {
        $message = self::getNextMessage();
        if (!$message || !isset($message['uid'])) {
            return null;
        }

        if (self::checkAndMarkProcessed($message['uid'])) {
            Debug::log("Event {$message['uid']} već potvrđen, preskačem");
            return null;
        }

        return new DataEvent('event', $message);
    }

    private static function getNextMessage(): ?array
    {
        $buffer = StorageHelper::get_json_buffer();
        if (!is_array($buffer) || empty($buffer['events'])) {
            return null;
        }

        $ctx = UserHelper::get_current_user_context();
        if (!$ctx['authorized']) {
            Debug::log('Neautorizirani korisnik, event se ne šalje.', $ctx);
            return null;
        }

        foreach ($buffer['events'] as $event) {
            if (isset($event['uid'], $event['is_processed']) && $event['is_processed'] === false) {
                if (!isset($event['timestamp'])) {
                    $event['timestamp'] = time();
                }
                Debug::log("Sljedeći neobrađeni događaj:", $event);
                if (isset($event['order_id'])) {
                    self::update_user_last_seen_order_id($event['order_id']);
                }
                
                return $event;
            }
        }

        Debug::log('Nema novog eventa');
        return null;
    }

    public static function checkAndMarkProcessed(string $uid): bool
    {
        $cookieValue = (int) StorageHelper::get_cookie($uid);
        $isProcessed = $cookieValue === 1;

        Debug::log("Provjera event UID {$uid}, procesuiran: " . ($isProcessed ? 'DA' : 'NE'));

        if ($isProcessed) {
            self::markProcessed($uid);
        }

        return $isProcessed;
    }

    public static function markProcessed(string $uid): void
    {
        $buffer = StorageHelper::get_json_buffer();
        if (!is_array($buffer)) {
            $buffer = ['events' => []];
        }

        $found = false;

        foreach ($buffer['events'] as &$event) {
            if (isset($event['uid']) && $event['uid'] === $uid) {
                $event['is_processed'] = true;
                StorageHelper::delete_cookie($uid);
                $found = true;
                break;
            }
        }
        unset($event);

        StorageHelper::set_json_buffer($buffer);

        if (!$found) {
            Debug::log("Nije pronađen događaj za UID: $uid", ['buffer' => $buffer]);
        }
    }

    private static function update_user_last_seen_order_id(int $order_id): void {
        $ctx = UserHelper::get_current_user_context();
        $user_id = $ctx['user_id'];
        $context = StorageHelper::get_user_meta($user_id, Constants::ON_USER_CONTEXT_KEY);
        if (!is_array($context)) {
            $context = [];
        }
        $context['last_seen_order_id'] = $order_id;
        StorageHelper::set_user_meta($user_id, Constants::ON_USER_CONTEXT_KEY, $context);
        Debug::log('Ažuriram meta tablicu sa korisnikovim id-om i id narudžbe');
    }
}
