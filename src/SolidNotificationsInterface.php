<?php
    namespace Pdsinterop\Solid\SolidNotifications;

    interface SolidNotificationsInterface
    {
        public function send($path, $type);
    }