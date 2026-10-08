<?php

namespace NishangSystems\Passkeys\Traits;

trait Loggable
{
    private function info(string $message): void
    {
        $class = get_class($this);
        logger()->info("$class:  $message");
    }

    private function error(string $message, array $context = []): void
    {
        $class = get_class($this);
        logger()->error("$class:  $message", $context);
    }

    /*    private function warning(string $message, array $context = []): void
        {
            $class = get_class($this);
            logger()->warning("$class:  $message", $context);
        }

        private function debug(string $message): void
        {
            $class = get_class($this);
            logger()->debug("$class:  $message");
        }

        private function critical(string $message): void
        {
            $class = get_class($this);
            logger()->critical("$class:  $message");
        }

        private function alert(string $message): void
        {
            $class = get_class($this);
            logger()->alert("$class:  $message");
        }

        private function emergency(string $message): void
        {
            $class = get_class($this);
            logger()->emergency("$class:  $message");
        }

        private function notice(string $message): void
        {
            $class = get_class($this);
            logger()->notice("$class:  $message");
        }

        private function log($level, string $message): void
        {
            $class = get_class($this);
            logger()->log($level, "$class:  $message");
        }*/
}
