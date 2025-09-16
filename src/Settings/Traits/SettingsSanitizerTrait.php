<?php
namespace OrderNotifier\Settings\Traits;

/**
 * Trait SettingsSanitizerTrait
 *
 * Omogućuje fleksibilnu i proširivu sanitizaciju polja u WordPress plugin postavkama.
 */
trait SettingsSanitizerTrait {

    /**
     * Sanitizira cijeli set postavki na temelju definiranih polja.
     *
     * @param array $input  Sirovi unos ($_POST).
     * @param array $fields Definirana polja postavki (s ključevima i argumentima).
     *
     * @return array Sanitizirani podatci spremni za spremanje.
     * @internal
     */
    protected function sanitize_settings(array $input, array $fields): array {
        $sanitized = [];

        foreach ($fields as $key => $args) {
            $raw = $input[$key] ?? null;
            $sanitized[$key] = $this->sanitize_field($raw, $args);
        }

        return $sanitized;
    }

    /**
     * Sanitizira pojedino polje na temelju tipa i dodatnih pravila.
     *
     * @param mixed  $raw   Izvorna (nesanitizirana) vrijednost.
     * @param array  $args  Argumenti za polje (type, default, min, max, multiplier, itd.).
     *
     * @return mixed Sanitizirana vrijednost.
     * @internal
     */
    protected function sanitize_field($raw, array $args) {
        // Osiguraj sigurne vrijednosti za ključeve koji bi mogli nedostajati
        $args['type']    = $args['type'] ?? 'text';
        $args['default'] = $args['default'] ?? null;
        
        if (is_null($raw)) {
            return $args['default'] ?? null;
        }

        switch ($args['type'] ?? 'text') {
            case 'checkbox':
                return $raw === '1' ? '1' : '';

            case 'multiselect':
                return array_map('sanitize_text_field', (array) $raw);

            case 'select':
            case 'text':
                return $this->sanitizeText($raw, $args);

            case 'number':
                return $this->sanitizeNumberWithMultiplier($raw, $args);

            default:
                return $this->sanitizeText($raw, $args);
        }
    }

    /**
     * Sanitizira tekstualnu vrijednost koristeći WordPress sanitizacijski filter.
     *
     * Ako je vrijednost null, vraća default iz polja ili prazan string.
     *
     * @param mixed $value  Ulazna vrijednost za sanitizaciju.
     * @param array $field  Polje definicija koje može sadržavati default vrijednost.
     *
     * @return string Sanitizirani tekst.
     * @internal
     */
    protected function sanitizeText($value, array $field) {
        if (is_null($value)) {
            $value = $field['default'] ?? '';
        }

        return sanitize_text_field($value);
    }


    /**
     * Validira brojčanu vrijednost tako da osigurava da je unutar zadanog raspona.
     *
     * @param float|int $value Vrijednost za validaciju.
     * @param float     $min   Minimalna dopuštena vrijednost.
     * @param float     $max   Maksimalna dopuštena vrijednost.
     *
     * @return float Vraća vrijednost ograničenu između min i max.
     * @internal
     */
    protected function validateNumber($value, float $min, float $max): float {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }

    /**
     * Sanitizira brojčano polje, uključujući validaciju i multiplikator.
     *
     * @param mixed $value Izvorna vrijednost (npr. iz $_POST).
     * @param array $field Polje s postavkama ('default', 'min', 'max', 'multiplier').
     *
     * @return float|int Sanitizirana brojčana vrijednost.
     * @internal
     */

    protected function sanitizeNumberWithMultiplier($value, array $field)
    {
        // Defaulti
        $default = $this->getDefault($field, 0);
        $min = $field['min'] ?? 0;
        $max = $field['max'] ?? PHP_INT_MAX;
        $multiplier = $field['multiplier'] ?? 1;

        // Pretvori input u float
        $value = is_numeric($value) ? (float) $value : $default;

        // Validacija ulazne vrijednosti *prije* množenja
        $value = $this->validateNumber($value, $min, $max);

        // Primijeni multiplier
        $value = $value * $multiplier;

        return $value;
    }

    /**
     * Dohvaća default vrijednost iz polja ili vraća zadani fallback.
     *
     * @param array $field    Polje definicija koje može sadržavati default vrijednost.
     * @param mixed $fallback Vrijednost koja se vraća ako default nije postavljen.
     *
     * @return mixed Default vrijednost ili fallback.
     * @internal
     */
    protected function getDefault(array $field, $fallback = null) {
        return $field['default'] ?? $fallback;
    }

}

