<?php
/**
 * Recargo de precio por cantidad de huéspedes en habitaciones privadas.
 *
 * BananaDesk devuelve el precio de la habitación para 1 persona. Para las
 * habitaciones que aparecen en hp_guest_surcharge_rules() el precio sube según
 * cuántos huéspedes tenga la reserva. Para sumar otra habitación, agregar su
 * id local (rooms.json) a la tabla.
 */

/**
 * Tabla de recargos: id local de habitación => [huéspedes => recargo %].
 * Si la cantidad de huéspedes no figura, se usa la clave más alta que sea
 * <= a la cantidad (ej: con 1 y 2 sin entrada, aplica 0%).
 */
function hp_guest_surcharge_rules(): array
{
    return [
        '1' => [1 => 0, 3 => 10, 4 => 20],
        // '2' => [1 => 0, ...],  // pendiente de definir
    ];
}

/** Porcentaje de recargo (0 si la habitación no tiene reglas). */
function hp_guest_surcharge_percent(string $localRoomId, int $guestsCount): float
{
    $rules = hp_guest_surcharge_rules()[$localRoomId] ?? null;
    if (!$rules) return 0.0;
    ksort($rules);
    $pct = 0.0;
    foreach ($rules as $minGuests => $p) {
        if ($guestsCount >= $minGuests) $pct = (float)$p;
    }
    return $pct;
}

/** Aplica el recargo al precio base (1 persona) de una habitación privada. */
function hp_apply_guest_surcharge(float $basePrice, string $localRoomId, int $guestsCount): float
{
    return round($basePrice * (1 + hp_guest_surcharge_percent($localRoomId, $guestsCount) / 100), 2);
}

/** id local de habitación a partir del room_type_id de BananaDesk. */
function hp_local_room_id_for_type(int $roomTypeId): ?string
{
    $map = json_decode((string)@file_get_contents(__DIR__ . '/room_mapping.json'), true);
    if (!is_array($map)) return null;
    foreach ($map as $localId => $typeId) {
        if ((int)$typeId === $roomTypeId) return (string)$localId;
    }
    return null;
}

/** Habitaciones que NO se listan (ni como "no disponible") cuando superan su capacidad. */
function hp_hide_when_over_capacity(string $localRoomId): bool
{
    return in_array($localRoomId, ['1'], true);
}
