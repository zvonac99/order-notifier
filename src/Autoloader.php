<?php
namespace OrderNotifier;

/**
 * Autoloader klasa za učitavanje klasa prema PSR-4 principu.
 *
 * @note Na Linux sustavima provjerava i točnost velikih/malih slova u nazivima
 *       mapa i datoteka te upozorava putem Debug loga i WP error loga
 *       kako bi se izbjegli problemi s case-sensitive datotečnim sustavima.
 */

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

   
    private function loadClass(string $class): void {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (strpos($class, $prefix) === 0) {
                $relativeClass = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                if (file_exists($file)) {
                    if (!$this->isCaseSensitiveMatch($file)) {
                        $msg = "UPOZORENJE: Putanja klase '{$class}' ne podudara se u potpunosti po slovima s datotečnim sustavom: {$file}. "
                            . "Provjeri namespace i naziv mape/datoteke.";
                        Debug::log($msg);
                        error_log($msg);
                    }
                    require_once $file;
                    $this->loadedClasses++;
                }

                return;
            }
        }
    }

    /**
     * Provjerava poklapa li se path po velikim/malim slovima.
     * Na Windowsu preskače provjeru radi performansi.
     */
    private function isCaseSensitiveMatch(string $path): bool {
        // Na Windowsu preskoči detaljnu provjeru
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            return true;
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            return false; // Datoteka ne postoji
        }

        // Usporedi dijelove putanje točno po slovima
        $expectedParts = explode(DIRECTORY_SEPARATOR, $path);
        $realParts = explode(DIRECTORY_SEPARATOR, $realPath);

        if (count($expectedParts) !== count($realParts)) {
            return false;
        }

        foreach ($expectedParts as $i => $part) {
            if ($part === '') continue;
            if ($part !== $realParts[$i]) {
                return false;
            }
        }
        return true;
    }


    public function logLoadedClasses(): void {
        Debug::log("Autoloader učitao ukupno {$this->loadedClasses} klasa.");
    }

}
