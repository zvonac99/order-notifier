<?php

namespace OrderNotifier\SSE\Factory;

use OrderNotifier\SSE\DataEvent;
use OrderNotifier\Helpers\StorageHelper;

class TestEventFactory
{
    /**
     * Stvori testni event s jedinstvenim UID-om.
     */
    public static function create(): DataEvent
    {
        $defaults = StorageHelper::get_options([
            'default_notification_type'     => 'info',
            'default_notification_position' => 'top-right',
            'default_notification_timeout'  => 0,
            'default_notification_icon'     => '',
        ]);

        $order_id = random_int(10000, 99999);
        $names = ['Demo Demic', 'Ivana Testic', 'Marko Proba', 'Ana Streamovic'];
        $random_name = $names[array_rand($names)];

        // Generiranje jedinstvenog UID-a za testni event
        $uid = uniqid('test_', true);

        return new DataEvent('event', [
            'uid'        => $uid,
            'timestamp'  => time(),
            'event_type' => 'message',
            'order_id'   => $order_id,
            'reload'     => false,
            'payload'    => [
                'title'    => "Nova narudžba #{$order_id}",
                'message'  => "Primljena je narudžba od {$random_name}.",
                'type'     => $defaults['default_notification_type'],
                'position' => $defaults['default_notification_position'],
                'timeout'  => $defaults['default_notification_timeout'],
                'icon'     => $defaults['default_notification_icon'],
            ],
        ]);
    }
}
