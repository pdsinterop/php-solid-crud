<?php
    namespace Pdsinterop\Solid;

    interface SolidNotificationsInterface
    {
        public function send($path, $type);
    }