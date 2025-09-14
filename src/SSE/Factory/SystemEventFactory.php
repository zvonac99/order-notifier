<?php

namespace OrderNotifier\SSE\Factory;

use OrderNotifier\SSE\DataEvent;

class SystemEventFactory
{
    /**
     * Stvori ping event.
     */
    public static function createPing(): DataEvent
    {
        return DataEvent::createSystem('ping', ['timestamp' => time()]);
    }

    /**
     * Stvori heartbeat ili neki drugi sistemski event.
     */
    public static function createHeartbeat(): DataEvent
    {
        return DataEvent::createSystem('heartbeat', ['timestamp' => time()]);
    }
}
