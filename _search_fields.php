<?php
/**
 * Campos compartidos de búsqueda: Check In / Check Out / Guests.
 * Usado por el hero de index.php y por el step 1 de book.php.
 *
 * Variables esperadas en scope antes del include (todas opcionales):
 *   $sf_variant      'card' (default, estilo book.php) | 'hero' (estilo index.php)
 *   $sf_check_in     valor prellenado de check_in
 *   $sf_check_out    valor prellenado de check_out
 *   $sf_guests       valor prellenado de guests_count (default 1)
 *   $sf_check_in_id  id del input de check_in  (default 'check_in_input')
 *   $sf_check_out_id id del input de check_out (default 'check_out_input')
 *   $sf_guests_id    id del input de guests    (default 'guests_count_input')
 */

$sf_variant      = $sf_variant      ?? 'card';
$sf_check_in     = $sf_check_in     ?? '';
$sf_check_out    = $sf_check_out    ?? '';
$sf_guests       = $sf_guests       ?? 1;
$sf_check_in_id  = $sf_check_in_id  ?? 'check_in_input';
$sf_check_out_id = $sf_check_out_id ?? 'check_out_input';
$sf_guests_id    = $sf_guests_id    ?? 'guests_count_input';

$sf_isHero = ($sf_variant === 'hero');

$sf_wrapClass  = $sf_isHero ? 'space-y-2 min-w-0' : '';
$sf_labelClass = $sf_isHero
    ? 'text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2'
    : 'text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block';
$sf_dateInputClass = $sf_isHero
    ? 'w-full min-w-0 bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none cursor-pointer'
    : 'w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal font-medium';
$sf_guestsInputClass = $sf_isHero
    ? 'w-full min-w-0 bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none'
    : 'w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal font-medium';
?>
<div<?php echo $sf_wrapClass ? ' class="' . $sf_wrapClass . '"' : ''; ?>>
    <label class="<?php echo $sf_labelClass; ?>"><?php if ($sf_isHero): ?><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> <?php endif; ?><span class="notranslate" data-hp-i18n="checkIn">Check In</span></label>
    <input type="text" name="check_in" id="<?php echo htmlspecialchars($sf_check_in_id); ?>"
           value="<?php echo htmlspecialchars($sf_check_in); ?>"
           <?php echo $sf_isHero ? 'placeholder="Check-in date" readonly' : ''; ?> required
           class="<?php echo $sf_dateInputClass; ?>" />
</div>
<div<?php echo $sf_wrapClass ? ' class="' . $sf_wrapClass . '"' : ''; ?>>
    <label class="<?php echo $sf_labelClass; ?>"><?php if ($sf_isHero): ?><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> <?php endif; ?><span class="notranslate" data-hp-i18n="checkOut">Check Out</span></label>
    <input type="text" name="check_out" id="<?php echo htmlspecialchars($sf_check_out_id); ?>"
           value="<?php echo htmlspecialchars($sf_check_out); ?>"
           <?php echo $sf_isHero ? 'placeholder="Check-out date" readonly' : ''; ?> required
           class="<?php echo $sf_dateInputClass; ?>" />
</div>
<div<?php echo $sf_wrapClass ? ' class="' . $sf_wrapClass . '"' : ''; ?>>
    <label class="<?php echo $sf_labelClass; ?>"><?php if ($sf_isHero): ?><i data-lucide="users" class="w-4 h-4 text-teal"></i> <?php endif; ?>Guests</label>
    <input type="number" name="guests_count" id="<?php echo htmlspecialchars($sf_guests_id); ?>"
           value="<?php echo (int)$sf_guests; ?>" min="1" max="8" required
           class="<?php echo $sf_guestsInputClass; ?>" />
</div>
