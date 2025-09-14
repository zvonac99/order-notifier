<?php
namespace OrderNotifier;

use OrderNotifier\Utils\Debug;
use OrderNotifier\Utils\Constants;
use OrderNotifier\Helpers\StorageHelper;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Klasa za upravljanje registracijom WordPress hookova (akcija i filtera).
 * 
 * Omogućava:
 * - Registraciju hookova s jedinstvenom kontrolom (sprječavanje višestruke registracije)
 * - Spremanje stanja registriranih hookova u WP opciju radi persistencije između zahtjeva
 * - Resetiranje i čišćenje registriranih hookova iz memorije i baze
 */
 
class HooksLoader {

	/**
	 * Opcija u WP bazi za pohranu registriranih hookova.
	 */
	
    /**
     * Polje za pohranu svih dodanih akcija.
     *
     * @var array
     */
    protected $actions;

    /**
     * Polje za pohranu svih dodanih filtera.
     *
     * @var array
     */
    protected $filters;

    /**
     * Polje za pohranu svih izvršenih akcija i filtera.
     *
     * @var array
     */
    protected $executed = [];

    /**
     * Konstruktor.
     */
    public function __construct() {
        $this->actions = [];
        $this->filters = [];
        $this->executed = StorageHelper::get_transient(Constants::ON_TRANSIENT_HOOKS, []);
		if (!is_array($this->executed)) {
			$this->executed = [];
		}
    }
	
	/**
	 * Provjerava je li hook s danim ID-em već registriran.
	 *
	 * @param string $id Jedinstveni identifikator hooka.
     * @param bool   $separate_transient  Jedinstveno ime transiennta
	 * @return bool
	 */
	 
	 public function already_executed(string $id, bool $separate_transient = false): bool {
        if ($separate_transient) {
            return (bool) StorageHelper::get_transient(Constants::ON_TRANSIENT_HOOKS . '_' . md5($id));
        }
        return isset($this->executed[$id]);
    }

	/**
	 * Označava hook kao registriran i sprema stanje u bazu.
	 *
	 * @param string $id Jedinstveni identifikator hooka.
     * @param bool   $separate_transient  Jedinstveno ime transiennta
	 */
	 
	public function mark_as_executed(string $id, bool $separate_transient = false): void {
        if ($separate_transient) {
            StorageHelper::set_transient(Constants::ON_TRANSIENT_HOOKS . '_' . md5($id), true, 2 * HOUR_IN_SECONDS);
            Debug::log("Zasebno spremljen hook transient: {$id}");
            return;
        }

        $this->executed[$id] = true;
        StorageHelper::set_transient(Constants::ON_TRANSIENT_HOOKS, $this->executed, 2 * HOUR_IN_SECONDS);
        Debug::log("Dodajemo akcije u zajednički transient: {$id}");
    }

    /**
     * Registrira WordPress akciju ili filter samo jednom, koristeći jedinstveni identifikator.
     *
     * Ova metoda osigurava da se određena akcija ili filter ne registrira višestruko, što je korisno
     * u kontekstu ponovnog učitavanja plugina (npr. kroz Heartbeat ili lazy loading faze).
     *
     * Ako identifikator ($id) nije eksplicitno zadan, automatski se generira prema sljedećoj shemi:
     *     '{hook}::{class}::{callback}'
     *
     * Podržani oblici callback-a:
     * - Ime metode kao string (npr. 'load_data')
     * - Polje [objekt ili ime klase, metoda] (npr. [$this, 'load_data'] ili ['MyClass', 'load_data'])
     * - Anonimna funkcija (Closure), za koju se koristi `spl_object_hash()` radi jedinstvenosti
     *
     * @param string        $hook          Naziv WordPress hooka (npr. 'init', 'the_content').
     * @param object|string $component     Objekt (instanca klase) ili naziv klase koji sadrži callback metodu.
     * @param callable      $callback      Callback metoda: string, array ili Closure.
     * @param int           $priority      Prioritet hooka. Niže vrijednosti znače ranije izvođenje. Zadano: 10.
     * @param int           $accepted_args Broj argumenata koje callback prihvaća. Zadano: 1.
     * @param string|null   $id            (Opcionalno) Jedinstveni ID za ovu akciju/filter. Ako nije zadan, generira se automatski.
     * @param bool          $separate_transient Mogućnost spremanja akcije/filtera u zaseban transiennt
     *
     * @return void
     */
    public function add_action_once($hook, $component, $callback, $priority = 10, $accepted_args = 1, ?string $id = null, bool $separate_transient = false) {
        if ($id === null) {
            $id = $this->generate_hook_id($hook, $component, $callback);
            Debug::log("Generiran id akcije: {$id}");
        }

        if ($this->already_executed($id, $separate_transient)) {
            Debug::log("Preskočeno ponovno registriranje akcije: {$id}");
            return;
        }

        $this->add_action($hook, $component, $callback, $priority, $accepted_args);
        $this->mark_as_executed($id, $separate_transient);
    }

    public function add_filter_once($hook, $component, $callback, $priority = 10, $accepted_args = 1, ?string $id = null, bool $separate_transient = false) {
        if ($id === null) {
            $id = $this->generate_hook_id($hook, $component, $callback);
            Debug::log("Generiran id filtera: {$id}");
        }

        if ($this->already_executed($id, $separate_transient)) {
            Debug::log("Preskočeno ponovno registriranje filtera: {$id}");
            return;
        }

        $this->add_filter($hook, $component, $callback, $priority, $accepted_args);
        $this->mark_as_executed($id, $separate_transient);
    }
	
	/**
	 * Generira jedinstveni ID za hook na temelju hooka, komponente i callback-a.
	 * 
	 * @param string        $hook      Naziv WordPress hooka.
	 * @param object|string $component Objekt ili naziv klase.
	 * @param callable      $callback  Callback funkcija/metoda.
	 * @return string
	 */
	 
	private function generate_hook_id(string $hook, $component, $callback): string {
        if (is_string($callback)) {
            // $component može biti string (naziv klase) ili objekt
            $component_name = is_object($component) ? get_class($component) : $component;
            return $hook . '::' . $component_name . '::' . $callback;
        } elseif (is_array($callback) && is_object($callback[0])) {
            return $hook . '::' . get_class($callback[0]) . '::' . $callback[1];
        } elseif (is_array($callback)) {
            return $hook . '::' . $callback[0] . '::' . $callback[1];
        } elseif ($callback instanceof \Closure) {
            return $hook . '::closure_' . spl_object_hash($callback);
        }
        return $hook . '::unknown_callback';
    }

    /**
     * Dodaj novu akciju u WordPress.
     *
     * @param string        $hook      Naziv hooka (npr. 'init').
     * @param object|string $component Objekt (instanca klase) ili ime klase za statičke metode.
     * @param string        $callback  Naziv metode.
     * @param int           $priority  Prioritet hooka.
     * @param int           $accepted_args Broj argumenata koje metoda prima.
     */
    public function add_action(string $hook, $component, $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
        $comp_name = is_object($component) ? get_class($component) : $component;
        Debug::log("Dodana akcija: {$hook} -> {$comp_name}::{$callback}()");
    }

    /**
     * Dodaj novi filter u WordPress.
     *
     * @param string        $hook      Naziv hooka (npr. 'the_content').
     * @param object|string $component Objekt (instanca klase) ili ime klase za statičke metode.
     * @param string        $callback  Naziv metode.
     * @param int           $priority  Prioritet hooka.
     * @param int           $accepted_args Broj argumenata koje metoda prima.
     */
    public function add_filter(string $hook, $component, $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
        $comp_name = is_object($component) ? get_class($component) : $component;
        Debug::log("Dodan filter: {$hook} -> {$comp_name}::{$callback}()");
    }

    /**
     * Registriraj sve dodane akcije i filtere u WordPress.
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }

    /**
     * Učitaj WordPress hookove iz klase ili instance pomoću `get_hooks()` metode.
     *
     * Funkcija podržava:
     * - Statičku metodu `get_hooks()` ako se proslijedi naziv klase kao string.
     * - Metodu instance `get_hooks()` ako se proslijedi instanca objekta.
     *
     * Hookovi definirani preko `get_hooks()` metode moraju imati:
     * - `callback` (string, obavezno): Ime metode unutar klase.
     * - `type` (string, opcionalno): 'action' ili 'filter'. Zadano: 'action'.
     * - `hook` (string, opcionalno): Ime stvarnog WordPress hooka.
     *      Ako nije navedeno, koristit će se naziv ključa u nizu.
     * - `priority` (int, opcionalno): Prioritet hooka. Zadano: 10.
     * - `args` (int, opcionalno): Broj argumenata. Zadano: 1.
     *
     * Primjer:
     * return [
     *     'hook_a_settings' => [
     *         'hook'     => 'admin_menu',
     *         'callback' => 'add_settings_page',
     *         'type'     => 'action',
     *         'priority' => 10,
     *         'args'     => 1,
     *     ],
     *     'hook_b_debug' => [
     *         'hook'     => 'admin_menu',
     *         'callback' => 'add_debug_log_page',
     *         'type'     => 'action',
     *     ],
     * ];
     *
     * @param object|string $class_or_instance Instanca klase ili ime klase.
     */
    public function load_hooks_from_class($class_or_instance) {
        $instance   = is_object($class_or_instance) ? $class_or_instance : null;
        $class_name = is_string($class_or_instance) ? $class_or_instance : get_class($class_or_instance);

        // Dohvati hookove iz klase (staticki ili iz instance)
        if (method_exists($class_name, 'get_hooks')) {
            $hooks = $class_name::get_hooks();
            Debug::log('HooksLoader-učitani hookovi iz statičkih klasa');
        } elseif ($instance && method_exists($instance, 'get_hooks')) {
            $hooks = $instance->get_hooks();
            Debug::log('HooksLoader-učitani hookovi iz instanca klasa');
        } else {
            return;
        }

        // Instanciraj klasu ako je potrebno
        if (!$instance) {
            $instance = new $class_name();
        }

        foreach ($hooks as $hook_key => $hook_data) {
            $callback      = $hook_data['callback'];
            $type          = $hook_data['type'] ?? 'action';
            $priority      = $hook_data['priority'] ?? 10;
            $accepted_args = $hook_data['args'] ?? 1;
            $hook_name     = $hook_data['hook'] ?? $hook_key;

            if ($type === 'filter') {
                $this->add_filter($hook_name, $instance, $callback, $priority, $accepted_args);
            } else {
                $this->add_action($hook_name, $instance, $callback, $priority, $accepted_args);
            }
        }
    }
	
     /**
     * Uklanja jedan ili više hookova iz interne evidencije izvršenih hookova.
     *
     * Funkcija omogućuje ponovno izvršavanje hookova tako da se ukloni
     * njihov identifikator iz pohranjenih vrijednosti u WordPress options.
     * Možeš predati jedan ID kao string ili više ID-jeva kao niz.
     *
     * @param string|array $ids Jedan ili više ID-eva hookova u formatu: `hook::class::method`.
     *
     * @return void
     */
    public function delete_transient_hook(?string $id = null): void {
        $transient_name = Constants::ON_TRANSIENT_HOOKS;

        if (!empty($id)) {
            $transient_name .= '_' . $id;
        }

        StorageHelper::delete_transient($transient_name);
        Debug::log("Obrisan transient: {$transient_name}");
    }



	/**
	 * Resetira sve trenutno spremljene hookove (akcije, filtere i izvršene oznake),
	 * te briše spremljeno stanje iz WP baze.
	 */
	public function reset_hooks(): void {
		$this->executed = [];
		$this->actions = [];
		$this->filters = [];

		StorageHelper::delete_transient(Constants::ON_TRANSIENT_HOOKS);

		Debug::log('HooksLoader: Resetirani svi hookovi i obrisana spremljena opcija.');
	}
	
	/**
	 * Spremi trenutno označene hookove u WP opciju (batch update).
	 */
	public function save_executed_hooks(): void {
		StorageHelper::set_transient(Constants::ON_TRANSIENT_HOOKS, $this->executed, 2 * HOUR_IN_SECONDS);
		Debug::log('HooksLoader: Spremljeni izvršeni hookovi u transiennt.');
	}

}
