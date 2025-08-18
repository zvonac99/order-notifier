<?php
namespace OrderNotifier;
use OrderNotifier\Utils\Debug;

class Autoloader {
    private $prefixes = [];
    private int $loadedClasses = 0;

    /**
     * Dodaj namespace mapiranje.
     *
     * @param string $namespace Korijenski namespace (npr. 'MyPlugin\')
     * @param string $baseDir Putanja do direktorija
     */
    public function addNamespace(string $namespace, string $baseDir): void {
        $prefix = trim($namespace, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $this->prefixes[$prefix] = $baseDir;
    }

    /**
     * Registriraj autoloader.
     */
    public function register(): void {
        Debug::log('START: Autoloader pokreće registriranje klasa');
        spl_autoload_register([$this, 'loadClass']);

         // Odgodi ispis sve dok WP ne učita plugine
        add_action('plugins_loaded', [$this, 'logLoadedClasses'], 99);
    }

    /**
     * Učitaj klasu.
     */
    private function loadClass(string $class): void {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                // Debug ispis
                /* 
                Debug::log('Autoload pokušaj:', [
                    'Klasa'        => $class,
                    'Prefix'       => $prefix,
                    'BaseDir'      => $baseDir,
                    'RelativeClass'=> $relativeClass,
                    'File'         => $file,
                    'FileExists'   => file_exists($file) ? 'DA' : 'NE',
                ]);
                 */

                if (file_exists($file)) {
                    require_once $file;
                    $this->loadedClasses++;
                }

                return;
            }
        }
    }

    public function logLoadedClasses(): void {
        Debug::log("Autoloader učitao ukupno {$this->loadedClasses} klasa.");
    }

}
