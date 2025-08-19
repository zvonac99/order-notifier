<?php



namespace EliasHaeussler\SSE;

use EliasHaeussler\SSE\Event\Event;

/**
 * https://github.com/eliashaeussler/sse
 * Class DataEvent
 *
 * Implementira Event interface iz "eliashaeussler/sse" biblioteke,
 * i predstavlja jedan pojedinačni SSE događaj.
 *
 * Ova klasa enkapsulira ime događaja i pripadajuće podatke u obliku polja.
 * 
 * SSE biblioteka koristi ovaj Event kako bi serializirala i emitirala
 * događaje kroz SSE protokol prema klijentskoj strani.
 *
 * Važno:
 * - getName() vraća ime događaja koje će klijent koristiti za identifikaciju (event type)
 * - getData() vraća asocijativni niz podataka koji će biti poslani u JSON formatu klijentu
 * - jsonSerialize() implementira JsonSerializable, vraća podatke za JSON enkodiranje
 *
 * Kako koristiti:
 * Kreirajte instancu DataEvent sa imenom i podacima događaja, npr:
 * 
 * $event = new DataEvent('noviUnos', ['id' => 123, 'poruka' => 'Novi zapis']);
 * 
 * Zatim proslijedite ovu instancu SSE stream objektu, npr:
 * 
 * $stream->sendEvent($event);
 */
class DataEvent implements Event
{
    private string $name;
    private array $data;

    /**
     * DataEvent konstruktor.
     * @param string $name Ime događaja (event name)
     * @param array<string, mixed> $data Podaci događaja kao asocijativni niz (event payload)
    */
    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * Vrati ime događaja.
     *
     * Ovo ime će se koristiti u SSE protokolu kao tip eventa.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Vrati podatke događaja kao asocijativni niz.
     *
     * Ovi podaci će biti JSON kodirani i poslani klijentu.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Implementacija JsonSerializable interface.
     *
     * Vraća podatke koji će biti enkodirani u JSON pri emitiranju događaja.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }

    /**
     * Stvori sistemski događaj s podtipom.
     *
     * Ova metoda omogućuje stvaranje sistemskih događaja (npr. "ping", "keepalive", "heartbeat"),
     * koji se koriste za održavanje veze ili dijagnostiku, a ne predstavljaju konkretan poslovni događaj.
     *
     * Ime događaja bit će prefiksirano sa "system-", npr. "system-ping".
     *
     * @param string $subtype Podtip sistemskog događaja, npr. "ping", "heartbeat"
     * @param array<string, mixed> $payload Dodatni podaci koji se šalju s događajem (opcionalno)
     *
     * @return self
     */
    public static function createSystem(string $subtype, array $payload = []): self
    {
        $eventName = 'event';

        // Dodaj event_type i type unutar payloada
        $payload['event_type'] = 'system';
        $payload['type'] = $subtype;

        return new self($eventName, $payload);
    }

}
