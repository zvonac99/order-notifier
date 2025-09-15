## 📖 Dokumentacija

Detaljnu dokumentaciju, primjere korištenja i dodatne informacije pronađite na [Wiki stranici](https://github.com/zvonac99/order-notifier/wiki).

# Order Notifier – Generalni opis

**Order Notifier** je modularni WordPress/WooCommerce plugin koji omogućuje **real-time notifikacije o narudžbama**, praćenje statusa, testne i sistemske evente, te administrativnu kontrolu putem WordPress/WooCommerce Settings API-ja.

Plugin je kompatibilan s **vanjskom SSE biblioteku** (`eliashaeussler/sse`) i prilagođen je WordPress/WooCommerce okruženju.

---

## 1. SSE i Event sustav

* **DataEvent** – prilagođena implementacija SSE eventa.
* **SseCore** – centralna klasa za upravljanje SSE streamovima.
* **Factory klase** – generiraju različite tipove eventa:

  * `RealEventFactory` – stvarni događaji iz buffer-a.
  * `TestEventFactory` – testni eventi.
  * `SystemEventFactory` – ping/heartbeat eventi.

> Modularnost omogućuje dodavanje novih Factory klasa bez mijenjanja osnovnog SSE sustava.

---

## 2. Administracija i postavke

* **SettingsPage** – registracija i prikaz postavki plugina: scope, statusi narudžbi, prilagođena poruka, reload tablice, dozvoljene uloge, ping interval, test evente i defaultne notifikacije.
* **SettingsSanitizerTrait** – centralizirana logika sanitizacije i validacije postavki.
* **DebugPage** – pregled i upravljanje debug logovima, trenutni i arhivirani log, AJAX metode, prikaz samo u debug modu.

---

## 3. Core i servisne klase

* **PluginCore** – inicijalizacija svih ključnih klasa, registracija hookova, upravljanje lokalizacijom, assets, postavkama i debug stranicama.
* **PluginAssets** – registracija i učitavanje JS/CSS datoteka, inline konfiguracija, REST endpoint podaci.
* **Lifecycle** – aktivacija, deaktivacija, deinstalacija i nadogradnja plugina, čišćenje opcija, sessiona, transient podataka i logova.
* **Autoloader** – automatsko učitavanje klasa po PSR-4, s provjerom case-sensitive putanja na Linuxu.
* **HooksLoader** – jedinstvena registracija i upravljanje WP hookovima, sprema stanje u transient radi persistencije.

---

## 4. Helper klase

Ove klase čine **temelj plugina**, pružajući osnovne funkcionalnosti i pristup podacima:

1. **OrdersHelper** – rad s WooCommerce narudžbama, dohvat i filtriranje po statusu/datumima.
2. **ScreenHelper** – rad s admin ekranima, keširanje trenutnog screen ID-a, provjere ekrana.
3. **StorageHelper** – centralizirana pohrana podataka plugina, uključujući session, transients i SSE buffer.
4. **UserHelper** – rad s korisničkim kontekstom, dohvat, autorizacija i čišćenje konteksta.
5. **Debug** – logiranje, backtrace, rotacija i brisanje logova, koristi se u svim helperima i core klasama.
6. **Constants** – centralne statične vrijednosti: role, screen ID-evi, option/meta ključevi, REST API rute, transient/session ključevi.

> Helperi standardiziraju pristup podacima, logiranje i korisnički kontekst te olakšavaju integraciju sa SSE, adminom i notifikacijama.

---

## 5. Redoslijed izvršavanja i hookovi

Reference za admin hookove i ključne evente:

| Hook                               | Callback / Klasa                                 | Opis                                  |
| ---------------------------------- | ------------------------------------------------ | ------------------------------------- |
| `plugins_loaded`                   | `Locale::load_plugin_textdomain()`               | Učitavanje prijevoda                  |
| `current_screen`                   | `ScreenHelper::capture_screen`                   | Prikupljanje podataka o ekranu admina |
| `admin_menu`                       | `SettingsPage::add_settings_page()`              | Dodavanje stranice postavki           |
| `admin_menu`                       | `DebugPage::add_debug_log_page()`                | Dodavanje debug stranice              |
| `admin_init`                       | `SettingsPage::register_settings()`              | Registracija postavki                 |
| `admin_head`                       | `PluginBootstrapper::prepare_environment()`      | Priprema okruženja i provjere         |
| `admin_footer`                     | `PluginAssets::output_config_inline()`           | Inline JS za notifikacije             |
| `admin_enqueue_scripts`            | `PluginAssets::enqueue_notifier_script_assets()` | JS/CSS za admin i notifikacije        |
| `woocommerce_order_status_changed` | `OrderEventService::dispatch_new_order_event`    | Reakcija na promjenu statusa          |
| `woocommerce_new_order`            | `OrderEventService::get_new_order()`             | Reakcija na novu narudžbu             |
| `rest_api_init`                    | `SseCore::register_rest_routes()`                | Registracija SSE REST endpointa       |
| `wp_logout`                        | `UserHelper::clear_user_context()`               | Čišćenje korisničkog konteksta        |

> WordPress izvršava hookove prema svom redoslijedu; PluginCore i HooksLoader osiguravaju da se svi plugin hookovi integriraju u ispravan tok.

---

## 6. Ulazna skripta (`order-notifier.php`)

* Provjerava aktivnost WooCommerce-a.
* Registrira hookove za aktivaciju, deaktivaciju i deinstalaciju.
* Pokreće glavni objekt **PluginCore** za inicijalizaciju plugina.

---

## 7. Povezanost i arhitektura

* Plugin je modularan i proširiv.
* SSE sustav, hookovi, assets, postavke i debug logovi povezani su kroz `PluginCore`.
* Administracijske stranice koriste zajedničke obrasce i traitove za standardiziranu validaciju i prikaz podataka.
* Eventi se šalju kroz SSE stream (stvarni, testni ili sistemski).
* Helper blok pruža ključnu infrastrukturu za rad plugina, olakšava upravljanje narudžbama, podacima, korisnicima i logovima.

---

## 8. JavaScript datoteke i moduli

Plugin koristi kombinaciju klasičnih WordPress skripti i modernih ESM modula (type="module", dostupno od WP 6.5).

### a) Postavke i administracija

|Datoteka                               | Vrsta| Opis                                                                |
|---------------------------------------|------|---------------------------------------------------------------------|
|assets/js/order-notifier-settings.js   |JS    |Funkcionalnost za postavke i debug stranicu plugina (AJAX za logove).|
|assets/select2/js/select2.js           |JS    |Uvoz Select2 biblioteke za višestruke odabire u postavkama.          |
|assets/css/order-notifier-settings.css |CSS   |Stilovi za postavke plugina.                                         |
|assets/select2/css/select2.css         |CSS   |Stilovi Select2 komponenti.                                          |

### b) Notifikacijski sustav (Orders / SSE)

|Datoteka                             | Vrsta   | Opis                                                                  |
|-------------------------------------|---------|-----------------------------------------------------------------------|
|assets/notifier/js/notifier.js       |JS       |Klasična skripta za prikaz i animaciju notifikacija.                   |
|assets/js/OrderNotifierConfig.js     |ESM modul|Izvoz globalne konfiguracije (OrderNotifierData: REST endpoint, nonce).|
|assets/js/BroadcastChannelHandler.js |ESM modul|Upravljanje BroadcastChannel API-jem (sinkronizacija među tabovima).   |
|assets/js/storage-utils.js           |ESM modul|Helper funkcije za session/local storage. (trenutno opcionalno učitan) |
|assets/js/custom.js                  |ESM modul|Glavni modul plugina: povezuje SSE stream, obrađuje evente, koristi ostale module.|
|assets/notifier/css/notifier.css     |CSS      |Stilovi za notifikacijski prozor.                                      |
|assets/css/order-notifier.css        |CSS      |Opći stilovi za integraciju notifikacija u WP admin.                   |

Konfiguracija (OrderNotifierData) se dodaje globalno preko PluginAssets::output_config_inline(), kako bi bila dostupna prije učitavanja ESM modula.

## 9. Kontekst korištenja

* Real-time push notifikacije za WooCommerce administratore i shop managere.
* Testiranje i debugging notifikacija putem test eventova.
* Praćenje SSE streama kroz ping/heartbeat događaje.
* Konfiguracija postavki putem administratorskog sučelja.
* Proširivost za dodatne tipove eventa, hookove i assets bez izmjene osnovne arhitekture.
